<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Comment extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->useDisk('local')
            ->storeConversionsOnDisk('local')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'text/plain']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        if ($media === null || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $this->addMediaConversion('preview')
            ->fit(Fit::Max, (int) config('comment-media.preview_width'), (int) config('comment-media.preview_height'))
            ->format('jpg')
            ->performOnCollections('attachments')
            ->nonQueued();
    }

    protected $fillable = [
        'user_id',
        'parent_id',
        'root_id',
        'guest_name',
        'guest_email',
        'guest_home_page',
        'body_html',
        'edited_at',
        'deletion_type',
    ];

    protected function casts(): array
    {
        return [
            'edited_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::restoring(function (Comment $comment): void {
            $comment->deletion_type = null;
        });

        static::saving(function (Comment $comment): void {
            $id = $comment->getKey();

            if ($id === null) {
                return;
            }

            if ($comment->parent_id !== null && (string) $comment->parent_id === (string) $id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A comment cannot be its own parent.',
                ]);
            }

            if ($comment->root_id !== null && (string) $comment->root_id === (string) $id) {
                throw ValidationException::withMessages([
                    'root_id' => 'A comment cannot be its own root.',
                ]);
            }
        });
    }

    protected function runSoftDelete(): void
    {
        // Persist deletion time and type together to satisfy the database invariant.
        $query = $this->setKeysForSaveQuery($this->newModelQuery());
        $time = $this->freshTimestamp();
        $this->deletion_type ??= 'user';

        $columns = [
            $this->getDeletedAtColumn() => $this->fromDateTime($time),
            'deletion_type' => $this->deletion_type,
        ];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);
        $this->syncOriginalAttributes(array_keys($columns));
        $this->fireModelEvent('trashed', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function root(): BelongsTo
    {
        return $this->belongsTo(self::class, 'root_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
