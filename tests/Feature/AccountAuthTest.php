<?php

namespace Tests\Feature;

use App\Auth\QueuedResetPasswordNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_creates_and_authenticates_a_user(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'password' => 'lovelace1234',
            'password_confirmation' => 'lovelace1234',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('users', ['email' => 'ada@example.test']);
        $this->assertAuthenticatedAs(User::where('email', 'ada@example.test')->first());
    }

    public function test_duplicate_email_and_weak_password_are_rejected(): void
    {
        User::factory()->create(['email' => 'ada@example.test']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada',
            'email' => 'ada@example.test',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['email', 'password']]]);
    }

    public function test_login_and_logout_use_the_session_guard(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'correct-horse123',
        ])->assertOk();
        $this->assertAuthenticatedAs($user);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_invalid_credentials_use_the_authentication_error_code(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'incorrect-password',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'AUTHENTICATION_FAILED');
    }

    public function test_password_recovery_sends_a_reset_link_and_resets_the_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();
        Notification::assertSentTo($user, QueuedResetPasswordNotification::class);

        $resetNotification = Notification::sent($user, QueuedResetPasswordNotification::class)->first();
        $this->assertStringContainsString('/reset-password/'.$resetNotification->token, $resetNotification->toMail($user)->actionUrl);

        $token = Password::createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }
}
