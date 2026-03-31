# UC08 - Remover Campo do Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor remover campos de uma versão draft de formulário.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário possui versão em draft
- Campo existe na versão draft

---

## Pós-condições

- Campo removido da versão draft
- Outros campos não são afetados
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa edição de formulário
2. Sistema exibe lista de campos da versão draft
3. Gestor seleciona campo para remover
4. Sistema exibe confirmação:
   - "Tem certeza que deseja remover o campo '{label}'?"
   - Aviso sobre impacto
5. Gestor confirma remoção
6. Sistema valida operação:
   - Versão está em draft
   - Campo pertence à versão
7. Sistema remove campo da versão draft
8. Sistema registra ação no log de auditoria
9. Sistema exibe confirmação
10. Sistema atualiza lista de campos

---

## Fluxos Alternativos

### FA01 - Versão já publicada

**Quando:** Passo 6 - Versão está publicada

1. Sistema detecta versão publicada
2. Sistema exibe mensagem: "Campos de versões publicadas não podem ser removidos"
3. Caso de uso é encerrado

### FA02 - Gestor cancela operação

**Quando:** Passo 5 - Gestor não confirma

1. Sistema cancela a remoção
2. Nenhuma alteração é realizada
3. Retorna à lista de campos

### FA03 - Campo não encontrado

**Quando:** Passo 6 - Campo foi removido ou não existe

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Campo não encontrado"
3. Caso de uso é encerrado

---

## Regras de Negócio

- **RN01:** Apenas campos de versões draft podem ser removidos
- **RN02:** Remoção de campo não afeta versões publicadas anteriores
- **RN03:** Submissões de versões anteriores preservam valores do campo removido
- **RN04:** Remoção é permanente (soft delete não implementado na v1)
- **RN05:** Se campo for o último, formulário fica sem campos (não pode publicar)

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| field_id | integer | Sim | Campo deve existir na versão draft |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": null,
  "message": "Campo removido com sucesso",
  "errors": null
}
```

### Resposta de Erro (400 Bad Request)

```json
{
  "success": false,
  "data": null,
  "message": "Campos de versões publicadas não podem ser removidos",
  "errors": null
}
```

---

## Endpoint da API

```
DELETE /api/forms/{form_id}/versions/{version_id}/fields/{field_id}
Authorization: Bearer {token}
```

---

## Modelo de Dados Afetado

**Tabela:** `form_fields`

```sql
DELETE FROM form_fields
WHERE id = 25 AND form_version_id = 8;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'DELETE_FIELD', 'form_field', 25,
  '{"field_name": "nome_completo", "form_version_id": 8}', NOW());
```

---

## Impactos da Remoção

### Versão Atual (Draft)
- Campo é removido permanentemente
- Não pode ser restaurado (v1)
- Outros campos não são afetados

### Versões Anteriores Publicadas
- Versões anteriores com o campo permanecem inalteradas
- Submissões antigas mantêm o valor do campo

### Novas Submissões
- Após publicar versão sem o campo, novas submissões não terão esse campo
- Exportações combinarão dados de versões diferentes

---

## Testes Requeridos

### Teste de Sucesso
✅ Remover campo de versão draft
✅ Verificar que campo foi excluído
✅ Verificar que outros campos não foram afetados
✅ Verificar registro no audit log

### Testes de Validação
❌ Tentar remover campo de versão publicada
❌ Tentar remover campo inexistente
❌ Tentar remover campo de outra versão

### Testes de Isolamento
🔒 Verificar que gestor só remove campos de formulários do próprio tenant
🔒 Verificar que versões anteriores não são afetadas

### Testes de Integridade
🔍 Verificar que remoção do último campo permite salvar (mas não publicar)
🔍 Verificar que submissões antigas com o campo são preservadas

---

## Exceções

- `VersionPublishedException`: Versão já publicada
- `FieldNotFoundException`: Campo não encontrado
- `UnauthorizedException`: Sem permissão

---

## Dependências

- Service: `FormFieldService`
- Repository: `FormFieldRepository`
- Model: `FormField`, `FormVersion`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC06:** Adicionar Campos
- **UC07:** Editar Campos
- **UC09:** Publicar Versão (bloqueada se sem campos)

---

## Confirmação de Remoção (UI)

### Modal Sugerido

```
┌────────────────────────────────────────────┐
│ Remover Campo                              │
├────────────────────────────────────────────┤
│                                            │
│ Tem certeza que deseja remover o campo:   │
│                                            │
│   "Nome Completo"                          │
│                                            │
│ ⚠️  Esta ação não pode ser desfeita.       │
│                                            │
│ Versões publicadas anteriores com este    │
│ campo não serão afetadas.                 │
│                                            │
├────────────────────────────────────────────┤
│              [Cancelar]  [Remover]         │
└────────────────────────────────────────────┘
```

---

## Notas de Implementação

### Verificar se Versão é Draft

```php
$field = FormField::with('formVersion')->findOrFail($fieldId);

if ($field->formVersion->is_published) {
    throw new VersionPublishedException('Campo de versão publicada não pode ser removido');
}
```

### Registrar Dados do Campo Antes de Remover

```php
$fieldData = [
    'id' => $field->id,
    'name' => $field->name,
    'label' => $field->label,
    'form_version_id' => $field->form_version_id
];

$field->delete();

AuditLog::create([
    'action' => 'DELETE_FIELD',
    'metadata' => $fieldData
]);
```

### Futuras Melhorias (Fora do Escopo v1)

- Implementar soft delete para permitir restauração
- Histórico de campos removidos
- Confirmação adicional para campos críticos
- Validar se campo está sendo usado em integrações

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Média
