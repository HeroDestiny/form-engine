# UC18 - Consultar Log de Auditoria

**Categoria:** Auditoria
**Ator Principal:** Administrador do Tenant

---

## Descrição

Permite ao administrador consultar o histórico de ações realizadas no sistema, garantindo rastreabilidade e segurança.

---

## Pré-condições

- Usuário autenticado com papel `admin`
- Existem registros de auditoria no tenant

---

## Pós-condições

- Registros de auditoria exibidos conforme filtros
- Administrador pode visualizar detalhes (UC19)

---

## Fluxo Principal

1. Administrador acessa área de auditoria
2. Sistema exibe filtros disponíveis:
   - Período (data inicial e final)
   - Usuário (dropdown/autocomplete)
   - Tipo de ação (CREATE, UPDATE, DELETE, etc.)
   - Entidade (form, user, submission, etc.)
3. Administrador aplica filtros (opcional)
4. Sistema busca registros do tenant com filtros aplicados
5. Sistema exibe lista paginada com:
   - Timestamp (data/hora)
   - Usuário responsável
   - Ação realizada
   - Entidade afetada
   - Status/resultado
6. Sistema ordena por mais recentes primeiro
7. Administrador pode:
   - Visualizar detalhes de registro específico (UC19)
   - Aplicar novos filtros
   - Exportar log (opcional)
   - Navegar para entidade relacionada

---

## Fluxos Alternativos

### FA01 - Nenhum registro encontrado

**Quando:** Passo 4 - Filtros não retornam resultados

1. Sistema exibe mensagem: "Nenhum registro encontrado"
2. Sistema sugere ajustar filtros
3. Administrador pode modificar filtros

### FA02 - Filtro por entidade específica

**Quando:** Passo 3 - Administrador filtra por entidade e ID

1. Sistema busca registros relacionados à entidade
2. Sistema exibe timeline de ações sobre a entidade
3. Continua para passo 5

---

## Regras de Negócio

- **RN01:** Isolamento por tenant é obrigatório
- **RN02:** Logs são imutáveis
- **RN03:** Apenas administradores do tenant acessam auditoria
- **RN04:** Logs não podem ser excluídos (v1)
- **RN05:** Retenção de dados conforme política (futuro)
- **RN06:** Ações sensíveis são sempre registradas

---

## Tipos de Ações Auditadas

| Ação | Descrição | Entidade |
|------|-----------|----------|
| CREATE_FORM | Formulário criado | form |
| UPDATE_FORM | Formulário atualizado | form |
| DELETE_FORM | Formulário excluído (futuro) | form |
| CREATE_VERSION | Nova versão criada | form_version |
| PUBLISH_VERSION | Versão publicada | form_version |
| ADD_FIELD | Campo adicionado | form_field |
| UPDATE_FIELD | Campo editado | form_field |
| DELETE_FIELD | Campo removido | form_field |
| SUBMIT_FORM | Formulário submetido | form_submission |
| SAVE_DRAFT | Rascunho salvo | form_submission |
| CREATE_USER | Usuário criado | user |
| UPDATE_USER | Usuário atualizado | user |
| DEACTIVATE_USER | Usuário desativado | user |
| LOGIN_SUCCESS | Login bem-sucedido | user |
| LOGIN_FAILED | Tentativa de login falha | user |
| EXPORT_DATA | Dados exportados | - |

---

## Dados de Entrada (Filtros)

| Campo | Tipo | Obrigatório | Descrição |
|-------|------|-------------|-----------|
| date_from | datetime | Não | Data/hora inicial |
| date_to | datetime | Não | Data/hora final |
| user_id | integer | Não | ID do usuário |
| action | string | Não | Tipo de ação |
| entity_type | string | Não | Tipo de entidade |
| entity_id | integer | Não | ID da entidade específica |
| page | integer | Não | Número da página |
| per_page | integer | Não | Itens por página |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 1523,
        "user": {
          "id": 10,
          "name": "João Silva",
          "email": "joao@example.com"
        },
        "action": "SUBMIT_FORM",
        "entity_type": "form_submission",
        "entity_id": 452,
        "metadata": {
          "form_id": 5,
          "form_name": "Cadastro de Beneficiários",
          "fields_count": 5
        },
        "ip_address": "192.168.1.100",
        "user_agent": "Mozilla/5.0...",
        "created_at": "2026-03-22T19:30:00Z"
      },
      {
        "id": 1522,
        "user": {
          "id": 1,
          "name": "Admin Master",
          "email": "admin@example.com"
        },
        "action": "PUBLISH_VERSION",
        "entity_type": "form_version",
        "entity_id": 8,
        "metadata": {
          "form_id": 5,
          "version_number": 1,
          "fields_count": 5
        },
        "ip_address": "192.168.1.10",
        "user_agent": "Mozilla/5.0...",
        "created_at": "2026-03-22T16:00:00Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 1523,
      "last_page": 31
    },
    "filters_applied": {
      "date_from": "2026-03-01",
      "date_to": "2026-03-31"
    }
  },
  "message": null,
  "errors": null
}
```

---

## Endpoint da API

```
GET /api/audit-logs
Authorization: Bearer {token}

