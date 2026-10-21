<?php

namespace App\Http\Controllers\Api\V1;

use App\Comments\CommentRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListCommentsRequest;
use App\Http\Resources\CommentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CommentController extends Controller
{
    public function index(ListCommentsRequest $request, CommentRepository $comments): AnonymousResourceCollection
    {
        $validated = $request->validated();

        return CommentResource::collection($comments->paginatePublicTree(
            $validated['sort_by'] ?? 'date',
            $validated['direction'] ?? 'desc',
            (int) ($validated['page'] ?? 1),
        ));
    }
}
