# API Blog

API REST de blog construída com **Laravel** e autenticação via **Laravel Sanctum**.

Permite registrar usuários, autenticar com token Bearer, gerenciar posts (CRUD), listar categorias e criar/excluir comentários.

---

## Stack

| Tecnologia | Uso |
|------------|-----|
| PHP 8.3+ | Runtime |
| Laravel 13 | Framework |
| Laravel Sanctum | Tokens de API (Bearer) |
| SQLite (padrão) | Banco de dados local |
| PHPUnit | Testes automatizados |

---

## Requisitos

- PHP `^8.3`
- Composer
- Extensões PHP comuns do Laravel (`pdo_sqlite` ou driver do banco escolhido)

---

## Instalação e setup

```bash
# 1. Clone o repositório
git clone https://github.com/romulosant/api-blog.git
cd api-blog

# 2. Instale as dependências
composer install

# 3. Configure o ambiente
cp .env.example .env
php artisan key:generate

# 4. Crie o banco SQLite (se ainda não existir)
# No Windows (PowerShell): New-Item database/database.sqlite -ItemType File
# No Linux/macOS:
touch database/database.sqlite

# 5. Rode as migrations
php artisan migrate

# 6. (Opcional) Popule com dados de exemplo
php artisan db:seed

# 7. Suba o servidor
php artisan serve
```

A API fica disponível em: `http://localhost:8000`

> Prefixo de todas as rotas da API: **`/api`**

### Setup rápido (script do Composer)

```bash
composer setup
```

---

## Autenticação (Sanctum)

A API usa **token Bearer** (Sanctum personal access tokens).

### Fluxo

1. **Register** — cria o usuário  
2. **Login** — retorna um `token`  
3. Nas rotas protegidas, envie o header:

```http
Authorization: Bearer {seu_token}
Accept: application/json
Content-Type: application/json
```

4. **Logout** — revoga o token atual  

### Rotas públicas vs protegidas

| Tipo | Rotas |
|------|--------|
| **Públicas** | `POST /api/auth/register`, `POST /api/auth/login` |
| **Protegidas** (`auth:sanctum`) | logout, categories, posts, comments |

Sem token válido em rota protegida → **`401 Unauthorized`**.

### Autorização de posts (Policy)

Além de estar autenticado, operações em um post específico respeitam a **`PostPolicy`**:

| Papel | view / update / delete de um post |
|-------|-----------------------------------|
| **Dono do post** | permitido |
| **Admin** (`is_admin = true`) | permitido em qualquer post |
| **Outro usuário** | **`403 Forbidden`** |

Comentários: só o **autor do comentário** pode excluir. Se não for o dono, a API responde **`404`** (não revela existência).

---

## Endpoints

Base URL de desenvolvimento: `http://localhost:8000/api`

### Auth

#### `POST /auth/register`

Cria um novo usuário.

**Body (JSON):**

| Campo | Tipo | Regras |
|-------|------|--------|
| `name` | string | obrigatório, max 255 |
| `email` | string | obrigatório, e-mail único |
| `password` | string | obrigatório, min 8 |

**Resposta `201`:**

```json
{
  "message": "User registered successfully"
}
```

**Exemplo (cURL):**

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"Romulo\",\"email\":\"romulo@example.com\",\"password\":\"password123\"}"
```

---

#### `POST /auth/login`

Autentica e retorna o token Sanctum.

**Body (JSON):**

| Campo | Tipo | Regras |
|-------|------|--------|
| `email` | string | obrigatório, e-mail |
| `password` | string | obrigatório, min 8 |

**Resposta `200`:**

```json
{
  "token": "1|xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
}
```

**Credenciais inválidas → `401`:**

```json
{
  "message": "Invalid credentials"
}
```

**Exemplo:**

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d "{\"email\":\"romulo@example.com\",\"password\":\"password123\"}"
```

---

#### `POST /auth/logout` 🔒

Revoga o token atual.

**Headers:** `Authorization: Bearer {token}`

**Resposta `200`:**

```json
{
  "message": "Logged out successfully"
}
```

```bash
curl -X POST http://localhost:8000/api/auth/logout \
  -H "Accept: application/json" \
  -H "Authorization: Bearer SEU_TOKEN"
```

---

### Categories

#### `GET /categories` 🔒

Lista todas as categorias (ordenadas por nome).

**Resposta `200`:**

```json
[
  { "id": 1, "name": "Geral", "created_at": "...", "updated_at": "..." },
  { "id": 2, "name": "Tecnologia", "created_at": "...", "updated_at": "..." }
]
```

---

### Posts

#### `GET /posts` 🔒

Lista **apenas os posts do usuário autenticado**, com comentários, do mais recente para o mais antigo.

**Resposta `200`:** array de posts (cada um com relação `comments`).

---

#### `POST /posts` 🔒

Cria um post para o usuário autenticado. O `slug` é gerado automaticamente a partir do `title`.

**Body (JSON):**

