<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;


class PostPolicy
{
    /**
     * Admin tem acesso total (usando coluna is_admin)
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->is_admin) {        // ← removido hasRole
            return true;
        }
        return null;
    }

    public function view(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function update(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }

    public function delete(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}