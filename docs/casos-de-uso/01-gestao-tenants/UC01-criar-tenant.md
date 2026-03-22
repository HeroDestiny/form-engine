# UC01 - Criar Tenant

**Categoria:** Gestão de Tenants
**Ator Principal:** Administrador do Sistema

---

## Descrição

Permite ao administrador do sistema criar uma nova unidade administrativa (tenant) que servirá como contexto de isolamento para dados e usuários.

---

## Pré-condições

- Usuário autenticado como administrador do sistema
- Possui permissões de administrador global

---

## Pós-condições

- Tenant criado e ativo no sistema
- Ação registrada no audit log
- Tenant disponível para criação de usuários

---

## Fluxo Principal

1. Administrador acessa a funcionalidade de criação de tenant
2. Sistema exibe formulário de cadastro
3. Administrador informa:
   - Nome da unidade administrativa
   - Slug único para identificação
4. Sistema valida os dados:
   - Nome é obrigatório (mínimo 3 caracteres)
   - Slug é único no sistema
   - Slug contém apenas caracteres válidos (a-z, 0-9, hífen)
5. Sistema cria o tenant com:
   - `is_active = true`
   - `created_at = now()`
6. Sistema registra ação no log de auditoria:
   - Ação: `CREATE_TENANT`
   - Usuário: administrador autenticado
   - Entidade: tenant criado
7. Sistema exibe mensagem de sucesso com dados do tenant criado

---

## Fluxos Alternativos

### FA01 - Slug duplicado

**Quando:** Passo 4 - Sistema detecta slug já existente

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "O slug informado já está em uso"
3. Retorna ao passo 3 do fluxo principal

### FA02 - Dados inválidos

**Quando:** Passo 4 - Validação falha

1. Sistema identifica campos com erro:
   - Nome vazio ou muito curto
   - Slug com caracteres inválidos
   - Slug vazio
2. Sistema retorna lista de erros de validação
3. Retorna ao passo 3 do fluxo principal

---

## Regras de Negócio

- **RN01:** Slug deve ser único em todo o sistema
- **RN02:** Slug deve conter apenas letras minúsculas, números e hífens
- **RN03:** Slug será usado para identificação contextual (ex: subdomínio, filtros)
- **RN04:** Nome do tenant é obrigatório e deve ter pelo menos 3 caracteres
- **RN05:** Tenant criado já nasce ativo por padrão
- **RN06:** Slug não pode ser alterado após criação (futuro)

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| name | string | Sim | Min: 3, Max: 255 |
| slug | string | Sim | Único, formato: `^[a-z0-9-]+$` |

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Prefeitura Municipal de Exemplo",
    "slug": "prefeitura-exemplo",
    "is_active": true,
    "created_at": "2026-03-22T10:30:00Z",
    "updated_at": "2026-03-22T10:30:00Z"
  },
  "message": "Tenant criado com sucesso",
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
    "slug": ["O slug informado já está em uso"],
    "name": ["O nome é obrigatório"]
  }
}
```

---

## Endpoint da API

```
POST /api/admin/tenants
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Prefeitura Municipal de Exemplo",
  "slug": "prefeitura-exemplo"
}
```

---

## Modelo de Dados Afetado

**Tabela:** `tenants`

```sql
INSERT INTO tenants (name, slug, is_active, created_at, updated_at)
VALUES ('Prefeitura Municipal de Exemplo', 'prefeitura-exemplo', true, NOW(), NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, created_at)
VALUES (NULL, 1, 'CREATE_TENANT', 'tenant', 1, NOW());
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Criar tenant com dados válidos
✅ Verificar que slug é único
✅ Verificar que tenant nasce ativo
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar criar tenant com slug duplicado
❌ Tentar criar tenant sem nome
❌ Tentar criar tenant com slug contendo caracteres inválidos
❌ Tentar criar tenant com slug vazio

### Testes de Segurança
🔒 Tentar criar tenant sem autenticação
🔒 Tentar criar tenant com usuário não-administrador

---

## Exceções

- `ValidationException`: Dados de entrada inválidos
- `UnauthorizedException`: Usuário sem permissão de administrador
- `DuplicateSlugException`: Slug já existe no sistema

---

## Dependências

- Service: `TenantService`
- Repository: `TenantRepository`
- Model: `Tenant`
- Request: `CreateTenantRequest`
- Middleware: `AdminOnly`

---

## Casos de Uso Relacionados

- **UC02:** Ativar/Desativar Tenant
- **UC03:** Criar Usuário (depende de tenant existente)

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Alta (Fundação do sistema)
