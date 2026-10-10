# Documento de Arquitetura — Form Engine

## 1. Introdução

Este documento descreve a arquitetura técnica do **Form Engine**, uma API modular para gerenciamento de formulários dinâmicos com isolamento multi-tenant, versionamento de estruturas e auditoria básica.

O foco principal da arquitetura é:

- Separação clara de responsabilidades
- Baixo acoplamento
- Alta testabilidade
- Evolução incremental
- Preparação para integração futura com SPA (Vue)

---

# 2. Visão Arquitetural Geral

O sistema adota arquitetura **API-first**, com backend independente e preparado para futura integração com frontend SPA.

## 2.1 Arquitetura Macro

```

  Cliente (SPA / HTTP Client)
               │
               ▼
┌────────────────────────────┐
│           API REST         │
│          (Laravel)         │
└──────────────┬─────────────┘
               │
        ┌──────▼──────┐
        │ PostgreSQL  │
        └─────────────┘

```

Fases iniciais concentram-se apenas no backend.

---

# 3. Estilo Arquitetural

O backend utiliza:

- Arquitetura em camadas (Layered Architecture)
- Repository Pattern
- Service Layer
- Dependency Injection
- API REST baseada em JSON

---

# 4. Arquitetura em Camadas

O backend segue o fluxo:

```

Controller
   ↓
Service
   ↓
Repository
   ↓
Model (Eloquent)

```

## 4.1 Controller

Responsabilidades:

- Receber requisições HTTP
- Validar entrada (Form Requests)
- Delegar execução ao Service
- Retornar resposta padronizada
- Definir códigos HTTP

Não deve conter:

- Regra de negócio
- Acesso direto ao banco

---

## 4.2 Service

Responsabilidades:

- Implementar regra de negócio
- Coordenar repositórios
- Validar regras institucionais
- Lançar exceções de domínio

Não deve conter:

- Código HTTP
- Lógica de persistência detalhada

---

## 4.3 Repository

Responsabilidades:

- Abstração da camada de dados
- Operações de persistência
- Consultas específicas

Deve:

- Implementar interfaces
- Ser facilmente mockável (mesmo que foco seja integração)

---

## 4.4 Model

Responsabilidades:

- Representação da tabela
- Relacionamentos
- Configuração de atributos

Não deve conter regra de negócio complexa.

---

# 5. Organização de Pastas

Estrutura do backend:

```

backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Requests/
│   │   ├── Resources/
│   │   └── Middleware/
│   ├── Services/
│   ├── Repositories/
│   ├── Exceptions/
│   └── Models/
├── database/
│   ├── migrations/
│   └── seeders/
├── routes/
│   └── api.php
└── tests/
├── Feature/
└── Unit/

```

---

# 6. Multi-Tenancy (Estratégia v1)

## 6.1 Modelo

Isolamento por unidade administrativa via:

- Coluna `tenant_id` nas tabelas principais
- Middleware para identificar tenant ativo
- Escopos globais para garantir isolamento

## 6.2 Estratégia

- Não haverá banco por tenant na v1
- Isolamento será lógico (row-level isolation)
- Testes garantirão segregação correta

---

# 7. Versionamento de Fichas

Estratégia:

- Cada ficha possui múltiplas versões
- Versões são imutáveis após publicação
- Respostas sempre associadas a uma versão específica

Modelo simplificado:

```

forms
└── form_versions
└── form_fields

````

---

# 8. Auditoria

Auditoria básica incluirá:

- Ação realizada
- Usuário responsável
- Data/hora
- Entidade afetada
- Tenant relacionado

Implementação:

- Listener ou Service dedicado
- Persistência estruturada
- Sem solução externa (ex: Elastic) na v1

---

# 9. Padrão de Resposta da API

Estrutura padrão JSON:

```json
{
  "success": true,
  "data": {},
  "message": "Operação realizada com sucesso",
  "errors": null
}
````

Erros seguirão padrão:

```json
{
  "success": false,
  "data": null,
  "message": "Erro de validação",
  "errors": {
    "campo": ["mensagem"]
  }
}
```

---

# 10. Tratamento de Exceções

Será implementado:

* BusinessException
* NotFoundException
* Handler global padronizado
* Nenhum stack trace exposto em produção

---

# 11. Estratégia de Testes

Foco principal:

* Testes de integração (Feature)
* Testes HTTP
* Testes de isolamento multi-tenant

Não será priorizado:

* Mock excessivo
* Teste de detalhe interno irrelevante

Cada nova funcionalidade deve:

* Possuir teste de sucesso
* Possuir teste de erro
* Validar persistência

---

# 12. Banco de Dados

Inicialmente:

* PostgreSQL local
* Migrações versionadas
* Seeds estruturais

Princípios:

* Integridade referencial
* Índices para campos críticos
* Chaves estrangeiras explícitas

---

# 13. Segurança (v1)

Inclui:

* Autenticação básica (Sanctum)
* Middleware de proteção
* Validação via Form Requests
* Sanitização de entrada

Não inclui:

* RBAC avançado
* OAuth externo
* SSO institucional

---

# 14. Escalabilidade e Evolução

A arquitetura permite:

* Introdução futura de cache Redis
* Containerização com Docker
* Separação em serviços se necessário
* Inclusão de frontend SPA

