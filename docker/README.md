# Configurações Docker

Este diretório contém todas as configurações relacionadas ao Docker.

## Estrutura

```
docker/
├── backend/
│   └── Dockerfile        # Imagem do backend (PHP-FPM + Laravel)
└── nginx/
    └── default.conf      # Configuração do Nginx
```

## Backend (PHP-FPM)

**Dockerfile:** `backend/Dockerfile`

- **Base:** `php:8.5-fpm-alpine3.19`
- **Extensões:** pdo_pgsql, redis, opcache, mbstring, zip, bcmath, intl
- **Composer:** v2.9.5
- **Usuário:** appuser (UID/GID configurável)
- **Porta:** 9000

### Build Args

- `USER_ID` - ID do usuário (padrão: 1000)
- `GROUP_ID` - ID do grupo (padrão: 1000)

## Nginx

**Config:** `nginx/default.conf`

- **Porta:** 80 (mapeada para 8000 no host)
- **Root:** `/var/www/public`
- **FastCGI:** backend:9000
- **Upload Max:** 100M
- **Security Headers:** Habilitados
- **Static Cache:** 1 ano

## Uso

As configurações são referenciadas pelo `docker-compose.yml` na raiz do projeto.

```bash
# Build e iniciar
docker-compose up -d --build

# Reconstruir apenas backend
docker-compose build backend

# Ver logs
docker-compose logs -f nginx backend
```

## Segurança

- Containers rodam com usuário não-root
- Capabilities limitadas (principle of least privilege)
- No new privileges habilitado
- Logs rotacionados (10M, 3 arquivos)
- Resource limits configurados
