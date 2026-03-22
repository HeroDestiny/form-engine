# UC13 - Preencher Formulário

**Categoria:** Preenchimento de Formulários
**Ator Principal:** Usuário Final

---

## Descrição

Permite ao usuário preencher e submeter um formulário, com validação de campos e persistência dos dados.

---

## Pré-condições

- Usuário autenticado
- Formulário está ativo (`is_active = true`)
- Formulário possui versão publicada

---

## Pós-condições

- Submission criada e persistida
- Valores vinculados ao submission
- Dados prontos para consulta e exportação
- Ação registrada no audit log

---

## Fluxo Principal

1. Usuário seleciona formulário da lista (UC12)
2. Sistema busca última versão publicada do formulário
3. Sistema renderiza campos da versão:
   - Na ordem definida (`order`)
   - Com tipos apropriados (text, select, date, etc.)
   - Com validações configuradas (obrigatório, tipo)
   - Com options para campos select/radio/checkbox
4. Usuário preenche campos
5. Usuário submete formulário
6. Sistema valida dados no backend:
   - Campos obrigatórios preenchidos
   - Tipos de dados corretos
   - Valores dentro de options (se aplicável)
   - Formatos válidos (email, data, etc.)
7. Sistema cria registro de submission:
   - `tenant_id` = tenant do usuário
   - `form_version_id` = versão usada
   - `submitted_by` = ID do usuário
   - `submitted_at` = now()
8. Sistema armazena valores:
   - Cria um registro `form_submission_values` para cada campo
   - Vincula ao submission
   - Armazena valor como texto
9. Sistema registra ação no log de auditoria
10. Sistema exibe mensagem de sucesso
11. Sistema oferece opções:
    - Voltar à lista de formulários
    - Preencher novo formulário
    - Ver próprias submissões

---

## Fluxos Alternativos

### FA01 - Validação falha

**Quando:** Passo 6 - Dados inválidos

1. Sistema detecta erros de validação
2. Sistema retorna lista de erros por campo:
   - Campo obrigatório não preenchido
   - Tipo inválido
   - Valor fora das opções
3. Sistema exibe mensagens de erro próximas aos campos
4. Valores já preenchidos são mantidos
5. Retorna ao passo 4 do fluxo principal

### FA02 - Formulário desativado

**Quando:** Passo 2 - Formulário foi desativado

1. Sistema detecta `is_active = false`
2. Sistema exibe mensagem: "Este formulário não está mais disponível"
3. Sistema redireciona para lista de formulários
4. Caso de uso é encerrado

### FA03 - Versão foi substituída durante preenchimento

**Quando:** Passo 5 - Nova versão publicada enquanto usuário preenchia

1. Sistema detecta mudança de versão
2. Sistema oferece opções ao usuário:
   - Usar versão atual (nova)
   - Continuar com versão que estava preenchendo
3. Usuário escolhe
4. Sistema procede conforme escolha

---

## Regras de Negócio

- **RN01:** Submission sempre vinculada à versão usada
- **RN02:** Valores são armazenados como texto
- **RN03:** Conversão tipada ocorre na camada Service/Repository
- **RN04:** Usuário pode submeter mesmo formulário múltiplas vezes
- **RN05:** Campos não preenchidos (opcionais) geram valor NULL ou vazio
- **RN06:** Validação de backend é obrigatória (não confiar apenas no frontend)

---

## Tipos de Campo e Validação

| Tipo | Validação Backend | Exemplo |
|------|-------------------|---------|
| text | string, max_length | "João Silva" |
| textarea | string, max_length | "Observações..." |
| number | numeric | "42" |
| email | email format | "user@example.com" |
| date | date format | "2026-03-22" |
| select | in:options | "casado" |
| radio | in:options | "masculino" |
| checkbox | array, in:options | ["opt1", "opt2"] |

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_id | integer | Sim | Formulário deve existir e estar ativo |
| values | object | Sim | Chave = field name, Valor = resposta |

---

## Exemplo de Payload

```json
{
  "form_id": 5,
  "values": {
    "nome_completo": "Maria Santos",
    "estado_civil": "solteiro",
    "data_nascimento": "1990-05-15",
    "telefone": "(11) 98765-4321",
    "observacoes": "Primeira solicitação"
  }
}
```

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "submission": {
      "id": 452,
      "tenant_id": 1,
      "form_version_id": 8,
      "submitted_by": 25,
      "submitted_at": "2026-03-22T19:30:00Z",
      "created_at": "2026-03-22T19:30:00Z"
    },
    "form": {
      "id": 5,
      "name": "Cadastro de Beneficiários",
      "version": 1
    }
  },
  "message": "Formulário enviado com sucesso!",
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
    "nome_completo": ["O campo nome completo é obrigatório"],
    "estado_civil": ["O valor selecionado é inválido"],
    "data_nascimento": ["O campo data de nascimento deve ser uma data válida"]
  }
}
```

---

## Endpoint da API

```
POST /api/forms/{form_id}/submit
Content-Type: application/json
Authorization: Bearer {token}

