<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CommentReadApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_full_tree_with_normalized_current_authors_and_chronological_replies(): void
    {
        $user = User::factory()->create(['name' => 'Initial Name', 'email' => 'current@example.test', 'home_page' => 'https://example.test']);
        $user->update(['name' => 'Current Name']);
        $root = $this->guestComment('root');
        $reply = $this->guestComment('reply', $root);
        $nested = Comment::create(['user_id' => $user->id, 'parent_id' => $reply->id, 'root_id' => $root->id, 'body_html' => '<strong>nested</strong>']);
        $this->guestComment('earlier', $root, now()->subMinute());

        $this->getJson('/api/v1/comments')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.author.name', 'Guest root')
            ->assertJsonPath('data.0.children.0.body_html', '<p>earlier</p>')
            ->assertJsonPath('data.0.children.1.children.0.author.name', 'Current Name')
            ->assertJsonPath('data.0.children.1.children.0.author.email', 'current@example.test')
            ->assertJsonPath('data.0.children.1.children.0.author.home_page', 'https://example.test')
            ->assertJsonPath('data.0.children.1.children.0.id', $nested->id);
    }

    public function test_root_sort_modes_mix_registered_and_guest_authors_and_use_stable_ids(): void
    {
        $zulu = User::factory()->create(['name' => 'Zulu', 'email' => 'zulu@example.test']);
        $alpha = User::factory()->create(['name' => 'Alpha', 'email' => 'alpha@example.test']);
        $registeredZulu = Comment::create(['user_id' => $zulu->id, 'body_html' => 'z']);
        $guestBeta = $this->guestComment('beta', null, null, 'Beta', 'beta@example.test');
        $registeredAlpha = Comment::create(['user_id' => $alpha->id, 'body_html' => 'a']);
        $guestAlpha = $this->guestComment('alpha', null, null, 'Alpha', 'alpha@example.test');

        $this->getJson('/api/v1/comments?sort_by=name&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $registeredAlpha->id)
            ->assertJsonPath('data.1.id', $guestAlpha->id)
            ->assertJsonPath('data.2.id', $guestBeta->id)
            ->assertJsonPath('data.3.id', $registeredZulu->id);

        $this->getJson('/api/v1/comments?sort_by=email&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $registeredZulu->id)
            ->assertJsonPath('data.3.id', $registeredAlpha->id);

        $this->getJson('/api/v1/comments?sort_by=email&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $registeredAlpha->id)
            ->assertJsonPath('data.1.id', $guestAlpha->id)
            ->assertJsonPath('data.3.id', $registeredZulu->id);

        $this->getJson('/api/v1/comments?sort_by=name&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $registeredZulu->id)
            ->assertJsonPath('data.2.id', $guestAlpha->id)
            ->assertJsonPath('data.3.id', $registeredAlpha->id);

        $this->getJson('/api/v1/comments?sort_by=date&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $registeredZulu->id);

        $this->getJson('/api/v1/comments?sort_by=date&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.id', $guestAlpha->id);
    }

    public function test_it_paginates_roots_only_and_rejects_unknown_sort_parameters(): void
    {
        $first = $this->guestComment('root');
        $this->guestComment('reply', $first);

        for ($i = 1; $i < 26; $i++) {
            $this->guestComment("root {$i}");
        }

        $this->getJson('/api/v1/comments?direction=asc&page=1')
            ->assertOk()
            ->assertJsonCount(25, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/comments?direction=asc&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson('/api/v1/comments?sort_by=body')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $injectedSort = urlencode('date desc; DROP TABLE comments --');
        $this->getJson('/api/v1/comments?sort_by='.$injectedSort)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertTrue(Schema::hasTable('comments'));
    }

    public function test_it_prunes_deleted_leaves_and_keeps_only_required_tombstones(): void
    {
        $emptyDeletedRoot = $this->guestComment('empty');
        $emptyDeletedRoot->delete();
        $root = $this->guestComment('root');
        $deletedAncestor = $this->guestComment('ancestor', $root);
        $live = $this->guestComment('live', $deletedAncestor);
        $deletedLeaf = $this->guestComment('leaf', $root);
        $deletedAncestor->delete();
        $deletedLeaf->delete();
        $root->delete();

        $response = $this->getJson('/api/v1/comments')->assertOk();
        $response->assertJsonPath('data.0.id', $root->id)
            ->assertJsonPath('data.0.is_deleted', true)
            ->assertJsonPath('data.0.children.0.id', $deletedAncestor->id)
            ->assertJsonPath('data.0.children.0.is_deleted', true)
            ->assertJsonPath('data.0.children.0.children.0.id', $live->id)
            ->assertJsonMissingPath('data.0.children.0.body_html')
            ->assertJsonMissingPath('data.0.children.0.author')
            ->assertJsonMissingPath('data.0.children.0.attachments')
            ->assertJsonMissingPath('data.0.body_html')
            ->assertJsonMissingPath('data.0.author')
            ->assertJsonMissingPath('data.0.children.1');

        $this->assertNotSame($emptyDeletedRoot->id, data_get($response->json(), 'data.0.id'));
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_read_query_count_does_not_grow_with_tree_size(): void
    {
        $root = $this->guestComment('root');
        $this->guestComment('reply', $root);
        $users = User::factory()->count(8)->create();

        foreach ($users as $index => $user) {
            Comment::create([
                'user_id' => $user->id,
                'parent_id' => $root->id,
                'root_id' => $root->id,
                'body_html' => "registered reply {$index}",
            ]);
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        Cache::flush();
        $this->getJson('/api/v1/comments')->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::flushQueryLog();
        DB::enableQueryLog();

        for ($i = 0; $i < 8; $i++) {
            $this->guestComment("reply {$i}", $root);
        }

        DB::flushQueryLog();
        Cache::flush();
        $this->getJson('/api/v1/comments')->assertOk();
        $largeCount = count(DB::getQueryLog());

        $this->assertSame(5, $smallCount, 'A cache miss should use the root count/list, tree, author and media queries.');
        $this->assertSame($smallCount, $largeCount);
    }

    private function guestComment(string $body, ?Comment $parent = null, mixed $createdAt = null, string $name = '', string $email = ''): Comment
    {
        $comment = Comment::create([
            'parent_id' => $parent?->id,
            'root_id' => $parent === null ? null : ($parent->parent_id === null ? $parent->id : $parent->root_id),
            'guest_name' => $name !== '' ? $name : "Guest {$body}",
            'guest_email' => $email !== '' ? $email : strtolower(str_replace(' ', '.', $body)).'@example.test',
            'body_html' => "<p>{$body}</p>",
        ]);

        if ($createdAt !== null) {
            DB::table('comments')->where('id', $comment->id)->update(['created_at' => $createdAt]);
            $comment->refresh();
        }

        return $comment;
    }
}
