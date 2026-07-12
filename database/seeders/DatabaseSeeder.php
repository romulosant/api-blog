<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categoryNames = ['Geral', 'Tecnologia', 'Lifestyle'];
        $categories = collect($categoryNames)->mapWithKeys(
            fn (string $name) => [$name => Category::query()->firstOrCreate(['name' => $name])]
        );

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $posts = [
            [
                'author' => $user,
                'category' => $categories['Tecnologia'],
                'title' => 'Primeiros passos com Laravel e Sanctum',
                'content' => "Neste post explicamos como autenticar uma API com Laravel Sanctum.\n\nCobriremos register, login e proteção de rotas com tokens.",
            ],
            [
                'author' => $user,
                'category' => $categories['Geral'],
                'title' => 'Bem-vindo ao blog da API',
                'content' => "Este é o primeiro post de exemplo do projeto.\n\nUse as rotas de posts e comments para explorar a API.",
            ],
            [
                'author' => $user,
                'category' => $categories['Lifestyle'],
                'title' => 'Produtividade no home office',
                'content' => "Algumas dicas rápidas: rotina fixa, pausas curtas e um ambiente organizado.\n\nTambém vale desligar notificações no horário de foco.",
            ],
            [
                'author' => $admin,
                'category' => $categories['Tecnologia'],
                'title' => 'Checklist de API REST em Laravel',
                'content' => "1. Validação com Form Requests\n2. Policies para autorização\n3. Migrations na ordem correta\n4. Seeds para dados de desenvolvimento\n5. Testes de feature",
            ],
            [
                'author' => $admin,
                'category' => $categories['Geral'],
                'title' => 'Como contribuir com o projeto',
                'content' => "Crie uma branch a partir de develop, implemente a feature e abra um PR.\n\nMantenha commits pequenos e mensagens claras.",
            ],
        ];

        foreach ($posts as $data) {
            $post = Post::query()->create([
                'user_id' => $data['author']->id,
                'category_id' => $data['category']->id,
                'title' => $data['title'],
                'slug' => Str::slug($data['title']),
                'content' => $data['content'],
            ]);

            Comment::query()->create([
                'user_id' => $data['author']->id === $user->id ? $admin->id : $user->id,
                'post_id' => $post->id,
                'content' => 'Ótimo post! Comentário de exemplo gerado pelo seeder.',
            ]);
        }
    }
}
