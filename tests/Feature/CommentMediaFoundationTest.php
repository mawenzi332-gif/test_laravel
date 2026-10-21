<?php

namespace Tests\Feature;

use App\Models\Comment;
use App\Rules\CommentAttachmentCount;
use App\Rules\CommentAttachmentFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentMediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attachment_rule_accepts_valid_text_and_rejects_oversized_or_invalid_text(): void
    {
        $this->assertValid(UploadedFile::fake()->createWithContent('note.txt', 'valid text'));
        $this->assertInvalid(UploadedFile::fake()->createWithContent('large.txt', str_repeat('a', 102_401)));
        $this->assertInvalid(UploadedFile::fake()->createWithContent('binary.txt', "\xFF\xFE"));
        $this->assertInvalid(UploadedFile::fake()->createWithContent('bad.svg', '<svg></svg>'));
    }

    public function test_attachment_rule_detects_valid_images_and_applies_the_configured_image_limit(): void
    {
        $png = $this->imageBlob('png', 1, 1);
        $this->assertValid(UploadedFile::fake()->createWithContent('tiny.png', $png));

        config(['comment-media.image_max_bytes' => 4]);
        $this->assertInvalid(UploadedFile::fake()->createWithContent('too-large.png', $png));
    }

    public function test_attachment_count_rule_allows_zero_to_three_files_only(): void
    {
        foreach ([[], ['one'], ['one', 'two', 'three']] as $attachments) {
            $this->assertValidCount($attachments);
        }

        $this->assertInvalidCount(['one', 'two', 'three', 'four']);
    }

    public function test_image_and_static_gif_previews_are_synchronous_private_and_never_upscale(): void
    {
        Storage::fake('local');

        $comment = Comment::create([
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.test',
            'body_html' => 'body',
        ]);

        $png = $this->imageBlob('png', 1, 1);
        $smallImage = $comment->addMediaFromString($png)
            ->usingFileName('tiny.png')
            ->toMediaCollection('attachments');

        $this->assertSame('local', $smallImage->disk);
        $this->assertSame('local', $smallImage->conversions_disk);
        $this->assertTrue($smallImage->hasGeneratedConversion('preview'));
        $smallPreview = new \Imagick($smallImage->getPath('preview'));
        $this->assertSame(1, $smallPreview->getImageWidth());
        $this->assertSame(1, $smallPreview->getImageHeight());
        $this->assertSame('JPEG', strtoupper($smallPreview->getImageFormat()));

        $largeImage = $comment->addMediaFromString($this->imageBlob('png', 640, 480))
            ->usingFileName('landscape.png')
            ->toMediaCollection('attachments');
        $largePreview = new \Imagick($largeImage->getPath('preview'));
        $this->assertSame(320, $largePreview->getImageWidth());
        $this->assertSame(240, $largePreview->getImageHeight());

        $gif = $this->imageBlob('gif', 1, 1, true);
        $gifMedia = $comment->addMediaFromString($gif)
            ->usingFileName('tiny.gif')
            ->toMediaCollection('attachments');
        $this->assertTrue($gifMedia->hasGeneratedConversion('preview'));

        $gifPreview = new \Imagick($gifMedia->getPath('preview'));
        $this->assertSame('JPEG', strtoupper($gifPreview->getImageFormat()));
        $this->assertSame(1, $gifPreview->getNumberImages());
        $this->assertLessThanOrEqual(320, $gifPreview->getImageWidth());
        $this->assertLessThanOrEqual(240, $gifPreview->getImageHeight());
    }

    private function assertValid(UploadedFile $file): void
    {
        $errors = [];
        (new CommentAttachmentFile)->validate('attachment', $file, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->assertSame([], $errors);
    }

    private function assertInvalid(UploadedFile $file): void
    {
        $errors = [];
        (new CommentAttachmentFile)->validate('attachment', $file, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors);
    }

    private function assertValidCount(array $attachments): void
    {
        $errors = [];
        (new CommentAttachmentCount)->validate('attachments', $attachments, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->assertSame([], $errors);
    }

    private function assertInvalidCount(array $attachments): void
    {
        $errors = [];
        (new CommentAttachmentCount)->validate('attachments', $attachments, function (string $message) use (&$errors): void {
            $errors[] = $message;
        });

        $this->assertNotEmpty($errors);
    }

    private function imageBlob(string $format, int $width, int $height, bool $animated = false): string
    {
        $image = new \Imagick;
        $image->newImage($width, $height, new \ImagickPixel('white'));
        $image->setImageFormat($format);

        if ($animated) {
            $frame = new \Imagick;
            $frame->newImage($width, $height, new \ImagickPixel('black'));
            $frame->setImageFormat($format);
            $image->addImage($frame);
        }

        return $image->getImagesBlob();
    }
}
