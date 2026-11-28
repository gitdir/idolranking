<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SetPublicLocale
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->session()->has('locale')) {
            $detectedLocale = $this->localeForIp($request->ip());
            if ($detectedLocale !== null) {
                $request->session()->put('locale', $detectedLocale);
            }
        }

        $locale = $request->routeIs('admin.*') ? 'ko' : $request->session()->get('locale', 'en');
        App::setLocale(array_key_exists($locale, config('locales.public', [])) ? $locale : 'en');

        return $next($request);
    }

    private function localeForIp(?string $ip): ?string
    {
        if (! is_string($ip) || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $countries = ['KR' => 'ko', 'JP' => 'ja', 'ID' => 'id', 'BR' => 'pt-BR', 'CN' => 'zh-CN'];
        $spanishCountries = ['AR', 'BO', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'ES', 'GQ', 'GT', 'HN', 'MX', 'NI', 'PA', 'PE', 'PR', 'PY', 'SV', 'UY', 'VE'];

        try {
            $country = Cache::remember('locale_country:'.hash('sha256', $ip), now()->addDay(), function () use ($ip) {
                $response = Http::connectTimeout(1)->timeout(2)->get('https://ipwho.is/'.rawurlencode($ip));

                return $response->successful() && $response->json('success') === true
                    ? $response->json('country_code') : null;
            });
        } catch (\Throwable $exception) {
            report($exception);
            return null;
        }

        if (! is_string($country) || ! preg_match('/^[A-Z]{2}$/', $country)) {
            return null;
        }

        return $countries[$country] ?? (in_array($country, $spanishCountries, true) ? 'es' : 'en');
    }
}
