<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Mockery;
use Tests\TestCase;

class ProfileAndAbuseProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_requires_authentication_and_returns_only_public_profile_fields(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $user = User::factory()->create(['home_page' => 'https://ada.example.test']);

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.home_page', 'https://ada.example.test')
            ->assertJsonMissingPath('data.password');
    }

    public function test_profile_can_be_updated_and_profile_changes_bump_comment_cache_generation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/v1/me', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.test',
            'home_page' => 'https://ada.example.test',
        ])->assertOk()
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.email', 'ada@example.test')
            ->assertJsonPath('data.home_page', 'https://ada.example.test');

        $this->assertSame(1, cache('comments:list:version'));
    }

    public function test_duplicate_email_and_invalid_home_page_are_rejected(): void
    {
        $owner = User::factory()->create();
        $taken = User::factory()->create(['email' => 'taken@example.test']);

        $this->actingAs($owner)->patchJson('/api/v1/me', [
            'email' => $taken->email,
            'home_page' => 'javascript:alert(1)',
        ])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['email', 'home_page']]]);
    }

    public function test_comment_publication_limiter_uses_configured_redis_rate_limiter(): void
    {
        config(['account-protection.rate_limits.comment.attempts' => 1]);
        RateLimiter::clear('comment-publication:127.0.0.1');
        Route::middleware(['api', 'throttle:comment-publication'])
            ->get('/api/v1/__test-comment-limiter', fn () => response()->noContent());

        $this->get('/api/v1/__test-comment-limiter')->assertNoContent();
        $this->get('/api/v1/__test-comment-limiter')
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_login_rate_limiter_returns_the_normalized_429_response(): void
    {
        config(['account-protection.rate_limits.login.attempts' => 1]);
        RateLimiter::clear('login:missing@example.test|127.0.0.1');
        $request = [
            'email' => 'missing@example.test',
            'password' => 'incorrect-password',
        ];

        $this->postJson('/api/v1/auth/login', $request)->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', $request)
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_registration_and_password_recovery_have_separate_rate_limits(): void
    {
        config([
            'account-protection.rate_limits.registration.attempts' => 1,
            'account-protection.rate_limits.password_recovery.attempts' => 1,
        ]);
        RateLimiter::clear('registration:127.0.0.1');
        RateLimiter::clear('password-recovery:missing@example.test|127.0.0.1');

        $registration = [
            'name' => 'Missing Data',
            'email' => 'invalid',
            'password' => 'weak',
            'password_confirmation' => 'weak',
        ];
        $this->postJson('/api/v1/auth/register', $registration)->assertUnprocessable();
        $this->postJson('/api/v1/auth/register', $registration)
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');

        $recovery = ['email' => 'missing@example.test'];
        $this->postJson('/api/v1/auth/forgot-password', $recovery)->assertUnprocessable();
        $this->postJson('/api/v1/auth/forgot-password', $recovery)
            ->assertTooManyRequests()
            ->assertJsonPath('error.code', 'RATE_LIMITED');
    }

    public function test_captcha_validation_uses_the_recaptcha_package_integration(): void
    {
        $captcha = Mockery::mock();
        $captcha->shouldReceive('verifyResponse')->once()->with('verified-token', request()->ip())->andReturnTrue();
        app()->instance('captcha', $captcha);

        $validator = Validator::make(
            ['g-recaptcha-response' => 'verified-token'],
            ['g-recaptcha-response' => ['required', 'captcha']]
        );

        $this->assertTrue($validator->passes());
    }

    public function test_missing_or_invalid_captcha_response_fails_validation(): void
    {
        $captcha = Mockery::mock();
        $captcha->shouldReceive('verifyResponse')->once()->with('invalid-token', request()->ip())->andReturnFalse();
        app()->instance('captcha', $captcha);

        $validator = Validator::make(
            ['g-recaptcha-response' => 'invalid-token'],
            ['g-recaptcha-response' => ['required', 'captcha']]
        );

        $this->assertFalse($validator->passes());
    }
}
