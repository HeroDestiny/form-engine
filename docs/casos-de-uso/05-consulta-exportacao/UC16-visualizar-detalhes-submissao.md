# UC16 - Visualizar Detalhes de Submissão

**Categoria:** Consulta e Exportação
**Ator Principal:** Gestor (Manager) / Administrador / Usuário (próprias)

---

## Descrição

Permite visualizar todos os detalhes de uma submissão específica, incluindo valores preenchidos, metadados e informações de auditoria.

---

## Pré-condições

- Usuário autenticado
- Submission existe e pertence ao tenant (ou ao próprio usuário, se user)

---

## Pós-condições

- Detalhes completos da submissão exibidos
- Usuário pode exportar individualmente (opcional)

---

## Fluxo Principal

1. Usuário acessa consulta de submissões (UC15) ou próprias submissões
2. Usuário seleciona submission específica
3. Sistema valida acesso:
   - Gestor/Admin: qualquer submission do tenant
   - User: apenas próprias submissões
4. Sistema busca dados completos:
   - Informações da versão usada
   - Todos campos e valores preenchidos
   - Metadados (usuário, timestamps)
5. Sistema renderiza visualização formatada:
   - Nome do formulário e versão
   - Data/hora de submissão
   - Usuário que submeteu
   - Lista de campos com labels e valores
   - Formatação por tipo de campo
6. Sistema exibe opções:
   - Voltar à lista
   - Exportar (PDF/CSV individual - futuro)
   - Imprimir

---

## Fluxos Alternativos

### FA01 - Submission não encontrada ou acesso negado

**Quando:** Passo 3 - ID inválido ou de outro tenant/usuário

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Submissão não encontrada"
3. Caso de uso é encerrado

### FA02 - Submission é rascunho

**Quando:** Passo 4 - Status é draft

1. Sistema exibe indicador "RASCUNHO"
2. Sistema exibe data de criação/atualização
3. Se usuário é dono, oferece opção de continuar preenchendo (UC14)
4. Continua para passo 5

---

## Regras de Negócio

- **RN01:** Gestores/admins acessam qualquer submission do tenant
- **RN02:** Usuários finais acessam apenas próprias submissões
- **RN03:** Valores são exibidos formatados conforme tipo de campo
- **RN04:** Options de select/radio/checkbox mostram labels, não values
- **RN05:** Campos vazios (opcionais não preenchidos) são indicados
- **RN06:** Isolamento por tenant é obrigatório

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "submission": {
      "id": 452,
      "status": "submitted",
      "submitted_at": "2026-03-22T19:30:00Z",
      "created_at": "2026-03-22T19:30:00Z"
    },
    "form": {
      "id": 5,
      "name": "Cadastro de Beneficiário",
      "description": "Formulário para registro de novos beneficiários"
    },
    "version": {
      "id": 8,
      "version_number": 1,
      "published_at": "2026-03-22T16:00:00Z"
    },
    "user": {
      "id": 25,
      "name": "Maria Santos",
      "email": "maria@example.com"
    },
    "values": [
      {
        "field_id": 25,
        "label": "Nome Completo",
        "name": "nome_completo",
        "type": "text",
        "value": "Maria Santos",
        "is_required": true
      },
      {
        "field_id": 26,
        "label": "Estado Civil",
        "name": "estado_civil",
        "type": "select",
        "value": "solteiro",
        "value_label": "Solteiro(a)",
        "is_required": true
      },
      {
        "field_id": 27,
        "label": "Data de Nascimento",
        "name": "data_nascimento",
        "type": "date",
        "value": "1990-05-15",
        "value_formatted": "15/05/1990",
        "is_required": true
      },
      {
        "field_id": 28,
        "label": "Telefone",
        "name": "telefone",
        "type": "text",
        "value": "(11) 98765-4321",
        "is_required": false
      },
      {
        "field_id": 29,
        "label": "Observações",
        "name": "observacoes",
        "type": "textarea",
        "value": "",
        "is_required": false
      }
    ]
  },
  "message": null,
  "errors": null
}
```

---

## Endpoint da API

```
GET /api/submissions/{submission_id}
Authorization: Bearer {token}
```

---

## Modelo de Dados Consultado

```sql
SELECT
    fs.id,
    fs.status,
    fs.submitted_at,
    fs.created_at,
    f.id as form_id,
    f.name as form_name,
    f.description as form_description,
    fv.id as version_id,
    fv.version_number,
    fv.published_at,
    u.id as user_id,
    u.name as user_name,
    u.email as user_email
