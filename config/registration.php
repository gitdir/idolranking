<?php

return [
    'email_verification' => [
        'expires_seconds' => 300,
        'resend_after_seconds' => 60,
        'max_attempts' => 5,
        'rate_limits' => [
            'email_per_hour' => 5,
            'session_per_hour' => 10,
            'ip_per_hour' => 20,
        ],
    ],
];
