<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Post;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;   // ← LINHA NOVA (resolve Intelephense)
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PostController extends Controller
{
    use AuthorizesRequests;   // ← TRAIT EXPLÍCITA (isso mata o erro)

    /**
     * Lista posts do usuário autenticado com filtros opcionais:
     * - category: filtra pelo nome da categoria (?category=laravel)
     * - search: busca no título (?search=eloquent)
     * - page / per_page: paginação (?page=1&per_page=10)
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();

        $query = $user->posts()
            ->with(['comments', 'category'])
            ->latest();

        if ($request->filled('category')) {
            $category = $request->query('category');

            $query->whereHas('category', function ($q) use ($category) {
                $q->where('name', $category);
            });
        }

        if ($request->filled('search')) {
            $search = $request->query('search');

            $query->where('title', 'like', '%' . $search . '%');
        }

        $perPage = $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $posts = $query->paginate($perPage);

        return response()->json($posts);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = auth()->user();

        $post = $user->posts()->create([
            'category_id' => $validated['category_id'],
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']),
            'content' => $validated['content'],
        ]);

        return response()->json($post, 201);
    }

    public function show(Post $post): JsonResponse
    {
        $this->authorize('view', $post);      // ← agora o Intelephense reconhece

        return response()->json($post->load('category', 'comments'));
    }

    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $this->authorize('update', $post);    // ← só autor edita
        $validated = $request->validated();

        if (isset($validated['title'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $post->update($validated);

        return response()->json($post);
    }

    public function destroy(Post $post): JsonResponse
    {
        $this->authorize('delete', $post);    // ← só autor deleta
        $post->delete();

        return response()->json(['message' => 'Post excluído com sucesso.'], 200);
    }
}
