# UC06 - Adicionar Campos ao Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor adicionar campos dinâmicos a uma versão em draft de um formulário, definindo tipo, validações e ordem de exibição.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário existe e pertence ao tenant do usuário
- Existe uma versão em draft (`is_published = false`)

---

## Pós-condições

- Campo adicionado à versão draft
- Versão pronta para receber mais campos ou ser publicada
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa edição de formulário
2. Sistema exibe versão atual em draft com lista de campos existentes
3. Gestor solicita adicionar novo campo
4. Sistema exibe formulário de configuração de campo
5. Gestor informa:
   - Label (rótulo visível ao usuário)
   - Name (identificador único no formulário)
   - Tipo (text, number, date, select, checkbox, textarea, email, etc.)
   - Se é obrigatório
   - Opções (para tipos select, radio, checkbox)
   - Ordem de exibição
   - Configurações adicionais (placeholder, tamanho, etc.)
6. Sistema valida os dados:
   - Label é obrigatório
   - Name é único dentro da versão
   - Name está em snake_case
   - Tipo é válido
   - Opções são fornecidas para tipos que requerem
7. Sistema adiciona campo à versão draft:
   - `form_version_id` da versão atual
   - `order` determina posição
8. Sistema registra ação no log de auditoria
9. Sistema exibe lista atualizada de campos
10. Sistema permite adicionar mais campos ou publicar versão

---

## Fluxos Alternativos

### FA01 - Versão já publicada

**Quando:** Passo 1 - Não existe versão draft

1. Sistema detecta que última versão está publicada
2. Sistema exibe mensagem: "Crie uma nova versão para adicionar campos"
3. Sistema oferece opção de criar nova versão (UC10)
4. Caso de uso é encerrado

### FA02 - Name duplicado na versão

**Quando:** Passo 6 - Name já existe

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "Já existe um campo com este identificador nesta versão"
3. Retorna ao passo 5 do fluxo principal

### FA03 - Tipo requer opções mas não foram fornecidas

**Quando:** Passo 6 - Select/radio sem options

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "Este tipo de campo requer opções"
3. Retorna ao passo 5 do fluxo principal

---

## Regras de Negócio

- **RN01:** Campos só podem ser adicionados a versões draft
- **RN02:** Name deve ser único dentro da versão
- **RN03:** Name deve seguir padrão snake_case (ex: `data_nascimento`)
- **RN04:** Ordem determina sequência de exibição no formulário
- **RN05:** Tipos select, radio e checkbox requerem campo `options` (JSON)
- **RN06:** Options é array de objetos `{value, label}`
- **RN07:** Versões publicadas são imutáveis

---

## Tipos de Campo Suportados

| Tipo | Descrição | Requer Options | Exemplo |
|------|-----------|----------------|---------|
| text | Texto curto | Não | Nome, CPF |
| textarea | Texto longo | Não | Observações |
| number | Numérico | Não | Idade, quantidade |
| email | E-mail | Não | Contato |
| date | Data | Não | Data de nascimento |
| select | Seleção única | Sim | Estado civil |
| radio | Opções (radio) | Sim | Sexo |
| checkbox | Múltipla escolha | Sim | Áreas de interesse |

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_version_id | integer | Sim | Versão deve existir e estar em draft |
| label | string | Sim | Min: 2, Max: 255 |
| name | string | Sim | snake_case, único na versão |
| type | enum | Sim | Tipo válido |
| is_required | boolean | Sim | true ou false |
| options | JSON | Condicional | Requerido para select/radio/checkbox |
| order | integer | Sim | Posição no formulário |

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "id": 25,
    "form_version_id": 8,
    "label": "Nome Completo",
    "name": "nome_completo",
    "type": "text",
    "is_required": true,
    "options": null,
    "order": 1,
    "created_at": "2026-03-22T15:30:00Z"
  },
  "message": "Campo adicionado com sucesso",
  "errors": null
}
```

### Exemplo com Options (Select)

```json
{
  "success": true,
  "data": {
    "id": 26,
    "form_version_id": 8,
    "label": "Estado Civil",
    "name": "estado_civil",
    "type": "select",
    "is_required": true,
    "options": [
      {"value": "solteiro", "label": "Solteiro(a)"},
      {"value": "casado", "label": "Casado(a)"},
      {"value": "divorciado", "label": "Divorciado(a)"},
      {"value": "viuvo", "label": "Viúvo(a)"}
    ],
    "order": 2,
    "created_at": "2026-03-22T15:32:00Z"
  },
  "message": "Campo adicionado com sucesso",
  "errors": null
}
```

---

## Endpoint da API

```
POST /api/forms/{form_id}/versions/{version_id}/fields
Content-Type: application/json
Authorization: Bearer {token}

{
  "label": "Nome Completo",
  "name": "nome_completo",
  "type": "text",
  "is_required": true,
  "order": 1
}
```

### Exemplo com Options

```json
{
  "label": "Estado Civil",
  "name": "estado_civil",
  "type": "select",
  "is_required": true,
  "options": [
    {"value": "solteiro", "label": "Solteiro(a)"},
    {"value": "casado", "label": "Casado(a)"}
  ],
  "order": 2
}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_fields`

```sql
INSERT INTO form_fields (form_version_id, label, name, type, is_required, options, "order", created_at)
VALUES (8, 'Nome Completo', 'nome_completo', 'text', true, NULL, 1, NOW());
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'ADD_FIELD', 'form_field', 25, '{"form_version_id": 8, "field_name": "nome_completo"}', NOW());
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Adicionar campo tipo text
✅ Adicionar campo tipo select com options
✅ Adicionar múltiplos campos com diferentes orders
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar adicionar campo a versão publicada
❌ Tentar adicionar campo com name duplicado
❌ Tentar adicionar campo select sem options
❌ Tentar adicionar campo com name fora do padrão snake_case

### Testes de Isolamento
🔒 Verificar que gestor só adiciona campos em formulários do próprio tenant

### Testes de Segurança
🔒 Tentar adicionar campo sem autenticação
🔒 Tentar adicionar campo com papel `user`

---

## Exceções

- `ValidationException`: Dados inválidos
- `VersionPublishedException`: Versão já publicada
- `DuplicateFieldNameException`: Name já existe na versão
- `UnauthorizedException`: Sem permissão

---

## Dependências

- Service: `FormFieldService`
- Repository: `FormFieldRepository`, `FormVersionRepository`
- Model: `FormField`, `FormVersion`
- Request: `CreateFormFieldRequest`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC05:** Criar Formulário (pré-requisito)
- **UC07:** Editar Campos do Formulário
- **UC08:** Remover Campo do Formulário
- **UC09:** Publicar Versão (próximo passo)

---

## Notas de Implementação

### Validação de Name Snake_case

```php
'name' => [
    'required',
    'regex:/^[a-z0-9_]+$/',
    Rule::unique('form_fields')->where('form_version_id', $versionId)
]
```

### Validação Condicional de Options

```php
'options' => [
    'required_if:type,select,radio,checkbox',
    'array',
    'min:2'
],
'options.*.value' => 'required|string',
'options.*.label' => 'required|string'
```

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Alta (Engine core)
