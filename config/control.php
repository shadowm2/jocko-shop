<?php

return [
    /*
    | Where this installation reports to (Jocko Control). The module does
    | nothing unless both are set, so the shop runs fine on its own.
    | The token is a secret: keep it in .env only, never in source control.
    */
    'url' => env('JOCKO_CONTROL_URL'),
    'token' => env('JOCKO_CONTROL_TOKEN'),

    'timeout' => (int) env('JOCKO_CONTROL_TIMEOUT', 10),
    'connect_timeout' => 5,

    // Shown in Jocko Control. Defaults to the Composer root package version.
    'app_version' => env('APP_VERSION'),

    'commands' => [
        'max_per_run' => 20,
    ],

    'diagnostics' => [
        'max_log_lines' => 50,
        'max_log_line_length' => 300,
        'log_tail_bytes' => 262144,
    ],
];
