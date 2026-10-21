<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MySqlCommentConstraintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Run with DB_CONNECTION=mysql against an isolated test database to verify MySQL CHECK constraints.');
        }
    }

    public function test_author_tree_and_deletion_invariants_are_enforced_by_mysql(): void
    {
        $user = User::factory()->create();

        $this->expectException(QueryException::class);
        Comment::create([
            'user_id' => $user->id,
            'guest_name' => 'Spoofed guest',
            'guest_email' => 'spoof@example.test',
            'body_html' => '<p>Invalid hybrid author</p>',
        ]);
    }

    public function test_parent_and_root_must_be_null_or_set_together(): void
    {
        $this->expectException(QueryException::class);
        Comment::create([
            'parent_id' => 999,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Invalid tree node</p>',
        ]);
    }

    public function test_deleted_state_and_deletion_type_must_match(): void
    {
        $this->expectException(QueryException::class);
        Comment::create([
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => '<p>Invalid deletion state</p>',
            'deletion_type' => 'user',
        ]);
    }

    public function test_self_referencing_foreign_keys_remain_without_the_unsupported_checks(): void
    {
        $foreignKeys = collect(DB::select(<<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'comments'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            SQL))->pluck('CONSTRAINT_NAME');

        $checks = collect(DB::select(<<<'SQL'
            SELECT CONSTRAINT_NAME
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'comments'
                AND CONSTRAINT_TYPE = 'CHECK'
            SQL))->pluck('CONSTRAINT_NAME');

        $this->assertContains('comments_parent_id_foreign', $foreignKeys);
        $this->assertContains('comments_root_id_foreign', $foreignKeys);
        $this->assertContains('comments_user_id_foreign', $foreignKeys);
        $this->assertContains('comments_author_invariant_chk', $checks);
        $this->assertContains('comments_parent_root_pair_chk', $checks);
        $this->assertContains('comments_deletion_state_chk', $checks);
        $this->assertNotContains('comments_not_own_parent_chk', $checks);
        $this->assertNotContains('comments_not_own_root_chk', $checks);
    }
}
