<?php

namespace App\Comments;

use Stevebauman\Purify\Facades\Purify;

class CommentSanitizer
{
    public function sanitize(string $html): string
    {
        return Purify::config('comments')->clean($html);
    }
}
