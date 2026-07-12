<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $comment = $post->comments()->create([
            'user_id' => $user->id,
            'content' => $request->validated()['content'],
        ]);

        return response()->json($comment, 201);
    }

    public function destroy(Comment $comment): JsonResponse
    {
        if ($response = $this->denyIfNotOwner($comment)) {
            return $response;
        }

        $comment->delete();

        return response()->json(['message' => 'Comentário excluído com sucesso.'], 200);
    }

    private function denyIfNotOwner(Comment $comment): ?JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($comment->user_id !== $user->id) {
            return response()->json(['message' => 'Comentário não encontrado.'], 404);
        }

        return null;
    }
}