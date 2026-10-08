<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveMember
{
    public function handle(Request $request, Closure $next)
    {
        $member = null;
        if ($request->session()->has('member_idx')) {
            $idx = $request->session()->get('member_idx');
            if (is_scalar($idx) && ctype_digit((string) $idx)) {
                $member = DB::table('members')->where('idx', $idx)
                    ->where('member_level', '>', 0)->where('status', 'active')->whereNull('deleted_at')
                    ->first([
                        'idx',
                        'member_code',
                        'email',
                        'nickname',
                        'country_code',
                        'profile_image_path',
                        'heart_balance',
                        'member_level',
                    ]);
            }
            if ($member === null) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
        $request->attributes->set('currentMember', $member);

        return $next($request);
    }
}
