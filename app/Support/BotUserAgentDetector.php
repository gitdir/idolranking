<?php

namespace App\Support;

class BotUserAgentDetector
{
    public function isBot(?string $userAgent): bool
    {
        $normalized = strtolower(trim((string) $userAgent));

        if ($normalized === '') {
            return true;
        }

        foreach (config('tracking.bot_user_agent_markers', []) as $marker) {
            if ($marker !== '' && str_contains($normalized, strtolower($marker))) {
                return true;
            }
        }

        return false;
    }
}
