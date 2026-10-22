<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    /**
     * @return array{id: int, parent_id: int|null, created_at: string, is_deleted?: bool, edited_at?: string|null, body_html?: string, author?: array{name: string, email: string, home_page: string|null}, can_edit?: bool, can_delete?: bool, attachments?: list<array{id: int, name: string, file_name: string, mime_type: string|null, size: int, has_preview: bool, url: string, preview_url: string|null}>, children: list<CommentResource>}
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'created_at' => $this->created_at,
        ];

        if ($this->trashed()) {
            $data['is_deleted'] = true;
            $data['children'] = CommentResource::collection($this->whenLoaded('children'));

            return $data;
        }

        $author = $this->user;

        $data += [
            'edited_at' => $this->edited_at,
            'body_html' => $this->body_html,
            'can_edit' => $request->user()?->can('update', $this->resource) ?? false,
            'can_delete' => $request->user()?->can('delete', $this->resource) ?? false,
            'author' => [
                'name' => $author?->name ?? $this->guest_name,
                'email' => $author?->email ?? $this->guest_email,
                'home_page' => $author?->home_page ?? $this->guest_home_page,
            ],
            'attachments' => $this->whenLoaded('media', fn () => $this->media
                ->where('collection_name', 'attachments')
                ->map(fn ($media): array => [
                    'id' => $media->id,
                    'name' => $media->name,
                    'file_name' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'has_preview' => $media->hasGeneratedConversion('preview'),
                    'url' => route('api.v1.attachments.show', ['attachment' => $media->id]),
                    'preview_url' => $media->hasGeneratedConversion('preview')
                        ? route('api.v1.attachments.preview', ['attachment' => $media->id])
                        : null,
                ])->values()),
            'children' => CommentResource::collection($this->whenLoaded('children')),
        ];

        return $data;
    }
}
