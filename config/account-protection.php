<?php

return [
    'rate_limits' => [
        'comment' => [
            'attempts' => (int) env('COMMENT_RATE_LIMIT', 10),
            'decay_minutes' => (int) env('COMMENT_RATE_LIMIT_DECAY', 1),
        ],
        'registration' => [
            'attempts' => (int) env('REGISTRATION_RATE_LIMIT', 5),
            'decay_minutes' => (int) env('REGISTRATION_RATE_LIMIT_DECAY', 60),
        ],
        'login' => [
            'attempts' => (int) env('LOGIN_RATE_LIMIT', 5),
            'decay_minutes' => (int) env('LOGIN_RATE_LIMIT_DECAY', 1),
        ],
        'password_recovery' => [
            'attempts' => (int) env('PASSWORD_RECOVERY_RATE_LIMIT', 3),
            'decay_minutes' => (int) env('PASSWORD_RECOVERY_RATE_LIMIT_DECAY', 15),
        ],
    ],
];
