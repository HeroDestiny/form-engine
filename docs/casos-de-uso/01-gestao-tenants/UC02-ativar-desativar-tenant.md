# UC02 - Ativar/Desativar Tenant

**Categoria:** Gestão de Tenants
**Ator Principal:** Administrador do Sistema

---

## Descrição

Permite ao administrador do sistema ativar ou desativar uma unidade administrativa (tenant), controlando o acesso de todos os usuários vinculados a ela.

---

## Pré-condições

- Usuário autenticado como administrador do sistema
- Tenant existe no sistema

---

## Pós-condições

- Status do tenant atualizado (`is_active`)
- Ação registrada no audit log
- Usuários do tenant afetados pelo novo status

---

## Fluxo Principal

1. Administrador acessa a lista de tenants
2. Sistema exibe todos os tenants com seus status atuais
3. Administrador seleciona tenant específico
4. Administrador solicita alteração de status (ativar/desativar)
5. Sistema valida a operação
6. Sistema exibe confirmação com aviso sobre impacto:
   - "Desativar este tenant bloqueará acesso de X usuários"
7. Administrador confirma a operação
8. Sistema atualiza campo `is_active`:
   - `true` → ativar
   - `false` → desativar
9. Sistema atualiza `updated_at = now()`
10. Sistema registra ação no log de auditoria
11. Sistema exibe mensagem de confirmação

---

## Fluxos Alternativos

### FA01 - Tenant não encontrado

**Quando:** Passo 3 - Tenant não existe ou foi excluído

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Tenant não encontrado"
3. Caso de uso é encerrado

### FA02 - Administrador cancela operação

**Quando:** Passo 7 - Administrador não confirma

1. Sistema cancela a operação
2. Nenhuma alteração é realizada
3. Sistema retorna à lista de tenants

---

## Regras de Negócio

- **RN01:** Desativar tenant não exclui dados
- **RN02:** Usuários de tenant desativado não conseguem autenticar
- **RN03:** Formulários de tenant desativado ficam inacessíveis
- **RN04:** Submissões de tenant desativado são preservadas
- **RN05:** Tenant pode ser reativado a qualquer momento
- **RN06:** Operação deve registrar auditoria com razão (futuro)
- **RN07:** Não há tenant "sistema" que não pode ser desativado (v1)

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| tenant_id | integer | Sim | Deve existir |
| is_active | boolean | Sim | true ou false |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Prefeitura Municipal de Exemplo",
    "slug": "prefeitura-exemplo",
    "is_active": false,
    "created_at": "2026-03-22T10:30:00Z",
    "updated_at": "2026-03-22T15:45:00Z"
  },
  "message": "Tenant desativado com sucesso",
  "errors": null
}
```

### Resposta de Erro (404 Not Found)

```json
{
  "success": false,
  "data": null,
  "message": "Tenant não encontrado",
  "errors": null
}
```

---

## Endpoint da API

```
PATCH /api/admin/tenants/{id}/status
Content-Type: application/json
Authorization: Bearer {token}

{
  "is_active": false
}
```

---

## Modelo de Dados Afetado

**Tabela:** `tenants`

```sql
UPDATE tenants
SET is_active = false, updated_at = NOW()
WHERE id = 1;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 1, 'DEACTIVATE_TENANT', 'tenant', 1, '{"previous_status": true}', NOW());
```

---

## Impactos da Desativação

### Autenticação
- Usuários do tenant não conseguem fazer login
- Sessões ativas são mantidas até expiração

### Acesso a Dados
- APIs retornam erro para operações do tenant
- Dados permanecem no banco (não são excluídos)

### Formulários
- Formulários ficam inacessíveis
- Não é possível criar novas submissões
- Submissões existentes são preservadas

### Auditoria
- Registros de auditoria são mantidos
- Novas ações não são permitidas

---

## Testes Requeridos

### Teste de Sucesso
✅ Desativar tenant ativo
✅ Reativar tenant desativado
✅ Verificar atualização de `is_active`
✅ Verificar registro no audit log
✅ Verificar que dados não são excluídos

### Testes de Impacto
🔍 Verificar que usuários de tenant desativado não conseguem autenticar
🔍 Verificar que APIs retornam erro para tenant desativado
🔍 Verificar que formulários ficam inacessíveis

### Testes de Validação
❌ Tentar desativar tenant inexistente
❌ Tentar desativar com ID inválido

### Testes de Segurança
🔒 Tentar alterar status sem autenticação
🔒 Tentar alterar status com usuário não-administrador

---

## Exceções

- `NotFoundException`: Tenant não encontrado
- `UnauthorizedException`: Usuário sem permissão de administrador
- `ValidationException`: Dados de entrada inválidos

---

## Dependências

- Service: `TenantService`
- Repository: `TenantRepository`
- Model: `Tenant`
- Request: `UpdateTenantStatusRequest`
- Middleware: `AdminOnly`

---

## Casos de Uso Relacionados

- **UC01:** Criar Tenant
- **UC04:** Ativar/Desativar Usuário
- **UC20:** Autenticar no Sistema (afetado)

---

## Notas de Implementação

### Fase 1 (v1)
- Implementação básica de ativação/desativação
- Sem soft delete de dados
- Sem motivo/razão obrigatória

### Fases Futuras
- Adicionar campo `reason` para justificar desativação
- Implementar soft delete com período de retenção
- Notificar usuários sobre desativação
- Agendar desativação automática

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Média
