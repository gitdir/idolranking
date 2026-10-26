<?php

namespace App\Support;

final class FanPostLanguages
{
    public static function all(): array
    {
        return [
            'ar' => 'العربية', 'de' => 'Deutsch', 'en' => 'English', 'es' => 'Español', 'fr' => 'Français',
            'hi' => 'हिन्दी', 'id' => 'Bahasa Indonesia', 'it' => 'Italiano', 'ja' => '日本語', 'ko' => '한국어',
            'ms' => 'Bahasa Melayu', 'nl' => 'Nederlands', 'pt' => 'Português', 'pt-BR' => 'Português (Brasil)',
            'ru' => 'Русский', 'th' => 'ไทย', 'tr' => 'Türkçe', 'vi' => 'Tiếng Việt', 'zh' => '中文', 'zh-CN' => '简体中文',
        ];
    }

    public static function canonicalCode(?string $languageCode): ?string
    {
        if ($languageCode === null) {
            return null;
        }

        $code = strtolower(trim($languageCode));
        $supported = array_change_key_case(self::all(), CASE_LOWER);
        if (!array_key_exists($code, $supported)) {
            return null;
        }

        return match ($code) {
            'pt-br' => 'pt-BR',
            'zh-cn' => 'zh-CN',
            default => $code,
        };
    }

    public static function isValid(?string $languageCode): bool
    {
        return self::canonicalCode($languageCode) !== null;
    }
}
