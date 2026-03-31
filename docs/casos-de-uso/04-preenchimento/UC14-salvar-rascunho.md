# UC14 - Salvar Rascunho de Formulário

**Categoria:** Preenchimento de Formulários
**Ator Principal:** Usuário Final

---

## Descrição

Permite ao usuário salvar parcialmente o preenchimento de um formulário como rascunho, para continuar depois sem perder dados.

---

## Pré-condições

- Usuário autenticado
- Formulário está ativo
- Usuário iniciou preenchimento

---

## Pós-condições

- Rascunho salvo com status draft
- Valores parciais armazenados
- Usuário pode continuar preenchimento depois
- Ação registrada no audit log

---

## Fluxo Principal

1. Usuário acessa formulário e inicia preenchimento
2. Usuário preenche parcialmente os campos
3. Usuário solicita salvar rascunho
4. Sistema valida dados preenchidos:
   - Não aplica validação de campos obrigatórios
   - Valida apenas tipos de dados
5. Sistema cria submission com status draft:
   - `status = 'draft'`
   - `tenant_id` = tenant do usuário
   - `form_version_id` = versão atual
   - `submitted_by` = ID do usuário
   - `submitted_at = NULL`
6. Sistema armazena valores preenchidos (mesmo campos parciais)
7. Sistema registra ação no log de auditoria
8. Sistema exibe confirmação: "Rascunho salvo com sucesso"
9. Usuário pode:
   - Continuar preenchendo
   - Voltar à lista de formulários
   - Acessar rascunho depois

---

## Fluxos Alternativos

### FA01 - Já existe rascunho para este formulário

**Quando:** Passo 5 - Usuário já tem rascunho salvo

**Opção A: Sobrescrever**
1. Sistema detecta rascunho existente
2. Sistema pergunta: "Já existe um rascunho. Deseja sobrescrever?"
3. Usuário confirma
4. Sistema atualiza rascunho existente
5. Continua para passo 7

**Opção B: Criar novo (v1)**
1. Sistema permite múltiplos rascunhos
2. Cria novo rascunho
3. Continua para passo 7

### FA02 - Validação de tipo falha

**Quando:** Passo 4 - Valor preenchido tem tipo inválido

1. Sistema detecta erro de tipo (ex: texto em campo numérico)
2. Sistema exibe mensagem de erro
3. Campo com erro é indicado
4. Retorna ao passo 3 do fluxo principal

---

## Regras de Negócio

- **RN01:** Rascunho não precisa validação completa
- **RN02:** Campos obrigatórios NÃO são validados no rascunho
- **RN03:** Apenas validação de tipo é aplicada
- **RN04:** Usuário pode ter múltiplos rascunhos do mesmo formulário (v1)
- **RN05:** Rascunho não aparece em consultas de submissões finalizadas
- **RN06:** Rascunho pode ser convertido em submissão final
- **RN07:** Rascunho expira após X dias (opcional, fora do escopo v1)

---

## Diferença: Rascunho vs Submissão Final

| Aspecto | Rascunho | Submissão Final |
|---------|----------|-----------------|
| Status | draft | submitted |
| Campos obrigatórios | Não validados | Validados |
| submitted_at | NULL | Timestamp |
| Visível em consultas | Não | Sim |
| Pode editar | Sim | Não (v1) |
| Aparece em relatórios | Não | Sim |

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_id | integer | Sim | Formulário deve existir e estar ativo |
| values | object | Sim | Valores parciais |

---

## Exemplo de Payload

```json
{
  "form_id": 5,
  "values": {
    "nome_completo": "Maria Santos",
    "telefone": "(11) 98765-4321"
  }
}
```
*Nota: Campos obrigatórios como `estado_civil` podem estar ausentes.*

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "draft": {
      "id": 453,
      "tenant_id": 1,
      "form_version_id": 8,
      "status": "draft",
      "submitted_by": 25,
      "submitted_at": null,
      "created_at": "2026-03-22T20:00:00Z",
      "updated_at": "2026-03-22T20:00:00Z"
    },
    "form": {
      "id": 5,
      "name": "Cadastro de Beneficiários",
      "version": 1
    },
    "fields_filled": 2,
    "fields_total": 5
  },
  "message": "Rascunho salvo com sucesso. Você pode continuar depois.",
  "errors": null
}
```

---

## Endpoint da API

```
POST /api/forms/{form_id}/drafts
Content-Type: application/json
Authorization: Bearer {token}

{
  "values": {
    "nome_completo": "Maria Santos",
    "telefone": "(11) 98765-4321"
  }
}
```

### Atualizar Rascunho Existente

```
PUT /api/forms/{form_id}/drafts/{draft_id}
Content-Type: application/json
Authorization: Bearer {token}

