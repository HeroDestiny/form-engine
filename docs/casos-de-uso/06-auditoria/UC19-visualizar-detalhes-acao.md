# UC19 - Visualizar Detalhes de Ação Auditada

**Categoria:** Auditoria
**Ator Principal:** Administrador do Tenant

---

## Descrição

Permite ao administrador visualizar informações detalhadas de uma ação específica registrada no log de auditoria.

---

## Pré-condições

- Usuário autenticado com papel `admin`
- Registro de auditoria existe
- Registro pertence ao tenant do usuário

---

## Pós-condições

- Detalhes completos do registro exibidos
- Administrador pode navegar para entidade relacionada

---

## Fluxo Principal

1. Administrador acessa consulta de auditoria (UC18)
2. Administrador seleciona registro específico
3. Sistema valida acesso (tenant match)
4. Sistema busca detalhes completos do registro:
   - Data/hora precisa
   - Usuário responsável (com role e status)
   - Ação executada
   - Entidade e ID afetados
   - Metadados (JSON) com contexto adicional
   - IP de origem
   - User agent (navegador/dispositivo)
5. Sistema renderiza visualização formatada
6. Sistema exibe opções:
   - Ver entidade relacionada (se ainda existir)
   - Ver todas ações do usuário
   - Ver todas ações na entidade
   - Voltar à lista

---

## Fluxos Alternativos

### FA01 - Registro não encontrado ou acesso negado

**Quando:** Passo 3 - ID inválido ou de outro tenant

1. Sistema retorna erro 404
2. Sistema exibe mensagem: "Registro não encontrado"
3. Caso de uso é encerrado

### FA02 - Entidade relacionada foi excluída

**Quando:** Passo 6 - Entidade não existe mais

1. Sistema detecta entidade inexistente
2. Sistema indica "Entidade excluída" ou "Não disponível"
3. Botão de navegação fica desabilitado
4. Dados do log permanecem acessíveis

---

## Regras de Negócio

- **RN01:** Logs são imutáveis e permanentes
- **RN02:** Isolamento por tenant é obrigatório
- **RN03:** Metadados JSON armazenam contexto relevante
- **RN04:** IP e user agent são registrados para segurança
- **RN05:** Navegação para entidade só se ela ainda existir

---

## Dados de Saída

### Resposta de Sucesso (200 OK)

```json
{
  "success": true,
  "data": {
    "log": {
      "id": 1523,
      "tenant_id": 1,
      "action": "SUBMIT_FORM",
      "entity_type": "form_submission",
      "entity_id": 452,
      "metadata": {
        "form_id": 5,
        "form_name": "Cadastro de Beneficiários",
        "form_version_id": 8,
        "version_number": 1,
        "fields_count": 5,
        "fields_filled": ["nome_completo", "estado_civil", "data_nascimento", "telefone", "observacoes"]
      },
      "ip_address": "192.168.1.100",
      "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
      "created_at": "2026-03-22T19:30:15.234Z"
    },
    "user": {
      "id": 10,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "manager",
      "is_active": true
    },
    "entity_exists": true,
    "related_actions_count": 15
  },
  "message": null,
  "errors": null
}
```

### Exemplo: Login Falho

```json
{
  "success": true,
  "data": {
    "log": {
      "id": 1520,
      "tenant_id": 1,
      "action": "LOGIN_FAILED",
      "entity_type": "user",
      "entity_id": null,
      "metadata": {
        "email_attempted": "joao@example.com",
        "reason": "invalid_credentials",
        "attempt_number": 3
      },
      "ip_address": "203.0.113.42",
      "user_agent": "Mozilla/5.0...",
      "created_at": "2026-03-22T14:25:10.123Z"
    },
    "user": null,
    "entity_exists": false
  },
  "message": null,
  "errors": null
}
```

---

## Endpoint da API

```
GET /api/audit-logs/{log_id}
Authorization: Bearer {token}
```

---

## Interface Visual Sugerida

### Tela de Detalhes

```
┌──────────────────────────────────────────────────────────────┐
│ Detalhes da Auditoria #1523                                  │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ ⏰ Data/Hora                                                 │
│ 22 de março de 2026 às 19:30:15                             │
│                                                              │
│ 👤 Usuário                                                   │
│ João Silva (manager)                                         │
│ joao@example.com                                             │
│ Status: Ativo                                                │
│                                                              │
│ 🎬 Ação                                                      │
│ SUBMIT_FORM - Formulário submetido                          │
│                                                              │
│ 📦 Entidade Afetada                                          │
│ form_submission #452                                         │
│ [Ver Submissão]                                              │
│                                                              │
│ ℹ️  Informações Adicionais                                   │
│ • Formulário: Cadastro de Beneficiários (ID: 5)             │
│ • Versão: 1                                                  │
│ • Campos preenchidos: 5                                      │
│ • Campos: nome_completo, estado_civil, data_nascimento...    │
│                                                              │
│ 🌐 Informações Técnicas                                      │
│ IP: 192.168.1.100                                            │
│ Navegador: Chrome 120 (Windows)                              │
│                                                              │
├──────────────────────────────────────────────────────────────┤
│ [Ver outras ações deste usuário]                             │
│ [Ver todas ações nesta submissão]                            │
│                                                              │
│ [← Voltar para lista]                                        │
└──────────────────────────────────────────────────────────────┘
```

