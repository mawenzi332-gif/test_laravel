<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttachmentController extends Controller
{
    public function show(Request $request, int $attachment): BinaryFileResponse
    {
        $media = $this->findActiveAttachment($attachment);
        $path = $media->getPath();

        abort_unless(is_file($path), 404);

        if ($media->mime_type === 'text/plain') {
            return response()->download($path, basename($media->file_name), [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return response()->file($path, [
            'Content-Type' => $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function preview(Request $request, int $attachment): BinaryFileResponse
    {
        $media = $this->findActiveAttachment($attachment);

        abort_unless(str_starts_with((string) $media->mime_type, 'image/'), 404);
        abort_unless($media->hasGeneratedConversion('preview'), 404);

        $path = $media->getPath('preview');
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function findActiveAttachment(int $id): Media
    {
        $media = Media::query()
            ->whereKey($id)
            ->where('model_type', 'comment')
            ->where('collection_name', 'attachments')
            ->firstOrFail();

        if (! Comment::query()->whereKey($media->model_id)->exists()) {
            Log::notice('Attachment read denied because its comment is unavailable.', ['media_id' => $media->getKey()]);
            abort(404);
        }

        return $media;
    }
}
