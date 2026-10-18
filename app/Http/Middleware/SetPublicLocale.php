<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->routeIs('admin.*')) {
            $locale = $request->session()->get('locale', 'en');
            App::setLocale(in_array($locale, ['ko', 'en'], true) ? $locale : 'en');
        }

        return $next($request);
    }
}