---

## Metadados por Tipo de Ação

### CREATE_FORM
```json
{
  "form_name": "Nome do formulário",
  "form_id": 5
}
```

### PUBLISH_VERSION
```json
{
  "form_id": 5,
  "version_number": 1,
  "fields_count": 5
}
```

### SUBMIT_FORM
```json
{
  "form_id": 5,
  "form_name": "...",
  "form_version_id": 8,
  "version_number": 1,
  "fields_count": 5,
  "fields_filled": ["campo1", "campo2"]
}
```

### LOGIN_FAILED
```json
{
  "email_attempted": "user@example.com",
  "reason": "invalid_credentials",
  "attempt_number": 3
}
```

### EXPORT_DATA
```json
{
  "form_id": 5,
  "records_count": 127,
  "file_format": "csv",
  "filters": {
    "date_from": "2026-03-01",
    "date_to": "2026-03-31"
  }
}
```

---

## Implementação na Interface

No frontend, os detalhes de uma ação auditada devem ser exibidos em uma view dedicada:

1. A partir da listagem de auditoria (`/audit-logs`), o administrador clica em "Ver" em um registro específico.
2. A navegação deve levar para `/audit-logs/{logId}`, onde o frontend consome `GET /api/audit-logs/{log_id}`.
3. A tela deve apresentar os dados principais (usuário, ação, entidade, data/hora, IP, user agent) e os metadados formatados, seguindo o layout sugerido neste UC.
4. Quando aplicável, botões de atalho devem permitir navegar para a entidade relacionada (por exemplo, submissão ou formulário) usando as rotas já existentes no front.

---

## Testes Requeridos

### Teste de Sucesso
✅ Visualizar detalhes completos de log
✅ Verificar formatação de metadados JSON
✅ Verificar links para entidade relacionada
✅ Verificar informações do usuário

### Testes de Acesso
❌ Tentar visualizar log de outro tenant
❌ Tentar visualizar log inexistente
❌ Tentar visualizar como manager/user

### Testes de Isolamento
🔒 Verificar que admin só vê logs do próprio tenant

### Testes Funcionais
🔍 Verificar que entidade excluída é indicada
🔍 Verificar parsing correto de user agent
🔍 Verificar formatação de IP

---

## Exceções

- `AuditLogNotFoundException`: Log não encontrado
- `UnauthorizedException`: Usuário não é admin
- `TenantMismatchException`: Log de outro tenant

---

## Dependências

- Service: `AuditLogService`
- Repository: `AuditLogRepository`
- Model: `AuditLog`, `User`
- Helper: `UserAgentParser` (para formatar navegador/SO)
- Middleware: `AdminOnly`, `TenantScope`

---

## Casos de Uso Relacionados

- **UC18:** Consultar Log de Auditoria (passo anterior)
- **UC16:** Visualizar Detalhes de Submissão (navegação)

---

## Notas de Implementação

### Verificar Acesso

```php
$log = AuditLog::with('user')->findOrFail($logId);

if ($log->tenant_id !== auth()->user()->tenant_id) {
    abort(404);
}
```

### Verificar se Entidade Existe

```php
public function entityExists(AuditLog $log): bool
{
    if (!$log->entity_id) {
        return false;
    }

    return match($log->entity_type) {
        'form' => Form::where('id', $log->entity_id)->exists(),
        'form_submission' => FormSubmission::where('id', $log->entity_id)->exists(),
        'user' => User::where('id', $log->entity_id)->exists(),
        default => false
    };
}
```

### Parsear User Agent

```php
use Jenssegers\Agent\Agent;

$agent = new Agent();
$agent->setUserAgent($log->user_agent);

$browser = $agent->browser(); // Chrome
$version = $agent->version($browser); // 120
$platform = $agent->platform(); // Windows
```

### Formatar Ação

```php
public function getActionDescription(AuditLog $log): string
{
    $action = $log->action;
    $metadata = $log->metadata;

    return match($action) {
        'SUBMIT_FORM' => "Formulário '{$metadata['form_name']}' submetido",
        'CREATE_FORM' => "Formulário '{$metadata['form_name']}' criado",
        'PUBLISH_VERSION' => "Versão {$metadata['version_number']} publicada",
        'LOGIN_SUCCESS' => "Login realizado com sucesso",
        'LOGIN_FAILED' => "Tentativa de login falhou",
        default => $action
    };
}
```

---

**Última atualização:** 2026-03-31
**Status:** Especificado
**Prioridade:** Média (Segurança e conformidade)
