# UC03 - Criar Usuário

**Categoria:** Gestão de Usuários
**Ator Principal:** Administrador do Tenant

---

## Descrição

Permite ao administrador de um tenant criar novos usuários vinculados à sua unidade administrativa, definindo papéis e permissões.

---

## Pré-condições

- Usuário autenticado como administrador do tenant (`role = admin`)
- Tenant está ativo (`is_active = true`)

---

## Pós-condições

- Usuário criado e vinculado ao tenant
- Usuário pode autenticar no sistema
- Ação registrada no audit log

---

## Fluxo Principal

1. Administrador acessa a funcionalidade de gestão de usuários
2. Sistema exibe lista de usuários do tenant
3. Administrador solicita criação de novo usuário
4. Sistema exibe formulário de cadastro
5. Administrador informa:
   - Nome completo
   - E-mail
   - Senha
   - Papel (role): `admin`, `manager` ou `user`
6. Sistema valida os dados:
   - Nome é obrigatório (mínimo 3 caracteres)
   - E-mail é válido e único dentro do tenant
   - Senha atende critérios mínimos (mínimo 8 caracteres)
   - Papel é válido
7. Sistema cria usuário com:
   - `tenant_id` do administrador autenticado
   - Senha hasheada (bcrypt)
   - `is_active = true`
   - `created_at = now()`
8. Sistema registra ação no log de auditoria
9. Sistema exibe mensagem de sucesso com dados do usuário

---

## Fluxos Alternativos

### FA01 - E-mail duplicado no tenant

**Quando:** Passo 6 - Sistema detecta e-mail já cadastrado

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "O e-mail informado já está cadastrado neste tenant"
3. Retorna ao passo 5 do fluxo principal

### FA02 - Senha não atende critérios

**Quando:** Passo 6 - Senha muito fraca

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "A senha deve ter no mínimo 8 caracteres"
3. Retorna ao passo 5 do fluxo principal

### FA03 - Papel inválido

**Quando:** Passo 6 - Role fora dos valores permitidos

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "O papel informado é inválido"
3. Retorna ao passo 5 do fluxo principal

---

## Regras de Negócio

- **RN01:** E-mail deve ser único dentro do tenant (pode repetir entre tenants)
- **RN02:** Usuário pertence a apenas um tenant na v1
- **RN03:** Senha deve ser hasheada com bcrypt antes de salvar
- **RN04:** Papéis válidos: `admin`, `manager`, `user`
- **RN05:** Usuário criado já nasce ativo por padrão
- **RN06:** Isolamento de dados por tenant_id é obrigatório
- **RN07:** Administrador só pode criar usuários no próprio tenant

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| name | string | Sim | Min: 3, Max: 255 |
| email | string | Sim | E-mail válido, único no tenant |
| password | string | Sim | Min: 8 caracteres |
| role | enum | Sim | admin, manager, user |

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "id": 10,
    "tenant_id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "role": "manager",
    "is_active": true,
    "created_at": "2026-03-22T11:00:00Z",
    "updated_at": "2026-03-22T11:00:00Z"
  },
  "message": "Usuário criado com sucesso",
  "errors": null
}
```

### Resposta de Erro de Validação (422 Unprocessable Entity)

```json
{
  "success": false,
  "data": null,
  "message": "Erro de validação",
  "errors": {
    "email": ["O e-mail informado já está cadastrado neste tenant"],
    "password": ["A senha deve ter no mínimo 8 caracteres"]
  }
}
```

---

## Endpoint da API

```
POST /api/tenants/{tenant_id}/users
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "João Silva",
  "email": "joao@example.com",
  "password": "senha123",
  "role": "manager"
}
```

---

## Modelo de Dados Afetado

**Tabela:** `users`

```sql
INSERT INTO users (tenant_id, name, email, password, role, is_active, created_at, updated_at)
VALUES (1, 'João Silva', 'joao@example.com', '$2y$10$...', 'manager', true, NOW(), NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, created_at)
VALUES (1, 1, 'CREATE_USER', 'user', 10, NOW());
```

---

## Papéis e Permissões

### user (Usuário Final)
- Listar formulários do tenant
- Preencher formulários
- Ver próprias submissões

### manager (Gestor)
- Todas permissões de `user`
- Criar formulários
- Versionar formulários
- Publicar versões
- Consultar todas submissões do tenant
- Exportar dados

### admin (Administrador do Tenant)
- Todas permissões de `manager`
- Gerenciar usuários do tenant
- Ativar/desativar formulários
- Consultar log de auditoria completo

---

## Testes Requeridos

### Teste de Sucesso
✅ Criar usuário com papel `user`
✅ Criar usuário com papel `manager`
✅ Criar usuário com papel `admin`
✅ Verificar que senha é hasheada
✅ Verificar registro no audit log
✅ Verificar isolamento por tenant_id

### Testes de Validação
❌ Tentar criar usuário com e-mail duplicado no mesmo tenant
❌ Tentar criar usuário sem nome
❌ Tentar criar usuário com senha curta
❌ Tentar criar usuário com role inválido
❌ Tentar criar usuário com e-mail inválido

### Testes de Isolamento
🔒 Verificar que e-mail pode ser duplicado entre tenants diferentes
🔒 Verificar que administrador só vê usuários do próprio tenant
🔒 Verificar que tenant_id é sempre aplicado

### Testes de Segurança
🔒 Tentar criar usuário sem autenticação
🔒 Tentar criar usuário com papel `manager` ou `user`
🔒 Tentar criar usuário em outro tenant

---

## Exceções

- `ValidationException`: Dados de entrada inválidos
- `UnauthorizedException`: Usuário sem permissão de administrador
- `DuplicateEmailException`: E-mail já existe no tenant
- `TenantInactiveException`: Tenant está desativado

---

## Dependências

- Service: `UserService`
- Repository: `UserRepository`
- Model: `User`, `Tenant`
- Request: `CreateUserRequest`
- Middleware: `AdminOnly`, `TenantScope`
- Helper: `Hash` (para bcrypt)

---

## Casos de Uso Relacionados

- **UC04:** Ativar/Desativar Usuário
- **UC20:** Autenticar no Sistema
- **UC01:** Criar Tenant (pré-requisito)

---

## Notas de Implementação

### Validação de E-mail
```php
'email' => [
    'required',
    'email',
    Rule::unique('users')->where('tenant_id', auth()->user()->tenant_id)
]
```

### Hash de Senha
```php
'password' => Hash::make($request->password)
```

### Escopo de Tenant
```php
User::where('tenant_id', auth()->user()->tenant_id)->get();
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Fundação do sistema)
