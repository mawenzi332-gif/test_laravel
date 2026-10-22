<?php

namespace App\Http\Requests;

use App\Rules\CommentAttachmentCount;
use App\Rules\CommentAttachmentFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $registered = $this->user() !== null;

        return [
            'text' => ['required', 'string'],
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('comments', 'id')->whereNull('deleted_at')],
            'guest_name' => [$registered ? 'prohibited' : 'required', 'string', 'max:255'],
            'guest_email' => [$registered ? 'prohibited' : 'required', 'string', 'email', 'max:255'],
            'guest_home_page' => [$registered ? 'prohibited' : 'sometimes', 'nullable', 'string', 'url:http,https', 'max:2048'],
            'g-recaptcha-response' => ['required', 'string', 'captcha'],
            'attachments' => ['sometimes', 'array', new CommentAttachmentCount],
            'attachments.*' => ['required', 'file', new CommentAttachmentFile],
        ];
    }
}
