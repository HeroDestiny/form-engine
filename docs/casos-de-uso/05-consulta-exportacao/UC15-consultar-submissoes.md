# UC15 - Consultar Submissões

**Categoria:** Consulta e Exportação
**Ator Principal:** Gestor (Manager) / Administrador

---

## Descrição

Permite ao gestor consultar submissões de formulários do tenant, com filtros e visualização de dados coletados.

---

## Pré-condições

- Usuário autenticado com papel `manager` ou `admin`
- Existem submissões no tenant

---

## Pós-condições

- Lista de submissões exibida conforme filtros
- Gestor pode visualizar detalhes (UC16)
- Gestor pode exportar dados (UC17)

---

## Fluxo Principal

1. Gestor acessa área de consultas/relatórios
2. Sistema exibe filtros disponíveis:
   - Formulário (dropdown)
   - Versão (dropdown)
   - Período - data inicial e final
   - Usuário (autocomplete)
   - Status (submitted/draft - se implementado)
3. Gestor aplica filtros desejados (opcional)
4. Sistema busca submissões do tenant com filtros aplicados
5. Sistema exibe lista paginada com:
   - ID da submissão
   - Formulário e versão
   - Usuário que submeteu
   - Data/hora de submissão
   - Ações: [Visualizar] [Exportar]
6. Gestor pode:
   - Visualizar detalhes de submission específica (UC16)
   - Exportar seleção (UC17)
   - Aplicar novos filtros
   - Paginar resultados

---

## Fluxos Alternativos

### FA01 - Nenhuma submissão encontrada

**Quando:** Passo 4 - Filtros não retornam resultados

1. Sistema exibe mensagem: "Nenhuma submissão encontrada"
2. Sistema sugere ajustar filtros
3. Gestor pode modificar filtros e tentar novamente

### FA02 - Usuário final consulta próprias submissões

**Quando:** Usuário com papel `user` acessa

1. Sistema aplica filtro automático: `submitted_by = user_id`
2. Usuário vê apenas as próprias submissões
3. Continua para passo 5 do fluxo principal

---

## Regras de Negócio

- **RN01:** Isolamento por tenant é obrigatório
- **RN02:** Apenas dados do próprio tenant são exibidos
- **RN03:** Gestores e admins veem todas submissões do tenant
- **RN04:** Usuários finais veem apenas próprias submissões
- **RN05:** Rascunhos não aparecem na consulta padrão (filtro separado)
- **RN06:** Paginação obrigatória para grandes volumes
- **RN07:** Ordenação padrão: mais recentes primeiro

---

## Dados de Entrada (Filtros)

