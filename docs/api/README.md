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

JSON malformado, raiz escalar ou lista não vazia recebe 400 antes de executar a operação. Um objeto vazio continua permitido quando o endpoint aceita entrada vazia; uma lista vazia é aceita como entrada vazia por compatibilidade. Caracteres NUL em textos ou chaves, inclusive em opções aninhadas e filtros, recebem 422, sem remoção silenciosa. Datas de calendário exigem um dia válido entre os anos 0001 e 9999; filtros de auditoria também respeitam essa faixa após conversão para UTC.

Nos detalhes de submissão, `value` preserva o valor original. Select/radio acrescentam `value_label`, checkbox acrescenta `value_labels` e datas acrescentam `value_formatted` em `dd/mm/aaaa`. Códigos como `01` e `1` são distintos nos detalhes e no CSV.

Conflitos dos índices únicos de slug, e-mail no tenant, nome de formulário no tenant e nome de campo na versão retornam 422, inclusive em requisições concorrentes. Erros HTTP da API, incluindo 404/405, seguem o envelope abaixo e preservam cabeçalhos como `Allow`; falhas internas retornam 500 sem detalhes de implementação. O logout registra `LOGOUT` e revoga somente o token atual na mesma transação: se a auditoria falhar, a revogação é revertida.

Os endereços de staging e produção acima são exemplos de configuração; esses ambientes não estão provisionados.

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

As respostas listadas por endpoint destacam os resultados específicos do fluxo. Os erros transversais descritos no início também se aplicam: 400 para JSON inválido, 413 para corpo excessivo, 422 para validação e 500 para falhas internas; operações protegidas exigem autenticação, tenant ativo e permissões correspondentes.

### Resumo por Categoria

