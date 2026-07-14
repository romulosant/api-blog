<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for Posts CRUD, authorization (Policy) and Comments.
 *
 * Conceitos-chave:
 *
 * 1) Sanctum::actingAs($user)
 *    Autentica o user nos testes SEM precisar gerar token manualmente.
 *    Equivale a enviar Authorization: Bearer <token> em toda request.
 *
 * 2) PostPolicy
 *    Regras de quem pode view/update/delete um post.
 *    - dono do post → permitido
 *    - admin (is_admin) → permitido em tudo (método before)
 *    - outro user → 403 Forbidden
 *
 * 3) CRUD
 *    Create / Read / Update / Delete — o ciclo completo de um recurso.
 *
 * 4) Factory
 *    Gera dados falsos realistas (User, Post, Category, Comment)
 *    sem precisar digitar campos um a um.
 */
class PostTest extends TestCase
{
    use RefreshDatabase;

    // ─── HELPERS ────────────────────────────────────────────

    /**
     * Autentica um user (cria se não passar um) e retorna ele.
     * Helper evita repetir Sanctum::actingAs em todo teste.
     */
    private function actingAsUser(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // ─── AUTH GATE ──────────────────────────────────────────

    public function test_posts_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertStatus(401);
    }

    // ─── CREATE (store) ─────────────────────────────────────

    public function test_user_can_create_a_post(): void
    {
        $user = $this->actingAsUser();
        $category = Category::factory()->create();

        $payload = [
            'category_id' => $category->id,
            'title' => 'Meu primeiro post',
            'content' => 'Conteúdo de teste do blog.',
        ];

        $response = $this->postJson('/api/posts', $payload);

        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'title' => 'Meu primeiro post',
                'content' => 'Conteúdo de teste do blog.',
                'user_id' => $user->id,
                'category_id' => $category->id,
            ]);

        // Slug é gerado automaticamente a partir do título
        $this->assertDatabaseHas('posts', [
            'title' => 'Meu primeiro post',
            'slug' => 'meu-primeiro-post',
            'user_id' => $user->id,
        ]);
    }

    public function test_create_post_validates_required_fields(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/posts', []);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'title', 'content']);
    }

    // ─── READ (index + show) ────────────────────────────────

    public function test_user_can_list_only_their_own_posts(): void
    {
        $user = $this->actingAsUser();
        $other = User::factory()->create();

        // 2 posts do user autenticado
        Post::factory()->count(2)->create(['user_id' => $user->id]);
        // 3 posts de outro user (não devem aparecer no index)
        Post::factory()->count(3)->create(['user_id' => $other->id]);

        $response = $this->getJson('/api/posts');

        $response
            ->assertStatus(200)
            ->assertJsonCount(2);
    }

    public function test_user_can_view_own_post(): void
    {
        $user = $this->actingAsUser();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->getJson("/api/posts/{$post->id}");

        $response
            ->assertStatus(200)
            ->assertJsonFragment(['id' => $post->id, 'title' => $post->title])
            // show carrega category e comments (eager load)
            ->assertJsonStructure(['id', 'title', 'category', 'comments']);
    }

    // ─── UPDATE ─────────────────────────────────────────────

    public function test_user_can_update_own_post(): void
    {
        $user = $this->actingAsUser();
        $post = Post::factory()->create([
            'user_id' => $user->id,
            'title' => 'Titulo antigo',
        ]);

        $response = $this->patchJson("/api/posts/{$post->id}", [
            'title' => 'Titulo novo',
            'content' => 'Conteudo atualizado.',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonFragment([
                'title' => 'Titulo novo',
                'slug' => 'titulo-novo',
            ]);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Titulo novo',
        ]);
    }

    // ─── DELETE ─────────────────────────────────────────────

    public function test_user_can_delete_own_post(): void
    {
        $user = $this->actingAsUser();
        $post = Post::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/posts/{$post->id}");

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Post excluído com sucesso.']);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    // ─── AUTHORIZATION (Policy) ─────────────────────────────
    //
    // PostPolicy garante: só o dono (ou admin) pode ver/editar/apagar.
    // Quando $this->authorize() falha → HTTP 403 Forbidden.

    public function test_user_cannot_view_another_users_post(): void
    {
        $this->actingAsUser();
        $foreignPost = Post::factory()->create(); // dono = outro user (factory)

        $response = $this->getJson("/api/posts/{$foreignPost->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_update_another_users_post(): void
    {
        $this->actingAsUser();
        $foreignPost = Post::factory()->create();

        $response = $this->patchJson("/api/posts/{$foreignPost->id}", [
            'title' => 'Hack attempt',
        ]);

        $response->assertStatus(403);

        // Título original permanece intacto
        $this->assertDatabaseHas('posts', [
            'id' => $foreignPost->id,
            'title' => $foreignPost->title,
        ]);
    }

    public function test_user_cannot_delete_another_users_post(): void
    {
        $this->actingAsUser();
        $foreignPost = Post::factory()->create();

        $response = $this->deleteJson("/api/posts/{$foreignPost->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('posts', ['id' => $foreignPost->id]);
    }

    public function test_admin_can_manage_any_post(): void
    {
        // UserFactory tem state admin() → is_admin = true
        // PostPolicy::before() libera tudo para admin
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $post = Post::factory()->create(); // dono = user comum

        // view
        $this->getJson("/api/posts/{$post->id}")->assertStatus(200);

        // update
        $this->patchJson("/api/posts/{$post->id}", [
            'title' => 'Admin editou',
        ])->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'title' => 'Admin editou',
        ]);

        // delete
        $this->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJson(['message' => 'Post excluído com sucesso.']);

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    }

    // ─── COMMENTS ───────────────────────────────────────────

    public function test_user_can_comment_on_a_post(): void
    {
        $user = $this->actingAsUser();
        $post = Post::factory()->create(); // pode ser post de qualquer um

        $response = $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => 'Ótimo post!',
        ]);

        $response
            ->assertStatus(201)
            ->assertJsonFragment([
                'content' => 'Ótimo post!',
                'user_id' => $user->id,
                'post_id' => $post->id,
            ]);

        $this->assertDatabaseHas('comments', [
            'post_id' => $post->id,
            'user_id' => $user->id,
            'content' => 'Ótimo post!',
        ]);
    }

    public function test_comment_requires_content(): void
    {
        $this->actingAsUser();
        $post = Post::factory()->create();

        $response = $this->postJson("/api/posts/{$post->id}/comments", [
            'content' => '',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    public function test_user_can_delete_own_comment(): void
    {
        $user = $this->actingAsUser();
        $comment = Comment::factory()->create(['user_id' => $user->id]);

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Comentário excluído com sucesso.']);

        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
    }

    public function test_user_cannot_delete_another_users_comment(): void
    {
        $this->actingAsUser();
        // Comentário de outro user
        $comment = Comment::factory()->create();

        $response = $this->deleteJson("/api/comments/{$comment->id}");

        // CommentController retorna 404 (não 403) para esconder existência
        $response
            ->assertStatus(404)
            ->assertJson(['message' => 'Comentário não encontrado.']);

        $this->assertDatabaseHas('comments', ['id' => $comment->id]);
    }
}