| Campo | Tipo | Obrigatório | Opcões |
|-------|------|-------------|---------|
| form_id | integer | Não | ID do formulário |
| version_id | integer | Não | ID da versão |
| date_from | date | Não | Data inicial |
| date_to | date | Não | Data final |
| user_id | integer | Não | ID do usuário |
| status | enum | Não | submitted, draft |
| page | integer | Não | Número da página (default: 1) |
| per_page | integer | Não | Itens por página (default: 20) |

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "submissions": [
      {
        "id": 452,
        "form": {
          "id": 5,
          "name": "Cadastro de Beneficiários"
        },
        "version": {
          "id": 8,
          "version_number": 1
        },
        "user": {
          "id": 25,
          "name": "Maria Santos",
          "email": "maria@example.com"
        },
        "submitted_at": "2026-03-22T19:30:00Z",
        "fields_count": 5
      },
      {
        "id": 451,
        "form": {
          "id":5,
          "name": "Cadastro de Beneficiários"
        },
        "version": {
          "id": 8,
          "version_number": 1
        },
        "user": {
          "id": 30,
          "name": "João Silva",
          "email": "joao@example.com"
        },
        "submitted_at": "2026-03-22T18:15:00Z",
        "fields_count": 5
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 127,
      "last_page": 7,
      "from": 1,
      "to": 20
    },
    "filters_applied": {
      "form_id": 5,
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
GET /api/submissions
Authorization: Bearer {token}

Query Parameters:
?form_id=5
&version_id=8
&date_from=2026-03-01
&date_to=2026-03-31
&user_id=25
&status=submitted
&page=1
&per_page=20
```

---

## Modelo de Dados Consultado

```sql
SELECT
    fs.id,
    fs.tenant_id,
    fs.form_version_id,
    fs.submitted_by,
    fs.submitted_at,
    f.name as form_name,
    fv.version_number,
    u.name as user_name,
    u.email as user_email,
    COUNT(fsv.id) as fields_count
FROM form_submissions fs
INNER JOIN form_versions fv ON fs.form_version_id = fv.id
INNER JOIN forms f ON fv.form_id = f.id
INNER JOIN users u ON fs.submitted_by = u.id
LEFT JOIN form_submission_values fsv ON fs.id = fsv.form_submission_id
WHERE fs.tenant_id = ?
  AND fs.status = 'submitted'
  [AND fv.form_id = ?]
  [AND fs.submitted_at BETWEEN ? AND ?]
  [AND fs.submitted_by = ?]
GROUP BY fs.id
ORDER BY fs.submitted_at DESC
LIMIT ? OFFSET ?;
```

---

## Interface Visual Sugerida

### Tela de Consultas

```
┌─────────────────────────────────────────────────────────────────┐
│ Consultar Submissões                                            │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ Formulário: [Todos            ▼]  Versão: [Todas        ▼]    │
│ Período: [01/03/2026] até [31/03/2026]                         │
│ Usuário: [Todos               ▼]  Status: [Submitted    ▼]    │
│                                                                 │
│ [Limpar Filtros]  [Buscar]                      [📥 Exportar]  │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ 127 submissões encontradas                                     │
│                                                                 │
│ ID   │ Formulário              │ Versão │ Usuário        │ Data      │ Ações      │
│ 452  │ Cadastro Beneficiários  │ v1     │ Maria Santos   │ 22/03 19:30│ [👁️] [📥] │
│ 451  │ Cadastro Beneficiários  │ v1     │ João Silva     │ 22/03 18:15│ [👁️] [📥] │
│ 450  │ Pesquisa Satisfação     │ v2     │ Ana Paula      │ 22/03 17:00│ [👁️] [📥] │
│                                                                 │
│ ◀ Anterior  [1] 2 3 4 5 ... 7  Próximo ▶                      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Implementação na Interface

A consulta de submissões deve estar disponível em dois pontos da interface:

1. **Visão geral de submissões**
  - Através do menu superior, o usuário acessa a rota `/submissions`.
  - A tela deve listar submissões do tenant (ou apenas as próprias, no caso de papel `user`), com paginação.
  - A interface deve expor, no mínimo, filtros por formulário e período, utilizando os parâmetros descritos neste UC; filtros adicionais (usuário, status) podem ser adicionados conforme necessidade.

2. **Submissões de um formulário específico**
  - Na tela de detalhe de um formulário, deve existir um bloco "Submissões deste formulário".
  - Essa lista deve vir filtrada por `form_id` e mostrar ID, versão, data e contagem de campos, com link para o detalhe (UC16).

3. Em ambos os casos, o frontend deve consumir o endpoint `GET /api/submissions`, aplicando os filtros apropriados (tenant, usuário atual, e opcionalmente `form_id`, `date_from`, `date_to`, etc.).

---

## Testes Requeridos

### Teste de Sucesso
✅ Consultar todas submissões do tenant
✅ Filtrar por formulário
✅ Filtrar por período
✅ Filtrar por usuário
✅ Verificar paginação

### Testes de Isolamento
🔒 Verificar que gestor só vê submissões do próprio tenant
🔒 Verificar que usuário final só vê próprias submissões
🔒 Verificar erro 404 para submissões de outros tenants

### Testes Funcionais
🔍 Verificar ordenação (mais recentes primeiro)
🔍 Verificar contagem correta
🔍 Verificar filtros múltiplos combinados

---

## Exceções

- `UnauthorizedException`: Sem permissão
- `TenantMismatchException`: Tentativa de acesso cross-tenant

---

## Dependências

- Service: `FormSubmissionService`
- Repository: `FormSubmissionRepository`
- Model: `FormSubmission`, `Form`, `FormVersion`, `User`
- Middleware: `ManagerOrAdmin`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC16:** Visualizar Detalhes de Submissão (próximo passo)
- **UC17:** Exportar Dados em CSV
- **UC13:** Preencher Formulário (gera submissões)

---

## Notas de Implementação

### Query Otimizada com Relacionamentos

```php
FormSubmission::with(['formVersion.form', 'user'])
    ->where('tenant_id', auth()->user()->tenant_id)
    ->where('status', 'submitted')
    ->when($formId, fn($q) => $q->whereHas('formVersion', fn($qv) =>
        $qv->where('form_id', $formId)
    ))
    ->when($dateFrom, fn($q) => $q->where('submitted_at', '>=', $dateFrom))
    ->when($dateTo, fn($q) => $q->where('submitted_at', '<=', $dateTo))
    ->when($userId, fn($q) => $q->where('submitted_by', $userId))
    ->orderBy('submitted_at', 'desc')
    ->paginate(20);
```

### Scope para Papel de Usuário

```php
// No model FormSubmission
public function scopeForUser($query, User $user)
{
    if ($user->role === 'user') {
        return $query->where('submitted_by', $user->id);
    }

    return $query;
}
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Alta (Consulta core)