| Categoria | Endpoints | Autenticação |
|-----------|-----------|--------------|
| [Autenticação](#autenticação-1) | 3 | Parcial |
| [Tenants](#tenants) | 3 | Admin Sistema |
| [Usuários](#usuários) | 3 | Admin |
| [Formulários](#formulários) | 5 | Conforme operação |
| [Versões](#versões) | 3 | Manager+ |
| [Campos](#campos) | 4 | Manager+ |
| [Submissões](#submissões) | 7 | Autenticado; rascunhos restritos ao proprietário |
| [Exportação](#exportação) | 1 | Manager+ |
| [Auditoria](#auditoria) | 2 | Admin |

**Total:** 31 operações (método + caminho). A tabela resume categorias; permissões de leitura e escrita variam por operação. `admin-sistema` também satisfaz as permissões `admin`, mantendo o isolamento por tenant.

---

## Autenticação

### POST `/auth/login`
Autentica usuário e retorna token. `tenant_slug` é obrigatório quando o e-mail existe em mais de um tenant; `0` é um slug válido. Usuário e tenant devem estar ativos. A expiração padrão é 60 minutos, configurável por `SANCTUM_EXPIRATION`; `expires_in` é informado em segundos.

**Permissões:** Público

**Body:**
```json
{
  "email": "string (required)",
  "password": "string (required, max:72 bytes UTF-8, sem caracteres nulos)",
  "tenant_slug": "string (optional, max:100, letras minúsculas, números e hífen)"
}
```

**Responses:** `200`, `400`, `401`, `403`, `422`, `429`

---

### POST `/auth/logout`
Invalida somente o token atual e registra `LOGOUT` atomicamente. Continua permitido com tenant desativado; falha na auditoria reverte a revogação.

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
  "slug": "string (required, max:100, pattern: ^[a-z0-9-]+$)"
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
  "password": "string (required, min:8 caracteres, max:72 bytes UTF-8, sem caracteres nulos)",
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

### POST `/tenants/{tenantId}/forms`
Cria formulário com a primeira versão draft no tenant informado. Recebe o mesmo body de `POST /forms` e retorna 201 com `data.form` e `data.version`. Exige papel manager/admin (ou admin-sistema) e tenant da URL igual ao do usuário autenticado; esta rota não permite criar em outro tenant.

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

**Responses:** `200`, `400`, `401`, `403`, `422`, `429`

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

**Body:** Mesmo schema de submit, mas sem exigir preenchimento dos campos obrigatórios. `values` pode ser omitido ao criar um rascunho vazio; valores fornecidos ainda são validados quanto a tipo, opções, calendário e limites.

**Responses:** `201`, `401`

---

### PATCH `/forms/{formId}/drafts/{submissionId}`
Atualiza um rascunho do próprio usuário na versão original. O campo `values` é obrigatório e substitui todas as respostas; `{"values": {}}` limpa as respostas. Um body `{}` recebe 422. `version_id` não é permitido. Retorna 200 com `data.draft`. Outro proprietário ou tenant recebe 404.

### POST `/forms/{formId}/drafts/{submissionId}/submit`
Finaliza o rascunho do próprio usuário, preservando ID e versão. Recebe `{"values": {...}}` completo e valida os campos obrigatórios da versão original. Retorna 200 com `data.submission`; validação inválida retorna 422 e acesso a outro proprietário/tenant retorna 404.

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

**Responses:** `200`, `400`, `401`, `403`, `422`, `429`

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
- `checkbox`: Múltipla escolha (requer options). Envie um array de strings com os valores das opções, por exemplo `["opt1", "opt2"]`. O armazenamento utiliza JSON em texto; os detalhes da submissão retornam um array, e o CSV apresenta os labels das escolhas.

### Atualização dos fluxos — 10/10/2026

Criação de submissão/draft aceita `version_id` opcional para fixar a versão publicada exibida ao usuário. Sem esse campo, usa a última versão publicada. Rascunhos existentes podem ser editados com `PATCH /api/forms/{formId}/drafts/{submissionId}` e finalizados com `POST /api/forms/{formId}/drafts/{submissionId}/submit`. Ambos exigem `{"values": {...}}` e proíbem `version_id`; edição substitui todas as respostas, e finalização valida os obrigatórios da versão original. Apenas o proprietário pode escrever.

Exportação aceita `separator` como vírgula ou ponto e vírgula. Auditoria é gerada automaticamente nos fluxos e protegida contra alteração/exclusão. Consulte as decisões de [arquitetura](../arquitetura.md) e o [guia de testes](../testes.md).

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

## Validação de consultas e paginação

Listagens paginadas (`/submissions`, `/audit-logs`, `/admin/tenants` e `/tenants/{tenantId}/users`) aceitam `page` de 1 a 2147483647 e `per_page` de 1 a 100 (padrões: 1 e 20). Valores inválidos retornam 422 com os campos em `errors`; não são convertidos silenciosamente. Uma página após a última retorna lista vazia, total preservado e `from`/`to` nulos.

Submissões combinam filtros por formulário, versão, usuário, status e período. IDs devem ser inteiros positivos de 32 bits; IDs inexistentes ou de outro tenant resultam em lista vazia. Datas exigem `YYYY-MM-DD` e incluem o dia inteiro: `submitted_at` para finais e `created_at` para rascunhos. `user_id` não amplia acesso de usuários finais.

Auditoria aceita datas ou timestamps ISO 8601 com segundos e fuso, normalizados para UTC. Data final sem hora inclui todo o dia; timestamp final é exato. Datas finais anteriores à inicial retornam 422. Listagens cronológicas usam ID como desempate, evitando repetição entre páginas com datas iguais.

Formulários aceitam `search` (até 255 caracteres, sem distinção de caixa), `sort=name|created_at`, `order=asc|desc` e `scope=available|all`. O escopo `all` só amplia resultados para manager/admin/admin-sistema. A listagem mantém coleção e total.

## Limites de entrada e exportação

As requisições da API têm limite de corpo de 2 MiB, com resposta 413 em JSON no middleware e no Nginx. Validações de campos/estruturas continuam retornando 422, sem alterações parciais.

| Entrada | Limite |
|---|---|
| Texto | 1.000 caracteres |
| Texto longo | 10.000 caracteres |
| E-mail | 254 caracteres |
| Número | -10^15 a 10^15 |
| Data de campo/filtro de exportação | Dia válido em `YYYY-MM-DD`, anos 0001 a 9999 |
| Campos por versão / valores por envio | 100 |
| Nome técnico do campo | 100 caracteres |
| Opções por campo / escolhas de checkbox | 100, sem duplicação |
| Valor e rótulo de opção | 255 caracteres |
| Senha recebida | Até 72 bytes UTF-8, sem NUL; criação exige ao menos 8 caracteres |
| Slug de login | 100 caracteres |

Texto conta caracteres, não bytes. Limites também se aplicam aos rascunhos, sua atualização e finalização. O limite de campos é verificado sob lock da versão; editar um campo existente continua permitido. Dados históricos não são truncados nem excluídos.

CSV é limitado a 10.000 registros, 100 versões, 500 colunas (incluindo 5 fixas) e 25 MiB. `EXPORT_MAX_ROWS` e `EXPORT_MAX_BYTES` permitem reduzir esses tetos; valores configurados são limitados a 1..10000 e 1..26214400. Exceder qualquer teto retorna 422 com orientação para restringir filtros, antes do download e sem log de sucesso.

A seleção inicial lê apenas IDs e IDs de versões, até o limite + 1. Esses IDs fixam os participantes e a ordem da exportação; novas submissões não entram durante a geração. Valores/usuários são lidos em lotes de 25. Cabeçalhos carregam apenas metadados dos campos, sem opções; um cache de até dois esquemas completos permite mapear opções sem reter todos os esquemas em memória. O CSV é escrito em arquivo temporário do sistema e entregue por `fpassthru`, sem formar uma string com o arquivo inteiro. O arquivo fecha após a transmissão ou em erro; falha ao auditar também libera o recurso. `EXPORT_DATA` registra linhas e bytes efetivamente preparados, não confirma que o cliente terminou de baixar. Não há snapshot transacional de alterações administrativas em nomes de usuários durante a exportação.

Células textuais com prefixos de fórmula (`=`, `+`, `-`, `@` e variantes de largura completa), controles iniciais ou espaços seguidos desses prefixos recebem tabulação inicial dentro da célula delimitada. Isso inclui rótulos, nomes/e-mails, valores e labels de opções; o banco permanece intacto. A tabulação fica no dado exportado, inclusive para textos negativos, e deve ser considerada por consumidores programáticos. A proteção segue a orientação para visualização no Excel da [OWASP](https://community.owasp.org/attacks/CSV_Injection); os testes verificam o CSV serializado, não execução em Excel/LibreOffice. Não há garantia universal para todos os programas ou para arquivos editados e regravados pelo usuário.

## Rate Limiting

O login permite 5 tentativas por minuto por IP e por combinação de e-mail/tenant,
incluindo sucessos e entradas inválidas. Ambos os limites usam o cache compartilhado
(Redis no Docker) e são configuráveis por `LOGIN_ATTEMPTS_PER_MINUTE`.
O bloqueio retorna 429, `Retry-After` em segundos e registra `LOGIN_THROTTLED` sem credenciais.
Não há limite geral de 60 requisições aplicado nesta etapa.

**Headers de resposta:**
```
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1616425200
Retry-After: 60
```

**Resposta ao exceder:**
```json
{
  "success": false,
  "data": null,
  "message": "Muitas tentativas. Tente novamente em 60 segundos.",
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

**Última atualização:** 2026-10-17
**Versão da API:** 1.0.0
