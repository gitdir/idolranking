<?php

namespace App\Http\Middleware;

use App\Support\HeartRewardManager;
use Closure;
use Illuminate\Http\Request;

class CaptureReferral
{
    public function handle(Request $request, Closure $next)
    {
        $heartRewards = app(HeartRewardManager::class);
        if ($request->attributes->get('currentMember') !== null) {
            $request->session()->forget(HeartRewardManager::REFERRAL_SESSION_KEY);
        } elseif ($request->query->has('ref')) {
            $request->session()->forget(HeartRewardManager::REFERRAL_SESSION_KEY);
            $memberCode = $request->query('ref');
            $inviterIdx = is_string($memberCode) ? $heartRewards->eligibleInviterIdx($memberCode) : null;

            if ($inviterIdx !== null) {
                $request->session()->put(HeartRewardManager::REFERRAL_SESSION_KEY, $memberCode);
            }
        }

        return $next($request);
    }
}
