# UC07 - Editar Campos do Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor modificar configurações de campos existentes em uma versão draft de formulário.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário possui versão em draft
- Campo existe na versão draft

---

## Pós-condições

- Campo atualizado na versão draft
- Versão permanece em draft
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa edição de formulário
2. Sistema exibe lista de campos da versão draft
3. Gestor seleciona campo para editar
4. Sistema exibe formulário com dados atuais do campo
5. Gestor modifica informações desejadas:
   - Label
   - Tipo (com cuidado)
   - Obrigatoriedade
   - Opções
   - Ordem
6. Sistema valida alterações:
   - Label não vazio
   - Se alterou name, verifica unicidade
   - Se alterou tipo, valida compatibilidade
   - Se tipo requer options, valida presença
7. Sistema atualiza campo na versão draft
8. Sistema registra ação no log de auditoria
9. Sistema exibe confirmação e lista atualizada

---

## Fluxos Alternativos

### FA01 - Versão já publicada

**Quando:** Passo 1 - Versão está publicada

1. Sistema detecta versão publicada
2. Sistema exibe mensagem: "Versões publicadas não podem ser editadas. Crie uma nova versão."
3. Caso de uso é encerrado

### FA02 - Name duplicado (se alterado)

**Quando:** Passo 6 - Novo name já existe

1. Sistema retorna erro de validação
2. Sistema exibe mensagem: "Já existe um campo com este identificador"
3. Retorna ao passo 5 do fluxo principal

### FA03 - Alteração de tipo incompatível

**Quando:** Passo 6 - Mudança pode causar perda de dados

1. Sistema detecta mudança arriscada (ex: select → text)
2. Sistema exibe aviso sobre possível impacto
3. Gestor confirma ou cancela
4. Se confirmado, continua para passo 7
5. Se cancelado, retorna ao passo 5

---

## Regras de Negócio

- **RN01:** Apenas versões draft podem ser editadas
- **RN02:** Versões publicadas são imutáveis
- **RN03:** Alterar name pode quebrar integrações (deve ser evitado)
- **RN04:** Alterar tipo deve ser feito com cuidado
- **RN05:** Submissões de versões anteriores não são afetadas
- **RN06:** Order pode ser alterada para reorganizar formulário

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| field_id | integer | Sim | Campo deve existir na versão |
| label | string | Sim | Min: 2, Max: 255 |
| name | string | Sim | snake_case, único (se alterado) |
| type | enum | Sim | Tipo válido |
| is_required | boolean | Sim | true ou false |
| options | JSON | Condicional | Se tipo requer |
| order | integer | Sim | Posição |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 25,
    "form_version_id": 8,
    "label": "Nome Completo do Beneficiário",
    "name": "nome_completo",
    "type": "text",
    "is_required": true,
    "options": null,
    "order": 1,
    "created_at": "2026-03-22T15:30:00Z"
  },
  "message": "Campo atualizado com sucesso",
  "errors": null
}
```

---

## Endpoint da API

```
PUT /api/forms/{form_id}/versions/{version_id}/fields/{field_id}
Content-Type: application/json
Authorization: Bearer {token}

{
  "label": "Nome Completo do Beneficiário",
  "name": "nome_completo",
  "type": "text",
  "is_required": true,
  "order": 1
}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_fields`

```sql
UPDATE form_fields
SET
  label = 'Nome Completo do Beneficiário',
  is_required = true,
  "order" = 1
WHERE id = 25 AND form_version_id = 8;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'UPDATE_FIELD', 'form_field', 25, '{"changes": {"label": "..."}}', NOW());
```

---

## Alterações Comuns e Seus Impactos

| Alteração | Impacto | Recomendação |
|-----------|---------|--------------|
| Label | Nenhum | Seguro |
| Order | Visual apenas | Seguro |
| is_required | Validação futura | Seguro |
| Name | Pode quebrar integrações | Evitar |
| Type | Pode causar incompatibilidade | Muito cuidado |
| Options | Afeta validação | Cuidado moderado |

---

## Testes Requeridos

### Teste de Sucesso
✅ Editar label de campo
✅ Alterar ordem de exibição
✅ Mudar obrigatoriedade
✅ Adicionar/remover options
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar editar campo em versão publicada
❌ Tentar alterar name para um já existente
❌ Tentar remover options de campo select

### Testes de Isolamento
🔒 Verificar que gestor só edita campos de formulários do próprio tenant

---

## Exceções

- `ValidationException`: Dados inválidos
- `VersionPublishedException`: Versão já publicada
- `FieldNotFoundException`: Campo não encontrado
- `UnauthorizedException`: Sem permissão

---

## Dependências

- Service: `FormFieldService`
- Repository: `FormFieldRepository`
- Model: `FormField`, `FormVersion`
- Request: `UpdateFormFieldRequest`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC06:** Adicionar Campos
- **UC08:** Remover Campo
- **UC10:** Criar Nova Versão (alternativa para edições)

---

## Notas de Implementação

### Verificar se Versão é Draft

```php
$formVersion = FormVersion::findOrFail($versionId);

if ($formVersion->is_published) {
    throw new VersionPublishedException('Versão já publicada não pode ser editada');
}
```

### Rastrear Alterações para Auditoria

```php
$changes = $field->getDirty(); // Laravel

AuditLog::create([
    'action' => 'UPDATE_FIELD',
    'metadata' => ['changes' => $changes]
]);
```

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Média
