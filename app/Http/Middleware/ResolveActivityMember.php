<?php

namespace App\Http\Middleware;

use App\Support\RandomDummyActivity;
use App\Support\AdminActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveActivityMember
{
    public const SESSION_KEY = 'acting_dummy_member_idx';
    public const MODE_ENABLED_SESSION_KEY = 'acting_dummy_member_enabled';
    public const MODE_ACTOR_SESSION_KEY = 'acting_dummy_member_actor_idx';

    public function handle(Request $request, Closure $next)
    {
        $actor = $request->attributes->get('currentMember');
        $isAdmin = $actor !== null && (int) $actor->member_level === 10 && (int) $actor->is_dummy === 0;
        $activityMember = $actor;
        $selectedDummy = null;
        $dummyMode = false;
        $randomMode = false;

        if (! $isAdmin) {
            $request->session()->forget([
                self::SESSION_KEY,
                self::MODE_ENABLED_SESSION_KEY,
                self::MODE_ACTOR_SESSION_KEY,
                RandomDummyActivity::SESSION_KEY,
            ]);
        } else {
            $adminIdx = (int) $actor->idx;
            $modeActorIdx = (int) $request->session()->get(self::MODE_ACTOR_SESSION_KEY, 0);
            $newAdminModeSession = $modeActorIdx !== $adminIdx;
            if ($newAdminModeSession) {
                // A new admin session starts in dummy mode; do not carry another admin's selection.
                if ($modeActorIdx !== 0) {
                    $request->session()->forget(self::SESSION_KEY);
                    $request->session()->forget(RandomDummyActivity::SESSION_KEY);
                }
                $request->session()->put(self::MODE_ACTOR_SESSION_KEY, $adminIdx);
                $request->session()->put(self::MODE_ENABLED_SESSION_KEY, true);
            }

            $selectedIdx = $request->session()->get(self::SESSION_KEY);
            if (is_scalar($selectedIdx) && ctype_digit((string) $selectedIdx)) {
                $selectedDummy = $this->activeDummy((int) $selectedIdx);
            }

            $enabled = filter_var($request->session()->get(self::MODE_ENABLED_SESSION_KEY, true), FILTER_VALIDATE_BOOLEAN);
            $randomMode = app(RandomDummyActivity::class)->enabled($request);
            if ($randomMode) {
                $request->session()->forget(self::MODE_ENABLED_SESSION_KEY);
                $request->session()->put(self::MODE_ENABLED_SESSION_KEY, false);
                $enabled = false;
            } elseif ($enabled && $selectedDummy === null) {
                $selectedDummy = DB::table('members')->where('member_level', 1)->where('is_dummy', 1)
                    ->where('status', 'active')->whereNull('deleted_at')->orderBy('idx')
                    ->first(['idx', 'member_code', 'email', 'nickname', 'country_code', 'profile_image_path', 'heart_balance', 'member_level', 'is_dummy']);
                if ($selectedDummy !== null) {
                    $request->session()->put(self::SESSION_KEY, (int) $selectedDummy->idx);
                    if ($newAdminModeSession) {
                        AdminActivityLogger::record($adminIdx, 'dummy_activity_mode_entered', 'dummy_member',
                            (int) $selectedDummy->idx, null,
                            "관리자 #{$adminIdx}가 기본 더미 회원 #{$selectedDummy->idx} 활동 모드를 시작했습니다.");
                    }
                } else {
                    $request->session()->put(self::MODE_ENABLED_SESSION_KEY, false);
                    $enabled = false;
                }
            }

            $dummyMode = $enabled && $selectedDummy !== null && ! $randomMode;
            if ($dummyMode) {
                $activityMember = $selectedDummy;
            }
        }

        $request->attributes->set('activityActor', $actor);
        $request->attributes->set('activityMember', $activityMember);
        $request->attributes->set('selectedActivityMember', $selectedDummy);
        $request->attributes->set('activityDummyMode', $dummyMode);
        $request->attributes->set('randomDummyMode', $randomMode);

        return $next($request);
    }

    private function activeDummy(int $idx): ?object
    {
        return DB::table('members')->where('idx', $idx)->where('member_level', 1)->where('is_dummy', 1)
            ->where('status', 'active')->whereNull('deleted_at')
            ->first(['idx', 'member_code', 'email', 'nickname', 'country_code', 'profile_image_path', 'heart_balance', 'member_level', 'is_dummy']);
    }
}
