<?php

namespace App\Comments;

use App\Models\Comment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CommentRepository
{
    private const PAGE_SIZE = 25;

    public function paginatePublicTree(string $sortBy = 'date', string $direction = 'desc', int $page = 1): LengthAwarePaginator
    {
        $roots = Comment::withTrashed()
            ->with(['user'])
            ->whereNull('comments.parent_id')
            ->where(function (Builder $query): void {
                $query->whereNull('comments.deleted_at')
                    ->orWhereExists(function ($descendants): void {
                        $descendants->selectRaw('1')
                            ->from('comments as descendants')
                            ->whereColumn('descendants.root_id', 'comments.id')
                            ->whereNull('descendants.deleted_at');
                    });
            })
            ->leftJoin('users', 'users.id', '=', 'comments.user_id')
            ->select('comments.*');

        $sortColumn = match ($sortBy) {
            'name' => 'CASE WHEN comments.user_id IS NULL THEN comments.guest_name ELSE users.name END',
            'email' => 'CASE WHEN comments.user_id IS NULL THEN comments.guest_email ELSE users.email END',
            default => 'comments.created_at',
        };

        $roots->orderByRaw($sortColumn.' '.$direction)
            ->orderBy('comments.id', $direction);

        $paginator = $roots->paginate(self::PAGE_SIZE, ['comments.*'], 'page', $page);
        $rootComments = $paginator->getCollection();

        if ($rootComments->isEmpty()) {
            return $paginator->setCollection(collect());
        }

        $rootIds = $rootComments->modelKeys();
        $treeComments = Comment::withTrashed()
            ->with(['user', 'media'])
            ->where(function (Builder $query) use ($rootIds): void {
                $query->whereIn('comments.id', $rootIds)
                    ->orWhereIn('comments.root_id', $rootIds);
            })
            ->orderBy('comments.created_at')
            ->orderBy('comments.id')
            ->get();

        $nodes = $treeComments->keyBy(fn (Comment $comment): string => (string) $comment->getKey());
        $childrenByParent = $treeComments
            ->filter(fn (Comment $comment): bool => $comment->parent_id !== null)
            ->groupBy(fn (Comment $comment): string => (string) $comment->parent_id);

        foreach ($treeComments as $comment) {
            $comment->setRelation('children', $childrenByParent->get((string) $comment->getKey(), collect()));
        }

        $publicRoots = $rootComments
            ->map(fn (Comment $root): ?Comment => $this->prune($nodes->get((string) $root->getKey())))
            ->filter()
            ->values();

        return $paginator->setCollection($publicRoots);
    }

    private function prune(Comment $comment): ?Comment
    {
        /** @var Collection<int, Comment> $children */
        $children = $comment->getRelation('children');
        $visibleChildren = $children
            ->map(fn (Comment $child): ?Comment => $this->prune($child))
            ->filter()
            ->values();

        $comment->setRelation('children', $visibleChildren);

        if ($comment->trashed() && $visibleChildren->isEmpty()) {
            return null;
        }

        return $comment;
    }
}
