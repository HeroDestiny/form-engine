# 📌 Form Engine

![Status](https://img.shields.io/badge/status-v1%20em%20estabiliza%C3%A7%C3%A3o-blue)
![Laravel](https://img.shields.io/badge/laravel-%5E13.0-red)
![Vue](https://img.shields.io/badge/vue-3.x-brightgreen)
![Docker](https://img.shields.io/badge/docker-enabled-blue)
![CI](https://img.shields.io/badge/CI-GitHub%20Actions-black)

## 📖 Visão Geral

O **Form Engine** é um sistema modular para gerenciamento de formulários dinâmicos, com isolamento multi-tenant por unidade administrativa, versionamento de estruturas, preenchimento, auditoria e exportação de dados.

A v1 já possui backend Laravel e frontend Vue 3 funcionais. O projeto está em fase de estabilização, com foco em testes, automação de qualidade, documentação e ajustes de UX.

## 🎯 Objetivos do Projeto

- ✅ Arquitetura em camadas (Controller → Service → Repository)
- ✅ Isolamento multi-tenant por unidade administrativa
- ✅ Fichas dinâmicas com campos customizáveis
- ✅ Versionamento e publicação de estruturas
- ✅ Preenchimento e rascunhos
- ✅ Auditoria de ações
- ✅ Exportação CSV
- ✅ Frontend SPA em Vue 3
- ✅ Testes de integração/contrato do backend
- ✅ CI com GitHub Actions

## 🏗️ Stack

| Componente | Tecnologia |
|------------|------------|
| **Backend** | Laravel 13 / PHP 8.3 |
| **Frontend** | Vue 3 + Vue Router + Vite |
| **Banco de Dados** | PostgreSQL |
| **Cache / Queue** | Redis |
| **Autenticação** | Laravel Sanctum |
| **Containerização** | Docker + Docker Compose (backend/infra) |
| **CI** | GitHub Actions |

### Arquitetura de execução em desenvolvimento

```text
Vue 3 / Vite (:5173)
        │
        │ HTTP / JSON
        ▼
Nginx (:8000)
        │
        ▼
Laravel API
   │       │
   ▼       ▼
PostgreSQL Redis
```

O `docker-compose.yml` atual sobe backend, Nginx, PostgreSQL, Redis e pgAdmin opcional. O frontend é executado separadamente via Vite durante o desenvolvimento.

## 📦 Funcionalidades da v1

- 🏢 Gestão de tenants
- 👥 Gestão de usuários por tenant
- 🔐 Autenticação Bearer Token com Laravel Sanctum
- 🧭 Autorização por papéis (`user`, `manager`, `admin`, `admin-sistema`)
- 📝 Criação e gestão de formulários
- 🧩 Campos dinâmicos
- 📌 Versionamento e publicação de formulários
- ✍️ Preenchimento de formulários
- 💾 Salvamento de rascunhos
- 📥 Consulta de submissões
- 📤 Exportação CSV por formulário
- 📊 Auditoria de ações
- 🖥️ SPA Vue 3 cobrindo os fluxos principais

## 🗺️ Roadmap

### Fase 1 – Fundação Arquitetural ✅

- [x] Documentação de visão do produto
- [x] Documentação de arquitetura técnica
- [x] Modelo de dados
- [x] 21 casos de uso especificados
- [x] Especificação REST com 29 endpoints
- [x] Ambiente Docker para backend/infra
- [x] Estrutura base do backend
- [x] Padrão de camadas
- [x] CI com GitHub Actions

### Fase 2 – Multi-tenant Institucional ✅

- [x] Modelo de unidades administrativas
- [x] Middleware de isolamento
- [x] Gestão de usuários por tenant
- [x] Autorização por papel
- [x] Testes de isolamento

### Fase 3 – Fichas Dinâmicas Versionadas ✅

- [x] SPA Vue 3
- [x] Comunicação frontend ↔ API REST
- [x] Renderização dinâmica de formulários
- [x] Sistema de versionamento
- [x] Validação dinâmica no backend
- [x] Interface de criação e edição de formulários/campos

### Fase 4 – Auditoria e Exportação 🟡

- [x] Log de auditoria
- [x] Exportação CSV
- [x] API de consulta
- [ ] Relatórios básicos

### Estabilização da v1

- [x] CI de backend e frontend
- [ ] Testes automatizados do frontend
- [ ] Guards centralizados no Vue Router
- [ ] Testes de integração periódicos com PostgreSQL
- [ ] Consolidação do padrão Service/Repository em controllers maiores
- [ ] Pipeline de deploy

## 🚀 Início Rápido

### Pré-requisitos

- Docker >= 20.10
- Docker Compose >= 2.0
- Node.js >= 22
- Git

### Clone

```bash
git clone https://github.com/HeroDestiny/form-engine.git
cd form-engine
```

### Backend e infraestrutura

Configure as variáveis de ambiente conforme necessário:

```bash
cp .env.example .env
```

Suba os serviços:

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

Também é possível usar os scripts do projeto:

```powershell
.\scripts\dev.ps1 up
.\scripts\dev.ps1 migrate
.\scripts\dev.ps1 test
```

## 🧪 Testes

### Backend

```bash
cd backend
php artisan test
```

Ou via Docker:

```bash
docker compose exec backend php artisan test
```

Verificação de estilo:

```bash
cd backend
./vendor/bin/pint --test
```

Os testes Feature incluem contratos para autenticação, autorização, formulários, fluxo de versões/campos, usuários de tenant, consultas e auditoria.

### Frontend

O frontend possui build de produção validado pelo CI:

```bash
cd frontend
npm ci
npm run build
```

Testes automatizados do frontend ainda são uma pendência da fase de estabilização.

## ✅ Integração Contínua

O workflow `.github/workflows/ci.yml` executa em pull requests e pushes para `main`/`dev`:

- instalação das dependências PHP;
- Laravel Pint em modo de verificação;
- suíte de testes do backend;
- instalação determinística das dependências frontend;
- build de produção do Vue/Vite.

## 📚 Documentação

### Documentos principais

- [`docs/visao.md`](docs/visao.md) — visão do produto
- [`docs/arquitetura.md`](docs/arquitetura.md) — decisões e padrões técnicos
- [`docs/modelo_de_dados.md`](docs/modelo_de_dados.md) — modelo de dados
- [`docs/casos-de-uso/`](docs/casos-de-uso/) — 21 casos de uso
- [`docs/api/openapi.yaml`](docs/api/openapi.yaml) — especificação OpenAPI
- [`docs/api/README.md`](docs/api/README.md) — guia da API
- [`docs/api/quick-reference.md`](docs/api/quick-reference.md) — referência rápida
- [`docs/api/postman-collection.json`](docs/api/postman-collection.json) — coleção Postman

## 📂 Estrutura

```text
form-engine/
├── backend/                 # Laravel API
│   ├── app/
│   │   ├── Http/
│   │   ├── Models/
│   │   ├── Repositories/
│   │   └── Services/
│   ├── database/
│   ├── routes/
│   └── tests/
├── frontend/                # Vue 3 SPA
│   ├── src/
│   │   ├── router/
│   │   ├── views/
│   │   └── api.js
│   ├── package.json
│   └── vite.config.js
├── docker/                  # Dockerfiles e Nginx
├── docs/                    # Documentação funcional e técnica
├── scripts/                 # Scripts de desenvolvimento
├── .github/workflows/       # CI
├── docker-compose.yml
└── README.md
```

## 🔐 Segurança

O backend aplica isolamento por tenant e autorização por papel nos endpoints protegidos. Recursos pertencentes a outro tenant são tratados como não encontrados nos fluxos protegidos correspondentes.

Para produção, ainda é recomendável revisar a estratégia de armazenamento do token no frontend, hardening de exportações CSV e configurações específicas do ambiente de deploy.

## 🤝 Contribuindo

1. Crie uma branch a partir de `main`.
2. Faça alterações pequenas e testáveis.
3. Execute os testes e o build localmente.
4. Abra um Pull Request.
5. Aguarde o CI ficar verde antes do merge.

## 📝 Licença

MIT.

## 🐛 Issues

Use o rastreador do repositório para bugs e melhorias:

https://github.com/HeroDestiny/form-engine/issues
