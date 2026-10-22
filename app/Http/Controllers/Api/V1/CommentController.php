<?php

namespace App\Http\Controllers\Api\V1;

use App\Comments\CommentRepository;
use App\Comments\CommentSanitizer;
use App\Comments\CommentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListCommentsRequest;
use App\Http\Requests\PreviewCommentRequest;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

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

    public function store(StoreCommentRequest $request, CommentService $comments): JsonResponse
    {
        return (new CommentResource($comments->create($request->validated(), $request->user())))
            ->response()
            ->setStatusCode(201);
    }

    public function preview(PreviewCommentRequest $request, CommentSanitizer $sanitizer): JsonResponse
    {
        return response()->json(['data' => ['body_html' => $sanitizer->sanitizeRequired($request->validated('text'))]]);
    }

    public function update(UpdateCommentRequest $request, Comment $comment, CommentService $comments): CommentResource
    {
        return new CommentResource($comments->update($comment, $request->validated()));
    }

    public function destroy(Comment $comment, CommentService $comments): Response
    {
        Gate::authorize('delete', $comment);
        $comments->delete($comment);

        return response()->noContent();
    }
}
