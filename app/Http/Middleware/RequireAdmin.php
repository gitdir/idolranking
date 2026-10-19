<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $member = $request->attributes->get('currentMember');

        if ($member === null) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('home')->with('open_sign_in', true);
        }

        if ((int) $member->member_level !== 10 || (int) ($member->is_dummy ?? 0) !== 0) {
            return response()->view('admin.forbidden', status: 403);
        }

        return $next($request);
    }
}
