<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveActivityMember;
use App\Support\RandomDummyActivity;
use App\Support\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActingDummyMemberController extends Controller
{
    public function update(Request $request)
    {
        $admin = $request->attributes->get('currentMember');
        abort_unless($this->isRealAdmin($admin), 403);

        $adminIdx = (int) $admin->idx;
        if (app(RandomDummyActivity::class)->enabled($request)) {
            $request->session()->forget(RandomDummyActivity::SESSION_KEY);
            AdminActivityLogger::record($adminIdx, 'random_dummy_mode_disabled', 'admin_member', $adminIdx, null,
                "관리자 #{$adminIdx}가 랜덤 더미 모드를 종료했습니다.");
        }
        $currentIdx = $request->session()->get(ResolveActivityMember::SESSION_KEY);
        $currentIdx = is_scalar($currentIdx) && ctype_digit((string) $currentIdx)
            ? (int) $currentIdx
            : null;
        $wasEnabled = (bool) $request->attributes->get('activityDummyMode', false);

        $selectedIdx = $request->input('dummy_member_idx');
        if ($selectedIdx === null || $selectedIdx === '') {
            if ($wasEnabled && $currentIdx !== null) {
                $this->recordModeChange(
                    $adminIdx,
                    'dummy_activity_mode_exited',
                    $currentIdx,
                    "관리자 #{$adminIdx}가 더미 회원 #{$currentIdx} 활동 모드를 종료했습니다."
                );
            }

            $request->session()->forget(ResolveActivityMember::SESSION_KEY);
            $request->session()->put(ResolveActivityMember::MODE_ACTOR_SESSION_KEY, $adminIdx);
            $request->session()->put(ResolveActivityMember::MODE_ENABLED_SESSION_KEY, false);
            $request->session()->forget(RandomDummyActivity::SESSION_KEY);
            return redirect()->route('home')->with('admin_success', '더미 회원 활동 선택을 해제했습니다.');
        }

        abort_unless(is_scalar($selectedIdx) && ctype_digit((string) $selectedIdx), 422, '활성 상태의 더미 회원을 선택해 주세요.');
        $dummy = DB::table('members')->where('idx', $selectedIdx)
            ->where('member_level', 1)->where('is_dummy', 1)
            ->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx']);
        abort_if($dummy === null, 422, '활성 상태의 더미 회원을 선택해 주세요.');

        $selectedDummyIdx = (int) $dummy->idx;
        if ($currentIdx !== $selectedDummyIdx || ! $wasEnabled) {
            $action = ! $wasEnabled || $currentIdx === null
                ? 'dummy_activity_mode_entered'
                : 'dummy_activity_mode_switched';
            $summary = ! $wasEnabled || $currentIdx === null
                ? "관리자 #{$adminIdx}가 더미 회원 #{$selectedDummyIdx} 활동 모드에 진입했습니다."
                : "관리자 #{$adminIdx}가 더미 회원 #{$currentIdx}에서 #{$selectedDummyIdx}(으)로 활동 모드를 전환했습니다.";

            $this->recordModeChange($adminIdx, $action, $selectedDummyIdx, $summary);
        }

        $request->session()->put(ResolveActivityMember::SESSION_KEY, $selectedDummyIdx);
        $request->session()->put(ResolveActivityMember::MODE_ACTOR_SESSION_KEY, $adminIdx);
        $request->session()->put(ResolveActivityMember::MODE_ENABLED_SESSION_KEY, true);
        $request->session()->forget(RandomDummyActivity::SESSION_KEY);

        return redirect()->route('home')->with('admin_success', '더미 회원 활동을 선택했습니다.');
    }

    public function toggleActivityMode(Request $request)
    {
        $admin = $request->attributes->get('currentMember');
        abort_unless($this->isRealAdmin($admin), 403);

        $adminIdx = (int) $admin->idx;
        $enabled = $request->boolean('enabled');
        $selectedIdx = (int) $request->session()->get(ResolveActivityMember::SESSION_KEY, 0);
        $selected = $selectedIdx > 0 ? DB::table('members')->where('idx', $selectedIdx)
            ->where('member_level', 1)->where('is_dummy', 1)->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx']) : null;

        if ($enabled && $selected === null) {
            $selected = DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                ->where('status', 'active')->whereNull('deleted_at')->orderBy('idx')->first(['idx']);
            if ($selected === null) {
                return back()->withErrors(['dummy_member' => '활성 더미 회원이 없어 더미회원 모드를 켤 수 없습니다.']);
            }
            $request->session()->put(ResolveActivityMember::SESSION_KEY, (int) $selected->idx);
        }

        $wasEnabled = filter_var($request->session()->get(ResolveActivityMember::MODE_ENABLED_SESSION_KEY, true), FILTER_VALIDATE_BOOLEAN);
        $request->session()->put(ResolveActivityMember::MODE_ACTOR_SESSION_KEY, $adminIdx);
        $request->session()->put(ResolveActivityMember::MODE_ENABLED_SESSION_KEY, $enabled);
        $request->session()->forget(RandomDummyActivity::SESSION_KEY);

        if ($enabled !== $wasEnabled) {
            $targetIdx = (int) ($selected->idx ?? ($selectedIdx > 0 ? $selectedIdx : $adminIdx));
            $this->recordModeChange($adminIdx, $enabled ? 'dummy_activity_mode_enabled' : 'dummy_activity_mode_disabled',
                $targetIdx, "Admin #{$adminIdx} ".($enabled ? 'enabled' : 'disabled')." dummy-member activity mode for member #{$targetIdx}.");
        }

        return back();
    }

    public function toggleRandom(Request $request)
    {
        $admin = $request->attributes->get('currentMember');
        abort_unless($this->isRealAdmin($admin), 403);

        $enabled = $request->boolean('enabled');
        $wasEnabled = app(RandomDummyActivity::class)->enabled($request);
        if ($enabled) {
            $request->session()->forget(ResolveActivityMember::SESSION_KEY);
            $request->session()->put(ResolveActivityMember::MODE_ACTOR_SESSION_KEY, (int) $admin->idx);
            $request->session()->put(ResolveActivityMember::MODE_ENABLED_SESSION_KEY, false);
            $request->session()->put(RandomDummyActivity::SESSION_KEY, true);
        } else {
            $request->session()->forget(RandomDummyActivity::SESSION_KEY);
        }

        if ($enabled !== $wasEnabled) {
            $adminIdx = (int) $admin->idx;
            AdminActivityLogger::record($adminIdx, $enabled ? 'random_dummy_mode_enabled' : 'random_dummy_mode_disabled',
                'admin_member', $adminIdx, null,
                $enabled ? "관리자 #{$adminIdx}가 랜덤 더미 모드를 시작했습니다." : "관리자 #{$adminIdx}가 랜덤 더미 모드를 종료했습니다.");
        }

        return back()->with('admin_success', $enabled ? '랜덤 더미 모드를 켰습니다.' : '랜덤 더미 모드를 껐습니다.');
    }

    private function isRealAdmin(?object $member): bool
    {
        return $member !== null && (int) $member->member_level === 10 && (int) $member->is_dummy === 0;
    }

    private function recordModeChange(int $adminIdx, string $action, int $dummyIdx, string $summary): void
    {
        DB::table('admin_activity_logs')->insert([
            'actor_member_idx' => $adminIdx,
            'action' => $action,
            'target_type' => 'dummy_member',
            'target_idx' => $dummyIdx,
            'summary' => $summary,
            'created_at' => now(),
        ]);
    }
}
