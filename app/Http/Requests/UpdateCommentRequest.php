<?php

namespace App\Http\Requests;

use App\Rules\CommentAttachmentCount;
use App\Rules\CommentAttachmentFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('comment')) ?? false;
    }

    public function rules(): array
    {
        return [
            'text' => ['sometimes', 'required', 'string'],
            'attachments_to_keep' => ['sometimes', 'array', new CommentAttachmentCount],
            'attachments_to_keep.*' => ['required', 'integer', 'distinct'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['required', 'file', new CommentAttachmentFile],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->exists('text') && ! $this->exists('attachments_to_keep') && ! $this->exists('attachments')) {
                $validator->errors()->add('text', 'Provide text or attachment changes.');
            }
        }];
    }
}
