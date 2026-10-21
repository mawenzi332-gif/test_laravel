<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BackendFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_approved_persistence_tables_are_created(): void
    {
        foreach (['users', 'comments', 'media', 'password_reset_tokens', 'failed_jobs', 'migrations'] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        foreach (['attachments', 'comment_closure', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'personal_access_tokens'] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Unexpected table [{$table}] exists.");
        }

        $this->assertTrue(Schema::hasColumns('users', ['name', 'email', 'home_page', 'password', 'created_at', 'updated_at']));
        $this->assertFalse(Schema::hasColumns('users', ['email_verified_at', 'remember_token']));
        $this->assertTrue(Schema::hasColumns('comments', [
            'user_id', 'parent_id', 'root_id', 'guest_name', 'guest_email', 'guest_home_page',
            'body_html', 'edited_at', 'deleted_at', 'deletion_type', 'created_at', 'updated_at',
        ]));
        $this->assertFalse(Schema::hasColumns('comments', ['username', 'email', 'home_page']));
    }

    public function test_user_and_comment_relations_soft_deletes_and_morph_aliases_work(): void
    {
        $user = User::factory()->create();
        $registeredComment = Comment::create([
            'user_id' => $user->id,
            'body_html' => '<p>Registered comment</p>',
        ]);
        $guestRoot = Comment::create([
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Root</p>',
        ]);
        $guestReply = Comment::create([
            'parent_id' => $guestRoot->id,
            'root_id' => $guestRoot->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Reply</p>',
        ]);

        $this->assertTrue($registeredComment->user->is($user));
        $this->assertTrue($user->comments->contains($registeredComment));
        $this->assertTrue($guestReply->parent->is($guestRoot));
        $this->assertTrue($guestReply->root->is($guestRoot));
        $this->assertTrue($guestRoot->replies->contains($guestReply));
        $this->assertSame('comment', $registeredComment->getMorphClass());
        $this->assertSame('user', $user->getMorphClass());

        $registeredComment->delete();

        $this->assertSoftDeleted('comments', ['id' => $registeredComment->id]);
        $this->assertSame('user', $registeredComment->deletion_type);
        $this->assertTrue($registeredComment->deleted_at->isImmutable());
        $this->assertSame('<p>Registered comment</p>', $registeredComment->fresh()->body_html);

        $registeredComment->restore();

        $this->assertDatabaseHas('comments', [
            'id' => $registeredComment->id,
            'deleted_at' => null,
            'deletion_type' => null,
        ]);
    }

    public function test_a_comment_cannot_be_its_own_parent(): void
    {
        $comment = new Comment([
            'parent_id' => 42,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Invalid parent</p>',
        ]);
        $comment->setAttribute($comment->getKeyName(), 42);

        try {
            $comment->save();
            $this->fail('Saving a comment with itself as parent should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
        }
    }

    public function test_a_comment_cannot_be_its_own_root(): void
    {
        $comment = new Comment([
            'root_id' => 42,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Invalid root</p>',
        ]);
        $comment->setAttribute($comment->getKeyName(), 42);

        try {
            $comment->save();
            $this->fail('Saving a comment with itself as root should fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('root_id', $exception->errors());
        }
    }

    public function test_api_validation_errors_use_the_standard_error_envelope(): void
    {
        Route::post('/api/v1/test-validation', function (Request $request) {
            $request->validate(['name' => ['required', 'string']]);

            return response()->noContent();
        });

        $this->postJson('/api/v1/test-validation', [])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.message', 'The submitted data is invalid.')
            ->assertJsonStructure(['error' => ['code', 'message', 'details' => ['name']]]);
    }

    public function test_api_not_found_errors_use_the_standard_error_envelope(): void
    {
        $this->getJson('/api/v1/missing-resource')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'The requested resource was not found.');
    }

    public function test_api_internal_errors_do_not_reveal_exception_details(): void
    {
        Route::get('/api/v1/test-internal-error', function () {
            throw new \RuntimeException('database password is secret');
        });

        $this->getJson('/api/v1/test-internal-error')
            ->assertInternalServerError()
            ->assertJsonPath('error.code', 'INTERNAL_ERROR')
            ->assertJsonPath('error.message', 'An unexpected error occurred.')
            ->assertDontSee('database password is secret')
            ->assertJsonStructure(['error' => ['code', 'message', 'details']]);
    }

    public function test_api_unauthenticated_errors_use_the_standard_error_envelope(): void
    {
        Route::get('/api/v1/test-authentication', fn () => response()->noContent())->middleware('auth');

        $this->getJson('/api/v1/test-authentication')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }
}
