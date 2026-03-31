# UC05 - Criar Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor criar uma nova ficha (formulário) dentro do tenant, estabelecendo a estrutura base que receberá campos dinâmicos.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Tenant está ativo

---

## Pós-condições

- Formulário criado com status ativo
- Versão inicial (draft) criada automaticamente
- Gestor pode adicionar campos à versão draft
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa funcionalidade de criação de formulários
2. Sistema exibe formulário de cadastro
3. Gestor informa:
   - Nome do formulário
   - Descrição (opcional)
4. Sistema valida os dados:
   - Nome é obrigatório (mínimo 3 caracteres)
   - Nome é único dentro do tenant
5. Sistema cria formulário com:
   - `tenant_id` do usuário autenticado
   - `created_by` = ID do usuário autenticado
   - `is_active = true`
   - `created_at = now()`
6. Sistema cria versão inicial automaticamente:
   - `form_id` = ID do formulário criado
   - `version_number = 1`
   - `is_published = false`
   - `created_by` = ID do usuário autenticado
7. Sistema registra ação no log de auditoria
8. Sistema redireciona para edição de campos (UC06)
9. Sistema exibe mensagem de sucesso

---

## Fluxos Alternativos

### FA01 - Nome duplicado no tenant

**Quando:** Passo 4 - Sistema detecta nome já existente

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "Já existe um formulário com este nome"
3. Retorna ao passo 3 do fluxo principal

### FA02 - Dados inválidos

**Quando:** Passo 4 - Validação falha

1. Sistema identifica campos com erro
2. Sistema retorna lista de erros de validação
3. Retorna ao passo 3 do fluxo principal

---

## Regras de Negócio

- **RN01:** Nome deve ser único dentro do tenant (pode repetir entre tenants)
- **RN02:** Formulário criado já nasce ativo por padrão
- **RN03:** Versão inicial é criada automaticamente como draft
- **RN04:** Versão inicial sempre tem `version_number = 1`
- **RN05:** Formulário sem campos não pode ser publicado
- **RN06:** Isolamento por tenant_id é obrigatório
- **RN07:** Descrição é opcional mas recomendada

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| name | string | Sim | Min: 3, Max: 255, único no tenant |
| description | text | Não | Max: 1000 caracteres |

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "form": {
      "id": 5,
      "tenant_id": 1,
      "name": "Cadastro de Beneficiários",
      "description": "Formulário para registro de novos beneficiários do programa",
      "is_active": true,
      "created_by": 10,
      "created_at": "2026-03-22T15:00:00Z",
      "updated_at": "2026-03-22T15:00:00Z"
    },
    "version": {
      "id": 8,
      "form_id": 5,
      "version_number": 1,
      "is_published": false,
      "published_at": null,
      "created_by": 10,
      "created_at": "2026-03-22T15:00:00Z"
    }
  },
  "message": "Formulário criado com sucesso. Adicione campos para publicar.",
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
    "name": ["Já existe um formulário com este nome"]
  }
}
```

---

## Endpoint da API

```
POST /api/tenants/{tenant_id}/forms
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Cadastro de Beneficiários",
  "description": "Formulário para registro de novos beneficiários do programa"
}
```

---

## Modelo de Dados Afetado

**Tabela:** `forms`

```sql
INSERT INTO forms (tenant_id, name, description, is_active, created_by, created_at, updated_at)
VALUES (1, 'Cadastro de Beneficiários', 'Formulário para...', true, 10, NOW(), NOW());
```

**Tabela:** `form_versions`

```sql
INSERT INTO form_versions (form_id, version_number, is_published, published_at, created_by, created_at)
VALUES (5, 1, false, NULL, 10, NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, created_at)
VALUES (1, 10, 'CREATE_FORM', 'form', 5, NOW());
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Criar formulário com dados válidos
✅ Verificar que versão draft é criada automaticamente
✅ Verificar que `version_number = 1`
✅ Verificar que `is_published = false`
✅ Verificar isolamento por tenant_id
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar criar formulário com nome duplicado no mesmo tenant
❌ Tentar criar formulário sem nome
❌ Tentar criar formulário com nome muito curto

### Testes de Isolamento
🔒 Verificar que nome pode ser duplicado entre tenants diferentes
🔒 Verificar que gestor só cria formulários no próprio tenant

### Testes de Segurança
🔒 Tentar criar formulário sem autenticação
🔒 Tentar criar formulário com papel `user`
🔒 Tentar criar formulário em outro tenant

---

## Exceções

- `ValidationException`: Dados de entrada inválidos
- `UnauthorizedException`: Usuário sem permissão
- `DuplicateFormNameException`: Nome já existe no tenant
- `TenantInactiveException`: Tenant está desativado

---

## Dependências

- Service: `FormService`, `FormVersionService`
- Repository: `FormRepository`, `FormVersionRepository`
- Model: `Form`, `FormVersion`
- Request: `CreateFormRequest`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC06:** Adicionar Campos ao Formulário (próximo passo)
- **UC09:** Publicar Versão de Formulário
- **UC11:** Ativar/Desativar Formulário

---

## Fluxo Completo de Criação

```
UC05: Criar Formulário
  ↓
UC06: Adicionar Campos (repetir N vezes)
  ↓
UC09: Publicar Versão
  ↓
UC13: Formulário disponível para preenchimento
```

---

## Notas de Implementação

### Transação Atômica

A criação do formulário e da versão inicial deve ser feita em transação:

```php
DB::transaction(function () use ($data) {
    $form = Form::create($data);

    FormVersion::create([
        'form_id' => $form->id,
        'version_number' => 1,
        'is_published' => false,
        'created_by' => auth()->id(),
    ]);

    return $form;
});
```

### Validação de Nome Único

```php
'name' => [
    'required',
    'string',
    'min:3',
    'max:255',
    Rule::unique('forms')->where('tenant_id', auth()->user()->tenant_id)
]
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Engine core)
