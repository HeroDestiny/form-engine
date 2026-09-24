# UC21 - Verificar Permissões de Acesso

**Categoria:** Autenticação e Autorização
**Ator Principal:** Sistema

---

## Descrição

Processo automatizado que valida permissões de usuários para acessar recursos e executar ações no sistema.

---

## Pré-condições

- Usuário autenticado (possui token válido)
- Requisição a recurso protegido

---

## Pós-condições

- Acesso liberado ou negado
- Tentativa de acesso não autorizado registrada no log (se aplicável)

---

## Fluxo Principal

1. Usuário faz requisição HTTP a recurso protegido
2. Middleware captura requisição
3. Sistema valida token de autenticação:
   - Token está presente no header
   - Token é válido (não expirado)
   - Token pertence a usuário ativo
4. Sistema identifica usuário e tenant associados
5. Sistema verifica isolamento de tenant:
   - Recurso solicitado pertence ao tenant do usuário
   - Ou recurso é global/público
6. Sistema verifica papel (role) do usuário
7. Sistema valida permissão para ação solicitada:
   - Papel tem permissão para o tipo de operação
   - Contexto específico permite acesso
8. Sistema permite acesso
9. Requisição prossegue para controller

---

## Fluxos Alternativos

### FA01 - Token ausente ou inválido

**Quando:** Passo 3 - Token não fornecido ou inválido

1. Sistema detecta problema com token
2. Sistema retorna erro 401 Unauthorized
3. Sistema exibe mensagem: "Não autenticado"
4. Requisição é bloqueada

### FA02 - Violação de isolamento de tenant

**Quando:** Passo 5 - Recurso pertence a outro tenant

1. Sistema detecta tenant_id diferente
2. Sistema retorna erro 404 Not Found (não revela existência)
3. Sistema registra tentativa no audit log
4. Requisição é bloqueada

### FA03 - Sem permissão para ação

**Quando:** Passo 7 - Papel não tem permissão

1. Sistema detecta falta de permissão
2. Sistema retorna erro 403 Forbidden
3. Sistema exibe mensagem: "Você não tem permissão para esta ação"
4. Sistema registra tentativa no audit log
5. Requisição é bloqueada

---

## Regras de Negócio

### Isolamento Multi-Tenant

- **RN01:** Usuário nunca acessa dados de outro tenant
- **RN02:** Validação de tenant é obrigatória em todas operações
- **RN03:** Violação de isolamento retorna 404 (não 403) para não revelar existência

### Hierarquia de Papéis

**user (Usuário Final)**
- Listar formulários do tenant
- Preencher formulários
- Ver próprias submissões
- Salvar rascunhos

**manager (Gestor)**
- Todas permissões de `user`
- Criar formulários
- Adicionar/editar/remover campos
- Versionar formulários
- Publicar versões
- Consultar todas submissões do tenant
- Visualizar submissões de outros usuários
- Exportar dados

**admin (Administrador do Tenant)**
- Todas permissões de `manager`
- Gerenciar usuários do tenant
- Ativar/desativar usuários
- Ativar/desativar formulários
- Consultar log de auditoria completo
- Visualizar detalhes de auditoria

**admin-sistema (Administrador Global)**
- Todas permissões de `admin`
- Criar/gerenciar tenants
- Acesso cross-tenant para suporte

---

## Matriz de Permissões

| Ação | user | manager | admin | admin-sistema |
|------|------|---------|-------|---------------|
| **Formulários** |
| Listar formulários disponíveis | ✅ | ✅ | ✅ | ✅ |
| Criar formulário | ❌ | ✅ | ✅ | ✅ |
| Editar formulário | ❌ | ✅ | ✅ | ✅ |
| Ativar/desativar formulário | ❌ | ✅ | ✅ | ✅ |
| **Versões e Campos** |
| Adicionar campos | ❌ | ✅ | ✅ | ✅ |
| Editar campos | ❌ | ✅ | ✅ | ✅ |
| Remover campos | ❌ | ✅ | ✅ | ✅ |
| Publicar versão | ❌ | ✅ | ✅ | ✅ |
| Criar nova versão | ❌ | ✅ | ✅ | ✅ |
| **Submissões** |
| Preencher formulário | ✅ | ✅ | ✅ | ✅ |
| Salvar rascunho | ✅ | ✅ | ✅ | ✅ |
| Ver próprias submissões | ✅ | ✅ | ✅ | ✅ |
| Ver todas submissões | ❌ | ✅ | ✅ | ✅ |
| **Exportação** |
| Exportar dados | ❌ | ✅ | ✅ | ✅ |
| **Usuários** |
| Criar usuário | ❌ | ❌ | ✅ | ✅ |
| Editar usuário | ❌ | ❌ | ✅ | ✅ |
| Ativar/desativar usuário | ❌ | ❌ | ✅ | ✅ |
| **Auditoria** |
| Consultar log | ❌ | ❌ | ✅ | ✅ |
| Ver detalhes de ação | ❌ | ❌ | ✅ | ✅ |

---

