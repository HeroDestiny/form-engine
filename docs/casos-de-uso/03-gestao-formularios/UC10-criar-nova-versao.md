# UC10 - Criar Nova Versão de Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor criar uma nova versão de um formulário já publicado, copiando a estrutura anterior e permitindo modificações sem afetar versões publicadas.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário possui versão publicada
- Não existe versão draft ativa

---

## Pós-condições

- Nova versão criada em draft
- Estrutura copiada da versão anterior
- Gestor pode modificar campos da nova versão
- Versão anterior permanece inalterada
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa formulário publicado
2. Sistema exibe última versão publicada (somente leitura)
3. Gestor solicita criação de nova versão
4. Sistema valida operação:
   - Última versão está publicada
   - Não existe versão draft ativa
5. Sistema exibe confirmação:
   - "Criar nova versão baseada na versão X?"
   - Informações sobre a cópia
6. Gestor confirma criação
7. Sistema busca última versão publicada
8. Sistema cria nova versão:
   - Incrementa `version_number`
   - Define `is_published = false`
   - Define `created_by` = usuário autenticado
9. Sistema copia todos os campos da versão anterior:
   - Mantém configurações (label, name, type, etc.)
   - Mantém ordem
   - Mantém options
10. Sistema registra ação no log de auditoria
11. Sistema redireciona para edição da nova versão
12. Sistema exibe mensagem de sucesso

---

## Fluxos Alternativos

### FA01 - Já existe versão draft

**Quando:** Passo 4 - Versão draft já existe

1. Sistema detecta versão draft ativa
2. Sistema exibe mensagem: "Já existe uma versão em edição"
3. Sistema oferece opção de continuar editando versão draft
4. Sistema oferece opção de excluir draft e criar nova
5. Caso de uso é encerrado

### FA02 - Formulário sem versão publicada

**Quando:** Passo 4 - Apenas versão draft existe

1. Sistema detecta ausência de versão publicada
2. Sistema exibe mensagem: "Publique a versão atual antes de criar nova versão"
3. Caso de uso é encerrado

### FA03 - Gestor cancela criação

**Quando:** Passo 6 - Gestor não confirma

1. Sistema cancela a operação
2. Permanece visualizando versão atual
3. Nenhuma alteração é realizada

---

## Regras de Negócio

- **RN01:** Nova versão inicia como cópia da anterior
- **RN02:** Versão anterior permanece inalterada e publicada
- **RN03:** Apenas uma versão draft por formulário
- **RN04:** Version_number é incrementado automaticamente
- **RN05:** Submissões continuam usando última versão publicada até nova publicação
- **RN06:** Campos são copiados mas podem ser editados livremente
- **RN07:** Nova versão não herda submissões da anterior

---

## Estratégias de Versionamento

### Incremental (Implementado)
- Copia estrutura anterior
- Permite modificações pontuais
- Mantém histórico completo

### Reset (Fora do Escopo v1)
- Criar versão do zero
- Não copia campos anteriores

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_id | integer | Sim | Formulário deve ter versão publicada |

---

## Dados de Saída

### Resposta de Sucesso (201 Created)

```json
{
  "success": true,
  "data": {
    "version": {
      "id": 12,
      "form_id": 5,
      "version_number": 2,
      "is_published": false,
      "published_at": null,
      "created_by": 10,
      "created_at": "2026-03-22T17:00:00Z"
    },
    "fields_copied": 5,
    "from_version": 1,
    "fields": [
      {
        "id": 35,
        "label": "Nome Completo",
        "name": "nome_completo",
        "type": "text",
        "is_required": true,
        "order": 1
      }
    ]
  },
  "message": "Nova versão criada com sucesso. 5 campos copiados da versão 1.",
  "errors": null
}
```

---

## Endpoint da API

```
POST /api/forms/{form_id}/versions
Authorization: Bearer {token}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_versions`

```sql
-- Nova versão
INSERT INTO form_versions (form_id, version_number, is_published, published_at, created_by, created_at)
VALUES (5, 2, false, NULL, 10, NOW());
```

**Tabela:** `form_fields`

```sql
-- Cópia dos campos (um INSERT por campo)
INSERT INTO form_fields (form_version_id, label, name, type, is_required, options, "order", created_at)
SELECT 12, label, name, type, is_required, options, "order", NOW()
FROM form_fields
WHERE form_version_id = 8;  -- versão anterior
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'CREATE_VERSION', 'form_version', 12,
  '{"form_id": 5, "version_number": 2, "from_version": 1, "fields_copied": 5}', NOW());
```

