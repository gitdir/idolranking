<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireMember
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->attributes->get('currentMember') !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => 'Please sign in to continue.'], 401);
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('home')->with('open_sign_in', true);
    }
}
