# UC09 - Publicar Versão de Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor publicar uma versão draft de formulário, tornando-a disponível para preenchimento por usuários finais e imutável para edição.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário possui versão em draft
- Versão possui pelo menos um campo configurado

---

## Pós-condições

- Versão publicada e disponível para uso
- Versão torna-se imutável
- Usuários finais podem preencher o formulário
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa edição de formulário
2. Sistema exibe versão draft atual com todos os campos
3. Gestor solicita publicação da versão
4. Sistema valida a estrutura:
   - Formulário possui pelo menos um campo
   - Todos os campos estão configurados corretamente
   - Campos com options têm opções válidas
5. Sistema exibe resumo da versão para confirmação:
   - Número da versão
   - Quantidade de campos
   - Lista de campos
6. Gestor confirma publicação
7. Sistema atualiza versão:
   - `is_published = true`
   - `published_at = now()`
8. Sistema torna versão imutável
9. Sistema registra ação no log de auditoria
10. Sistema exibe mensagem de sucesso
11. Sistema exibe opção de criar nova versão (UC10)

---

## Fluxos Alternativos

### FA01 - Formulário sem campos

**Quando:** Passo 4 - Versão não possui campos

1. Sistema detecta ausência de campos
2. Sistema exibe mensagem: "Adicione pelo menos um campo antes de publicar"
3. Caso de uso é encerrado

### FA02 - Campos com configuração inválida

**Quando:** Passo 4 - Campos tipo select sem options

1. Sistema detecta campos inválidos
2. Sistema exibe lista de problemas detectados
3. Sistema exibe mensagem: "Corrija os problemas antes de publicar"
4. Caso de uso é encerrado

### FA03 - Gestor cancela publicação

**Quando:** Passo 6 - Gestor não confirma

1. Sistema cancela a publicação
2. Versão permanece em draft
3. Retorna à edição da versão

---

## Regras de Negócio

- **RN01:** Versão publicada é imutável
- **RN02:** Version_number é sequencial dentro do formulário
- **RN03:** Novas submissões usam a última versão publicada
- **RN04:** Submissões antigas permanecem vinculadas à sua versão
- **RN05:** Apenas uma versão draft permitida por formulário
- **RN06:** Publicação requer pelo menos um campo
- **RN07:** Após publicação, edições requerem nova versão (UC10)

---

## Validações Pré-Publicação

### Requisitos Obrigatórios
✅ Formulário possui pelo menos 1 campo
✅ Todos campos têm label e name
✅ Campos select/radio/checkbox têm options válidas
✅ Não há names duplicados

### Recomendações (avisos, não bloqueiam)
⚠️ Formulário sem descrição
⚠️ Apenas campos opcionais (nenhum obrigatório)
⚠️ Campos sem ordem definida

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_version_id | integer | Sim | Versão deve existir e estar em draft |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 8,
    "form_id": 5,
    "version_number": 1,
    "is_published": true,
    "published_at": "2026-03-22T16:00:00Z",
    "created_by": 10,
    "created_at": "2026-03-22T15:00:00Z",
    "fields_count": 5,
    "fields": [
      {
        "id": 25,
        "label": "Nome Completo",
        "name": "nome_completo",
        "type": "text",
        "is_required": true,
        "order": 1
      },
      {
        "id": 26,
        "label": "Estado Civil",
        "name": "estado_civil",
        "type": "select",
        "is_required": true,
        "order": 2
      }
    ]
  },
  "message": "Versão publicada com sucesso. Formulário disponível para preenchimento.",
  "errors": null
}
```

### Resposta de Erro (422 Unprocessable Entity)

```json
{
  "success": false,
  "data": null,
  "message": "Versão não pode ser publicada",
  "errors": {
    "fields": ["Adicione pelo menos um campo antes de publicar"],
    "field_27": ["Campo do tipo 'select' requer opções"]
  }
}
```

---

## Endpoint da API

```
POST /api/forms/{form_id}/versions/{version_id}/publish
Authorization: Bearer {token}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_versions`

```sql
UPDATE form_versions
SET
  is_published = true,
  published_at = NOW()
WHERE id = 8 AND form_id = 5 AND is_published = false;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'PUBLISH_VERSION', 'form_version', 8,
  '{"form_id": 5, "version_number": 1, "fields_count": 5}', NOW());
```

---

## Efeitos da Publicação

### Para Usuários Finais
- Formulário aparece na lista de formulários disponíveis
- Podem preencher e submeter

### Para Gestores
- Não podem editar campos desta versão
- Podem criar nova versão (UC10)
- Podem consultar submissões

### Para o Sistema
- Versão usada como base para novas submissões
- Versão usada na exportação de dados
- Versionamento preserva histórico

---

## Testes Requeridos

### Teste de Sucesso
✅ Publicar versão com campos válidos
✅ Verificar `is_published = true`
✅ Verificar `published_at` preenchido
✅ Verificar imutabilidade após publicação
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar publicar versão sem campos
❌ Tentar publicar versão com campo select sem options
❌ Tentar publicar versão já publicada

### Testes de Isolamento
🔒 Verificar que gestor só publica formulários do próprio tenant

### Testes Funcionais
🔍 Verificar que formulário aparece na lista de disponíveis
🔍 Verificar que submissões usam versão publicada
🔍 Verificar que não é possível editar após publicação

---

## Exceções

- `ValidationException`: Versão não atende requisitos
- `VersionAlreadyPublishedException`: Versão já publicada
- `NoFieldsException`: Versão sem campos
- `UnauthorizedException`: Sem permissão

---

## Dependências

- Service: `FormVersionService`, `FormFieldService`
- Repository: `FormVersionRepository`, `FormFieldRepository`
- Model: `FormVersion`, `FormField`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC05:** Criar Formulário
- **UC06:** Adicionar Campos (pré-requisito)
- **UC10:** Criar Nova Versão (próximo passo)
- **UC13:** Preencher Formulário (habilitado após publicação)

---

## Confirmação de Publicação (UI)

### Modal Sugerido

```
┌────────────────────────────────────────────────┐
│ Publicar Versão 1                              │
├────────────────────────────────────────────────┤
│                                                │
│ Formulário: Cadastro de Beneficiários         │
│ Versão: 1                                      │
│ Campos: 5                                      │
│                                                │
│ Campos incluídos:                              │
│  1. Nome Completo (text, obrigatório)         │
│  2. Estado Civil (select, obrigatório)        │
│  3. Data de Nascimento (date, obrigatório)    │
│  4. Telefone (text, opcional)                 │
│  5. Observações (textarea, opcional)          │
│                                                │
│ ⚠️  Após publicar, esta versão não poderá     │
│     ser editada.                               │
│                                                │
├────────────────────────────────────────────────┤
│              [Cancelar]  [Publicar]            │
└────────────────────────────────────────────────┘
```

---

## Notas de Implementação

### Validação Completa

```php
public function canPublish(FormVersion $version): array
{
    $errors = [];

    // Verifica se possui campos
    if ($version->fields()->count() === 0) {
        $errors[] = 'Adicione pelo menos um campo';
    }

    // Valida cada campo
    foreach ($version->fields as $field) {
        if (in_array($field->type, ['select', 'radio', 'checkbox'])) {
            if (empty($field->options)) {
                $errors[] = "Campo '{$field->label}' requer opções";
            }
        }
    }

    return $errors;
}
```

### Tornar Imutável

A imutabilidade é garantida pela validação nas operações de edição/remoção, verificando `is_published`.

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Alta (Funcionalidade core)