## Dados de Entrada (Contexto)

```
Authorization: Bearer {token}
```

## Dados de Saída

### Acesso Permitido
- Requisição prossegue normalmente
- Status HTTP conforme controller (200, 201, etc.)

### Erro 401 - Não Autenticado

```json
{
  "success": false,
  "data": null,
  "message": "Não autenticado",
  "errors": null
}
```

### Erro 403 - Sem Permissão

```json
{
  "success": false,
  "data": null,
  "message": "Você não tem permissão para esta ação",
  "errors": null
}
```

### Erro 404 - Violação de Tenant

```json
{
  "success": false,
  "data": null,
  "message": "Recurso não encontrado",
  "errors": null
}
```

---

## Middlewares Implementados

### 1. `auth:sanctum` (Laravel Sanctum)
Valida o token de autenticação e garante que o usuário esteja autenticado.

### 2. `tenant.access` (EnsureTenantAccess)
Garante isolamento multi-tenant verificando se o usuário autenticado pertence ao `tenantId` informado na rota. Em caso de violação, retorna resposta 404 "Recurso não encontrado".

### 3. `role` (EnsureUserRole)
Recebe uma ou mais roles como parâmetro (por exemplo, `role:admin`, `role:manager,admin`, `role:admin-sistema`) e bloqueia o acesso com resposta 403 "Você não tem permissão para esta ação" quando o papel do usuário não está na lista permitida.

---

## Exemplos de Aplicação

### Exemplos de Aplicação

```php
// Rotas de autenticação
Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Rotas protegidas
Route::middleware('auth:sanctum')->group(function (): void {
    // Administração global (admin-sistema)
    Route::prefix('admin')->middleware('role:admin-sistema')->group(function (): void {
        Route::get('/tenants', [AdminTenantController::class, 'index']);
        Route::post('/tenants', [AdminTenantController::class, 'store']);
        Route::patch('/tenants/{tenantId}/status', [AdminTenantController::class, 'updateStatus']);
    });

    // Gestão de usuários por tenant (admin)
    Route::get('/tenants/{tenantId}/users', [TenantUserController::class, 'index'])
        ->middleware(['tenant.access', 'role:admin']);

    // Gestão de formulários (manager ou admin)
    Route::post('/forms', [FormController::class, 'storeForCurrentTenant'])
        ->middleware('role:manager,admin');

    // Auditoria (admin)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin');
});
```

---

## Testes Requeridos

### Teste de Autenticação
❌ Tentar acessar recurso sem token
❌ Tentar acessar com token inválido
❌ Tentar acessar com token expirado
✅ Acessar com token válido

### Teste de Isolamento
❌ Tentar acessar formulário de outro tenant (gestor)
❌ Tentar acessar submissão de outro tenant (gestor)
✅ Verificar que erro é 404 (não 403)

### Teste de Permissões
❌ User tentar criar formulário
❌ User tentar ver submissões de outros
❌ Manager tentar criar usuário
❌ Manager tentar acessar auditoria
✅ Admin acessar todos recursos do tenant
✅ Manager acessar recursos permitidos

### Teste de Ownership
❌ User tentar ver submissão de outro usuário
✅ User ver próprias submissões
✅ Manager ver submissões de todos

---

## Exceções

- `AuthenticationException`: Token ausente ou inválido
- `UnauthorizedException`: Sem permissão para ação
- `TenantMismatchException`: Violação de isolamento
- `ForbiddenException`: Acesso negado

---

## Dependências

- Middleware: `Authenticate`, `TenantScope`, `AdminOnly`, `ManagerOrAdmin`
- Package: `laravel/sanctum`
- Model: `User`, `Tenant`

---

## Casos de Uso Relacionados

- **UC20:** Autenticar no Sistema (pré-requisito)
- Todos os casos de uso do sistema (aplicado)

---

## Notas de Implementação

### Middleware TenantScope

```php
namespace App\Http\Middleware;

class TenantScope
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Aplica escopo global
        FormSubmission::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });

        Form::addGlobalScope('tenant', function ($query) use ($user) {
            $query->where('tenant_id', $user->tenant_id);
        });

        return $next($request);
    }
}
```

### Middleware AdminOnly

```php
namespace App\Http\Middleware;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para esta ação'
            ], 403);
        }

        return $next($request);
    }
}
```

### Middleware ManagerOrAdmin

```php
namespace App\Http\Middleware;

class ManagerOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $role = $request->user()->role;

        if (!in_array($role, ['manager', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Você não tem permissão para esta ação'
            ], 403);
        }

        return $next($request);
    }
}
```

### Verificação de Ownership (Submissão)

```php
public function show(Request $request, $id)
{
    $submission = FormSubmission::findOrFail($id);

    // Admin e Manager veem qualquer submissão do tenant
    if (in_array($request->user()->role, ['admin', 'manager'])) {
        return response()->json($submission);
    }

    // User vê apenas próprias submissões
    if ($submission->submitted_by !== $request->user()->id) {
        abort(404);
    }

    return response()->json($submission);
}
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Segurança fundamental)
