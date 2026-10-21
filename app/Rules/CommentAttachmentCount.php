<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CommentAttachmentCount implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_countable($value) || count($value) > (int) config('comment-media.max_attachments')) {
            $fail('A comment may have no more than 3 attachments.');
        }
    }
}
