#  Modelo de Dados Inicial — Form Engine (v1)

---

# 1️⃣ Entidades Principais

## 🏢 Tenant (Unidade Administrativa)

Representa o isolamento institucional.

```text
tenants
- id (PK)
- name
- slug
- is_active
- created_at
- updated_at
```

### Regras:

* Todos os dados do sistema devem pertencer a um tenant.
* Slug será usado para identificação contextual futura (ex: subdomínio).

---

## 👤 User

Usuário do sistema vinculado a um tenant.

```text
users
- id (PK)
- tenant_id (FK → tenants.id)
- name
- email
- password
- role (admin | manager | user)
- is_active
- created_at
- updated_at
```

### Regras:

* Usuário pertence a apenas um tenant na v1.
* Isolamento é garantido via tenant_id.

---

## 📝 Form

Representa uma ficha lógica (entidade raiz).

```text
forms
- id (PK)
- tenant_id (FK)
- name
- description
- is_active
- created_by (FK → users.id)
- created_at
- updated_at
```

### Regras:

* Um form pode ter múltiplas versões.
* Não armazena campos diretamente.

---

## 📌 Form Version

Versão imutável da estrutura de uma ficha.

```text
form_versions
- id (PK)
- form_id (FK → forms.id)
- version_number
- is_published
- published_at
- created_by (FK → users.id)
- created_at
```

### Regras:

* Versão publicada não pode ser alterada.
* Cada resposta sempre pertence a uma versão específica.

---

## 🧩 Form Field

Campos pertencentes a uma versão.

```text
form_fields
- id (PK)
- form_version_id (FK)
- label
- name
- type (text, number, date, select, checkbox, etc.)
- is_required
- options (JSON nullable)
- order
- created_at
```

### Regras:

* Pertence sempre a uma versão.
* Nunca pertence diretamente ao form.
* Options será JSON para tipos como select.

---

## 📥 Form Submission

Resposta preenchida por um usuário.

```text
form_submissions
- id (PK)
- tenant_id (FK)
- form_version_id (FK)
- submitted_by (FK → users.id)
- submitted_at
- created_at
```

### Regras:

* Sempre vinculada a uma versão específica.
* Não muda se nova versão for criada.

---

## 📦 Form Submission Value

Valores individuais por campo.

```text
form_submission_values
- id (PK)
- form_submission_id (FK)
- form_field_id (FK)
- value (TEXT)
- created_at
```

### Regras:

* Estrutura flexível.
* Permite armazenar qualquer tipo como string.
* Conversão tipada pode ocorrer no Service.

---

## 📊 Audit Log

Auditoria básica.

```text
audit_logs
- id (PK)
- tenant_id (FK)
- user_id (FK)
- action (CREATE_FORM, UPDATE_FORM, SUBMIT_FORM, etc.)
- entity_type (form, submission, user, etc.)
- entity_id
- metadata (JSON nullable)
- created_at
```

### Regras:

* Não depende de pacote externo.
* Simples e estruturado.

---

# 2️⃣ Relacionamentos

Resumo:

```
Tenant
 ├── Users
 ├── Forms
 ├── Form Submissions
 └── Audit Logs

Form
 └── Form Versions
      └── Form Fields

Form Version
 └── Form Submissions
      └── Submission Values
```

---

# 3️⃣ Princípios do Modelo

✔️ Isolamento lógico via tenant_id
✔️ Versionamento imutável
✔️ Estrutura flexível de campos
✔️ Submissão preserva histórico
✔️ Auditoria independente

---

# 4️⃣ Índices Recomendados

* users.tenant_id
* forms.tenant_id
* form_submissions.tenant_id
* form_versions.form_id
* form_fields.form_version_id
* audit_logs.tenant_id

Esses garantem performance no isolamento.

---

# 5️⃣ Decisões Arquiteturais Importantes

* Não usar banco por tenant (v1)
* Não usar JSON para guardar toda submissão (estrutura normalizada)
* Não permitir edição de versão publicada
* Não implementar hierarquia complexa ainda


Qual próximo passo?
