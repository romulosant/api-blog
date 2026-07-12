<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $categories = collect(['Geral', 'Tecnologia', 'Lifestyle'])
            ->map(fn (string $name) => Category::query()->firstOrCreate(['name' => $name]));

        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $admin = User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $users = User::factory(8)->create();
        $allUsers = $users->concat([$testUser, $admin]);

        $posts = Post::factory(15)
            ->recycle($allUsers)
            ->recycle($categories)
            ->create();

        foreach ($posts as $post) {
            Comment::factory(fake()->numberBetween(1, 4))
                ->recycle($allUsers)
                ->for($post)
                ->create();
        }
    }
}
