<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for authentication endpoints.
 *
 * Conceito: Feature Test = simula requisições HTTP reais à API
 * (register, login, logout) e verifica status + JSON + banco.
 *
 * RefreshDatabase: roda migrations em SQLite :memory: e limpa
 * o banco entre cada teste — isolamento total.
 */
class AuthTest extends TestCase
{
    use RefreshDatabase;

    // ─── REGISTER ───────────────────────────────────────────

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Romulo',
            'email' => 'romulo@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(201)
            ->assertJson(['message' => 'User registered successfully']);

        // Confirma que o usuário realmente entrou no banco
        $this->assertDatabaseHas('users', [
            'email' => 'romulo@example.com',
            'name' => 'Romulo',
        ]);

        // Confirma que a senha NÃO ficou em texto puro (cast "hashed")
        $user = User::where('email', 'romulo@example.com')->first();
        $this->assertNotEquals('password123', $user->password);
        $this->assertTrue(\Hash::check('password123', $user->password));
    }

    public function test_register_requires_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'short',
        ]);

        // 422 = Unprocessable Entity (falha de validação)
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'Outro',
            'email' => 'taken@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    // ─── LOGIN ──────────────────────────────────────────────

    public function test_user_can_login_and_receive_token(): void
    {
        // Factory cria user com senha "password" (veja UserFactory)
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(200)
            ->assertJsonStructure(['token']);

        // Token deve existir no banco (personal_access_tokens do Sanctum)
        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'email' => 'login@example.com',
            'password' => 'password123',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    public function test_login_fails_with_unknown_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'ghost@example.com',
            'password' => 'password123',
        ]);

        $response
            ->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials']);
    }

    // ─── LOGOUT ─────────────────────────────────────────────

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        // Cria um token real e autentica com ele
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/auth/logout');

        $response
            ->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);

        // Token deve ter sido revogado
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_requires_authentication(): void
    {
        // Sem token → 401 Unauthorized
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}
