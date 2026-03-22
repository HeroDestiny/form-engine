# UC11 - Ativar/Desativar Formulário

**Categoria:** Gestão de Formulários
**Ator Principal:** Gestor (Manager)

---

## Descrição

Permite ao gestor ativar ou desativar um formulário, controlando sua disponibilidade para preenchimento sem excluir dados.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Formulário existe no tenant

---

## Pós-condições

- Status do formulário atualizado (`is_active`)
- Acesso ao formulário afetado conforme novo status
- Ação registrada no audit log

---

## Fluxo Principal

1. Gestor acessa lista de formulários
2. Sistema exibe todos os formulários do tenant com status atual
3. Gestor seleciona formulário específico
4. Gestor solicita alteração de status (ativar/desativar)
5. Sistema valida operação
6. Sistema exibe confirmação com informações de impacto:
   - "Desativar impedirá novas submissões"
   - Quantidade de submissões existentes
7. Gestor confirma a operação
8. Sistema atualiza campo `is_active`:
   - `true` → ativar
   - `false` → desativar
9. Sistema atualiza `updated_at = now()`
10. Sistema registra ação no log de auditoria
11. Sistema exibe mensagem de confirmação

---

## Fluxos Alternativos

### FA01 - Formulário não encontrado

**Quando:** Passo 3 - ID inválido ou de outro tenant

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Formulário não encontrado"
3. Caso de uso é encerrado

### FA02 - Gestor cancela operação

**Quando:** Passo 7 - Gestor não confirma

1. Sistema cancela a alteração
2. Nenhuma modificação é realizada
3. Retorna à lista de formulários

---

## Regras de Negócio

- **RN01:** Formulário desativado não aceita novas submissões
- **RN02:** Submissões anteriores são preservadas e acessíveis
- **RN03:** Versões publicadas permanecem íntegras
- **RN04:** Formulário desativado não aparece na lista de disponíveis (UC12)
- **RN05:** Formulário desativado continua visível no histórico para gestores
- **RN06:** Formulário pode ser reativado a qualquer momento
- **RN07:** Desativação não impede consulta de submissões existentes

---

## Dados de Entrada

| Campo | Tipo | Obrigatório | Validação |
|-------|------|-------------|-----------|
| form_id | integer | Sim | Deve existir no tenant |
| is_active | boolean | Sim | true ou false |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "id": 5,
    "tenant_id": 1,
    "name": "Cadastro de Beneficiários",
    "description": "Formulário para registro de novos beneficiários",
    "is_active": false,
    "created_by": 10,
    "created_at": "2026-03-22T15:00:00Z",
    "updated_at": "2026-03-22T18:00:00Z",
    "submissions_count": 127
  },
  "message": "Formulário desativado com sucesso",
  "errors": null
}
```

---

## Endpoint da API

```
PATCH /api/forms/{form_id}/status
Content-Type: application/json
Authorization: Bearer {token}

{
  "is_active": false
}
```

---

## Modelo de Dados Afetado

**Tabela:** `forms`

```sql
UPDATE forms
SET is_active = false, updated_at = NOW()
WHERE id = 5 AND tenant_id = 1;
```

**Tabela:** `audit_logs`

```sql
INSERT INTO audit_logs (tenant_id, user_id, action, entity_type, entity_id, metadata, created_at)
VALUES (1, 10, 'DEACTIVATE_FORM', 'form', 5,
  '{"previous_status": true, "submissions_count": 127}', NOW());
```

---

## Impactos da Desativação

### Para Usuários Finais
- Formulário não aparece na lista de disponíveis (UC12)
- Não podem criar novas submissões
- Rascunhos salvos podem ficar inacessíveis (dependendo da implementação)

### Para Gestores
- Podem visualizar o formulário (modo somente leitura)
- Podem consultar submissões existentes (UC15)
- Podem exportar dados (UC17)
- Podem reativar quando necessário

### Para Versões
- Todas versões (draft e publicadas) são preservadas
- Nenhuma versão é excluída
- Estrutura permanece intacta

### Para Submissões
- Todas submissões são preservadas
- Dados podem ser consultados e exportados
- Vínculo com versões é mantido

---

## Testes Requeridos

### Teste de Sucesso
✅ Desativar formulário ativo
✅ Reativar formulário desativado
✅ Verificar atualização de `is_active`
✅ Verificar registro no audit log

### Testes de Impacto
🔍 Verificar que formulário não aparece na lista de disponíveis
🔍 Verificar que submissões antigas são preservadas
🔍 Verificar que consultas a submissões continuam funcionando
🔍 Verificar que novas submissões são bloqueadas

### Testes de Validação
❌ Tentar desativar formulário de outro tenant
❌ Tentar desativar formulário inexistente

### Testes de Isolamento
🔒 Verificar que gestor só modifica formulários do próprio tenant

### Testes de Segurança
🔒 Tentar alterar status sem autenticação
🔒 Tentar alterar status com papel `user`

---

## Exceções

- `FormNotFoundException`: Formulário não encontrado ou de outro tenant
- `UnauthorizedException`: Sem permissão
- `ValidationException`: Dados inválidos

---

## Dependências

- Service: `FormService`
- Repository: `FormRepository`
- Model: `Form`
- Request: `UpdateFormStatusRequest`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC05:** Criar Formulário
- **UC12:** Listar Formulários Disponíveis (afetado)
- **UC13:** Preencher Formulário (bloqueado se desativado)
- **UC02:** Ativar/Desativar Tenant (similar)

---

## Cenários de Uso

### Cenário 1: Formulário foi substituído
- Formulário antigo desativado
- Novo formulário criado com melhorias
- Dados antigos preservados para consulta

### Cenário 2: Período de coleta encerrado
- Desativar após prazo
- Não aceitar mais submissões
- Manter dados para análise

### Cenário 3: Manutenção temporária
- Desativar temporariamente
- Fazer ajustes (nova versão)
- Reativar após ajustes

---

## Confirmação de Desativação (UI)

### Modal Sugerido

```
┌────────────────────────────────────────────────┐
│ Desativar Formulário                           │
├────────────────────────────────────────────────┤
│                                                │
│ Formulário: Cadastro de Beneficiários         │
│ Submissões existentes: 127                    │
│                                                │
│ Ao desativar este formulário:                 │
│                                                │
│ ❌ Não aceitará novas submissões              │
│ ❌ Não aparecerá na lista de disponíveis      │
│                                                │
│ ✅ Submissões anteriores serão preservadas    │
│ ✅ Dados poderão ser consultados e exportados │
│ ✅ Poderá ser reativado a qualquer momento    │
│                                                │
├────────────────────────────────────────────────┤
│              [Cancelar]  [Desativar]           │
└────────────────────────────────────────────────┘
```

---

## Notas de Implementação

### Verificar Permissões e Tenant

```php
$form = Form::where('tenant_id', auth()->user()->tenant_id)
    ->findOrFail($formId);

$form->update(['is_active' => false]);
```

### Bloquear Novas Submissões

```php
// No UC13 (Preencher Formulário)
if (!$form->is_active) {
    throw new FormInactiveException('Este formulário não está mais disponível');
}
```

### Filtrar na Listagem (UC12)

```php
// Apenas para usuários finais
Form::where('tenant_id', auth()->user()->tenant_id)
    ->where('is_active', true)
    ->get();

// Gestores veem todos
Form::where('tenant_id', auth()->user()->tenant_id)
    ->get();
```

---

**Última atualização:** 2026-03-22
**Status:** Especificado
**Prioridade:** Média
