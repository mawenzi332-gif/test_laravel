<?php

namespace App\Comments;

use Illuminate\Validation\ValidationException;
use Stevebauman\Purify\Facades\Purify;

class CommentSanitizer
{
    public function sanitize(string $html): string
    {
        return Purify::config('comments')->clean($html);
    }

    public function sanitizeRequired(string $html): string
    {
        $sanitized = $this->sanitize($html);
        $plainText = html_entity_decode(strip_tags($sanitized), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/[^\s\p{Z}]/u', $plainText) !== 1) {
            throw ValidationException::withMessages([
                'text' => 'The text field must contain readable content after sanitization.',
            ]);
        }

        return $sanitized;
    }
}
