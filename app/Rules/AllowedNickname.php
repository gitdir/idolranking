<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AllowedNickname implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !in_array(config('database.default'), \PDO::getAvailableDrivers(), true) || !Schema::hasTable('restricted_nicknames')) {
            return;
        }

        $nickname = mb_strtolower(trim($value));
        $blocked = DB::table('restricted_nicknames')->where('is_enabled', 1)->get(['word', 'match_type'])
            ->contains(function ($rule) use ($nickname): bool {
                $word = mb_strtolower(trim($rule->word));
                return $word !== '' && ($rule->match_type === 'contains' ? str_contains($nickname, $word) : $nickname === $word);
            });

        if ($blocked) {
            $fail('This nickname is not available.');
        }
    }
}
