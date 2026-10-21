<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\PasswordResetRequest;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Contracts\Support\Responsable;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;

class AuthController extends Controller
{
    #[Response(status: 200, type: 'array{two_factor: bool}')]
    public function login(LoginRequest $request)
    {
        return app(AuthenticatedSessionController::class)->store($request);
    }

    public function resetPassword(PasswordResetRequest $request): Responsable
    {
        return app(NewPasswordController::class)->store($request);
    }
}
