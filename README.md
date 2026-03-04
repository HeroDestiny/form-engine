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
- [ ] Configuração de CI/CD
- [ ] Padrão de camadas implementado

### Fase 2 – Multi-tenant Institucional
- [ ] Modelo de dados para unidades administrativas
- [ ] Middleware de isolamento
- [ ] Gestão de usuários por tenant
- [ ] Testes de isolamento

### Fase 3 – Fichas Dinâmicas Versionadas
- [ ] Estrutura base do Frontend (Vue 3 SPA)
- [ ] Comunicação API REST entre frontend e backend
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

> 🪟 **Usuários Windows**: Se encontrar erro "execução de scripts foi desabilitada", veja o guia [WINDOWS_SETUP.md](WINDOWS_SETUP.md)

### Instalação

#### 🪟 Windows (PowerShell)

```powershell
# Clone o repositório
git clone https://github.com/seu-usuario/form-engine.git
cd form-engine

# Permitir execução de scripts (apenas uma vez)
Set-ExecutionPolicy -ExecutionPolicy Bypass -Scope Process

# Verificar ambiente
.\scripts\dev.ps1 check

# Configuração automática completa
.\scripts\dev.ps1 init

# URLs de acesso
# Backend API: http://localhost:8000
# PostgreSQL: localhost:5432
# Redis: localhost:6379
```

#### 🐧 Linux/Mac (Bash)

```bash
# Clone o repositório
git clone https://github.com/seu-usuario/form-engine.git
cd form-engine

# Criar projeto Laravel
docker run --rm -v $(pwd)/backend:/app composer create-project laravel/laravel:^11.0 .

# Configurar ambiente
cd backend
cp .env.example .env
cd ..

# Subir containers
docker-compose up -d

# Instalar dependências e configurar
docker-compose exec backend composer install
docker-compose exec backend php artisan key:generate
docker-compose exec backend php artisan migrate

# URLs de acesso
# Backend API: http://localhost:8000
# PostgreSQL: localhost:5432
# Redis: localhost:6379
```

## 🧪 Testes

### Backend (Laravel)

#### Windows (PowerShell)

```powershell
# Executar todos os testes
.\scripts\dev.ps1 test

# Executar testes com cobertura
.\scripts\dev.ps1 test-coverage

# Executar testes específicos
.\scripts\dev.ps1 test-filter FormTest
```

#### Linux/Mac (Bash)

```bash
# Executar todos os testes
docker-compose exec backend php artisan test

# Executar testes com cobertura
docker-compose exec backend php artisan test --coverage

# Executar testes específicos
docker-compose exec backend php artisan test --filter=FormTest
```
docker-compose exec frontend npm run test:watch
```

## 📂 Estrutura do Projeto

```
form-engine/
├── 📁 backend/              # API Laravel
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
│   └── composer.json
│
├── 📁 docker/               # Configurações Docker
│   ├── backend/             
│   │   └── Dockerfile       # Imagem do backend
│   └── nginx/
│       └── default.conf     # Config Nginx
│
├── 📁 docs/                 # Documentação
│   ├── README.md            # Índice da documentação
│   ├── visao.md             # Visão do produto
│   ├── arquitetura.md       # Arquitetura técnica
│   └── modelo_de_dados.md  # Modelagem do banco
│
├── 📁 scripts/              # Scripts de desenvolvimento
│   ├── README.md            # Documentação dos scripts
│   └── dev.ps1              # Script principal (Windows)
│
├── 📄 .env.example          # Variáveis de ambiente
├── 📄 .gitignore
├── 📄 docker-compose.yml    # Orquestração
└── 📄 README.md             # Este arquivo
```

### Organização

- **`backend/`** - Código-fonte do Laravel (API REST)
- **`docker/`** - Dockerfiles e configurações de containers
- **`docs/`** - Documentação técnica e conceitual
- **`scripts/`** - Scripts utilitários para desenvolvimento
- **`docker-compose.yml`** - Definição dos serviços (backend, nginx, postgres, redis)

> 📖 Para mais detalhes sobre cada diretório, veja os arquivos README.md específicos dentro de cada pasta.
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