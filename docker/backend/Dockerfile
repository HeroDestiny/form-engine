# Argumentos de build no escopo global
ARG COMPOSER_VERSION=2.9.5

# Stage do Composer
FROM composer:${COMPOSER_VERSION} AS composer

# Imagem base - FPM para uso com Nginx
# Usando PHP 8.5
FROM php:8.5-fpm-alpine3.19

# Labels de metadata
LABEL maintainer="form-engine-team"
LABEL description="Laravel Form Engine Backend"
LABEL version="1.0"

# Argumentos de build
ARG USER_ID=1000
ARG GROUP_ID=1000

# Instalar dependências do sistema e extensões PHP em uma única camada
RUN apk add --no-cache \
    # Dependências runtime permanentes
    git \
    curl \
    libpng \
    libzip \
    zip \
    unzip \
    postgresql-libs \
    oniguruma \
    icu-libs \
    && \
    # Dependências de build temporárias
    apk add --no-cache --virtual .build-deps \
    libpng-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    $PHPIZE_DEPS \
    && \
    # Instalar extensões PHP
    docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    exif \
    pcntl \
    bcmath \
    intl \
    opcache \
    && \
    # Instalar Redis extension (versão fixada)
    pecl install redis-6.0.2 \
    && docker-php-ext-enable redis \
    && \
    # Limpar dependências de build
    apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/* /usr/share/man

# Configurar PHP para desenvolvimento
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    { \
        echo 'memory_limit=512M'; \
        echo 'upload_max_filesize=100M'; \
        echo 'post_max_size=100M'; \
        echo 'max_execution_time=300'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=8'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'expose_php=Off'; \
    } > "$PHP_INI_DIR/conf.d/docker-php-config.ini"

# Instalar Composer com versão específica e verificação SHA256
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN sha256sum /usr/bin/composer && \
    composer --version

# Criar usuário do sistema para executar comandos Composer e Artisan
RUN addgroup -g ${GROUP_ID} appuser && \
    adduser -D -u ${USER_ID} -G appuser appuser && \
    install -d -m 0755 -o appuser -g appuser /var/www/.composer

# Definir diretório de trabalho
WORKDIR /var/www

# Copiar arquivos de configuração (se necessário ajuste conforme seu projeto)
# COPY --chown=appuser:appuser composer.json composer.lock ./

# Mudar para usuário não-root
USER appuser

# Expor porta 9000
EXPOSE 9000

# Comando padrão: iniciar PHP-FPM
CMD ["php-fpm"]
