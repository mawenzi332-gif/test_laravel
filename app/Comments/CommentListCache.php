<?php

namespace App\Comments;

use Illuminate\Support\Facades\Cache;

class CommentListCache
{
    private const VERSION_KEY = 'comments:list:version';

    public function remember(string $sortBy, string $direction, int $page, callable $resolve): mixed
    {
        $version = $this->version();
        $key = "comments:v{$version}:{$sortBy}:{$direction}:page:{$page}";

        return Cache::store()->remember($key, now()->addSeconds(config('comment-cache.ttl_seconds')), $resolve);
    }

    public function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 0, now()->addYears(10));
        Cache::increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        Cache::add(self::VERSION_KEY, 0, now()->addYears(10));

        return (int) Cache::get(self::VERSION_KEY, 0);
    }
}
