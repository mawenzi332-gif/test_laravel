<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class CommentAttachmentFile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('The :attribute must be a valid uploaded file.');

            return;
        }

        $mime = $value->getMimeType();
        $size = $value->getSize();

        if ($mime === 'text/plain') {
            if ($size > (int) config('comment-media.text_max_bytes')) {
                $fail('The :attribute may not be larger than 100 KB.');
            }

            if (! mb_check_encoding(file_get_contents($value->getRealPath()), 'UTF-8')) {
                $fail('The :attribute must contain valid UTF-8 text.');
            }

            return;
        }

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
            $fail('The :attribute must be a JPG, PNG, GIF, or TXT file.');

            return;
        }

        if ($size > (int) config('comment-media.image_max_bytes')) {
            $fail('The :attribute exceeds the configured image size limit.');
        }

        $image = @getimagesize($value->getRealPath());

        if ($image === false || ($image['mime'] ?? null) !== $mime) {
            $fail('The :attribute must be a valid image file.');
        }
    }
}
