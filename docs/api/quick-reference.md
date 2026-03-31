# Quick Reference - Form Engine API

Referência rápida dos endpoints mais usados da API.

---

## 🔑 Autenticação

### Login
```bash
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "senha123"
}
```

**Response:** Token no campo `data.token`

---

## 📝 Criar Formulário (Fluxo Completo)

### 1. Criar Formulário
```bash
POST /api/forms
{
  "name": "Meu Formulário",
  "description": "Descrição opcional"
}
```

**Response:** `form.id` e `version.id` (draft)

---

### 2. Adicionar Campo
```bash
POST /api/forms/{formId}/versions/{versionId}/fields
{
  "label": "Nome",
  "name": "nome",
  "type": "text",
  "is_required": true,
  "order": 1
}
```

**Tipos:** `text`, `textarea`, `number`, `email`, `date`, `select`, `radio`, `checkbox`

---

### 3. Adicionar Campo Select
```bash
POST /api/forms/{formId}/versions/{versionId}/fields
{
  "label": "Estado",
  "name": "estado",
  "type": "select",
  "is_required": true,
  "options": [
    {"value": "sp", "label": "São Paulo"},
    {"value": "rj", "label": "Rio de Janeiro"}
  ],
  "order": 2
}
```

---

### 4. Publicar Versão
```bash
POST /api/forms/{formId}/versions/{versionId}/publish
```

---

## 👤 Preencher Formulário

### Submeter
```bash
POST /api/forms/{formId}/submit
{
  "values": {
    "nome": "João Silva",
    "estado": "sp"
  }
}
```

### Salvar Rascunho
```bash
POST /api/forms/{formId}/drafts
{
  "values": {
    "nome": "João Silva"
  }
}
```

---

## 📊 Consultar Dados

### Listar Submissões
```bash
GET /api/submissions?form_id=5&date_from=2026-03-01&date_to=2026-03-31
```

### Visualizar Submissão
```bash
GET /api/submissions/{submissionId}
```

### Exportar CSV
```bash
POST /api/submissions/export
{
  "form_id": 5,
  "date_from": "2026-03-01",
  "date_to": "2026-03-31"
}
```

---

## 👥 Usuários

### Criar Usuário
```bash
POST /api/tenants/{tenantId}/users
{
  "name": "Maria Santos",
  "email": "maria@example.com",
  "password": "senha123",
  "role": "manager"
}
```

**Roles:** `user`, `manager`, `admin`

---

## 🔍 Auditoria

### Consultar Logs
```bash
GET /api/audit-logs?action=SUBMIT_FORM&date_from=2026-03-01
```

---

## 📋 Cheat Sheet - cURL

### Autenticar e Salvar Token
```bash
# Login e salvar token
response=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"senha123"}')

token=$(echo $response | jq -r '.data.token')
echo "Token: $token"

# Usar token em requisições
curl -H "Authorization: Bearer $token" \
  http://localhost:8000/api/forms
```

---

### Criar Formulário Completo
```bash
# 1. Criar formulário
form_response=$(curl -s -X POST http://localhost:8000/api/forms \
  -H "Authorization: Bearer $token" \
  -H "Content-Type: application/json" \
  -d '{"name":"Cadastro","description":"Formulário teste"}')

form_id=$(echo $form_response | jq -r '.data.form.id')
version_id=$(echo $form_response | jq -r '.data.version.id')

# 2. Adicionar campo
curl -X POST http://localhost:8000/api/forms/$form_id/versions/$version_id/fields \
  -H "Authorization: Bearer $token" \
  -H "Content-Type: application/json" \
  -d '{
    "label":"Nome",
    "name":"nome",
    "type":"text",
    "is_required":true,
    "order":1
  }'

# 3. Publicar
curl -X POST http://localhost:8000/api/forms/$form_id/versions/$version_id/publish \
  -H "Authorization: Bearer $token"
```

---

### Submeter Formulário
```bash
curl -X POST http://localhost:8000/api/forms/$form_id/submit \
  -H "Authorization: Bearer $token" \
  -H "Content-Type: application/json" \
  -d '{
    "values": {
      "nome": "João Silva"
    }
  }'
```

---

### Exportar Dados
```bash
curl -X POST http://localhost:8000/api/submissions/export \
  -H "Authorization: Bearer $token" \
  -H "Content-Type: application/json" \
  -d '{
    "form_id": '$form_id',
    "format": "csv"
  }' \
  -o export.csv
```

---

## 🎯 Endpoints por Permissão

### User (👤)
- `GET /forms` - Listar formulários
- `POST /forms/{id}/submit` - Submeter
- `POST /forms/{id}/drafts` - Salvar rascunho
- `GET /submissions` - Ver próprias submissões

### Manager (👔)
- Tudo de User +
- `POST /forms` - Criar formulário
- `POST /forms/{id}/versions/{vid}/fields` - Adicionar campos
- `POST /forms/{id}/versions/{vid}/publish` - Publicar
- `GET /submissions` - Ver todas do tenant
- `POST /submissions/export` - Exportar

### Admin (⚡)
- Tudo de Manager +
- `POST /tenants/{id}/users` - Criar usuário
- `PATCH /tenants/{id}/users/{uid}/status` - Ativar/desativar
- `GET /audit-logs` - Ver auditoria

---

## ⚡ Atalhos Úteis

### Paginação Padrão
```
?page=1&per_page=20
```

### Filtros Comuns
```
?form_id=5
&date_from=2026-03-01
&date_to=2026-03-31
&user_id=10
&status=submitted
```

### Ordenação
```
?sort=name&order=asc
?sort=created_at&order=desc
```

---

## 🚨 Códigos de Erro Comuns

| Código | Causa | Solução |
|--------|-------|---------|
| 401 | Token ausente/inválido | Fazer login novamente |
| 403 | Sem permissão | Verificar papel do usuário |
| 404 | Recurso não encontrado | Verificar ID ou tenant |
| 422 | Validação falhou | Verificar campos obrigatórios |

---

## 📦 Respostas Padronizadas

### Sucesso
```json
{
  "success": true,
  "data": {...},
  "message": "...",
  "errors": null
}
```

### Erro de Validação
```json
{
  "success": false,
  "data": null,
  "message": "Erro de validação",
  "errors": {
    "email": ["O e-mail é obrigatório"],
    "password": ["A senha deve ter no mínimo 8 caracteres"]
  }
}
```

### Erro Genérico
```json
{
  "success": false,
  "data": null,
  "message": "Recurso não encontrado",
  "errors": null
}
```

---

## 🔗 Links Úteis

- **Documentação Completa:** [README.md](./README.md)
- **Especificação OpenAPI:** [openapi.yaml](./openapi.yaml)
- **Collection Postman:** [postman-collection.json](./postman-collection.json)
- **Casos de Uso:** [../casos-de-uso/](../casos-de-uso/)

---

**Última atualização:** 2026-03-31
