<?php

namespace App\Comments;

use App\Events\CommentCreated;
use App\Jobs\BroadcastCommentCreated;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CommentService
{
    public function __construct(
        private readonly CommentSanitizer $sanitizer,
        private readonly CommentListCache $commentCache,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $user): Comment
    {
        $parent = isset($data['parent_id']) ? Comment::query()->findOrFail($data['parent_id']) : null;
        $attributes = [
            'user_id' => $user?->id,
            'parent_id' => $parent?->id,
            'root_id' => $parent === null ? null : ($parent->root_id ?? $parent->id),
            'body_html' => $this->sanitizer->sanitizeRequired($data['text']),
        ];

        if ($user === null) {
            $attributes += [
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_home_page' => $data['guest_home_page'] ?? null,
            ];
        }

        $createdMedia = [];
        try {
            $comment = DB::transaction(function () use ($attributes, $data, &$createdMedia): Comment {
                $comment = Comment::create($attributes);
                $this->addAttachments($comment, $data['attachments'] ?? [], $createdMedia);

                return $comment;
            });
        } catch (\Throwable $exception) {
            $this->cleanupCreatedMedia($createdMedia);
            throw $exception;
        }

        $this->commentCache->invalidate();
        CommentCreated::dispatchFor($comment);
        BroadcastCommentCreated::dispatch((int) $comment->getKey(), (int) ($comment->root_id ?? $comment->getKey()));

        return $comment->load(['user', 'media']);
    }

    /** @param array<string, mixed> $data */
    public function update(Comment $comment, array $data): Comment
    {
        $currentMedia = $comment->getMedia('attachments');
        $keepIds = array_key_exists('attachments_to_keep', $data)
            ? array_map('intval', $data['attachments_to_keep'])
            : $currentMedia->modelKeys();
        $currentIds = $currentMedia->modelKeys();
        sort($keepIds);
        sort($currentIds);

        if (array_diff($keepIds, $currentIds) !== []) {
            throw ValidationException::withMessages([
                'attachments_to_keep' => 'Only attachments belonging to this comment may be kept.',
            ]);
        }

        $newFiles = $data['attachments'] ?? [];
        if (count($keepIds) + count($newFiles) > (int) config('comment-media.max_attachments')) {
            throw ValidationException::withMessages([
                'attachments' => 'A comment may have no more than 3 attachments.',
            ]);
        }

        $bodyHtml = array_key_exists('text', $data)
            ? $this->sanitizer->sanitizeRequired($data['text'])
            : $comment->body_html;
        $attachmentsChanged = $keepIds !== $currentIds || $newFiles !== [];
        $textChanged = $bodyHtml !== $comment->body_html;

        if (! $attachmentsChanged && ! $textChanged) {
            return $comment->load(['user', 'media']);
        }

        $createdMedia = [];
        try {
            DB::transaction(function () use ($comment, $currentMedia, $keepIds, $newFiles, $bodyHtml, $attachmentsChanged, $textChanged, &$createdMedia): void {
                if ($textChanged) {
                    $comment->body_html = $bodyHtml;
                }

                $comment->edited_at = now();
                $comment->save();

                if ($attachmentsChanged) {
                    $this->addAttachments($comment, $newFiles, $createdMedia);

                    foreach ($currentMedia as $media) {
                        if (! in_array($media->getKey(), $keepIds, true)) {
                            $media->delete();
                        }
                    }
                }
            });
        } catch (\Throwable $exception) {
            $this->cleanupCreatedMedia($createdMedia);
            throw $exception;
        }

        $this->commentCache->invalidate();

        return $comment->refresh()->load(['user', 'media']);
    }

    public function delete(Comment $comment): void
    {
        try {
            DB::transaction(function () use ($comment): void {
                $comment->clearMediaCollection('attachments');
                $comment->deletion_type = 'user';
                $comment->delete();
            });
        } catch (\Throwable $exception) {
            Log::error('Comment deletion or attachment cleanup failed.', [
                'comment_id' => $comment->getKey(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->commentCache->invalidate();
    }

    /** @param array<int, UploadedFile> $files */
    private function addAttachments(Comment $comment, array $files, array &$createdMedia): void
    {
        foreach ($files as $file) {
            try {
                $createdMedia[] = $comment->addMedia($file)->toMediaCollection('attachments');
            } catch (\Throwable $exception) {
                Log::error('Comment attachment persistence failed.', [
                    'comment_id' => $comment->getKey(),
                    'exception' => $exception::class,
                ]);

                throw $exception;
            }
        }
    }

    /** @param array<int, Media> $mediaItems */
    private function cleanupCreatedMedia(array $mediaItems): void
    {
        foreach ($mediaItems as $media) {
            try {
                $media->delete();
            } catch (\Throwable $exception) {
                Log::error('Compensating cleanup of a comment attachment failed.', [
                    'media_id' => $media->getKey(),
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
