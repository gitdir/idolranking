<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveActivityMember
{
    public const SESSION_KEY = 'acting_dummy_member_idx';

    public function handle(Request $request, Closure $next)
    {
        $actor = $request->attributes->get('currentMember');
        $activityMember = $actor;

        if ($actor !== null && (int) $actor->member_level === 10 && $request->session()->has(self::SESSION_KEY)) {
            $selectedIdx = $request->session()->get(self::SESSION_KEY);
            $activityMember = is_scalar($selectedIdx) && ctype_digit((string) $selectedIdx)
                ? DB::table('members')->where('idx', $selectedIdx)
                    ->where('member_level', 1)->where('is_dummy', 1)
                    ->where('status', 'active')->whereNull('deleted_at')
                    ->first(['idx', 'member_code', 'email', 'nickname', 'country_code', 'profile_image_path', 'heart_balance', 'member_level', 'is_dummy'])
                : null;

            if ($activityMember === null) {
                $request->session()->forget(self::SESSION_KEY);
                $activityMember = $actor;
            }
        } else {
            $request->session()->forget(self::SESSION_KEY);
        }

        $request->attributes->set('activityActor', $actor);
        $request->attributes->set('activityMember', $activityMember);

        return $next($request);
    }
}
