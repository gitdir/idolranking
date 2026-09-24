<?php

return [

    // Authentication is not implemented yet. Configure a session guard and a
    // Query Builder provider for members.idx / password_hash when it is added.
    // The stock database provider assumes id / password / remember_token.
    'defaults' => [
        'guard' => null,
        'passwords' => null,
    ],

    // Use null rather than empty arrays: Laravel merges empty arrays with its
    // framework defaults, which would restore the stock authentication setup.
    'guards' => null,
    'providers' => null,

    // The stock password broker expects email / token columns. Our SQL-managed
    // password_reset_tokens uses member_idx / token_hash / expires_at / used_at.
    'passwords' => null,

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