Query Parameters:
?date_from=2026-03-01T00:00:00Z
&date_to=2026-03-31T23:59:59Z
&user_id=10
&action=SUBMIT_FORM
&entity_type=form_submission
&page=1
&per_page=50
```

---

## Modelo de Dados Consultado

```sql
SELECT
    al.id,
    al.tenant_id,
    al.user_id,
    al.action,
    al.entity_type,
    al.entity_id,
    al.metadata,
    al.ip_address,
    al.user_agent,
    al.created_at,
    u.name as user_name,
    u.email as user_email
FROM audit_logs al
LEFT JOIN users u ON al.user_id = u.id
WHERE al.tenant_id = ?
  [AND al.created_at BETWEEN ? AND ?]
  [AND al.user_id = ?]
  [AND al.action = ?]
  [AND al.entity_type = ?]
  [AND al.entity_id = ?]
ORDER BY al.created_at DESC
LIMIT ? OFFSET ?;
```

---

## Interface Visual Sugerida

### Tela de Auditoria

```
┌──────────────────────────────────────────────────────────────────┐
│ Log de Auditoria                                                 │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│ Período: [01/03/2026] até [31/03/2026]                          │
│ Usuário: [Todos        ▼]  Ação: [Todas         ▼]             │
│ Entidade: [Todas       ▼]                                       │
│                                                                  │
│ [Limpar]  [Buscar]                                              │
├──────────────────────────────────────────────────────────────────┤
│ 1.523 registros encontrados (último mês)                        │
│                                                                  │
│ Data/Hora       │ Usuário       │ Ação           │ Entidade    │ Detalhes   │
│ 22/03 19:30:15  │ João Silva    │ SUBMIT_FORM    │ Submission  │ [Ver]      │
│ 22/03 16:00:00  │ Admin Master  │ PUBLISH_VERSION│ Version     │ [Ver]      │
│ 22/03 15:30:45  │ Admin Master  │ ADD_FIELD      │ Field       │ [Ver]      │
│ 22/03 15:00:12  │ Admin Master  │ CREATE_FORM    │ Form        │ [Ver]      │
│ 21/03 10:15:00  │ Maria Santos  │ LOGIN_SUCCESS  │ User        │ [Ver]      │
│                                                                  │
│ ◀ Anterior  [1] 2 3 4 ... 31  Próximo ▶                        │
└──────────────────────────────────────────────────────────────────┘
```

---

## Testes Requeridos

### Teste de Sucesso
✅ Listar todos logs do tenant
✅ Filtrar por período
✅ Filtrar por usuário
✅ Filtrar por tipo de ação
✅ Filtrar por entidade específica

### Testes de Isolamento
🔒 Verificar que admin só vê logs do próprio tenant
🔒 Verificar que manager/user não acessam auditoria

### Testes de Integridade
🔍 Verificar imutabilidade dos logs
🔍 Verificar ordenação cronológica
🔍 Verificar paginação

---

## Exceções

- `UnauthorizedException`: Usuário não é admin
- `TenantMismatchException`: Tentativa de acesso cross-tenant

---

## Dependências

- Service: `AuditLogService`
- Repository: `AuditLogRepository`
- Model: `AuditLog`, `User`
- Middleware: `AdminOnly`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC19:** Visualizar Detalhes de Ação Auditada
- Todos os casos de uso que geram auditoria

---

## Notas de Implementação

### Query Otimizada

```php
AuditLog::with('user')
    ->where('tenant_id', auth()->user()->tenant_id)
    ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
    ->when($dateTo, fn($q) => $q->where('created_at', '<=', $dateTo))
    ->when($userId, fn($q) => $q->where('user_id', $userId))
    ->when($action, fn($q) => $q->where('action', $action))
    ->when($entityType, fn($q) => $q->where('entity_type', $entityType))
    ->orderBy('created_at', 'desc')
    ->paginate(50);
```

### Formatação de Ações

```php
public function getActionLabel(string $action): string
{
    return match($action) {
        'CREATE_FORM' => 'Formulário criado',
        'PUBLISH_VERSION' => 'Versão publicada',
        'SUBMIT_FORM' => 'Formulário submetido',
        'LOGIN_SUCCESS' => 'Login realizado',
        default => $action
    };
}
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Média (Segurança e conformidade)
