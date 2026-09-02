<?php
/**
 * Plugin Name: Bahia.ba - Homolog fora dos buscadores
 * Description: Faz hml.bahia.ba responder `noindex, nofollow` em toda página. SÓ EM HOMOLOG.
 *
 *              ================================================================
 *              O PROBLEMA — medido em 02/09/2026
 *              ================================================================
 *
 *              Homolog estava servindo `<meta name="robots" content="index, follow">`, com
 *              `blog_public = 1` no banco e sem `robots.txt` (404 no nginx). Resultado:
 *
 *              1. RASTREADORES ENTRAM. Vistos no log: ClaudeBot (rajada de ~46 req/min) e
 *                 Googlebot. Eles entram por `/?p=NNNN`, que 301 redireciona — e query string
 *                 FURA o `fastcgi_cache`, então cada acesso renderiza ~320 KB no PHP, contra um
 *                 `innodb_buffer_pool` de 128 MB. Contribuiu para a queda do ambiente no lote 7.
 *
 *              2. 🔴 CONTEÚDO DUPLICADO. É o acervo de produção servido num domínio diferente.
 *                 Confirmado por busca em 02/09: home, /entretenimento/, /justica/, /politica/
 *                 e /dende-e-poder/ de hml.bahia.ba JÁ ESTÃO INDEXADAS. Isso pode canibalizar a
 *                 posição das matérias reais, que é o problema mais sério dos dois.
 *
 *              ================================================================
 *              🔴 POR QUE `noindex` AGORA E `Disallow` SÓ DEPOIS — a ordem importa
 *              ================================================================
 *
 *              O instinto é pôr `Disallow: /` no `robots.txt`. **Aplicado agora, ele PIORA o
 *              problema de SEO.** `Disallow` proíbe o rastreador de BUSCAR a página — e, sem
 *              buscar, ele nunca vê o `noindex`. As páginas já indexadas **ficam no índice**,
 *              congeladas, às vezes por meses.
 *
 *              A ordem certa é:
 *
 *                1. AGORA  — `noindex` em toda página, com o rastreamento AINDA PERMITIDO, para
 *                            que o Google visite, leia o `noindex` e REMOVA o que já indexou;
 *                2. DEPOIS — quando as páginas tiverem saído do índice, aí sim `Disallow: /`,
 *                            para cortar a carga de rastreamento.
 *
 *              Este arquivo faz o passo 1. **O passo 2 é uma tarefa própria**, e exige mexer no
 *              nginx: `location = /robots.txt` não tem `try_files`, então a URL nunca chega ao
 *              PHP e o filtro `robots_txt` do WordPress não adianta nada aqui.
 *
 *              ================================================================
 *              O QUE FAZ — três camadas, porque uma só não cobre tudo
 *              ================================================================
 *
 *              1. `pre_option_blog_public` => 0
 *                 O interruptor nativo do WordPress ("desencorajar mecanismos de busca"). O
 *                 Yoast respeita e passa a emitir `noindex, nofollow` sozinho.
 *                 **Por filtro, NÃO por escrita no banco** — ver a seção do risco, abaixo.
 *
 *              2. `wp_robots` => força `noindex` e `nofollow`
 *                 Rede de segurança: se algum plugin reescrever a diretiva depois, esta roda no
 *                 fim e vence.
 *
 *              3. `X-Robots-Tag: noindex, nofollow` no cabeçalho HTTP
 *                 Cobre o que não é HTML e por isso não tem `<meta>`: XML dos sitemaps, feeds,
 *                 anexos. É a única das três que alcança esses.
 *
 *              ================================================================
 *              🔴 POR QUE FILTRO E NÃO `UPDATE` NA OPÇÃO — o risco que isso evita
 *              ================================================================
 *
 *              Gravar `blog_public = 0` no banco de homolog parece mais simples. **É perigoso.**
 *              O banco de homolog é retrato de produção e os dumps circulam entre os dois
 *              ambientes. Um dump de homolog restaurado em produção levaria junto o
 *              `blog_public = 0` — e **tiraria o site de verdade do Google**, em silêncio, sem
 *              erro nenhum na subida.
 *
 *              Por filtro, isso é impossível: o valor no banco continua `1`, e o comportamento
 *              vive no código, atrás da guarda de ambiente.
 *
 *              ================================================================
 *              COMO REVERTER
 *              ================================================================
 *
 *              APAGUE ESTE ARQUIVO. Nada foi escrito no banco. `blog_public` continua `1` em
 *              `wp_options`, e a página volta a responder `index, follow` no carregamento
 *              seguinte.
 *
 *              Conferir:  curl -s https://hml.bahia.ba/ | grep 'name="robots"'
 *
 *              ================================================================
 *              ⚠️ NÃO AGE EM PRODUÇÃO, DE PROPÓSITO
 *              ================================================================
 *
 *              A guarda de ambiente é a primeira coisa do corpo. Se este arquivo agisse em
 *              produção, tiraria o bahia.ba do Google.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Só homolog. Em produção — e em ambiente não identificado — este arquivo é inerte.
 *
 * O modo seguro aqui é NÃO aplicar: um `noindex` ligado por engano no site no ar é muito pior
 * que um homolog indexado por mais alguns dias.
 */
if (!function_exists('bahia_ambiente') || 'homolog' !== bahia_ambiente()) {
    return;
}

/**
 * 1. O interruptor nativo, sem tocar no banco.
 *
 * `get_option('blog_public')` passa a devolver 0. O núcleo e o Yoast leem daqui.
 */
add_filter('pre_option_blog_public', '__return_zero');

/**
 * 2. Rede de segurança na diretiva final.
 *
 * Prioridade alta para rodar depois de quem mais mexa em `wp_robots`.
 */
add_filter('wp_robots', function (array $robots) {
    unset($robots['index'], $robots['follow']);
    $robots['noindex']  = true;
    $robots['nofollow'] = true;
    return $robots;
}, 999);

/**
 * 3. Cabeçalho HTTP, para o que não é HTML.
 *
 * Sitemaps XML, feeds e arquivos não têm `<meta>`; o `X-Robots-Tag` é o que os alcança.
 * Fora do admin, para não poluir requisição de painel.
 */
add_action('send_headers', function () {
    if (is_admin() || headers_sent()) {
        return;
    }
    header('X-Robots-Tag: noindex, nofollow', true);
});

/**
 * 4. O `robots.txt` do WordPress, para quando/se a URL passar a chegar ao PHP.
 *
 * Hoje o nginx responde 404 em `/robots.txt` (não há `try_files` naquele `location`), então este
 * filtro não é exercitado. Fica registrado para o dia em que o passo 2 for feito.
 *
 * DE PROPÓSITO ele NÃO manda `Disallow: /`: enquanto houver página de homolog no índice, barrar
 * o rastreamento impede o Google de ver o `noindex` e de remover o que já indexou.
 */
add_filter('robots_txt', function ($saida) {
    return "# homolog — fora dos buscadores por noindex, nao por Disallow.\n"
         . "# Ver mu-plugins/bahia-homolog-noindex.php para a ordem e o porque.\n"
         . "User-agent: *\n"
         . "Disallow:\n";
}, 999);
