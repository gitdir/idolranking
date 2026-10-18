<?php

namespace App\Support;

final class FanPostLanguages
{
    public static function all(): array
    {
        return [
            'ar' => 'العربية', 'de' => 'Deutsch', 'en' => 'English', 'es' => 'Español', 'fr' => 'Français',
            'hi' => 'हिन्दी', 'id' => 'Bahasa Indonesia', 'it' => 'Italiano', 'ja' => '日本語', 'ko' => '한국어',
            'ms' => 'Bahasa Melayu', 'nl' => 'Nederlands', 'pt' => 'Português', 'ru' => 'Русский',
            'th' => 'ไทย', 'tr' => 'Türkçe', 'vi' => 'Tiếng Việt', 'zh' => '中文',
        ];
    }

    public static function isValid(?string $languageCode): bool
    {
        return $languageCode !== null && array_key_exists(strtolower($languageCode), self::all());
    }
}
