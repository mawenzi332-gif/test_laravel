<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CommentPublicationLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_guest_can_publish_sanitized_root_and_nested_replies_with_attachments(): void
    {
        $this->acceptCaptcha();
        $rootResponse = $this->postJson('/api/v1/comments', [
            'text' => '<strong onclick="x()">Root</strong><script>alert(1)</script>',
            'guest_name' => 'Guest Author',
            'guest_email' => 'guest@example.test',
            'guest_home_page' => 'https://guest.example.test',
            'g-recaptcha-response' => 'valid-token',
            'attachments' => [UploadedFile::fake()->createWithContent('note.txt', 'hello')],
        ])->assertCreated()
            ->assertJsonPath('data.author.name', 'Guest Author')
            ->assertJsonPath('data.body_html', '<strong>Root</strong>')
            ->assertJsonPath('data.author.home_page', 'https://guest.example.test')
            ->assertJsonPath('data.can_edit', false)
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.attachments.0.file_name', 'note.txt');

        $rootId = $rootResponse->json('data.id');
        $this->acceptCaptcha();
        $replyResponse = $this->postJson('/api/v1/comments', [
            'text' => 'reply',
            'guest_name' => 'Nested Guest',
            'guest_email' => 'nested@example.test',
            'parent_id' => $rootId,
            'g-recaptcha-response' => 'valid-token',
        ])->assertCreated();

        $reply = Comment::findOrFail($replyResponse->json('data.id'));
        $this->assertSame($rootId, $reply->root_id);
        $this->assertSame($rootId, $reply->parent_id);
        $this->assertNull($reply->user_id);

        $this->acceptCaptcha();
        $nestedResponse = $this->postJson('/api/v1/comments', [
            'text' => 'reply to a reply',
            'guest_name' => 'Deep Guest',
            'guest_email' => 'deep@example.test',
            'parent_id' => $reply->id,
            'g-recaptcha-response' => 'valid-token',
        ])->assertCreated();
        $nested = Comment::findOrFail($nestedResponse->json('data.id'));
        $this->assertSame($reply->id, $nested->parent_id);
        $this->assertSame($rootId, $nested->root_id);
    }

    public function test_registered_publication_uses_authenticated_author_and_rejects_guest_author_payload(): void
    {
        $user = User::factory()->create(['name' => 'Current User', 'email' => 'current@example.test']);
        $this->acceptCaptcha();

        $response = $this->actingAs($user)->postJson('/api/v1/comments', [
            'text' => 'registered root',
            'g-recaptcha-response' => 'valid-token',
        ])->assertCreated()
            ->assertJsonPath('data.author.name', 'Current User')
            ->assertJsonPath('data.author.email', 'current@example.test')
            ->assertJsonPath('data.can_edit', true)
            ->assertJsonPath('data.can_delete', true);

        $comment = Comment::findOrFail($response->json('data.id'));
        $this->assertSame($user->id, $comment->user_id);
        $this->assertNull($comment->guest_name);

        $this->acceptCaptcha();
        $this->actingAs($user)->postJson('/api/v1/comments', [
            'text' => 'spoof attempt',
            'guest_name' => 'Fake',
            'guest_email' => 'fake@example.test',
            'g-recaptcha-response' => 'valid-token',
        ])->assertUnprocessable()->assertJsonPath('error.details.guest_name.0', 'The guest name field is prohibited.');
    }

    public function test_preview_sanitizes_without_persisting_comments_or_media_and_captcha_is_required_for_publish(): void
    {
        $preview = $this->postJson('/api/v1/comments/preview', ['text' => '<a href="javascript:alert(1)">safe</a>'])
            ->assertOk()
            ->assertJsonPath('data.body_html', '<a>safe</a>');

        $this->assertNotEmpty($preview->json('data.body_html'));
        $this->assertDatabaseCount('comments', 0);
        $this->assertDatabaseCount('media', 0);

        $this->postJson('/api/v1/comments/preview', ['text' => '<script>alert(1)</script>'])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.text.0', 'The text field must contain readable content after sanitization.');

        $this->postJson('/api/v1/comments', [
            'text' => 'no captcha',
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'CAPTCHA_FAILED');

        $this->assertDatabaseCount('comments', 0);
    }

    public function test_only_owner_can_edit_registered_comment_and_attachment_sync_sets_edited_marker(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'before']);
        $oldMedia = $comment->addMedia(UploadedFile::fake()->createWithContent('old.txt', 'old'))->toMediaCollection('attachments');

        $this->actingAs($other)->patchJson("/api/v1/comments/{$comment->id}", ['text' => 'forbidden'])->assertForbidden();

        $this->actingAs($owner)->patchJson("/api/v1/comments/{$comment->id}", [
            'text' => '<i>after</i>',
            'attachments_to_keep' => [],
            'attachments' => [UploadedFile::fake()->createWithContent('new.txt', 'new')],
        ])->assertOk()
            ->assertJsonPath('data.body_html', '<i>after</i>')
            ->assertJsonPath('data.edited_at', fn ($value) => $value !== null)
            ->assertJsonPath('data.attachments.0.file_name', 'new.txt');

        $this->assertDatabaseMissing('media', ['id' => $oldMedia->id]);
        $this->assertFalse(Storage::disk('local')->exists($oldMedia->getPathRelativeToRoot()));
        $this->assertSame(1, $comment->fresh()->getMedia('attachments')->count());
    }

    public function test_edit_without_a_real_change_does_not_set_edited_at_and_foreign_attachment_ids_are_rejected(): void
    {
        $owner = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'same']);
        $elsewhere = Comment::create(['user_id' => $owner->id, 'body_html' => 'elsewhere']);
        $foreignMedia = $elsewhere->addMedia(UploadedFile::fake()->createWithContent('other.txt', 'other'))->toMediaCollection('attachments');

        $this->actingAs($owner)->patchJson("/api/v1/comments/{$comment->id}", ['text' => 'same'])
            ->assertOk()
            ->assertJsonPath('data.edited_at', null);

        $this->actingAs($owner)->patchJson("/api/v1/comments/{$comment->id}", ['attachments_to_keep' => [$foreignMedia->id]])
            ->assertUnprocessable()
            ->assertJsonPath('error.details.attachments_to_keep.0', 'Only attachments belonging to this comment may be kept.');
    }

    public function test_multipart_method_spoof_can_remove_every_attachment(): void
    {
        $owner = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'before']);
        $media = $comment->addMedia(UploadedFile::fake()->createWithContent('old.txt', 'old'))->toMediaCollection('attachments');

        $this->actingAs($owner)->post("/api/v1/comments/{$comment->id}", [
            '_method' => 'PATCH',
            '_attachments_to_keep_empty' => '1',
            'text' => 'after',
        ])->assertOk()
            ->assertJsonPath('data.body_html', 'after')
            ->assertJsonCount(0, 'data.attachments');

        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        $this->assertSame(0, $comment->fresh()->getMedia('attachments')->count());
    }

    public function test_edit_rejects_a_final_attachment_set_over_the_limit_without_changing_existing_state(): void
    {
        $owner = User::factory()->create();
        $comment = Comment::create(['user_id' => $owner->id, 'body_html' => 'unchanged']);
        $media = collect(['one.txt', 'two.txt', 'three.txt'])->map(
            fn (string $name) => $comment->addMedia(UploadedFile::fake()->createWithContent($name, $name))->toMediaCollection('attachments')
        );

        $this->actingAs($owner)->patchJson("/api/v1/comments/{$comment->id}", [
            'text' => 'should not persist',
            'attachments_to_keep' => $media->pluck('id')->all(),
            'attachments' => [UploadedFile::fake()->createWithContent('four.txt', 'four')],
        ])->assertUnprocessable()->assertJsonPath('error.details.attachments.0', 'A comment may have no more than 3 attachments.');

        $this->assertSame('unchanged', $comment->fresh()->body_html);
        $this->assertNull($comment->fresh()->edited_at);
        $this->assertSame(3, $comment->fresh()->getMedia('attachments')->count());
    }

    public function test_delete_clears_media_and_retains_only_required_public_tombstone(): void
    {
        $owner = User::factory()->create();
        $root = Comment::create(['user_id' => $owner->id, 'body_html' => 'private root text']);
        $rootMedia = $root->addMedia(UploadedFile::fake()->createWithContent('secret.txt', 'private'))->toMediaCollection('attachments');
        $reply = Comment::create([
            'parent_id' => $root->id,
            'root_id' => $root->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => 'visible reply',
        ]);

        $this->actingAs($owner)->deleteJson("/api/v1/comments/{$root->id}")->assertNoContent();

        $this->assertSoftDeleted('comments', ['id' => $root->id]);
        $this->assertDatabaseMissing('media', ['model_id' => $root->id, 'collection_name' => 'attachments']);
        $this->assertFalse(Storage::disk('local')->exists($rootMedia->getPathRelativeToRoot()));
        $this->getJson('/api/v1/comments')->assertOk()
            ->assertJsonPath('data.0.is_deleted', true)
            ->assertJsonMissingPath('data.0.body_html')
            ->assertJsonPath('data.0.children.0.id', $reply->id);
    }

    public function test_guest_comments_have_no_persistent_edit_or_delete_ownership(): void
    {
        $guest = Comment::create(['guest_name' => 'Guest', 'guest_email' => 'guest@example.test', 'body_html' => 'text']);
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson("/api/v1/comments/{$guest->id}", ['text' => 'changed'])->assertForbidden();
        $this->actingAs($user)->deleteJson("/api/v1/comments/{$guest->id}")->assertForbidden();
        $this->assertDatabaseHas('comments', ['id' => $guest->id, 'deleted_at' => null, 'body_html' => 'text']);
    }

    public function test_attachment_reads_use_safe_txt_headers_and_deny_media_for_deleted_comments(): void
    {
        $comment = Comment::create(['guest_name' => 'Guest', 'guest_email' => 'guest@example.test', 'body_html' => 'body']);
        $media = $comment->addMedia(UploadedFile::fake()->createWithContent('note.txt', 'safe text'))->toMediaCollection('attachments');

        $this->get(route('api.v1.attachments.show', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'attachment; filename=note.txt');

        $comment->delete();
        $this->getJson(route('api.v1.attachments.show', $media->id))->assertNotFound();
        $this->getJson(route('api.v1.attachments.preview', $media->id))->assertNotFound();
    }

    public function test_image_original_and_generated_preview_are_available_through_private_read_routes(): void
    {
        $comment = Comment::create(['guest_name' => 'Guest', 'guest_email' => 'guest@example.test', 'body_html' => 'body']);
        $media = $comment->addMedia(UploadedFile::fake()->createWithContent('pixel.png', $this->imageBlob()))
            ->toMediaCollection('attachments');

        $this->get(route('api.v1.attachments.show', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get(route('api.v1.attachments.preview', $media->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    private function imageBlob(): string
    {
        $image = new \Imagick;
        $image->newImage(1, 1, new \ImagickPixel('white'));
        $image->setImageFormat('png');

        return $image->getImageBlob();
    }

    private function acceptCaptcha(): void
    {
        $captcha = Mockery::mock();
        $captcha->shouldReceive('verifyResponse')->once()->with('valid-token', request()->ip())->andReturnTrue();
        app()->instance('captcha', $captcha);
    }
}
