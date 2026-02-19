# 📌 Form Engine

![Status](https://img.shields.io/badge/status-em%20planejamento-yellow)
![Laravel](https://img.shields.io/badge/laravel-%5E10.0-red)
![Vue](https://img.shields.io/badge/vue-3.x-brightgreen)
![Docker](https://img.shields.io/badge/docker-enabled-blue)

## 📖 Visão Geral

O **Form Engine** é um sistema modular para gerenciamento de formulários dinâmicos, com isolamento multi-tenant por unidade administrativa, versionamento de estruturas e auditoria básica.

O projeto demonstra uma arquitetura moderna, organizada em camadas, orientada a testes e preparada para evolução institucional.

## 🎯 Objetivos do Projeto

- ✅ Implementar arquitetura em camadas (Controller → Service → Repository)
- ✅ Garantir isolamento multi-tenant por unidade administrativa
- ✅ Permitir criação de fichas dinâmicas
- ✅ Implementar versionamento de ficha
- ✅ Implementar auditoria básica de ações
- ✅ Garantir qualidade por meio de testes de integração

## 🏗️ Arquitetura

### Stack Tecnológica

| Componente | Tecnologia |
|------------|------------|
| **Backend** | Laravel (API REST) |
| **Frontend** | Vue 3 (SPA) |
| **Banco de Dados** | PostgreSQL |
| **Cache** | Redis |
| **Containerização** | Docker + Docker Compose |

### Estrutura de Separação

O projeto adota **separação clara entre Backend e Frontend**, com repositórios/diretórios independentes:

```
┌─────────────────────────────────────────────────────┐
│                   FORM ENGINE                        │
└───────────────┬────────────────┬────────────────────┘
                │                │
        ┌───────▼──────┐  ┌──────▼────────┐
        │   BACKEND    │  │   FRONTEND    │
        │  (Laravel)   │  │    (Vue 3)    │
        │              │  │               │
        │  REST API    │◄─┤  HTTP/JSON    │
        │  :8000       │  │  :3000        │
        └──────┬───────┘  └───────────────┘
               │
        ┌──────▼──────┐
        │  PostgreSQL │
        │   Redis     │
        └─────────────┘
```

### Padrão Arquitetural (Backend)

```
┌─────────────┐
│ Controller  │  ← Recebe requisições HTTP (API)
└──────┬──────┘
       │
┌──────▼──────┐
│  Service    │  ← Lógica de negócio
└──────┬──────┘
       │
┌──────▼──────┐
│ Repository  │  ← Acesso a dados
└──────┬──────┘
       │
┌──────▼──────┐
│   Model     │  ← Eloquent ORM
└─────────────┘
```

### Princípios

- **Separação Backend/Frontend**: Aplicações independentes com comunicação via API REST
- **Separation of Concerns**: Cada camada tem responsabilidade única
- **API First**: Backend expõe apenas endpoints REST (JSON)
- **SPA Architecture**: Frontend como Single Page Application
- **Dependency Injection**: Facilitando testes e manutenção
- **Repository Pattern**: Abstração da camada de dados
- **Service Layer**: Centralização da lógica de negócio

## 📦 Escopo da v1

### ✅ Funcionalidades Incluídas

- 🏢 **Multi-tenant** por unidade administrativa
- 📝 **Fichas dinâmicas** com campos customizáveis
- 📌 **Versionamento** de estrutura de fichas
- 📊 **Auditoria básica** de ações do usuário
- 📥 **Exportação** de dados em CSV

### ❌ Fora do Escopo (v1)

- ❌ Dashboard analítico avançado
- ❌ Hierarquia estadual complexa
- ❌ Sistema avançado de permissões granulares
- ❌ Arquitetura em microserviços

## 🗺️ Roadmap

### Fase 1 – Fundação Arquitetural
- [ ] Configuração do ambiente Docker (backend + frontend separados)
- [ ] Estrutura base do Backend (Laravel API)
- [ ] Estrutura base do Frontend (Vue 3 SPA)
- [ ] Configuração de CI/CD
- [ ] Padrão de camadas implementado
- [ ] Comunicação API REST entre frontend e backend

### Fase 2 – Multi-tenant Institucional
- [ ] Modelo de dados para unidades administrativas
- [ ] Middleware de isolamento
- [ ] Gestão de usuários por tenant
- [ ] Testes de isolamento

### Fase 3 – Fichas Dinâmicas Versionadas
- [ ] Engine de renderização de formulários
- [ ] Sistema de versionamento
- [ ] Validação dinâmica
- [ ] Interface de criação de fichas

### Fase 4 – Auditoria e Exportação
- [ ] Log de auditoria
- [ ] Exportação CSV
- [ ] Relatórios básicos
- [ ] API de consulta

## 🚀 Início Rápido

### Pré-requisitos

- Docker >= 20.10
- Docker Compose >= 2.0
- Git

### Instalação

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/form-engine.git
cd form-engine

# Suba os containers (backend + frontend + banco de dados)
docker-compose up -d

# ==== BACKEND ====
# Acesse o container do backend
cd backend
cp .env.example .env

# Instale dependências do Laravel
docker-compose exec backend composer install

# Execute as migrations
docker-compose exec backend php artisan migrate

# Gere a chave da aplicação
docker-compose exec backend php artisan key:generate

# ==== FRONTEND ====
# Acesse o container do frontend
cd ../frontend
cp .env.example .env

# Instale dependências do Vue
docker-compose exec frontend npm install

# ==== ACESSO ====
# Backend API: http://localhost:8000/api
# Frontend SPA: http://localhost:3000
# PostgreSQL: localhost:5432
# Redis: localhost:6379
```

## 🧪 Testes

### Backend (Laravel)

```bash
# Executar todos os testes
docker-compose exec backend php artisan test

# Executar testes com cobertura
docker-compose exec backend php artisan test --coverage

# Executar testes específicos
docker-compose exec backend php artisan test --filter=FormTest
```

### Frontend (Vue)

```bash
# Executar testes unitários
docker-compose exec frontend npm run test:unit

# Executar testes e2e
docker-compose exec frontend npm run test:e2e

# Executar testes em modo watch
docker-compose exec frontend npm run test:watch
```

## 📂 Estrutura do Projeto

```
form-engine/
├── backend/                 # 🔴 API Laravel (Backend)
│   ├── app/
│   │   ├── Http/
│   │   │   ├── Controllers/ # Controllers da API
│   │   │   ├── Requests/    # Form Requests
│   │   │   ├── Resources/   # API Resources
│   │   │   └── Middleware/  # Middlewares
│   │   ├── Services/        # Lógica de negócio
│   │   ├── Repositories/    # Acesso a dados
│   │   └── Models/          # Eloquent Models
│   ├── database/
│   │   ├── migrations/      # Migrações do banco
│   │   └── seeders/         # Seeds
│   ├── routes/
│   │   └── api.php          # Rotas da API
│   ├── tests/
│   │   ├── Feature/         # Testes de integração
│   │   └── Unit/            # Testes unitários
│   ├── .env.example
│   ├── composer.json
│   └── Dockerfile
│
├── frontend/                # 🔵 SPA Vue 3 (Frontend)
│   ├── src/
│   │   ├── components/      # Componentes Vue
│   │   ├── views/           # Views/Pages
│   │   ├── router/          # Vue Router
│   │   ├── store/           # Pinia (State Management)
│   │   ├── services/        # API Services (Axios)
│   │   ├── composables/     # Composables Vue 3
│   │   └── assets/          # Assets estáticos
│   ├── public/
│   ├── tests/
│   │   ├── unit/            # Testes unitários
│   │   └── e2e/             # Testes E2E
│   ├── .env.example
│   ├── package.json
│   ├── vite.config.js
│   └── Dockerfile
│
├── docker-compose.yml       # Orquestração dos containers
└── README.md
```

## 🤝 Contribuindo

Contribuições são bem-vindas! Por favor:

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📝 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 👥 Autores

- **Paulo** - *Trabalho Inicial* - [GitHub](https://github.com/seu-usuario)

## 📞 Suporte

Tem alguma dúvida? Entre em contato:

- 📧 Email: seu-email@exemplo.com
- 🐛 Issues: [GitHub Issues](https://github.com/seu-usuario/form-engine/issues)

---

Feito com ❤️ utilizando Laravel e Vue