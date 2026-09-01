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
#
# PHP 8.3 desde 2026-08-29. Verificado antes de trocar:
#   - `php -l` em 173 arquivos nossos (mu-plugins + tema) sob 8.3 E 8.4: 0 erros
#   - `php -l` em ~9.800 arquivos dos 30 plugins sob 8.4: 0 erros
#   - paridade de extensoes 8.2 x 8.3: 41 x 41, nenhuma perdida
#
# NAO subir para 8.4 ainda: ele depreciou tipo implicitamente nullable
# (`Foo $x = null` passa a exigir `?Foo`), e ha 280 ocorrencias reais — 244 so no
# WP Offload Media, e 100% em biblioteca de terceiro vendorizada. Nenhuma no nosso
# codigo. O caminho e esperar release dos plugins, nao corrigir codigo nosso.
# Ver scratchpad/UPGRADE-PHP-WORDPRESS.md, Tarefa A.
#
# A versao do WordPress fica na linha 6.8 de proposito: o WP atual e o 7.1, tres
# versoes maiores a frente, e o teto declarado pelos plugins e 7.0 — a maioria
# para em 6.8. O tema Newspaper e os tres plugins tagDiv nao declaram nada.
#
# ATENCAO: esta tag entrega o core do WordPress 6.8.3, e o site auto-atualiza para
# 6.8.8 dentro do pod, num emptyDir que morre com ele. Todo rollout devolve o
# 6.8.3 ate o WP-Cron rodar de novo. Ver Tarefa B do mesmo documento.
#
# FROM wordpress:6.4-php8.2-fpm
# FROM wordpress:6.8-php8.2-fpm
FROM wordpress:6.8-php8.3-fpm

# PHP config customizado
COPY php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Pool do PHP-FPM. Nome com zzz- de proposito: os pools carregam por glob em
# ordem alfabetica e este precisa vir DEPOIS do www.conf da imagem oficial,
# senao o pm.max_children = 5 padrao continua valendo.
COPY php/zzz-bahia-pool.conf /usr/local/etc/php-fpm.d/zzz-bahia-pool.conf

# Copiar wp-content completo
COPY --chown=www-data:www-data . /var/www/html/wp-content/

# Copiar artefatos do build do tema
COPY --from=builder --chown=www-data:www-data \
  /build/themes/bahia_refactor/dist \
  /var/www/html/wp-content/themes/bahia_refactor/dist

# Remover arquivos desnecessários na imagem final
RUN rm -rf /var/www/html/wp-content/themes/bahia_refactor/node_modules

WORKDIR /var/www/html