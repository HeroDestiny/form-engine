# Documentação da API - Form Engine

Documentação completa da API REST do **Form Engine** v1.0.

---

## 📚 Índice

- [Visão Geral](#visão-geral)
- [Autenticação](#autenticação)
- [Endpoints](#endpoints)
- [Modelos de Dados](#modelos-de-dados)
- [Códigos de Status](#códigos-de-status)
- [Exemplos de Uso](#exemplos-de-uso)
- [Rate Limiting](#rate-limiting)
- [Ferramentas](#ferramentas)

---

## Visão Geral

### Base URL

```
Desenvolvimento: http://localhost:8000/api
Staging:        https://staging.formengine.com/api
Produção:       https://api.formengine.com/api
```

### Formato

- **Content-Type:** `application/json`
- **Encoding:** UTF-8
- **Formato de Data:** ISO 8601 (`2026-03-22T19:30:00Z`)

### Padrão de Resposta

Todas as respostas seguem o mesmo padrão:

```json
{
  "success": true,
  "data": {},
  "message": "Mensagem descritiva",
  "errors": null
}
```

---

## Autenticação

A API utiliza **Bearer Token Authentication** via Laravel Sanctum.

### 1. Obter Token

**Endpoint:** `POST /auth/login`

**Request:**
```json
{
  "email": "joao@example.com",
  "password": "senha123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 10,
      "name": "João Silva",
      "email": "joao@example.com",
      "role": "manager"
    },
    "tenant": {
      "id": 1,
      "name": "Prefeitura Municipal",
      "slug": "prefeitura"
    },
    "token": "5|KGpNxYZ8rJ3vQ2wB1mC6tF9sL4hD7eR0aI5nU8oP",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "message": "Login realizado com sucesso"
}
```

### 2. Usar Token

Inclua o token em todas as requisições protegidas:

```http
Authorization: Bearer 5|KGpNxYZ8rJ3vQ2wB1mC6tF9sL4hD7eR0aI5nU8oP
```

### 3. Logout

**Endpoint:** `POST /auth/logout`

```bash
curl -X POST https://api.formengine.com/api/auth/logout \
  -H "Authorization: Bearer {token}"
```

---

## Endpoints

### Resumo por Categoria

| Categoria | Endpoints | Autenticação |
|-----------|-----------|--------------|
| [Autenticação](#autenticação-1) | 3 | Parcial |
| [Tenants](#tenants) | 3 | Admin Sistema |
| [Usuários](#usuários) | 3 | Admin |
| [Formulários](#formulários) | 4 | User+ |
| [Versões](#versões) | 3 | Manager+ |
| [Campos](#campos) | 4 | Manager+ |
| [Submissões](#submissões) | 5 | User+ |
| [Exportação](#exportação) | 1 | Manager+ |
| [Auditoria](#auditoria) | 2 | Admin |

**Total:** 29 endpoints

---

## Autenticação

### POST `/auth/login`
Autentica usuário e retorna token.

**Permissões:** Público

**Body:**
```json
{
  "email": "string (required)",
  "password": "string (required)"
}
```

**Responses:** `200`, `401`, `403`, `422`

---

### POST `/auth/logout`
Invalida o token atual.

**Permissões:** Autenticado

**Responses:** `200`, `401`

---

### GET `/auth/me`
Retorna dados do usuário autenticado.

**Permissões:** Autenticado

**Responses:** `200`, `401`

---

## Tenants

### GET `/admin/tenants`
Lista todos os tenants.

**Permissões:** Admin Sistema

**Query Params:**
- `page` (int): Número da página
- `per_page` (int): Itens por página

**Responses:** `200`, `401`, `403`

---

### POST `/admin/tenants`
Cria novo tenant.

**Permissões:** Admin Sistema

**Body:**
```json
{
  "name": "string (required, min:3)",
  "slug": "string (required, pattern: ^[a-z0-9-]+$)"
}
```

**Responses:** `201`, `401`, `403`, `422`

---

### PATCH `/admin/tenants/{tenantId}/status`
Ativa/desativa tenant.

**Permissões:** Admin Sistema

**Body:**
```json
{
  "is_active": "boolean (required)"
}
```

**Responses:** `200`, `401`, `403`, `404`

---

## Usuários

### GET `/tenants/{tenantId}/users`
Lista usuários do tenant.

**Permissões:** Admin

**Responses:** `200`, `401`, `403`

---

### POST `/tenants/{tenantId}/users`
Cria novo usuário.

**Permissões:** Admin

**Body:**
```json
{
  "name": "string (required, min:3)",
  "email": "string (required, email)",
  "password": "string (required, min:8)",
  "role": "enum (required): admin|manager|user"
}
```

**Responses:** `201`, `401`, `403`, `422`

---

### PATCH `/tenants/{tenantId}/users/{userId}/status`
Ativa/desativa usuário.

**Permissões:** Admin

**Body:**
```json
{
  "is_active": "boolean (required)"
}
```

**Responses:** `200`, `401`, `403`, `404`

---

## Formulários

### GET `/forms`
Lista formulários disponíveis.

**Permissões:** User+

**Query Params:**
- `search` (string): Termo de busca
- `sort` (enum): `name|created_at`
- `order` (enum): `asc|desc`

**Responses:** `200`, `401`

---

### POST `/forms`
Cria novo formulário.

**Permissões:** Manager+

**Body:**
```json
{
  "name": "string (required, min:3, max:255)",
  "description": "string (optional, max:1000)"
}
```

**Response:** Retorna formulário com versão 1 em draft

**Responses:** `201`, `401`, `403`, `422`

---

### GET `/forms/{formId}`
Obtém detalhes do formulário.

**Permissões:** User+

**Responses:** `200`, `401`, `404`

---

### PATCH `/forms/{formId}/status`
Ativa/desativa formulário.

**Permissões:** Manager+

**Body:**
```json
{
  "is_active": "boolean (required)"
}
```

**Responses:** `200`, `401`, `403`, `404`

---

## Versões

### GET `/forms/{formId}/versions`
Lista versões do formulário.

**Permissões:** User+

**Responses:** `200`, `401`, `404`

---

### POST `/forms/{formId}/versions`
Cria nova versão (cópia da anterior).

**Permissões:** Manager+

**Responses:** `201`, `401`, `403`, `400`

---

### POST `/forms/{formId}/versions/{versionId}/publish`
Publica versão draft.

**Permissões:** Manager+

**Responses:** `200`, `401`, `403`, `422`

---

## Campos

### GET `/forms/{formId}/versions/{versionId}/fields`
Lista campos da versão.

**Permissões:** User+

**Responses:** `200`, `401`, `404`

---

### POST `/forms/{formId}/versions/{versionId}/fields`
Adiciona campo à versão draft.

**Permissões:** Manager+

**Body:**
```json
{
  "label": "string (required, min:2, max:255)",
  "name": "string (required, snake_case)",
  "type": "enum (required): text|textarea|number|email|date|select|radio|checkbox",
  "is_required": "boolean (required)",
  "options": "array (conditional): [{value, label}]",
  "order": "integer (required, min:1)"
}
```

**Responses:** `201`, `401`, `403`, `422`

---

### PUT `/forms/{formId}/versions/{versionId}/fields/{fieldId}`
Edita campo da versão draft.

**Permissões:** Manager+

**Body:** Mesmo schema de POST

**Responses:** `200`, `401`, `403`, `404`, `422`

---

### DELETE `/forms/{formId}/versions/{versionId}/fields/{fieldId}`
Remove campo da versão draft.

**Permissões:** Manager+

**Responses:** `200`, `401`, `403`, `404`

---

## Submissões

### POST `/forms/{formId}/submit`
Submete formulário preenchido.

**Permissões:** User+

**Body:**
```json
{
  "values": {
    "nome_completo": "Maria Santos",
    "estado_civil": "solteiro",
    "data_nascimento": "1990-05-15",
    "telefone": "(11) 98765-4321"
  }
}
```

**Responses:** `201`, `401`, `400`, `422`

---

### POST `/forms/{formId}/drafts`
Salva rascunho (preenchimento parcial).

**Permissões:** User+

**Body:** Mesmo schema de submit, mas sem validação de campos obrigatórios

**Responses:** `201`, `401`

---

### GET `/forms/{formId}/drafts`
Lista rascunhos do usuário.

**Permissões:** User+

**Responses:** `200`, `401`

---

### GET `/submissions`
Lista submissões.

**Permissões:**
- Manager/Admin: todas do tenant
- User: apenas próprias

**Query Params:**
- `form_id` (int): Filtrar por formulário
- `version_id` (int): Filtrar por versão
- `date_from` (date): Data inicial
- `date_to` (date): Data final
- `user_id` (int): Filtrar por usuário
- `status` (enum): `submitted|draft`
- `page`, `per_page`

**Responses:** `200`, `401`

---

### GET `/submissions/{submissionId}`
Visualiza detalhes completos da submissão.

**Permissões:**
- Manager/Admin: qualquer do tenant
- User: apenas próprias

**Responses:** `200`, `401`, `403`, `404`

---

## Exportação

### POST `/submissions/export`
Exporta submissões para CSV.

**Permissões:** Manager+

**Body:**
```json
{
  "form_id": "integer (required)",
  "version_ids": "array[integer] (optional)",
  "date_from": "date (optional)",
  "date_to": "date (optional)",
  "format": "enum (optional): csv"
}
```

**Response:** Arquivo CSV com header de download

**Responses:** `200`, `401`, `403`, `422`

---

## Auditoria

### GET `/audit-logs`
Lista logs de auditoria.

**Permissões:** Admin

**Query Params:**
- `date_from`, `date_to` (datetime)
- `user_id` (int)
- `action` (string)
- `entity_type` (string)
- `entity_id` (int)
- `page`, `per_page`

**Responses:** `200`, `401`, `403`

---

### GET `/audit-logs/{logId}`
Visualiza detalhes do log.

**Permissões:** Admin

**Responses:** `200`, `401`, `403`, `404`

---

## Modelos de Dados

### User

```json
{
  "id": 10,
  "tenant_id": 1,
  "name": "João Silva",
  "email": "joao@example.com",
  "role": "manager",
  "is_active": true,
  "created_at": "2026-03-22T10:00:00Z",
  "updated_at": "2026-03-22T10:00:00Z"
}
```

**Papéis:**
- `user`: Preencher formulários, ver próprias submissões
- `manager`: user + criar formulários, exportar dados
- `admin`: manager + gerenciar usuários, auditoria

---

### Form

```json
{
  "id": 5,
  "tenant_id": 1,
  "name": "Cadastro de Beneficiários",
  "description": "Formulário para...",
  "is_active": true,
  "created_by": 10,
  "created_at": "2026-03-22T15:00:00Z",
  "updated_at": "2026-03-22T15:00:00Z"
}
```

---

### FormField

```json
{
  "id": 25,
  "form_version_id": 8,
  "label": "Nome Completo",
  "name": "nome_completo",
  "type": "text",
  "is_required": true,
  "options": null,
  "order": 1,
  "created_at": "2026-03-22T15:30:00Z"
}
```

**Tipos de Campo:**
- `text`: Texto curto
- `textarea`: Texto longo
- `number`: Numérico
- `email`: E-mail
- `date`: Data
- `select`: Seleção única (requer options)
- `radio`: Opções radio (requer options)
- `checkbox`: Múltipla escolha (requer options)

---

## Códigos de Status

| Código | Significado |
|--------|-------------|
| `200` | OK - Sucesso |
| `201` | Created - Recurso criado |
| `400` | Bad Request - Erro de negócio |
| `401` | Unauthorized - Não autenticado |
| `403` | Forbidden - Sem permissão |
| `404` | Not Found - Recurso não encontrado |
| `422` | Unprocessable Entity - Erro de validação |
| `500` | Internal Server Error - Erro do servidor |

---

## Exemplos de Uso

### Fluxo Completo: Criar e Preencher Formulário

#### 1. Login

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "manager@example.com",
    "password": "senha123"
  }'
```

**Salvar token retornado:** `TOKEN=5|KGp...`

---

#### 2. Criar Formulário

```bash
curl -X POST http://localhost:8000/api/forms \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Cadastro de Beneficiários",
    "description": "Formulário de registro"
  }'
```

**Response:** `form.id = 5`, `version.id = 8`

---

#### 3. Adicionar Campos

```bash
# Campo 1: Nome
curl -X POST http://localhost:8000/api/forms/5/versions/8/fields \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "label": "Nome Completo",
    "name": "nome_completo",
    "type": "text",
    "is_required": true,
    "order": 1
  }'

# Campo 2: Estado Civil
curl -X POST http://localhost:8000/api/forms/5/versions/8/fields \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
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
    "order": 2
  }'
```

---

#### 4. Publicar Versão

```bash
curl -X POST http://localhost:8000/api/forms/5/versions/8/publish \
  -H "Authorization: Bearer $TOKEN"
```

---

#### 5. Usuário Preenche Formulário

```bash
curl -X POST http://localhost:8000/api/forms/5/submit \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "values": {
      "nome_completo": "Maria Santos",
      "estado_civil": "solteiro"
    }
  }'
```

---

#### 6. Consultar Submissões

```bash
curl -X GET "http://localhost:8000/api/submissions?form_id=5" \
  -H "Authorization: Bearer $TOKEN"
```

---

#### 7. Exportar Dados

```bash
curl -X POST http://localhost:8000/api/submissions/export \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "form_id": 5,
    "format": "csv"
  }' \
  -o cadastro-beneficiarios.csv
```

---

## Rate Limiting

**Limites por IP (padrão Laravel):**
- Autenticação: 5 tentativas / minuto
- API geral: 60 requisições / minuto

**Headers de resposta:**
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1616425200
```

**Resposta ao exceder:**
```json
{
  "success": false,
  "message": "Too Many Attempts.",
  "errors": null
}
```

Status: `429 Too Many Requests`

---

## Ferramentas

### Swagger UI

Visualize e teste a API interativamente:

```bash
# Via Docker
docker run -p 8080:8080 -e SWAGGER_JSON=/api/openapi.yaml \
  -v $(pwd)/docs/api:/api swaggerapi/swagger-ui

# Acesse: http://localhost:8080
```

### Postman Collection

Importe a especificação OpenAPI no Postman:

1. Abra Postman
2. Import → Upload Files
3. Selecione `docs/api/openapi.yaml`
4. Configure variáveis de ambiente:
   - `base_url`: http://localhost:8000/api
   - `token`: (preencher após login)

### Insomnia

Importe diretamente:
- Data → Import Data → From File → `openapi.yaml`

---

## Versionamento da API

**Versão Atual:** v1.0

A API segue [Versionamento Semântico](https://semver.org/):
- **Major:** Mudanças incompatíveis
- **Minor:** Novas funcionalidades compatíveis
- **Patch:** Correções de bugs

Versões futuras serão expostas via URL:
```
/api/v2/...
```

---

## Suporte

- **Documentação:** [docs/api](.)
- **Casos de Uso:** [docs/casos-de-uso](../casos-de-uso)
- **Repositório:** GitHub
- **Issues:** GitHub Issues

---

**Última atualização:** 2026-03-31
**Versão da API:** 1.0.0
