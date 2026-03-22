# UC04 - Ativar/Desativar Usuário

**Categoria:** Gestão de Usuários
**Ator Principal:** Administrador do Tenant

---

## Descrição

Permite ao administrador de um tenant ativar ou desativar usuários, controlando o acesso ao sistema sem excluir dados.

---

## Pré-condições

- Usuário autenticado como administrador do tenant (`role = admin`)
- Usuário a ser modificado pertence ao mesmo tenant
- Usuário a ser modificado existe no sistema

---

## Pós-condições

- Status do usuário atualizado (`is_active`)
- Ação registrada no audit log
- Acesso do usuário ao sistema afetado conforme novo status

---

## Fluxo Principal

1. Administrador acessa a lista de usuários do tenant
2. Sistema exibe todos os usuários com status atual
3. Administrador seleciona usuário específico
4. Administrador solicita alteração de status (ativar/desativar)
5. Sistema valida a operação:
   - Usuário não está tentando desativar a si mesmo
   - Usuário pertence ao mesmo tenant
6. Sistema exibe confirmação
7. Administrador confirma a operação
8. Sistema atualiza campo `is_active`:
   - `true` → ativar
   - `false` → desativar
9. Sistema atualiza `updated_at = now()`
10. Sistema registra ação no log de auditoria
11. Sistema exibe mensagem de confirmação

---

## Fluxos Alternativos

### FA01 - Tentativa de auto-desativação

**Quando:** Passo 5 - Administrador tenta desativar a si mesmo

1. Sistema detecta que user_id é o mesmo do autenticado
2. Sistema retorna erro de validação
3. Sistema exibe mensagem: "Você não pode desativar sua própria conta"
4. Caso de uso é encerrado

### FA02 - Usuário de outro tenant

**Quando:** Passo 5 - Usuário não pertence ao tenant

1. Sistema detecta tenant_id diferente
2. Sistema retorna erro 404 (não revela existência de usuário)
3. Sistema exibe mensagem: "Usuário não encontrado"
4. Caso de uso é encerrado

### FA03 - Usuário não encontrado

**Quando:** Passo 3 - ID inválido ou usuário excluído

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Usuário não encontrado"
3. Caso de uso é encerrado

---

## Regras de Negócio

- **RN01:** Usuário não pode desativar a si mesmo
- **RN02:** Usuário desativado não pode autenticar
- **RN03:** Submissões de usuário desativado são preservadas
- **RN04:** Formulários criados por usuário desativado permanecem ativos
- **RN05:** Usuário pode ser reativado a qualquer momento
- **RN06:** Isolamento por tenant é obrigatório
- **RN07:** Sessões ativas do usuário desativado expiram naturalmente

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| user_id | integer | Sim | Deve existir no tenant |
| is_active | boolean | Sim | true ou false |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 10,
    "tenant_id": 1,
    "name": "João Silva",
    "email": "joao@example.com",
    "role": "manager",
    "is_active": false,
    "created_at": "2026-03-22T11:00:00Z",
    "updated_at": "2026-03-22T14:30:00Z"
  },
  "message": "Usuário desativado com sucesso",
  "errors": null
}
```

### Resposta de Erro (403 Forbidden)

```json
{
  "success": false,
  "data": null,
  "message": "Você não pode desativar sua própria conta",
  "errors": null
}
```

### Resposta de Erro (404 Not Found)

```json
{
  "success": false,
  "data": null,
  "message": "Usuário não encontrado",
  "errors": null
}
```

---

## Endpoint da API

```
PATCH /api/tenants/{tenant_id}/users/{user_id}/status
Content-Type: application/json
Authorization: Bearer {token}

{
  "is_active": false
}
```

---

## Modelo de Dados Afetado

**Tabela:** `users`

```sql
UPDATE users
SET is_active = false, updated_at = NOW()
WHERE id = 10 AND tenant_id = 1;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 1, 'DEACTIVATE_USER', 'user', 10, '{"previous_status": true}', NOW());
```

---

## Impactos da Desativação

### Autenticação
- Usuário não consegue fazer login
- Sessões ativas são mantidas até expiração natural
- Tokens de autenticação continuam válidos até expirar

### Dados Existentes
- Formulários criados pelo usuário permanecem ativos
- Submissões do usuário são preservadas
- Histórico de auditoria é mantido

### Permissões
- Não pode executar novas ações
- Dados históricos permanecem vinculados ao usuário

---

## Testes Requeridos

### Teste de Sucesso
✅ Desativar usuário ativo
✅ Reativar usuário desativado
✅ Verificar atualização de `is_active`
✅ Verificar registro no audit log
✅ Verificar que dados do usuário não são excluídos

### Testes de Impacto
🔍 Verificar que usuário desativado não consegue autenticar
🔍 Verificar que submissões do usuário são preservadas
🔍 Verificar que formulários criados permanecem ativos

### Testes de Validação
❌ Tentar desativar a si mesmo
❌ Tentar desativar usuário de outro tenant
❌ Tentar desativar usuário inexistente

### Testes de Isolamento
🔒 Verificar que administrador só modifica usuários do próprio tenant
🔒 Verificar erro 404 para usuários de outros tenants

### Testes de Segurança
🔒 Tentar alterar status sem autenticação
🔒 Tentar alterar status com papel `manager` ou `user`

---

## Exceções

- `NotFoundException`: Usuário não encontrado ou de outro tenant
- `UnauthorizedException`: Usuário sem permissão de administrador
- `SelfDeactivationException`: Tentativa de desativar a si mesmo
- `ValidationException`: Dados de entrada inválidos

---

## Dependências

- Service: `UserService`
- Repository: `UserRepository`
- Model: `User`
- Request: `UpdateUserStatusRequest`
- Middleware: `AdminOnly`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC03:** Criar Usuário
- **UC20:** Autenticar no Sistema (afetado)
- **UC02:** Ativar/Desativar Tenant (similar)

---

## Notas de Implementação

### Validação de Auto-desativação

```php
if ($userId === auth()->id()) {
    throw new SelfDeactivationException('Você não pode desativar sua própria conta');
}
```

### Escopo de Tenant

```php
User::where('tenant_id', auth()->user()->tenant_id)
    ->findOrFail($userId);
```

### Revogação de Tokens (Futuro)

Na v1, tokens continuam válidos até expiração natural. Em versões futuras, considerar:
- Revogar tokens imediatamente ao desativar
- Implementar blacklist de tokens
- Forçar logout de sessões ativas

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Média
