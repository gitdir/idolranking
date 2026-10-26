<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->routeIs('admin.*') ? 'ko' : $request->session()->get('locale', 'en');
        App::setLocale(array_key_exists($locale, config('locales.public', [])) ? $locale : 'en');

        return $next($request);
    }
}