FROM form_submissions fs
INNER JOIN form_versions fv ON fs.form_version_id = fv.id
INNER JOIN forms f ON fv.form_id = f.id
INNER JOIN users u ON fs.submitted_by = u.id
WHERE fs.id = ?
  AND fs.tenant_id = ?;

-- Valores
SELECT
    fsv.form_submission_id,
    fsv.form_field_id,
    fsv.value,
    ff.label,
    ff.name,
    ff.type,
    ff.is_required,
    ff.options,
    ff."order"
FROM form_submission_values fsv
INNER JOIN form_fields ff ON fsv.form_field_id = ff.id
WHERE fsv.form_submission_id = ?
ORDER BY ff."order" ASC;
```

---

## Interface Visual Sugerida

### Tela de Detalhes

```
┌──────────────────────────────────────────────────────────────┐
│ Submissão #452                                               │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ Formulário: Cadastro de Beneficiários (v1)                  │
│ Submetido por: Maria Santos (maria@example.com)             │
│ Data: 22/03/2026 às 19:30                                   │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│ Dados Preenchidos                                            │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ Nome Completo *                                              │
│ Maria Santos                                                 │
│                                                              │
│ Estado Civil *                                               │
│ Solteiro(a)                                                  │
│                                                              │
│ Data de Nascimento *                                         │
│ 15/05/1990                                                   │
│                                                              │
│ Telefone                                                     │
│ (11) 98765-4321                                              │
│                                                              │
│ Observações                                                  │
│ (não preenchido)                                             │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│ [← Voltar]                    [🖨️ Imprimir]  [📥 Exportar]  │
└──────────────────────────────────────────────────────────────┘
```

---

## Formatação de Valores por Tipo

| Tipo | Armazenado | Exibido |
|------|------------|---------|
| text | "João Silva" | João Silva |
| number | "42" | 42 |
| email | "user@example.com" | user@example.com |
| date | "2026-03-22" | 22/03/2026 |
| select | "casado" | Casado(a) (label da option) |
| checkbox | ["opt1","opt2"] | Opção 1, Opção 2 (labels) |
| textarea | "texto..." | texto... (quebras de linha preservadas) |

---

## Testes Requeridos

### Teste de Sucesso
✅ Visualizar submission como gestor
✅ Visualizar própria submission como user
✅ Verificar formatação de valores
✅ Verificar exibição de labels de options

### Testes de Acesso
❌ Tentar visualizar submission de outro tenant (gestor)
❌ Tentar visualizar submission de outro usuário (user)
❌ Tentar visualizar submission inexistente

### Testes de Isolamento
🔒 Verificar que gestor só vê submissões do próprio tenant
🔒 Verificar que user só vê próprias submissões

### Testes Funcionais
🔍 Verificar exibição de campos vazios
🔍 Verificar ordem correta dos campos
🔍 Verificar indicador de rascunho

---

## Exceções

- `SubmissionNotFoundException`: Submission não encontrada
- `UnauthorizedException`: Sem permissão para visualizar
- `TenantMismatchException`: Submission de outro tenant

---

## Dependências

- Service: `FormSubmissionService`
- Repository: `FormSubmissionRepository`, `FormSubmissionValueRepository`
- Model: `FormSubmission`, `FormSubmissionValue`, `Form`, `FormVersion`, `User`, `FormField`
- Middleware: `Authenticate`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC15:** Consultar Submissões (passo anterior)
- **UC17:** Exportar Dados (próximo passo)
- **UC13:** Preencher Formulário (gerou a submission)

---

## Notas de Implementação

### Verificar Permissões

```php
$submission = FormSubmission::with([
    'formVersion.form',
    'user',
    'values.field'
])->findOrFail($submissionId);

// Verificar tenant
if ($submission->tenant_id !== auth()->user()->tenant_id) {
    abort(404);
}

// Se user, verificar ownership
if (auth()->user()->role === 'user' && $submission->submitted_by !== auth()->id()) {
    abort(404);
}
```

### Formatar Valores

```php
public function formatValue(FormSubmissionValue $value): string
{
    $field = $value->field;

    switch ($field->type) {
        case 'date':
            return Carbon::parse($value->value)->format('d/m/Y');

        case 'select':
        case 'radio':
            $option = collect($field->options)
                ->firstWhere('value', $value->value);
            return $option['label'] ?? $value->value;

        case 'checkbox':
            $selected = json_decode($value->value, true);
            $labels = collect($field->options)
                ->whereIn('value', $selected)
                ->pluck('label')
                ->toArray();
            return implode(', ', $labels);

        default:
            return $value->value ?? '(não preenchido)';
    }
}
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Consulta core)
