# 📌 Form Engine

![Status](https://img.shields.io/badge/status-v1%20consolidada-brightgreen)
![Laravel](https://img.shields.io/badge/laravel-%5E13.0-red)
![Vue](https://img.shields.io/badge/vue-3.x-brightgreen)
![WCAG](https://img.shields.io/badge/WCAG-2.2%20AA-1d4ed8)
![Docker](https://img.shields.io/badge/docker-enabled-blue)
![CI](https://img.shields.io/badge/CI-GitHub%20Actions-black)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## 📖 Visão Geral

O **Form Engine** é uma plataforma modular para gerenciamento de formulários dinâmicos institucionais, com isolamento multi-tenant por unidade administrativa, versionamento imutável de estruturas, construtor interativo com lógica condicional e layout em cards/etapas, preenchimento com salvamento automático de rascunhos, upload seguro de anexos, trilha de auditoria e exportação CSV.

A **v1** encontra-se consolidada ponta a ponta, contando com backend Laravel 13, frontend SPA em Vue 3 com identidade visual executiva (*Executive Blue*, suporte a tema claro/escuro/sistema e acessibilidade WCAG 2.2 AA), suítes automatizadas de testes no backend e no frontend, e validação automatizada de stack de produção e recuperação de banco no CI.

## 🎯 Objetivos do Projeto

- ✅ Arquitetura em camadas no backend (`Controller → FormRequest → Service → Repository`)
- ✅ Isolamento multi-tenant por unidade administrativa
- ✅ Fichas dinâmicas com 9 tipos de campo, incluindo upload de arquivos/anexos
- ✅ Construtor dinâmico com pré-visualização em tempo real, biblioteca de presets/blocos, regras condicionais e layout em colunas (`100%`, `50%`, `33%`), cards lógicos ou etapas (*Wizard*)
- ✅ Versionamento imutável e publicação segura de estruturas
- ✅ Preenchimento guiado com barra de progresso, rascunhos e salvamento automático (*autosave*)
- ✅ Trilha de auditoria imutável de autenticações e operações críticas
- ✅ Exportação CSV multi-versão com proteção contra injeção de fórmulas
- ✅ Frontend SPA em Vue 3 com guards centralizados, persistência de filtros na URL e testes automatizados
- ✅ Testes de integração/contrato do backend (SQLite e PostgreSQL, incluindo testes de concorrência)
- ✅ Operação de produção containerizada (`ops.ps1`, healthchecks, backup/restore e ensaio automatizado no CI)

## 🏗️ Stack

| Componente | Tecnologia |
|------------|------------|
| **Backend** | Laravel 13 / PHP 8.3 |
| **Frontend** | Vue 3 + Vue Router + Vite |
| **Banco de Dados** | PostgreSQL 17 |
| **Cache / Rate Limit / Queue** | Redis |
| **Autenticação** | Laravel Sanctum (Bearer Token) |
| **Containerização** | Docker + Docker Compose (dev, test, production e restore-test) |
| **CI** | GitHub Actions (Backend SQLite/PostgreSQL, Frontend Build/Test/Audit e Ensaio de Produção) |

### Arquitetura de execução em desenvolvimento

```text
Vue 3 / Vite (:5173)
        │
        │ HTTP / JSON (/api)
        ▼
Nginx (:8000)
        │
        ▼
Laravel API (PHP-FPM)
   │       │
   ▼       ▼
PostgreSQL Redis
```

O `docker-compose.yml` de desenvolvimento sobe `backend`, `nginx`, `postgres`, `redis` e `pgadmin` opcional (profile `tools`). O frontend roda via Vite com proxy `/api` apontando para o Nginx local. Em produção (`docker-compose.production.yml`), o Nginx serve diretamente o bundle compilado da SPA e encaminha `/api` e `/up` ao backend PHP-FPM.

## 📦 Funcionalidades da v1

- 🏢 **Gestão de unidades (Tenants):** cadastro, listagem paginada e ativação/desativação global (`admin-sistema`).
- 👥 **Gestão de usuários por unidade:** criação de contas e ativação/desativação isoladas por tenant (`admin`).
- 🔐 **Autenticação e sessão resiliente:** Bearer Token com Laravel Sanctum, restauração compartilhada de sessão (`/api/auth/me`), sincronização entre abas, tratamento dedicado de expiração (`401`) e indisponibilidade (`session-error`).
- 🧭 **Autorização por papéis:** `user`, `manager`, `admin` e `admin-sistema` validados nos guards do Vue Router e nos middlewares da API.
- 📝 **Catálogo e gestão de formulários:** indicadores executivos (KPIs), busca, ordenação, alternância de visualização (Grade / Tabela) e ativação/desativação.
- 🧩 **Construtor de campos e layout dinâmico:**
  - **9 tipos de campo:** texto curto (`text`), texto longo (`textarea`), número (`number`), e-mail (`email`), data (`date`), lista de seleção (`select`), opção única (`radio`), múltipla escolha (`checkbox`) e arquivo/anexo (`file`, até 10 MB com filtro opcional de extensões).
  - **Organização visual:** agrupamento em seções/cards lógicos, largura configurável em grid de 12 colunas (`100%`, `50%`, `33%`) e escolha entre modo **Cards Contínuos** ou **Etapas Passo a Passo (Wizard)**.
  - **Lógica condicional:** exibição dinâmica de campos baseada em respostas anteriores (`equals`, `not_equals`, `in`, `filled`, `empty`) com proteção contra dependências circulares e limpeza automática de valores ocultos.
  - **Biblioteca de Presets e Blocos:** gaveta lateral com campos brasileiros padronizados (CPF, CNPJ, CEP, UF, telefone etc.) e blocos prontos inseríveis com 1 clique, além de duplicação rápida e reordenação otimista sem recarregar o editor.
- 📌 **Versionamento imutável:** publicação com validação de campos mínimos e criação de novas versões em rascunho copiando a estrutura vigente sem afetar envios históricos.
- ✍️ **Preenchimento e rascunhos:** barra de progresso em tempo real, salvamento manual e automático (*autosave* ao sair do campo ou a cada 30s), retomada na versão original do rascunho, upload/download autenticado de anexos e comprovante pronto para impressão.
- 📥 **Consulta de submissões:** filtros por formulário, versão, respondente, status (`submitted` / `draft`) e período, com estado sincronizado na URL (`useQueryListing`) e retorno contextual (`returnTo`).
- 📤 **Exportação CSV:** seleção de múltiplas versões publicadas, período e separador (`,` ou `;` para Excel PT-BR), com proteção contra CSV Injection e contagem real de linhas exportadas.
- 📊 **Trilha de auditoria:** listagem filtrável e inspeção detalhada de eventos com metadados JSON, ator, IP, User-Agent, verificação de existência da entidade alvo e navegação direta.

## 🗺️ Roadmap e Status

### Fase 1 – Fundação Arquitetural ✅

- [x] Documentação de visão do produto, arquitetura técnica e modelo de dados
- [x] 21 casos de uso especificados e conferidos na matriz de fechamento da v1
- [x] Especificação REST OpenAPI e coleção Postman
- [x] Ambiente Docker para desenvolvimento, testes isolados e produção
- [x] Padrão em camadas (`Controller → FormRequest → Service → Repository`)
- [x] CI automatizado com GitHub Actions

### Fase 2 – Multi-tenant Institucional ✅

- [x] Modelo de unidades administrativas (Tenants)
- [x] Middlewares de isolamento e verificação de tenant ativo
- [x] Gestão de usuários por tenant e autorização por papel
- [x] Testes de contrato e matriz de permissões por rota

### Fase 3 – Fichas Dinâmicas Versionadas ✅

- [x] SPA Vue 3 com identidade visual executiva, tema claro/escuro/auto e WCAG 2.2 AA
- [x] Construtor interativo com preview ao vivo, presets/blocos, regras condicionais e layout em cards/etapas
- [x] Suporte a campos de anexo (`file`) com upload e download autenticados
- [x] Sistema de versionamento imutável e validação dinâmica no backend
- [x] Preenchimento com progresso dinâmico, rascunhos e *autosave*

### Fase 4 – Auditoria, Exportação e Estabilização da v1 ✅

- [x] Trilha de auditoria com inspeção detalhada de metadados e contexto técnico
- [x] Exportação CSV multi-versão com escolha de separador e sanitização de fórmulas
- [x] Guards centralizados no Vue Router e sincronização de sessão entre abas
- [x] Suíte de testes automatizados do frontend (`node --test`: autenticação, listagens, query string, fluxo de formulários, responsividade do construtor e acessibilidade/UX)
- [x] Testes de integração do backend em SQLite e PostgreSQL (incluindo regressão de concorrência)
- [x] Scripts de operação, backup/restore e ensaio de produção no CI (`validate-production.ps1`)
- [ ] Relatórios analíticos e gráficos integrados (evolução pós-v1)

## 🚀 Início Rápido

### Pré-requisitos

- Docker >= 20.10 e Docker Compose >= 2.0
- Node.js >= 22.12
- Git
- PowerShell 7 (`pwsh`) para os scripts operacionais de produção (`ops.ps1` / `validate-production.ps1`)

### Clone

```bash
git clone https://github.com/HeroDestiny/form-engine.git
cd form-engine
```

### Backend e infraestrutura

Configure as variáveis de ambiente:

```bash
cp .env.example .env
```

Suba os serviços e execute as migrations:

```bash
docker compose up -d
docker compose exec backend php artisan migrate
```

Backend/API:

```text
http://localhost:8000
```

> PostgreSQL e Redis não são expostos para o host por padrão.

### Frontend

Em outro terminal:

```bash
cd frontend
npm ci
npm run dev
```

Frontend:

```text
http://localhost:5173
```

O Vite encaminha requisições `/api` para `http://localhost:8000`.

### Windows / PowerShell

Você também pode gerenciar o ambiente de desenvolvimento pelo script utilitário:

```powershell
.\scripts\dev.ps1 up
.\scripts\dev.ps1 migrate
.\scripts\dev.ps1 status
```

## 🔄 Retomar o ambiente local e contas de demonstração

Com o Docker Desktop aberto, execute na raiz do projeto:

```powershell
docker compose up -d
```

Em outro terminal, inicie o frontend:

```powershell
cd frontend
npm.cmd run dev -- --host 127.0.0.1
```

Para criar ou atualizar as contas de demonstração no ambiente local (`APP_ENV=local`):

```powershell
docker compose exec -T backend php artisan db:seed --class=LocalDevelopmentSeeder
```

| Papel | E-mail | Senha | Tenant |
|-------|--------|-------|--------|
| Administrador da unidade | `admin@formengine.local` | `FormEngineLocal123!` | `demo` |
| Administrador do sistema | `admin-sistema@formengine.local` | `FormEngineLocal123!` | `demo` |

> Na tela de login (`/login`), em ambiente de desenvolvimento, há atalhos de preenchimento rápido para ambas as contas de demonstração.

Para parar o frontend, pressione `Ctrl+C` no terminal. Para parar os serviços Docker, execute `docker compose stop`.

## 🧪 Testes e Qualidade

### Backend

Execução da suíte rápida (SQLite em memória via Docker):

```bash
docker compose exec -T backend php artisan test --compact
```

Verificação de estilo (Laravel Pint):

```bash
docker compose exec -T backend ./vendor/bin/pint --test
```

Execução da suíte completa em PostgreSQL descartável (inclui testes de concorrência real entre processos):

```powershell
docker compose build backend
docker compose -p form-engine-tests -f docker-compose.test.yml up --abort-on-container-exit --exit-code-from backend-tests
docker compose -p form-engine-tests -f docker-compose.test.yml down
```

### Frontend

O frontend possui suíte automatizada de testes (61 testes cobrindo acessibilidade WCAG 2.2 AA, paridade de `label`/`id`, componentes SSR, autenticação, guards de rota, sincronização de filtros na URL, lógica condicional, layout em cards/wizard, presets, construtor reativo e *autosave*) além da validação de build:

```bash
cd frontend
npm test
npm run build
```

Também há um script de *smoke test* integrado ponta a ponta contra o backend local (`frontend/scripts/smoke-form-flow.mjs`). Veja [`frontend/README.md`](frontend/README.md) para instruções.

### Operação e Ensaio de Produção

Para validar a stack completa de produção, healthchecks, rate limit, backup/restauração e resiliência do Redis em um ambiente isolado descartável:

```powershell
pwsh ./scripts/validate-production.ps1
```

## ✅ Integração Contínua (CI)

O workflow [`.github/workflows/ci.yml`](.github/workflows/ci.yml) executa em pushes para `main`/`dev` e em Pull Requests com três jobs paralelos:

1. **Backend (`SQLite` e `PostgreSQL`):**
   - Instalação de dependências via Composer;
   - Verificação de estilo com Laravel Pint (`./vendor/bin/pint --test`);
   - Auditoria de segurança das dependências (`composer audit --locked --no-dev`);
   - Execução da suíte PHPUnit em matriz (`phpunit.xml` e `phpunit.postgres.xml`).
2. **Frontend:**
   - Instalação determinística (`npm ci`);
   - Build de produção (`npm run build`);
   - Suíte de testes automatizados (`npm test`);
   - Auditoria de vulnerabilidades (`npm audit --audit-level=high`).
3. **Production images and restore rehearsal:**
   - Executa `./scripts/validate-production.ps1` para validar imagens de produção, migrations, endpoints HTTP/SPA, backup e restauração do PostgreSQL e recuperação de falhas do Redis.

## 📚 Documentação

- [`docs/README.md`](docs/README.md) — índice geral da documentação
- [`docs/visao.md`](docs/visao.md) — visão do produto
- [`docs/arquitetura.md`](docs/arquitetura.md) — decisões e padrões técnicos
- [`docs/modelo_de_dados.md`](docs/modelo_de_dados.md) — modelo de dados
- [`docs/casos-de-uso/`](docs/casos-de-uso/) — 21 casos de uso detalhados
- [`docs/fechamento-funcional-v1.md`](docs/fechamento-funcional-v1.md) — matriz de conferência funcional da v1 e evidências de testes
- [`docs/api/openapi.yaml`](docs/api/openapi.yaml) — especificação OpenAPI
- [`docs/api/README.md`](docs/api/README.md) — guia da API REST
- [`docs/api/postman-collection.json`](docs/api/postman-collection.json) — coleção Postman
- [`docs/testes.md`](docs/testes.md) — guia de execução das suítes de teste e qualidade
- [`docs/operacao-producao.md`](docs/operacao-producao.md) — deploy, saúde, backup, restauração e rollback
- [`frontend/README.md`](frontend/README.md) — arquitetura, fluxos e testes do frontend
- [`scripts/README.md`](scripts/README.md) — scripts de desenvolvimento e operação

## 📂 Estrutura do Repositório

```text
form-engine/
├── backend/                          # API REST em Laravel 13
│   ├── app/
│   │   ├── Http/                     # Controllers, FormRequests e Middlewares
│   │   ├── Models/                   # Models Eloquent
│   │   ├── Repositories/             # Camada de acesso a dados
│   │   └── Services/                 # Regras de negócio, validação dinâmica e auditoria
│   ├── database/                     # Migrations, Factories e Seeders
│   ├── routes/                       # Rotas da API (routes/api.php)
│   ├── tests/                        # Suítes Feature e Unit (SQLite + PostgreSQL)
│   ├── phpunit.xml                   # Configuração PHPUnit (SQLite in-memory)
│   └── phpunit.postgres.xml          # Configuração PHPUnit (PostgreSQL isolado)
├── frontend/                         # SPA em Vue 3 + Vite
│   ├── scripts/                      # Smoke test funcional ponta a ponta
│   ├── src/
│   │   ├── auth/                     # Sessão, token e permissões por papel
│   │   ├── components/               # Layout executivo, tabela, diálogos, campos e badges
│   │   ├── composables/              # useFormFill, useListing, useQueryListing, useTheme, useToast
│   │   ├── router/                   # Rotas e guards centralizados
│   │   ├── utils/                    # Lógica condicional, layout em cards/wizard, presets, CSV
│   │   ├── views/                    # Telas da aplicação
│   │   └── api.js                    # Cliente HTTP autenticado
│   └── tests/                        # Testes automatizados do frontend (node --test)
├── docker/                           # Dockerfiles e configurações Nginx / Ops
├── docs/                             # Documentação funcional, arquitetural e operacional
├── scripts/                          # Scripts PowerShell (dev.ps1, ops.ps1, validate-production.ps1)
├── .github/workflows/                # Pipeline de CI (ci.yml)
├── docker-compose.yml                # Ambiente de desenvolvimento
├── docker-compose.test.yml           # Runner descartável para testes PostgreSQL
├── docker-compose.production.yml     # Stack de produção
├── docker-compose.restore-test.yml   # Ambiente auxiliar para validação de restore
└── README.md
```

## 🔐 Segurança

Consulte o [guia da API](docs/api/README.md) para os contratos e limites, a [arquitetura](docs/arquitetura.md) para as decisões técnicas e o [guia de testes](docs/testes.md) para executar as suítes isoladas.

- **Isolamento Multi-tenant:** todas as rotas protegidas validam tenant ativo e escopo do usuário; recursos de outro tenant retornam `404` para evitar enumeração cruzada.
- **Proteção de Exportação e Anexos:** o CSV aplica *escaping* contra injeção de fórmulas (`=`, `+`, `-`, `@`, tabulação e quebras de linha), e os anexos de submissões são armazenados fora da raiz pública e servidos exclusivamente via endpoint autenticado com verificação de acesso.
- **Operação em Produção:** antes do deploy, execute o preflight e revise domínio/TLS, armazenamento externo de backups, agendamentos e alertas conforme o [guia operacional de produção](docs/operacao-producao.md).

## 🤝 Contribuindo

1. Crie uma branch a partir de `main` ou `dev`.
2. Faça alterações pequenas, coesas e acompanhadas de testes.
3. Execute as suítes do backend (`php artisan test`, `pint --test`) e do frontend (`npm test`, `npm run build`).
4. Atualize a documentação caso altere contratos de API ou casos de uso.
5. Abra um Pull Request e aguarde o CI ficar verde antes do merge.

## 📝 Licença

Este projeto está licenciado sob os termos da licença [MIT](LICENSE).

## 🐛 Issues

Use o rastreador do repositório para reportar bugs e propor melhorias:

https://github.com/HeroDestiny/form-engine/issues
