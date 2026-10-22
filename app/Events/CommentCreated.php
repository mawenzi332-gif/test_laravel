<?php

namespace App\Events;

use App\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;

class CommentCreated
{
    use Dispatchable;

    public function __construct(public int $commentId, public int $rootId) {}

    public static function dispatchFor(Comment $comment): void
    {
        self::dispatch((int) $comment->getKey(), (int) ($comment->root_id ?? $comment->getKey()));
    }
}
