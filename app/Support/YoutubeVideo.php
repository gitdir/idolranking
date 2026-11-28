<?php

namespace App\Support;

final class YoutubeVideo
{
    public static function id(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) return null;
        $host = strtolower($parts['host'] ?? '');
        $id = null;
        if (in_array($host, ['youtu.be', 'www.youtu.be'], true)) {
            $id = trim($parts['path'] ?? '', '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            parse_str($parts['query'] ?? '', $query);
            $path = trim($parts['path'] ?? '', '/');
            $id = $path === 'watch' ? ($query['v'] ?? null) : (preg_match('~^(?:shorts|embed)/([^/]+)~', $path, $match) ? $match[1] : null);
        }
        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
}
