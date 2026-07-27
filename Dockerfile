# Stage 1: Build do tema
FROM node:20-alpine AS builder

WORKDIR /build

# Copiar apenas package.json primeiro para aproveitar cache do Docker
COPY themes/bahia_refactor/package*.json ./themes/bahia_refactor/
RUN cd themes/bahia_refactor && npm ci --production=false

# Copiar o resto do tema e buildar
COPY themes/bahia_refactor/ ./themes/bahia_refactor/
RUN cd themes/bahia_refactor && npm run theme:build

# Validar artefatos gerados
RUN test -f themes/bahia_refactor/dist/css/main.min.css || (echo "ERRO: CSS não gerado" && exit 1)
RUN test -f themes/bahia_refactor/dist/js/theme.min.js || (echo "ERRO: JS não gerado" && exit 1)

# Stage 2: Imagem final WordPress
FROM wordpress:6.4-php8.2-fpm

# PHP config customizado
COPY php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Copiar wp-content completo
COPY --chown=www-data:www-data . /var/www/html/wp-content/

# Copiar artefatos do build do tema
COPY --from=builder --chown=www-data:www-data \
  /build/themes/bahia_refactor/dist \
  /var/www/html/wp-content/themes/bahia_refactor/dist

# Remover arquivos desnecessários na imagem final
RUN rm -rf /var/www/html/wp-content/themes/bahia_refactor/node_modules

WORKDIR /var/www/html