Nada na v1 impede evolução futura.

---

# 15. Decisões Arquiteturais Importantes

1. Backend-first
2. Arquitetura em camadas clássica
3. Multi-tenant lógico (não físico)
4. Foco em testes de integração
5. Infraestrutura simplificada nas fases iniciais

---

# 16. Riscos Identificados

* Crescimento excessivo de escopo
* Overengineering precoce
* Complexidade excessiva no multi-tenant
* Desalinhamento entre domínio e estrutura

Mitigação:

* Escopo congelado
* Fases bem definidas
* Testes como segurança
* Revisão periódica de arquitetura

---

# 17. Critério de Qualidade Arquitetural

A arquitetura será considerada adequada quando:

* Controllers forem finos
* Services centralizarem regra
* Repositories abstraírem persistência
* Testes garantirem comportamento
* Código for explicável em poucos minutos

---

# 18. Consolidação das Camadas — 2026-10-10

Os controllers da API delegam consultas e persistência a Services e Repositories. As entradas dos fluxos de escrita são validadas por Form Requests, e a autorização por papel permanece nos middlewares das rotas.

Responsabilidades consolidadas:

* `AuthService`: seleção da conta, verificação de credenciais e condições de acesso; `UserRepository` executa consultas e operações com tokens.
* `FormVersionService`: decisão de criar nova versão e publicar; `FormVersionRepository` realiza persistência.
* `FormFieldService`: proteção de versões publicadas; `FormFieldRequest` valida os atributos dos campos.
* `SubmissionService`: contexto de preenchimento, acesso às submissões e coordenação dos repositórios; `SubmissionCsvService` gera o conteúdo da exportação, e o controller realiza o download HTTP.
* `FormSubmissionRepository`: consultas por tenant, filtros, paginação e carregamento antecipado dos autores para evitar consultas por registro.
* `BusinessException` e `NotFoundException`: erros esperados do domínio; `bootstrap/app.php` converte esses erros e `ValidationException` para o envelope JSON da API e os códigos HTTP existentes.

Nos fluxos de campos e preenchimento, os Form Requests resolvem o contexto por meio dos Services antes de validar o payload. Isso preserva a resposta 404 para recursos de outro tenant, inclusive quando a entrada é inválida. Um rascunho sem versão publicada é recusado com 422, sem tentar persistir uma referência inválida.

A suíte possui 75 testes, incluindo regressões para isolamento antes da validação e payloads de submissão inválidos. As rotas e os formatos de sucesso existentes foram preservados.

O `phpunit.xml` força as variáveis de teste em `env` e `server`, inclusive SQLite em memória, para impedir que a configuração PostgreSQL do Docker prevaleça pela precedência de `$_SERVER`. `tests/TestCase.php` verifica o ambiente e a conexão antes de executar as migrations dos testes e interrompe a suíte se o isolamento não estiver correto.

Pendências funcionais identificadas na revisão seguem separadas desta consolidação: geração automática de auditoria, validação completa de e-mail e checkbox, evolução do ciclo de rascunhos, bloqueio de tokens de tenants desativados e ajustes na exportação CSV. O isolamento atual utiliza filtros explícitos nos Repositories; os escopos globais descritos na estratégia original ainda não foram adotados.

# 19. Consolidação de Acesso e Valores Dinâmicos — 2026-10-10

O middleware `EnsureActiveTenant`, aplicado após `auth:sanctum`, verifica o status atual do tenant nas APIs de dados e em `/api/auth/me`. Tenant desativado recebe 403 mesmo com token previamente emitido. Logout permanece disponível para revogação. A reativação restabelece o acesso com tokens ainda válidos. As sessões de usuários individualmente desativados mantêm a política do UC04 RN07, sem revogação imediata nesta etapa.

`SubmissionService` valida o formato de email, permite valores opcionais nulos e exige listas de opções válidas para checkbox. No draft, os campos podem ser omitidos; quando preenchidos, precisam respeitar seus tipos e opções. `SubmissionFieldValue` centraliza a serialização de checkbox como JSON em texto e a recuperação como array, com leitura compatível com valores antigos separados por vírgula. `SubmissionCsvService` converte as escolhas de checkbox em labels para exportação.

O frontend envia checkbox como array conforme o contrato do UC13. A suíte passou com 94 testes e 392 assertions; o build do frontend passou. Permanecem pendentes auditoria automática, evolução de rascunhos, comprovação completa do histórico, filtros/paginação, formatação geral do CSV e testes com PostgreSQL exclusivo.

# 20. Consolidação Funcional e PostgreSQL — 2026-10-10

A sequência seguinte implementou auditoria automática, edição/finalização de drafts existentes, preservação de versão explícita no preenchimento, bloqueios transacionais em versionamento/campos e CSV com escaping e colunas combinadas por nome. As migrations acrescentam unicidade de draft de versão e imutabilidade dos logs no banco. O CI valida os mesmos contratos em SQLite e PostgreSQL exclusivo, incluindo um teste de bloqueio com duas conexões no perfil PostgreSQL.

Contratos, comandos, limites e pendências atuais estão em [Consolidação funcional do backend](consolidacao-backend.md). As pendências listadas nas seções anteriores representam o estado das etapas anteriores e devem ser lidas junto a esta atualização.
