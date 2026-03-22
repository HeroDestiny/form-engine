# UC20 - Autenticar no Sistema

**Categoria:** Autenticação e Autorização
**Ator Principal:** Qualquer Usuário

---

## Descrição

Permite autenticação de usuários no sistema, validando credenciais e gerando token de acesso.

---

## Pré-condições

- Usuário está cadastrado no sistema
- Usuário está ativo (`is_active = true`)
- Tenant do usuário está ativo

---

## Pós-condições

- Usuário autenticado
- Token de acesso gerado e válido
- Sessão iniciada
- Ação registrada no audit log

---

## Fluxo Principal

1. Usuário acessa tela de login
2. Sistema exibe formulário de autenticação
3. Usuário informa:
   - E-mail
   - Senha
4. Sistema valida formato dos dados:
   - E-mail é válido
   - Senha não está vazia
5. Sistema busca usuário por e-mail
6. Sistema valida credenciais:
   - E-mail existe
   - Senha corresponde ao hash armazenado
7. Sistema verifica status do usuário:
   - Usuário está ativo
   - Tenant do usuário está ativo
8. Sistema gera token de autenticação (Laravel Sanctum)
9. Sistema registra ação no log de auditoria:
   - Ação: `LOGIN_SUCCESS`
   - IP e user agent
10. Sistema retorna token e dados do usuário
11. Sistema exibe mensagem de sucesso

---

## Fluxos Alternativos

### FA01 - Credenciais inválidas

**Quando:** Passo 6 - E-mail ou senha incorretos

1. Sistema detecta credenciais inválidas
2. Sistema NÃO revela se e-mail existe (segurança)
3. Sistema exibe mensagem genérica: "E-mail ou senha incorretos"
4. Sistema registra tentativa no log:
   - Ação: `LOGIN_FAILED`
   - Metadata: email tentado, motivo
5. Sistema incrementa contador de tentativas (opcional, futuro)
6. Retorna ao passo 3 do fluxo principal

### FA02 - Usuário desativado

**Quando:** Passo 7 - `user.is_active = false`

1. Sistema detecta usuário desativado
2. Sistema exibe mensagem: "Sua conta está desativada. Entre em contato com o administrador."
3. Sistema registra tentativa no log
4. Caso de uso é encerrado

### FA03 - Tenant desativado

**Quando:** Passo 7 - `tenant.is_active = false`

1. Sistema detecta tenant desativado
2. Sistema exibe mensagem: "Acesso temporariamente indisponível. Entre em contato com o suporte."
3. Sistema registra tentativa no log
4. Caso de uso é encerrado

### FA04 - Múltiplas tentativas falhas (Futuro)

**Quando:** Passo 6 - 5+ tentativas em curto período

1. Sistema detecta múltiplas tentativas
2. Sistema bloqueia temporariamente (rate limiting)
3. Sistema exibe mensagem: "Muitas tentativas. Tente novamente em X minutos."
4. Sistema registra bloqueio no log
5. Caso de uso é encerrado

---

## Regras de Negócio

- **RN01:** Mensagens de erro não revelam se e-mail existe
- **RN02:** Senhas são verificadas via hash (bcrypt)
- **RN03:** Token tem tempo de expiração configurável
- **RN04:** Tentativas de login são auditadas (sucesso e falha)
- **RN05:** Rate limiting para prevenir brute force (futuro)
- **RN06:** IP e user agent são registrados
- **RN07:** Token é retornado apenas uma vez

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| email | string | Sim | Formato de e-mail válido |
| password | string | Sim | Min: 1 caractere |
| remember | boolean | Não | Estender validade do token (futuro) |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "user": {
      "id": 10,
      "tenant_id": 1,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "manager",
      "is_active": true
    },
    "tenant": {
      "id": 1,
      "name": "Prefeitura Municipal",
      "slug": "prefeitura"
    },
    "token": "5|KGpNxYZ8rJ3vQ2wB1mC6tF9sL4hD7eR0aI5nU8oP",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "message": "Login realizado com sucesso",
  "errors": null
}
```

### Resposta de Erro (401 Unauthorized)

```json
{
  "success": false,
  "data": null,
  "message": "E-mail ou senha incorretos",
  "errors": null
}
```

### Resposta de Erro - Usuário Desativado (403 Forbidden)

```json
{
  "success": false,
  "data": null,
  "message": "Sua conta está desativada. Entre em contato com o administrador.",
  "errors": null
}
```

---

## Endpoint da API

```
POST /api/auth/login
Content-Type: application/json

