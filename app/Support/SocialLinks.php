<?php

namespace App\Support;

final class SocialLinks
{
    public static function inputUrls(?string $storedLinks, ?string $legacyLink = null): array
    {
        if ($storedLinks !== null) {
            $decoded = json_decode($storedLinks, true);

            return is_array($decoded)
                ? array_values(array_slice(array_filter($decoded, 'is_string'), 0, 3))
                : [];
        }

        return self::isHttpUrl((string) $legacyLink) ? [(string) $legacyLink] : [];
    }

    public static function entries(?string $storedLinks, ?string $legacyLink = null): array
    {
        $urls = $storedLinks === null
            ? (self::isHttpUrl((string) $legacyLink) ? [(string) $legacyLink] : [])
            : self::inputUrls($storedLinks);

        return array_map(static function (string $url): array {
            $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
            $platform = self::platformForHost($host);

            return [
                'url' => $url,
                'platform' => $platform,
                'label' => [
                    'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok',
                    'x' => 'X / Twitter', 'facebook' => 'Facebook', 'threads' => 'Threads',
                    'linkedin' => 'LinkedIn', 'pinterest' => 'Pinterest', 'snapchat' => 'Snapchat',
                    'reddit' => 'Reddit', 'discord' => 'Discord', 'weibo' => 'Weibo',
                    'bilibili' => 'Bilibili', 'telegram' => 'Telegram', 'twitch' => 'Twitch',
                    'line' => 'LINE', 'external' => __('ui.social_platform_external'),
                ][$platform],
            ];
        }, array_values(array_filter($urls, static fn (string $url): bool => self::isHttpUrl($url))));
    }

    public static function encodeSubmitted(array $values): string
    {
        $urls = [];
        $seen = [];
        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                continue;
            }
            $url = trim($value);
            $key = self::duplicateKey($url);
            if (isset($seen[$key])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'sns_links' => __('ui.social_links_duplicate'),
                ]);
            }
            $seen[$key] = true;
            $urls[] = $url;
        }

        return json_encode($urls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function isHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        try {
            $parts = parse_url($url);
        } catch (\ValueError) {
            return false;
        }

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host'])
            && (!isset($parts['port']) || $parts['port'] <= 65535)
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    private static function duplicateKey(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return strtolower($url);
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = $parts['port'] ?? null;
        if (($scheme === 'http' && $port === 80) || ($scheme === 'https' && $port === 443)) {
            $port = null;
        }

        return $scheme.'://'.$host.($port ? ':'.$port : '')
            .($parts['path'] ?? '')
            .(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private static function platformForHost(string $host): string
    {
        $platformHosts = [
            'instagram' => ['instagram.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
            'tiktok' => ['tiktok.com'],
            'x' => ['x.com', 'twitter.com'],
            'facebook' => ['facebook.com', 'fb.com'],
            'threads' => ['threads.net'],
            'twitch' => ['twitch.tv'],
            'linkedin' => ['linkedin.com'],
            'pinterest' => ['pinterest.com'],
            'snapchat' => ['snapchat.com'],
            'reddit' => ['reddit.com'],
            'discord' => ['discord.com', 'discord.gg'],
            'weibo' => ['weibo.com'],
            'bilibili' => ['bilibili.com'],
            'telegram' => ['t.me', 'telegram.org'],
            'line' => ['line.me'],
        ];

        foreach ($platformHosts as $platform => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return $platform;
                }
            }
        }

        return 'external';
    }
}
