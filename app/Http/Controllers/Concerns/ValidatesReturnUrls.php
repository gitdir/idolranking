<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ValidatesReturnUrls
{
    private function safeReturnUrl(Request $request, mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $url = trim($value);
        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) === 1) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        if (isset($parts['scheme']) || isset($parts['host'])) {
            $origin = parse_url($request->getSchemeAndHttpHost());
            if (!isset($parts['scheme'], $parts['host'], $origin['scheme'], $origin['host'])
                || strcasecmp($parts['scheme'], $origin['scheme']) !== 0
                || strcasecmp($parts['host'], $origin['host']) !== 0
                || ($parts['port'] ?? null) !== ($origin['port'] ?? null)) {
                return null;
            }
        }

        $path = $parts['path'] ?? '/';
        $decodedPath = rawurldecode($path);
        if (!str_starts_with($path, '/') || str_starts_with($decodedPath, '//') || str_contains($decodedPath, '\\')) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
}
