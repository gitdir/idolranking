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
        abort_unless((int) $admin?->member_level === 10, 403);

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

        $selectedIdx = $request->input('dummy_member_idx');
        if ($selectedIdx === null || $selectedIdx === '') {
            if ($currentIdx !== null) {
                $this->recordModeChange(
                    $adminIdx,
                    'dummy_activity_mode_exited',
                    $currentIdx,
                    "관리자 #{$adminIdx}가 더미 회원 #{$currentIdx} 활동 모드를 종료했습니다."
                );
            }

            $request->session()->forget(ResolveActivityMember::SESSION_KEY);
            return redirect()->route('home')->with('admin_success', '더미 회원 활동 선택을 해제했습니다.');
        }

        abort_unless(is_scalar($selectedIdx) && ctype_digit((string) $selectedIdx), 422, '활성 상태의 더미 회원을 선택해 주세요.');
        $dummy = DB::table('members')->where('idx', $selectedIdx)
            ->where('member_level', 1)->where('is_dummy', 1)
            ->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx']);
        abort_if($dummy === null, 422, '활성 상태의 더미 회원을 선택해 주세요.');

        $selectedDummyIdx = (int) $dummy->idx;
        if ($currentIdx !== $selectedDummyIdx) {
            $action = $currentIdx === null
                ? 'dummy_activity_mode_entered'
                : 'dummy_activity_mode_switched';
            $summary = $currentIdx === null
                ? "관리자 #{$adminIdx}가 더미 회원 #{$selectedDummyIdx} 활동 모드에 진입했습니다."
                : "관리자 #{$adminIdx}가 더미 회원 #{$currentIdx}에서 #{$selectedDummyIdx}(으)로 활동 모드를 전환했습니다.";

            $this->recordModeChange($adminIdx, $action, $selectedDummyIdx, $summary);
        }

        $request->session()->put(ResolveActivityMember::SESSION_KEY, $selectedDummyIdx);

        return redirect()->route('home')->with('admin_success', '더미 회원 활동을 선택했습니다.');
    }

    public function toggleRandom(Request $request)
    {
        $admin = $request->attributes->get('currentMember');
        abort_unless((int) $admin?->member_level === 10, 403);

        $enabled = $request->boolean('enabled');
        $wasEnabled = app(RandomDummyActivity::class)->enabled($request);
        if ($enabled) {
            $request->session()->forget(ResolveActivityMember::SESSION_KEY);
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