---

## Cenários de Uso

### Cenário 1: Adicionar Campo
- Criar nova versão
- Adicionar novo campo
- Publicar versão 2
- Submissões antigas mantêm estrutura v1
- Novas submissões usam estrutura v2

### Cenário 2: Remover Campo
- Criar nova versão
- Remover campo desnecessário
- Publicar versão 2
- Dados antigos do campo são preservados

### Cenário 3: Modificar Validações
- Criar nova versão
- Tornar campo obrigatório que era opcional
- Ou vice-versa
- Publicar versão 2

---

## Testes Requeridos

### Teste de Sucesso
✅ Criar versão 2 a partir de versão 1 publicada
✅ Verificar cópia de todos os campos
✅ Verificar incremento do version_number
✅ Verificar que versão antiga não é afetada
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar criar versão quando já existe draft
❌ Tentar criar versão sem versão publicada prévia

### Testes de Isolamento
🔒 Verificar que gestor só cria versões de formulários do próprio tenant

### Testes de Integridade
🔍 Verificar que campos são copiados corretamente
🔍 Verificar que options JSON é copiado
🔍 Verificar que ordem é mantida

---

## Exceções

- `DraftVersionExistsException`: Já existe versão draft
- `NoPublishedVersionException`: Sem versão publicada para copiar
- `UnauthorizedException`: Sem permissão

---

## Dependências

- Service: `FormVersionService`, `FormFieldService`
- Repository: `FormVersionRepository`, `FormFieldRepository`
- Model: `FormVersion`, `FormField`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Implementação na Interface

Na tela de detalhe do formulário, a interface deve tratar a criação de nova versão assim:

1. Quando houver uma versão publicada e nenhuma versão em edição, deve existir um botão "Criar nova versão" visível para `manager` e `admin`.
2. Ao clicar, o frontend deve chamar `POST /api/forms/{form_id}/versions`, que retorna a nova versão em draft e os campos copiados.
3. A tela de detalhe deve ser atualizada para indicar que existe uma "versão em edição" e oferecer o link para "Gerenciar campos da versão em edição".
4. Se a API indicar que já existe uma versão draft, a interface deve apenas redirecionar para a gestão dessa versão, seguindo o fluxo alternativo FA01.

---

## Casos de Uso Relacionados

- **UC09:** Publicar Versão (pré-requisito)
- **UC06:** Adicionar Campos (após criar versão)
- **UC07:** Editar Campos (após criar versão)
- **UC08:** Remover Campo (após criar versão)

---

## Confirmação de Criação (UI)

### Modal Sugerido

```
┌────────────────────────────────────────────────┐
│ Criar Nova Versão                              │
├────────────────────────────────────────────────┤
│                                                │
│ Formulário: Cadastro de Beneficiários         │
│ Versão atual: 1 (publicada)                   │
│                                                │
│ A nova versão (v2) será criada como cópia     │
│ da versão 1, com os seguintes campos:         │
│                                                │
│  • Nome Completo                               │
│  • Estado Civil                                │
│  • Data de Nascimento                          │
│  • Telefone                                    │
│  • Observações                                 │
│                                                │
│ Você poderá adicionar, editar ou remover      │
│ campos antes de publicar.                     │
│                                                │
│ ℹ️  A versão 1 permanecerá publicada e        │
│     submissões antigas não serão afetadas.    │
│                                                │
├────────────────────────────────────────────────┤
│              [Cancelar]  [Criar Versão]        │
└────────────────────────────────────────────────┘
```

---

## Notas de Implementação

### Transação Atômica

```php
DB::transaction(function () use ($formId) {
    // Busca última versão publicada
    $lastVersion = FormVersion::where('form_id', $formId)
        ->where('is_published', true)
        ->orderBy('version_number', 'desc')
        ->firstOrFail();

    // Cria nova versão
    $newVersion = FormVersion::create([
        'form_id' => $formId,
        'version_number' => $lastVersion->version_number + 1,
        'is_published' => false,
        'created_by' => auth()->id(),
    ]);

    // Copia campos
    foreach ($lastVersion->fields as $field) {
        FormField::create([
            'form_version_id' => $newVersion->id,
            'label' => $field->label,
            'name' => $field->name,
            'type' => $field->type,
            'is_required' => $field->is_required,
            'options' => $field->options,
            'order' => $field->order,
        ]);
    }

    return $newVersion;
});
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Versionamento core)
