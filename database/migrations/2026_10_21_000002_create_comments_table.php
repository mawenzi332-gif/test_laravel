<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedBigInteger('root_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->text('guest_home_page')->nullable();
            $table->mediumText('body_html');
            $table->timestamp('edited_at', 6)->nullable();
            $table->string('deletion_type', 32)->nullable();
            $table->softDeletes('deleted_at', 6);
            $table->timestamps(6);

            $table->index(['parent_id', 'created_at', 'id'], 'comments_root_created_idx');
            $table->index(['root_id', 'parent_id', 'created_at', 'id'], 'comments_tree_idx');
            $table->index(['user_id', 'parent_id', 'id'], 'comments_user_root_idx');
            $table->index(['parent_id', 'user_id', 'guest_name', 'id'], 'comments_guest_name_idx');
            $table->index(['parent_id', 'user_id', 'guest_email', 'id'], 'comments_guest_email_idx');

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('parent_id')->references('id')->on('comments')->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('root_id')->references('id')->on('comments')->restrictOnDelete()->restrictOnUpdate();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE comments
                    ADD CONSTRAINT comments_author_invariant_chk CHECK (
                        (user_id IS NOT NULL AND guest_name IS NULL AND guest_email IS NULL AND guest_home_page IS NULL)
                        OR (user_id IS NULL AND guest_name IS NOT NULL AND guest_email IS NOT NULL)
                    ),
                    ADD CONSTRAINT comments_parent_root_pair_chk CHECK (
                        (parent_id IS NULL AND root_id IS NULL)
                        OR (parent_id IS NOT NULL AND root_id IS NOT NULL)
                    ),
                    ADD CONSTRAINT comments_deletion_type_chk CHECK (
                        deletion_type IS NULL OR deletion_type IN ('user', 'moderator', 'administrator', 'system')
                    ),
                    ADD CONSTRAINT comments_deletion_state_chk CHECK (
                        (deleted_at IS NULL AND deletion_type IS NULL)
                        OR (deleted_at IS NOT NULL AND deletion_type IS NOT NULL)
                    )
                SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
