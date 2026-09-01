# ############################################################################
# VERSAO DO WORDPRESS — A UNICA COISA QUE DIFERE ENTRE HOMOLOG E PRODUCAO
# ############################################################################
#
# O default e o de PRODUCAO. Quem construir sem argumento nenhum -- inclusive
# `docker build .` na mao -- recebe o comportamento de producao. Homolog e o
# desvio, e o desvio e explicito:
#
#   deploy-prod.yml     nao passa nada        -> usa este default
#   deploy-homolog.yml  --build-arg WP_VERSION=7.1.0
#
# POR QUE ESTAO SEPARADOS (2026-09-01, TEMPORARIO):
#   Homolog foi para a 7.1 em 29/08 para dimensionar a migracao. Producao fica
#   na 6.8 ate a divida de plugins ser paga. Enquanto o Dockerfile era unico,
#   versionar a 7.1 a levaria para producao no proximo merge para a `main` --
#   e por isso havia 12 commits parados na develop.
#
# >>> CONDICAO DE SAIDA, EXPLICITA <<<
#   A separacao acaba quando homolog e producao estiverem na MESMA versao de
#   WordPress E de plugins. O gesto de saida e:
#     1. apagar `--build-arg WP_VERSION=` do deploy-homolog.yml
#     2. alinhar o default abaixo a versao comum
#   Nenhum outro arquivo participa. Se voce esta lendo isto e os dois ambientes
#   ja estao equiparados, faca os dois passos e apague este bloco.
#
#   ATENCAO ao que mais esta separado sem estar escrito: cada tag do WordPress
#   empacota o SEU proprio patch de PHP. Hoje homolog roda PHP 8.3.33 (da tag
#   7.1.0) e producao roda 8.3.28 (da tag 6.8.3) -- mesma minor, patches
#   diferentes. Nao ha nada a fazer sobre isso: os patches SE REALINHAM SOZINHOS
#   no gesto de saida acima, porque os dois ambientes voltam a construir a
#   partir da mesma tag. O desalinhamento nasce e morre com a separacao.
#
# >>> PARA QUEM FOR FAZER O MERGE develop -> main <<<
#   O `FROM` de producao muda de `6.8-php8.3-fpm` para `6.8.3-php8.3-fpm`.
#   Medido em 01/09/2026: as duas tags tem o MESMO digest
#   (sha256:906c25725c2edccb7809851f61f98560ea73b6c01d482d69d1b9fdf04b5ff75f),
#   entao e no-op no dia. Confira o digest de novo antes de mergear -- `6.8` e
#   tag FLUTUANTE DE MINOR e pode ter se movido desde entao. A patch fixa nao.
#
# Fixar a patch e o mesmo remedio que aplicamos ao `prod-latest`, uma camada
# abaixo: referencia de imagem que se move sozinha desfaz rollback em silencio.
# Ver HANDOVER secoes 21 e 33.
#
ARG WP_VERSION=6.8.3

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
# ATENCAO: em PRODUCAO esta imagem entrega o core 6.8.3 e o site auto-atualiza
# para 6.8.8 dentro do pod, num emptyDir que morre com ele. Todo rollout devolve
# o 6.8.3 ate o WP-Cron rodar de novo. Ver Tarefa B do mesmo documento.
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