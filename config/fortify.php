<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'views' => false,
    'home' => '/',
    'prefix' => 'api/v1/auth',
    'domain' => null,
    'lowercase_usernames' => false,
    'limiters' => [
        'login' => 'login',
    ],
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
    ],
];
