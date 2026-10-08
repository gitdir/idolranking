<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\HeartRewardManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HeartController extends Controller
{
    public function index(Request $request)
    {
        $allowed = ['nickname' => 'm.nickname', 'heart_balance' => 'm.heart_balance', 'earned' => 'earned', 'used' => 'used', 'recent_at' => 'recent_at'];
        [$sort, $direction] = $this->sort($request, $allowed, 'recent_at');
        $search = trim($request->string('q')->toString());
        $members = DB::table('members as m')->whereNull('m.deleted_at')
            ->leftJoinSub(DB::table('heart_histories')->selectRaw('member_idx, SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) earned, ABS(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END)) used, MAX(created_at) recent_at')->groupBy('member_idx'), 'h', 'h.member_idx', '=', 'm.idx')
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n->where('m.nickname', 'like', '%'.$search.'%')->orWhere('m.email', 'like', '%'.$search.'%')->orWhere('m.member_code', 'like', '%'.$search.'%')))
            ->select('m.idx', 'm.nickname', 'm.email', 'm.member_code', 'm.heart_balance', DB::raw('COALESCE(h.earned,0) earned'), DB::raw('COALESCE(h.used,0) used'), 'h.recent_at')
            ->orderBy($allowed[$sort], $direction)->orderByDesc('m.idx')->paginate(20)->withQueryString();

        return view('admin.hearts.index', compact('members', 'search', 'sort', 'direction'));
    }

    public function show(int $idx)
    {
        $member = DB::table('members')->where('idx', $idx)->whereNull('deleted_at')->first(['idx', 'nickname', 'email', 'member_code', 'heart_balance']);
        abort_if($member === null, 404);
        $histories = DB::table('heart_histories')->where('member_idx', $idx)->orderByDesc('created_at')->orderByDesc('idx')->paginate(30);
        return view('admin.hearts.show', compact('member', 'histories'));
    }

    public function settings()
    {
        $settings = DB::table('heart_reward_settings')->get()->keyBy('action_code');
        $actions = collect(HeartRewardManager::ACTIONS)->map(function (array $definition, string $code) use ($settings) {
            $setting = $settings->get($code);
            return (object) ($definition + [
                'action_code' => $code, 'idx' => $setting?->idx,
                'heart_amount' => (int) ($setting?->heart_amount ?? $definition['amount']),
                'daily_limit' => (int) ($setting?->daily_limit ?? $definition['daily_limit']),
                'is_enabled' => (bool) ($setting?->is_enabled ?? true),
            ]);
        });
        return view('admin.hearts.settings', compact('actions'));
    }

    public function updateSetting(Request $request, string $actionCode)
    {
        $definition = HeartRewardManager::ACTIONS[$actionCode] ?? null;
        abort_if($definition === null, 404);
        $validated = $request->validate([
            'heart_amount' => ['required', 'integer', 'min:0'],
            'daily_limit' => [Rule::requiredIf($definition['limit_type'] === 'daily'), 'nullable', 'integer', 'min:0'],
            'is_enabled' => ['nullable', 'boolean'],
        ], [
            'heart_amount.required' => '지급 하트를 입력해 주세요.',
            'heart_amount.integer' => '지급 하트는 0 이상의 정수여야 합니다.',
            'heart_amount.min' => '지급 하트는 0 이상이어야 합니다.',
            'daily_limit.required' => '1일 제한을 입력해 주세요.',
            'daily_limit.integer' => '1일 제한은 0 이상의 정수여야 합니다.',
            'daily_limit.min' => '1일 제한은 0 이상이어야 합니다.',
        ]);
        $setting = DB::table('heart_reward_settings')->where('action_code', $actionCode)->first();
        abort_if($setting === null, 404);
        $values = [
            'heart_amount' => (int) $validated['heart_amount'],
            'daily_limit' => $definition['limit_type'] === 'daily' ? (int) $validated['daily_limit'] : 0,
            'is_enabled' => $request->boolean('is_enabled'), 'updated_at' => now(),
        ];
        DB::transaction(function () use ($request, $setting, $values, $actionCode): void {
            DB::table('heart_reward_settings')->where('idx', $setting->idx)->update($values);
            AdminActivityLogger::record((int) $request->attributes->get('currentMember')->idx,
                'heart_reward_setting_updated', 'heart_reward_setting', (int) $setting->idx, null,
                sprintf('%s: %d/%d/%d → %d/%d/%d', $actionCode, $setting->heart_amount, $setting->daily_limit, $setting->is_enabled, $values['heart_amount'], $values['daily_limit'], $values['is_enabled']));
        });
        return back()->with('admin_success', '하트 지급 설정을 저장했습니다.');
    }

    public function credit(Request $request, int $idx, HeartRewardManager $rewards)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:1000'],
        ], [
            'amount.required' => '지급할 하트 수량을 입력해 주세요.',
            'amount.integer' => '하트 수량은 정수여야 합니다.',
            'amount.min' => '하트는 1개 이상 지급해야 합니다.',
            'amount.max' => '한 번에 지급할 수 있는 하트 수량을 초과했습니다.',
            'reason.required' => '지급 사유를 입력해 주세요.',
            'reason.max' => '지급 사유는 1,000자 이하여야 합니다.',
        ]);
        $reason = trim($validated['reason']);
        if ($reason === '') return back()->withErrors(['reason' => '지급 사유를 입력해 주세요.'])->withInput();
        $adminIdx = (int) $request->attributes->get('currentMember')->idx;
        DB::transaction(function () use ($rewards, $idx, $validated, $reason, $adminIdx): void {
            $rewards->creditManually($idx, (int) $validated['amount'], $reason, $adminIdx);
            AdminActivityLogger::record($adminIdx, 'admin_heart_credited', 'member', $idx, $reason, number_format((int) $validated['amount']).' 하트 지급');
        });
        return back()->with('admin_success', '하트를 지급했습니다.');
    }

    private function sort(Request $request, array $allowed, string $default): array
    {
        $sort = array_key_exists($request->query('sort'), $allowed) ? $request->query('sort') : $default;
        return [$sort, $request->query('direction') === 'asc' ? 'asc' : 'desc'];
    }
}