| Campo | Tipo | Regras |
|-------|------|--------|
| `category_id` | integer | obrigatório, deve existir em `categories` |
| `title` | string | obrigatório, max 255 |
| `content` | string | obrigatório |

**Resposta `201`:** objeto do post criado.

```bash
curl -X POST http://localhost:8000/api/posts \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d "{\"category_id\":1,\"title\":\"Meu primeiro post\",\"content\":\"Conteúdo do post.\"}"
```

---

#### `GET /posts/{id}` 🔒

Exibe um post com `category` e `comments`.

- Dono ou admin → **`200`**
- Outro usuário → **`403`**

---

#### `PATCH /posts/{id}` 🔒

Atualiza parcialmente um post (campos opcionais). Se `title` for enviado, o `slug` é regenerado.

**Body (JSON) — todos opcionais (`sometimes`):**

| Campo | Tipo | Regras |
|-------|------|--------|
| `category_id` | integer | deve existir em `categories` |
| `title` | string | max 255 |
| `content` | string | — |

- Dono ou admin → **`200`**
- Outro usuário → **`403`**

```bash
curl -X PATCH http://localhost:8000/api/posts/1 \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d "{\"title\":\"Título atualizado\"}"
```

---

#### `DELETE /posts/{id}` 🔒

Remove um post.

**Resposta `200`:**

```json
{
  "message": "Post excluído com sucesso."
}
```

- Dono ou admin → **`200`**
- Outro usuário → **`403`**

---

### Comments

#### `POST /posts/{post}/comments` 🔒

Cria um comentário no post. Qualquer usuário autenticado pode comentar.

**Body (JSON):**

| Campo | Tipo | Regras |
|-------|------|--------|
| `content` | string | obrigatório, max 1000 |

**Resposta `201`:** objeto do comentário.

```bash
curl -X POST http://localhost:8000/api/posts/1/comments \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN" \
  -d "{\"content\":\"Ótimo post!\"}"
```

---

#### `DELETE /comments/{id}` 🔒

Exclui um comentário **somente se for o autor**.

**Resposta `200`:**

```json
{
  "message": "Comentário excluído com sucesso."
}
```

Se não for o dono → **`404`**:

```json
{
  "message": "Comentário não encontrado."
}
```

---

## Resumo rápido dos endpoints

| Método | Endpoint | Auth | Descrição |
|--------|----------|------|-----------|
| `POST` | `/api/auth/register` | Não | Registrar usuário |
| `POST` | `/api/auth/login` | Não | Login → token |
| `POST` | `/api/auth/logout` | Sim | Revogar token |
| `GET` | `/api/categories` | Sim | Listar categorias |
| `GET` | `/api/posts` | Sim | Listar meus posts |
| `POST` | `/api/posts` | Sim | Criar post |
| `GET` | `/api/posts/{id}` | Sim | Ver post (dono/admin) |
| `PATCH` | `/api/posts/{id}` | Sim | Atualizar post (dono/admin) |
| `DELETE` | `/api/posts/{id}` | Sim | Excluir post (dono/admin) |
| `POST` | `/api/posts/{id}/comments` | Sim | Comentar em um post |
| `DELETE` | `/api/comments/{id}` | Sim | Excluir meu comentário |

---

## Códigos HTTP usados

| Código | Significado |
|--------|-------------|
| `200` | Sucesso |
| `201` | Recurso criado |
| `401` | Não autenticado |
| `403` | Autenticado, sem permissão (Policy de posts) |
| `404` | Não encontrado (ou comentário de outro usuário) |
| `422` | Erro de validação |

---

## Seed (dados de exemplo)

```bash
php artisan db:seed
```

Cria:

- Categorias: **Geral**, **Tecnologia**, **Lifestyle**
- Usuários de teste:
  - `test@example.com` (senha padrão da factory: **`password`**)
  - `admin@example.com` (admin, senha: **`password`**)
- 8 usuários extras, 15 posts e comentários aleatórios

> Use o seed só em ambiente local/desenvolvimento.

---

## Testes

```bash
# Todos os testes
php artisan test

# Somente testes de feature do blog
php artisan test --filter="AuthTest|PostTest"
```

Os testes usam SQLite em memória (`phpunit.xml`) e o trait `RefreshDatabase`.

Cobertura principal:

- **AuthTest** — register, login, logout e validações  
- **PostTest** — CRUD de posts, Policy (403 / admin) e comentários  

---

## Estrutura relevante do projeto

```
app/
  Http/Controllers/     # Auth, Post, Comment, Category
  Http/Requests/        # Validação de posts e comments
  Models/               # User, Post, Comment, Category
  Policies/PostPolicy.php
database/
  factories/
  migrations/
  seeders/
routes/api.php          # Rotas da API
tests/Feature/          # AuthTest, PostTest
```

---

## Postman / Insomnia (dica)

1. `POST /api/auth/login` → copie o `token`  
2. Nas demais requests, header:

```http
Authorization: Bearer {token}
Accept: application/json
```

---

## Licença

Projeto de estudos / API de blog. Framework Laravel sob [licença MIT](https://opensource.org/licenses/MIT).