{
  "values": {
    "nome_completo": "Maria Santos",
    "estado_civil": "solteiro",
    "telefone": "(11) 98765-4321"
  }
}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_submissions`

```sql
INSERT INTO form_submissions (tenant_id, form_version_id, status, submitted_by, submitted_at, created_at, updated_at)
VALUES (1, 8, 'draft', 25, NULL, NOW(), NOW());
```

**Tabela:** `form_submission_values`

```sql
-- Apenas campos preenchidos
INSERT INTO form_submission_values (form_submission_id, form_field_id, value, created_at)
VALUES
  (453, 25, 'Maria Santos', NOW()),
  (453, 28, '(11) 98765-4321', NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 25, 'SAVE_DRAFT', 'form_submission', 453,
  '{"form_id": 5, "form_version_id": 8, "fields_filled": 2}', NOW());
```

---

## Recuperar Rascunho

### Listar Rascunhos do Usuário

```
GET /api/forms/{form_id}/drafts
Authorization: Bearer {token}
```

### Carregar Rascunho Específico

```
GET /api/forms/{form_id}/drafts/{draft_id}
Authorization: Bearer {token}
```

**Resposta:**

```json
{
  "success": true,
  "data": {
    "draft": {
      "id": 453,
      "form_version_id": 8,
      "status": "draft",
      "created_at": "2026-03-22T20:00:00Z"
    },
    "values": {
      "nome_completo": "Maria Santos",
      "telefone": "(11) 98765-4321"
    },
    "form": {
      "id": 5,
      "name": "Cadastro de Beneficiários"
    }
  },
  "message": null,
  "errors": null
}
```

---

## Finalizar Rascunho (Converter em Submissão)

```
POST /api/forms/{form_id}/drafts/{draft_id}/submit
Content-Type: application/json
Authorization: Bearer {token}

{
  "values": {
    "nome_completo": "Maria Santos",
    "estado_civil": "solteiro",
    "data_nascimento": "1990-05-15",
    "telefone": "(11) 98765-4321",
    "observacoes": "Completo"
  }
}
```

Sistema então:
1. Valida todos campos (incluindo obrigatórios)
2. Atualiza status para `submitted`
3. Define `submitted_at = now()`
4. Atualiza/adiciona valores

---

## Testes Requeridos

### Teste de Sucesso
✅ Salvar rascunho com campos parciais
✅ Recuperar rascunho salvo
✅ Atualizar rascunho existente
✅ Converter rascunho em submissão final
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar salvar rascunho com tipos inválidos
✅ Verificar que campos obrigatórios não são validados

### Testes de Isolamento
🔒 Verificar que usuário só vê próprios rascunhos
🔒 Verificar isolamento por tenant

### Testes Funcionais
🔍 Verificar múltiplos rascunhos do mesmo formulário
🔍 Verificar que rascunhos não aparecem em consultas finais

---

## Exceções

- `ValidationException`: Tipo de dado inválido
- `FormInactiveException`: Formulário desativado
- `DraftNotFoundException`: Rascunho não encontrado
- `UnauthorizedException`: Sem autenticação

---

## Dependências

- Service: `FormSubmissionService`
- Repository: `FormSubmissionRepository`, `FormSubmissionValueRepository`
- Model: `FormSubmission`, `FormSubmissionValue`
- Request: `SaveDraftRequest`
- Middleware: `Authenticate`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC13:** Preencher Formulário (finalizar rascunho)
- **UC12:** Listar Formulários
- **UC15:** Consultar Submissões (rascunhos separados)

---

## Interface Visual Sugerida

### Indicador de Rascunho

```
┌────────────────────────────────────────┐
│ 📋 Cadastro de Beneficiários          │
│                                        │
│ ⚠️  Você tem 1 rascunho salvo          │
│     (salvo em 22/03/2026 às 20:00)    │
│                                        │
│ [Continuar Rascunho]  [Novo]          │
└────────────────────────────────────────┘
```

---

## Notas de Implementação

### Adicionar Coluna Status

Se não existe coluna `status` em `form_submissions`, adicionar:

```sql
ALTER TABLE form_submissions
ADD COLUMN status VARCHAR(20) DEFAULT 'submitted';

-- Valores: 'draft', 'submitted'
```

### Query para Rascunhos do Usuário

```php
FormSubmission::where('form_id', $formId)
    ->where('submitted_by', auth()->id())
    ->where('status', 'draft')
    ->with('values.field')
    ->get();
```

### Excluir Rascunhos Antigos (Tarefa Agendada - Futuro)

```php
// Limpar rascunhos com mais de 30 dias
FormSubmission::where('status', 'draft')
    ->where('created_at', '<', now()->subDays(30))
    ->delete();
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Média (Melhoria de UX)
