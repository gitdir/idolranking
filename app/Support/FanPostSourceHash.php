<?php

namespace App\Support;

final class FanPostSourceHash
{
    public static function make(string $title, ?string $content): string
    {
        return hash('sha256', $title."\n".($content ?? ''));
    }
}