{
  "email": "joao@example.com",
  "password": "senha123"
}
```

---

## Modelo de Dados Afetado

**Tabela:** `personal_access_tokens` (Laravel Sanctum)

```sql
INSERT INTO personal_access_tokens (tokenable_type, tokenable_id, name, token, abilities, expires_at, created_at, updated_at)
VALUES ('App\\Models\\User', 10, 'auth-token', '...', '["*"]', NULL, NOW(), NOW());
```

**Tabela:** `audit_logs`

```sql
-- Login bem-sucedido
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, ip_address, user_agent, created_at)
VALUES (1, 10, 'LOGIN_SUCCESS', 'user', 10, '{}', '192.168.1.100', 'Mozilla/5.0...', NOW());

-- Login falho
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, ip_address, user_agent, created_at)
VALUES (NULL, NULL, 'LOGIN_FAILED', 'user', NULL,
  '{"email_attempted": "joao@example.com", "reason": "invalid_credentials"}',
  '192.168.1.100', 'Mozilla/5.0...', NOW());
```

---

## Fluxo de Autenticação

```
1. Cliente: POST /api/auth/login
           { email, password }

2. Servidor: Valida credenciais
            Verifica status (user e tenant)
            Gera token (Sanctum)

3. Servidor: Retorna token
            { user, tenant, token }

4. Cliente: Armazena token
           localStorage.setItem('token', ...)

5. Cliente: Requisições futuras
           Authorization: Bearer {token}
```

---

## Interface Visual Sugerida

### Tela de Login

```
┌────────────────────────────────────┐
│                                    │
│     Form Engine                    │
│     Sistema de Formulários         │
│                                    │
├────────────────────────────────────┤
│                                    │
│  E-mail                            │
│  [____________________________]    │
│                                    │
│  Senha                             │
│  [____________________________]    │
│                                    │
│  [ ] Lembrar de mim                │
│                                    │
│  [      Entrar       ]             │
│                                    │
│  Esqueceu a senha?                 │
│                                    │
└────────────────────────────────────┘
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Login com credenciais válidas
✅ Verificar geração de token
✅ Verificar dados de usuário e tenant retornados
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar login com e-mail inválido
❌ Tentar login com senha incorreta
❌ Tentar login com e-mail inexistente
❌ Tentar login com campos vazios

### Testes de Status
❌ Tentar login com usuário desativado
❌ Tentar login com tenant desativado
✅ Verificar mensagens apropriadas para cada caso

### Testes de Segurança
🔒 Verificar que mensagem não revela existência de e-mail
🔒 Verificar que senha é verificada via hash
🔒 Verificar registro de IP e user agent
🔒 Verificar tentativas falhas são auditadas

---

## Exceções

- `ValidationException`: Dados de entrada inválidos
- `UnauthorizedException`: Credenciais inválidas
- `UserInactiveException`: Usuário desativado
- `TenantInactiveException`: Tenant desativado

---

## Dependências

- Service: `AuthService`
- Repository: `UserRepository`
- Model: `User`, `Tenant`
- Request: `LoginRequest`
- Package: `laravel/sanctum`
- Helper: `Hash`, `Request` (para IP/user agent)

---

## Casos de Uso Relacionados

- **UC21:** Verificar Permissões (após autenticação)
- **UC03:** Criar Usuário (pré-requisito)
- **UC04:** Ativar/Desativar Usuário (afeta login)

---

## Notas de Implementação

### Controller de Login

```php
public function login(LoginRequest $request)
{
    $credentials = $request->only('email', 'password');

    // Busca usuário
    $user = User::with('tenant')
        ->where('email', $credentials['email'])
        ->first();

    // Valida credenciais
    if (!$user || !Hash::check($credentials['password'], $user->password)) {
        // Audita falha
        AuditLog::create([
            'action' => 'LOGIN_FAILED',
            'entity_type' => 'user',
            'metadata' => [
                'email_attempted' => $credentials['email'],
                'reason' => 'invalid_credentials'
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => false,
            'message' => 'E-mail ou senha incorretos'
        ], 401);
    }

    // Verifica status
    if (!$user->is_active) {
        throw new UserInactiveException();
    }

    if (!$user->tenant->is_active) {
        throw new TenantInactiveException();
    }

    // Gera token
    $token = $user->createToken('auth-token')->plainTextToken;

    // Audita sucesso
    AuditLog::create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'action' => 'LOGIN_SUCCESS',
        'entity_type' => 'user',
        'entity_id' => $user->id,
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    return response()->json([
        'success' => true,
        'data' => [
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('sanctum.expiration') * 60
        ],
        'message' => 'Login realizado com sucesso'
    ]);
}
```

### Logout

```php
POST /api/auth/logout

public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();

    AuditLog::create([
        'tenant_id' => auth()->user()->tenant_id,
        'user_id' => auth()->id(),
        'action' => 'LOGOUT',
        'entity_type' => 'user',
        'entity_id' => auth()->id(),
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Logout realizado com sucesso'
    ]);
}
```

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Alta (Fundação do sistema)
