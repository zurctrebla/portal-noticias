# ############################################################################
# VERSAO DO WORDPRESS — UMA SO, PARA OS DOIS AMBIENTES
# ############################################################################
#
# 2026-09-03: A SEPARACAO ACABOU. Homolog e producao constroem a partir da
# MESMA tag, sem argumento nenhum. O bloco temporario que vivia aqui, com a
# condicao de saida, foi executado e removido:
#
#   1. apagado o `--build-arg WP_VERSION=` do deploy-homolog.yml   ✔
#   2. alinhado o default abaixo a versao comum (7.1.0)            ✔
#
# POR QUE 7.1 e nao a 6.8 de producao:
#   Homolog rodou a 7.1 de 29/08 a 03/09 -- sete lotes de plugins e a validacao
#   da redacao inteira aconteceram SOBRE ELA. Manter producao na 6.8 faria os
#   plugins novos estrearem num nucleo que ninguem testou. Alem disso:
#     - a api do WordPress marca a 6.8.3 como `insecure`;
#     - o Yoast 28.4 declara `Requires at least: 6.9` e NAO se autodesativa --
#       na 6.8 ele sobe, roda, e quebra so onde chamar uma API que nao existe.
#
# O DESALINHAMENTO DE PHP MORRE AQUI JUNTO: cada tag do WordPress empacota o
# seu proprio patch de PHP. Homolog rodava 8.3.33 (tag 7.1.0) e producao 8.3.28
# (tag 6.8.3). Voltando a mesma tag, os dois ficam em 8.3.33.
#
# A tag e a patch EXATA (`7.1.0`), nao a flutuante `7.1`: referencia de imagem
# que se move sozinha desfaz rollback em silencio. Ver HANDOVER 21 e 33.
#
# >>> O QUE PRODUCAO PRECISA NO BANCO, MEDIDO EM 03/09 <<<
#   Subir de db_version 60421 (6.8.x) para 61833 (7.1) roda DUAS rotinas:
#     - a de `< 60497`: atras de `is_multisite()`. Producao e site unico: NAO RODA.
#     - `upgrade_700()`: UM update em wp_usermeta trocando admin_color
#       'fresh' -> 'modern'. Trivial.
#   E o dbDelta encontra UMA diferenca de esquema, a unica entre 6.8 e 7.1:
#     wp_posts  KEY type_status_author (post_type,post_status,post_author)
#   Em producao sao 441.626 linhas / 982 MB. Homolog ja tem esse indice desde
#   29/08 -- prova de que o caminho fecha. Ver MIGRACAO-homolog-para-prod.md.
#
ARG WP_VERSION=7.1.0

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
# A versao do WordPress vem do ARG do topo deste arquivo. Producao fica na linha
# 6.8 de proposito: o teto declarado pelos plugins e 7.0 e a maioria para em 6.8;
# o tema Newspaper e os tres plugins tagDiv nao declaram nada.
#
# ATENCAO (Tarefa B, PRE-EXISTENTE E NAO RESOLVIDA AQUI): o WordPress se
# auto-atualiza DENTRO do pod, num emptyDir que morre com ele. Medido em
# 03/09/2026, producao servia com QUATRO pods em DUAS versoes ao mesmo tempo --
# dois em 6.8.8 (auto-atualizados) e dois em 6.8.3 (recem-criados da imagem).
# Alinhar em 7.1.0 zera a divergencia HOJE, porque 7.1 e o topo: nao ha para
# onde auto-atualizar. Volta a aparecer quando sair a 7.1.1.
#
# FROM wordpress:6.4-php8.2-fpm
# FROM wordpress:6.8-php8.2-fpm
# FROM wordpress:6.8-php8.3-fpm     <- tag flutuante de minor, trocada em 01/09
FROM wordpress:${WP_VERSION}-php8.3-fpm

# GUARDA DE BUILD: a imagem entrega mesmo o core que pedimos?
#
# Existe porque a Tarefa B mostrou que ninguem sabia responder "qual core esta
# imagem entrega". Agora a resposta sai no log de TODO build, e uma tag que
# derivar de minor quebra a construcao em vez de virar surpresa em producao.
#
# A comparacao e por major.minor: a convencao de tag do Docker acrescenta o
# patch (`7.1.0`) onde o WordPress diz so `7.1`, e isso nao e divergencia.
ARG WP_VERSION
RUN real="$(php -r 'require "/usr/src/wordpress/wp-includes/version.php"; echo $wp_version;')"; \
    dbv="$(php -r 'require "/usr/src/wordpress/wp-includes/version.php"; echo $wp_db_version;')"; \
    echo "=== core na imagem: ${real} (db_version ${dbv}) — pedido: ${WP_VERSION} ==="; \
    a="$(echo "$real"        | cut -d. -f1,2)"; \
    b="$(echo "$WP_VERSION"  | cut -d. -f1,2)"; \
    if [ "$a" != "$b" ]; then \
      echo "ERRO: imagem entrega WordPress ${real}, mas o build pediu ${WP_VERSION}"; \
      exit 1; \
    fi

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