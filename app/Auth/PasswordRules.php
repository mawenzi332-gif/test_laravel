<?php

namespace App\Auth;

use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /** @return array<int, Password|string> */
    public static function defaults(): array
    {
        return [
            'required',
            'confirmed',
            Password::min(10)->letters()->numbers(),
        ];
    }
}
