<?php

namespace App\Support;

final class MemberCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

    public function generate(): string
    {
        $code = '';
        $lastIndex = strlen(self::ALPHABET) - 1;

        for ($index = 0; $index < 12; $index++) {
            $code .= self::ALPHABET[random_int(0, $lastIndex)];
        }

        return $code;
    }
}
