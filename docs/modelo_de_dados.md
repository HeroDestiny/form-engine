# Modelo de dados

O banco de execução é PostgreSQL. SQLite em memória é usado em parte da suíte de testes. As definições executáveis estão nas migrations em `backend/database/migrations`; todas as tabelas de domínio abaixo possuem `id`, `created_at` e `updated_at`.

## Entidades

| Tabela | Dados principais | Vínculos e restrições |
|---|---|---|
| `tenants` | `name`, `slug`, `is_active` | Slug único; unidade de isolamento. |
| `users` | `tenant_id`, `name`, `email`, `password`, `role`, `is_active`, `email_verified_at`, `remember_token` | Um tenant por usuário; e-mail único no tenant. Papéis: user, manager, admin, admin-sistema. |
| `forms` | `tenant_id`, `name`, `description`, `is_active`, `created_by` | Nome único no tenant; autor vinculado a users. |
| `form_versions` | `form_id`, `version_number`, `is_published`, `published_at`, `created_by` | Número único por formulário; índice parcial limita a uma versão não publicada por formulário. |
| `form_fields` | `form_version_id`, `label`, `name`, `type`, `is_required`, `options`, `order` | Nome técnico único na versão; options é JSON para escolhas. |
| `form_submissions` | `tenant_id`, `form_version_id`, `status`, `submitted_by`, `submitted_at` | Status draft/submitted; submitted_at nulo no draft; versão e proprietário preservados na finalização. |
| `form_submission_values` | `form_submission_id`, `form_field_id`, `value` | Um registro por resposta de campo criada pela aplicação; value é texto anulável e checkbox é codificado como JSON. |
| `audit_logs` | `tenant_id`, `user_id`, `action`, `entity_type`, `entity_id`, `metadata`, `ip_address`, `user_agent` | Tenant/ator/entidade podem ser nulos; metadata é JSON; alteração e exclusão bloqueadas. |

Os campos suportam text, textarea, number, email, date, select, radio e checkbox. Submissões armazenam respostas por campo, em vez de um único JSON com o formulário completo. A aplicação reconstrói os tipos e labels para detalhes e exportação.

## Relacionamentos

```text
Tenant → Users
Tenant → Forms → Form Versions → Form Fields
Tenant → Form Submissions → Submission Values
Form Version → Form Submissions
User → Form Submissions (submitted_by)
Form Field → Submission Values
Tenant / User → Audit Logs
```

Versões e campos herdam o tenant pelo formulário. A aplicação confere esses vínculos; não há banco separado por tenant. Novas versões não alteram a estrutura vinculada às submissões existentes.

## Integridade e auditoria

As unicidades de negócio são protegidas por índices no banco. A aplicação combina transações e bloqueios para preservar versão draft única, publicação e finalização concorrentes. Versões publicadas são imutáveis nos fluxos da aplicação.

Logs possuem índices por tenant/data e por ação/tipo de entidade. Triggers específicas para SQLite/PostgreSQL impedem UPDATE e DELETE. Embora os vínculos de auditoria tenham `nullOnDelete`, as triggers impedem essa mutação indireta; uma futura exclusão de tenant/usuário precisa definir retenção compatível. A v1 expõe desativação desses recursos, sem endpoints de exclusão.

## Tabelas de infraestrutura

Sanctum usa `personal_access_tokens`, incluindo expiração. Migrations do Laravel também criam tabelas de sessões, recuperação de senha, cache e jobs. Sua existência não significa que haja endpoints públicos de recuperação de senha ou workers de fila: cache é Redis no Docker e filas usam execução síncrona.
