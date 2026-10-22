<?php

namespace Tests\Feature;

use App\Events\CommentCreated;
use App\Jobs\BroadcastCommentCreated;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CommentInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Storage::fake('local');
    }

    public function test_public_tree_is_cached_and_authorization_flags_are_computed_per_request(): void
    {
        $owner = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'cached comment']);
        $commentQueries = 0;
        DB::listen(function ($query) use (&$commentQueries): void {
            if (str_contains(strtolower($query->sql), 'comments')) {
                $commentQueries++;
            }
        });

        $this->getJson('/api/v1/comments')->assertOk()
            ->assertJsonPath('data.0.body_html', 'cached comment')
            ->assertJsonPath('data.0.can_edit', false);
        $queriesAfterFirstRequest = $commentQueries;

        $this->actingAs($owner)->getJson('/api/v1/comments')->assertOk()
            ->assertJsonPath('data.0.can_edit', true)
            ->assertJsonPath('data.0.can_delete', true);

        $this->assertGreaterThan(0, $queriesAfterFirstRequest);
        $this->assertSame($queriesAfterFirstRequest, $commentQueries, 'The second request should use the shared cached tree.');
    }

    public function test_public_tree_cache_expires_using_the_configured_ttl(): void
    {
        config(['comment-cache.ttl_seconds' => 1]);
        Comment::create(['guest_name' => 'Guest', 'guest_email' => 'guest@example.test', 'body_html' => 'expires']);
        $commentQueries = 0;
        DB::listen(function ($query) use (&$commentQueries): void {
            if (str_contains(strtolower($query->sql), 'comments')) {
                $commentQueries++;
            }
        });

        $this->getJson('/api/v1/comments')->assertOk();
        $queriesBeforeExpiry = $commentQueries;
        sleep(2);
        $this->getJson('/api/v1/comments')->assertOk();

        $this->assertGreaterThan($queriesBeforeExpiry, $commentQueries);
    }

    public function test_comment_create_dispatches_queued_broadcast_after_persistence_and_invalidates_list_cache(): void
    {
        Cache::put('comments:list:version', 4);
        Event::fake([CommentCreated::class]);
        Queue::fake();
        $captcha = Mockery::mock();
        $captcha->shouldReceive('verifyResponse')->once()->with('valid-token', request()->ip())->andReturnTrue();
        app()->instance('captcha', $captcha);

        $response = $this->postJson('/api/v1/comments', [
            'text' => 'new comment',
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'g-recaptcha-response' => 'valid-token',
        ])->assertCreated();

        $this->assertSame(5, (int) Cache::get('comments:list:version'));
        Event::assertDispatched(CommentCreated::class, function (CommentCreated $event) use ($response): bool {
            return $event->commentId === $response->json('data.id');
        });
        Queue::assertPushed(BroadcastCommentCreated::class, function (BroadcastCommentCreated $job) use ($response): bool {
            return $job->queue === 'broadcasts' && $job->commentId === $response->json('data.id');
        });
    }

    public function test_broadcast_jobs_use_broadcast_queue_with_three_attempts_and_increasing_backoff(): void
    {
        Queue::fake();

        BroadcastCommentCreated::dispatch(12, 7);

        Queue::assertPushed(BroadcastCommentCreated::class, function (BroadcastCommentCreated $job): bool {
            return $job->queue === 'broadcasts'
                && $job->tries === 3
                && $job->backoff === [10, 30, 60]
                && $job->commentId === 12;
        });
    }

    public function test_reset_password_notification_uses_mail_queue_with_five_attempts(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $user->sendPasswordResetNotification('reset-token');

        Queue::assertPushed(SendQueuedNotifications::class, function ($job): bool {
            return $job->queue === 'mail'
                && $job->tries === 5
                && $job->backoff() === [10, 30, 60, 120];
        });
    }

    public function test_attachment_update_and_comment_delete_invalidate_public_tree_cache(): void
    {
        $owner = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'before']);
        $comment->addMedia(UploadedFile::fake()->createWithContent('old.txt', 'old'))->toMediaCollection('attachments');
        Cache::put('comments:list:version', 8);

        $this->actingAs($owner)->patchJson("/api/v1/comments/{$comment->id}", [
            'attachments_to_keep' => [],
            'attachments' => [UploadedFile::fake()->createWithContent('new.txt', 'new')],
        ])->assertOk();

        $this->assertSame(9, (int) Cache::get('comments:list:version'));

        $this->actingAs($owner)->deleteJson("/api/v1/comments/{$comment->id}")->assertNoContent();

        $this->assertSame(10, (int) Cache::get('comments:list:version'));
    }

    public function test_profile_update_refreshes_registered_authors_in_the_cached_tree(): void
    {
        $owner = User::factory()->create(['name' => 'Before Update']);
        Comment::create(['user_id' => $owner->id, 'body_html' => 'authored']);

        $this->getJson('/api/v1/comments?sort_by=name')->assertOk()
            ->assertJsonPath('data.0.author.name', 'Before Update');

        $this->actingAs($owner)->patchJson('/api/v1/me', ['name' => 'After Update'])->assertOk();

        $this->getJson('/api/v1/comments?sort_by=name')->assertOk()
            ->assertJsonPath('data.0.author.name', 'After Update');
    }
}
