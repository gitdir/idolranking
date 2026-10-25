<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminActivityLogger;
use App\Support\HeartRewardManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $grantRequestToken = old('grant_request_token');
        if (! is_string($grantRequestToken) || ! Str::isUuid($grantRequestToken)) {
            $grantRequestToken = (string) Str::uuid();
        }

        return view('admin.hearts.show', compact('member', 'histories', 'grantRequestToken'));
    }

    public function settings()
    {
        $settings = DB::table('heart_reward_settings')->get()->keyBy('action_code');
        $actions = collect(HeartRewardManager::ACTIONS)->map(function (array $definition, string $code) use ($settings) {
            $setting = $settings->get($code);
            return (object) ($definition + [
                'action_code' => $code, 'idx' => $setting?->idx,
                'heart_amount' => (int) ($setting?->heart_amount ?? $definition['amount']),
                'min_heart_amount' => (int) ($setting?->min_heart_amount ?? $definition['min_amount'] ?? $setting?->heart_amount ?? $definition['amount']),
                'max_heart_amount' => (int) ($setting?->max_heart_amount ?? $definition['max_amount'] ?? $setting?->heart_amount ?? $definition['amount']),
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
        $isRangeAction = $definition['reward_type'] === 'range';
        $minimumAmount = (int) ($definition['minimum_amount'] ?? 0);
        $validated = $request->validate([
            'heart_amount' => ['required', 'integer', 'min:0', 'max:2147483647'],
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
        if ($isRangeAction) {
            $range = $request->validate([
                'min_heart_amount' => ['required', 'integer', 'min:'.$minimumAmount, 'max:2147483647'],
                'max_heart_amount' => ['required', 'integer', 'min:'.$minimumAmount, 'max:2147483647'],
            ], [
                'min_heart_amount.required' => '최소 하트를 입력해 주세요.',
                'min_heart_amount.integer' => '최소 하트는 0 이상의 정수여야 합니다.',
                'min_heart_amount.min' => '최소 하트는 0 이상이어야 합니다.',
                'min_heart_amount.max' => '하트 범위가 너무 큽니다.',
                'max_heart_amount.required' => '최대 하트를 입력해 주세요.',
                'max_heart_amount.integer' => '최대 하트는 0 이상의 정수여야 합니다.',
                'max_heart_amount.min' => '최대 하트는 0 이상이어야 합니다.',
                'max_heart_amount.max' => '하트 범위가 너무 큽니다.',
            ]);
            if ((int) $range['min_heart_amount'] > (int) $range['max_heart_amount']) {
                return back()->withErrors(['max_heart_amount' => '최대 Heart는 최소 Heart보다 크거나 같아야 합니다.'])->withInput();
            }
            $validated = array_merge($validated, $range);
        }

        $setting = DB::table('heart_reward_settings')->where('action_code', $actionCode)->first();
        abort_if($setting === null, 404);
        $values = [
            'heart_amount' => $isRangeAction ? (int) $setting->heart_amount : (int) $validated['heart_amount'],
            'daily_limit' => $definition['limit_type'] === 'daily' ? (int) $validated['daily_limit'] : 0,
            'is_enabled' => $request->boolean('is_enabled'), 'updated_at' => now(),
        ];
        if ($isRangeAction) {
            $values['min_heart_amount'] = (int) $validated['min_heart_amount'];
            $values['max_heart_amount'] = (int) $validated['max_heart_amount'];
        }
        DB::transaction(function () use ($request, $setting, $values, $actionCode, $isRangeAction): void {
            DB::table('heart_reward_settings')->where('idx', $setting->idx)->update($values);
            $oldAmount = $isRangeAction
                ? ($setting->min_heart_amount ?? $setting->heart_amount).'-'.($setting->max_heart_amount ?? $setting->heart_amount)
                : (string) $setting->heart_amount;
            $newAmount = $isRangeAction
                ? $values['min_heart_amount'].'-'.$values['max_heart_amount']
                : (string) $values['heart_amount'];
            AdminActivityLogger::record((int) $request->attributes->get('currentMember')->idx,
                'heart_reward_setting_updated', 'heart_reward_setting', (int) $setting->idx, null,
                sprintf('%s: %s/%d/%d → %s/%d/%d', $actionCode, $oldAmount, $setting->daily_limit, $setting->is_enabled, $newAmount, $values['daily_limit'], $values['is_enabled']));
        });
        return back()->with('admin_success', '하트 지급 설정을 저장했습니다.');
    }

    public function credit(Request $request, int $idx, HeartRewardManager $rewards)
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'reason' => ['required', 'string', 'max:1000'],
            'grant_request_token' => ['required', 'uuid'],
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
        $operationKey = sprintf('admin_credit:%d:%d:%s', $adminIdx, $idx, strtolower($validated['grant_request_token']));
        $credited = DB::transaction(function () use ($rewards, $idx, $validated, $reason, $adminIdx, $operationKey): bool {
            $credited = $rewards->creditManually($idx, (int) $validated['amount'], $reason, $adminIdx, $operationKey);
            if (! $credited) {
                return false;
            }

            AdminActivityLogger::record($adminIdx, 'admin_heart_credited', 'member', $idx, $reason, number_format((int) $validated['amount']).' 하트 지급');
            return true;
        });

        return back()->with('admin_success', $credited ? '하트를 지급했습니다.' : '이미 처리된 하트 지급 요청입니다.');
    }

    private function sort(Request $request, array $allowed, string $default): array
    {
        $sort = array_key_exists($request->query('sort'), $allowed) ? $request->query('sort') : $default;
        return [$sort, $request->query('direction') === 'asc' ? 'asc' : 'desc'];
    }
}
