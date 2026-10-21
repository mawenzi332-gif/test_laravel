<?php

namespace App\Comments;

use Imagick;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

class MakeGifPreviewStatic
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $media = $event->media;

        if ($event->conversion->getName() !== 'preview' || $media->mime_type !== 'image/gif' || $media->collection_name !== 'attachments') {
            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'comment-gif-');

        try {
            $gif = new Imagick($media->getPath());
            $gif->setIteratorIndex(0);
            $firstFrame = $gif->getImage();
            $firstFrame->setImageFormat('png');
            $firstFrame->writeImage($temporaryPath);

            Image::useImageDriver('imagick')
                ->loadFile($temporaryPath)
                ->fit(Fit::Max, (int) config('comment-media.preview_width'), (int) config('comment-media.preview_height'))
                ->format('jpg')
                ->save($media->getPath('preview'));

            $firstFrame->clear();
            $firstFrame->destroy();
            $gif->clear();
            $gif->destroy();
        } finally {
            @unlink($temporaryPath);
        }
    }
}