{
  "values": {
    "nome_completo": "Maria Santos",
    "estado_civil": "solteiro",
    "data_nascimento": "1990-05-15"
  }
}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_submissions`

```sql
INSERT INTO form_submissions (tenant_id, form_version_id, submitted_by, submitted_at, created_at)
VALUES (1, 8, 25, NOW(), NOW());
```

**Tabela:** `form_submission_values`

```sql
-- Um INSERT para cada campo preenchido
INSERT INTO form_submission_values (form_submission_id, form_field_id, value, created_at)
VALUES
  (452, 25, 'Maria Santos', NOW()),
  (452, 26, 'solteiro', NOW()),
  (452, 27, '1990-05-15', NOW()),
  (452, 28, '(11) 98765-4321', NOW()),
  (452, 29, 'Primeira solicitação', NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 25, 'SUBMIT_FORM', 'form_submission', 452,
  '{"form_id": 5, "form_version_id": 8, "fields_count": 5}', NOW());
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Submeter formulário com todos campos obrigatórios
✅ Submeter formulário com campos opcionais vazios
✅ Verificar criação do submission
✅ Verificar criação dos values
✅ Verificar registro no audit log
✅ Verificar isolamento por tenant

### Testes de Validação
❌ Tentar submeter sem campo obrigatório
❌ Tentar submeter com tipo inválido (texto em campo número)
❌ Tentar submeter com valor fora das options
❌ Tentar submeter email inválido
❌ Tentar submeter data inválida

### Testes de Isolamento
🔒 Verificar que submission vincula ao tenant correto
🔒 Verificar que versão correta é usada

### Testes Funcionais
🔍 Verificar múltiplas submissões do mesmo usuário
🔍 Verificar submissão de formulário com checkbox múltiplo
🔍 Verificar formulário desativado bloqueia submissão

---

## Exceções

- `ValidationException`: Dados inválidos
- `FormInactiveException`: Formulário desativado
- `FormVersionNotFoundException`: Versão não encontrada
- `UnauthorizedException`: Sem autenticação

---

## Dependências

- Service: `FormSubmissionService`
- Repository: `FormSubmissionRepository`, `FormSubmissionValueRepository`
- Model: `FormSubmission`, `FormSubmissionValue`, `Form`, `FormVersion`, `FormField`
- Request: `SubmitFormRequest`
- Middleware: `Authenticate`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC12:** Listar Formulários (passo anterior)
- **UC14:** Salvar Rascunho (alternativa)
- **UC15:** Consultar Submissões (visualizar depois)

---

## Validação Dinâmica

### Exemplo de Validação Laravel

```php
$formVersion = Form::findOrFail($formId)->latestPublishedVersion;

$rules = [];
foreach ($formVersion->fields as $field) {
    $fieldRules = [];

    if ($field->is_required) {
        $fieldRules[] = 'required';
    }

    switch ($field->type) {
        case 'email':
            $fieldRules[] = 'email';
            break;
        case 'number':
            $fieldRules[] = 'numeric';
            break;
        case 'date':
            $fieldRules[] = 'date';
            break;
        case 'select':
        case 'radio':
            $options = array_column($field->options, 'value');
            $fieldRules[] = Rule::in($options);
            break;
    }

    $rules["values.{$field->name}"] = $fieldRules;
}

$validated = $request->validate($rules);
```

---

## Notas de Implementação

### Transação Atômica

```php
DB::transaction(function () use ($formVersionId, $values, $userId, $tenantId) {
    $submission = FormSubmission::create([
        'tenant_id' => $tenantId,
        'form_version_id' => $formVersionId,
        'submitted_by' => $userId,
        'submitted_at' => now(),
    ]);

    foreach ($values as $fieldName => $value) {
        $field = FormField::where('form_version_id', $formVersionId)
            ->where('name', $fieldName)
            ->first();

        if ($field) {
            FormSubmissionValue::create([
                'form_submission_id' => $submission->id,
                'form_field_id' => $field->id,
                'value' => $value,
            ]);
        }
    }

    return $submission;
});
```

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Alta (Funcionalidade core)
