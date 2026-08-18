<?php
/**
 * Plugin Name: Bahia.ba - Feeds RSS
 * Description: Desliga os feeds padrão do WordPress, remove os <link> de feed do <head> e
 *              registra o único feed do portal, `feedbahiaba`. Porte do que o tema
 *              bahia_refactor fazia em functions.php:1253-1277 — ver o bloco abaixo.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE ISTO SAIU DO TEMA (18/08/2026).
 *
 * O controle dos feeds morava em `themes/bahia_refactor/functions.php:1253-1277` e ia morrer
 * com a virada, sem que nada acusasse. Medido nos dois ambientes antes do porte:
 *
 *     /feed/feedbahiaba/        produção 200 com 5 itens  |  homolog 404
 *     /feed/ e /politica/feed/  produção 500 em ~1,5 s    |  homolog sem resposta em 45 s
 *
 * Ou seja: a virada trocaria uma coisa que funciona (o feed próprio) por duas que não
 * funcionam (os feeds padrão, que passam a varrer o acervo até estourar). E **504 não entra
 * no fastcgi_cache**, então cada passagem de robô pagaria o custo inteiro — e endereço de
 * feed é exatamente o que robô visita sem parar.
 *
 * Vale nos DOIS ambientes de propósito. Em homolog isso é ganho imediato: mata os timeouts
 * de 45 s que existem hoje e devolve o `feedbahiaba`, que lá respondia 404.
 */

/** Bump para forçar novo flush quando mexer no `add_feed` abaixo. */
define('BAHIA_FEEDS_VER', '1.0.0');

/** Quantos itens o feed publica. Era 5 no template do tema. */
define('BAHIA_FEEDS_ITENS', 5);

/* ---------------------------------------------------------------------------
   1. Desligar os feeds padrão
   --------------------------------------------------------------------------- */

/**
 * Mantido o texto e o código HTTP do tema (500), para o porte não mudar comportamento junto
 * com o lugar.
 *
 * FICA A DECISÃO, que é sua e não minha: 500 diz ao robô "deu erro, tente de novo", e ele
 * tenta — para sempre. Um 410 diria "isto acabou, esqueça", e o endereço sairia dos índices.
 * Como 500 é o que está no ar hoje, é o que este porte reproduz.
 */
function bahia_feeds_desligado() {
    wp_die(
        sprintf(
            /* Mesma frase do tema, inclusive a falta de espaço depois da vírgula. */
            'No feed available,please visit our <a href="%s">homepage</a>!',
            esc_url(get_bloginfo('url'))
        )
    );
}

/**
 * O CORTE É EM `parse_request`, E NÃO SÓ EM `do_feed` — medido, não suposto.
 *
 * O tema pendurava a recusa nos sete `do_feed_*`. Só que `do_feed()` roda no
 * `template-loader.php`, ou seja **depois** de `WP::main()` já ter montado E EXECUTADO a
 * consulta principal. A recusa saía, mas o banco já tinha trabalhado. Portei assim primeiro,
 * e a medição em homolog mostrou o defeito: `/feed/` e `/politica/feed/` continuaram
 * estourando 50 s exatamente como antes do porte.
 *
 * Em produção isso nunca apareceu porque o banco dá conta — o 500 sai em ~1,5 s e ninguém
 * repara na consulta desperdiçada. Mas o motivo de portar isto era justamente o custo com
 * robô, e endereço de feed é o que robô mais visita. Recusar depois de pagar a conta não
 * resolve o problema que se queria resolver.
 *
 * `parse_request` roda ANTES da consulta: as query vars já estão resolvidas e nada tocou o
 * banco ainda. Os `do_feed_*` ficam como rede de segurança, para o caso de algum plugin
 * marcar o request como feed mais tarde.
 */
add_action('parse_request', function ($wp) {
    if (empty($wp->query_vars['feed'])) {
        return;
    }
    if ('feedbahiaba' === $wp->query_vars['feed']) {
        return;   // o nosso; segue o fluxo normal
    }

    bahia_feeds_desligado();
}, 1);

foreach (array('do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2',
               'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments') as $bahia_feeds_hook) {
    add_action($bahia_feeds_hook, 'bahia_feeds_desligado', 1);
}
unset($bahia_feeds_hook);

/**
 * Tira os <link rel="alternate"> de feed do <head>: sem isto, o HTML anuncia endereços que
 * respondem 500. Funciona aqui porque o core registra estes callbacks em default-filters.php,
 * que o wp-settings.php carrega ANTES dos mu-plugins.
 */
remove_action('wp_head', 'feed_links', 2);
remove_action('wp_head', 'feed_links_extra', 3);

/* ---------------------------------------------------------------------------
   2. O feed próprio: /feed/feedbahiaba/
   --------------------------------------------------------------------------- */

/**
 * PRIORIDADE 99 E LIMPEZA DO HOOK — por causa da convivência com o tema antigo.
 *
 * Enquanto o `bahia_refactor` estiver ativo em produção, ele registra o MESMO feed
 * (`customRSS()` -> `add_feed('feedbahiaba', 'customRSSFunc')`, em init na prioridade padrão).
 * E `add_feed()` faz `add_action("do_feed_{$nome}", $callback)`: dois registros do mesmo nome
 * deixam DOIS callbacks pendurados, e o XML sairia duplicado — dois `<rss>` no mesmo corpo,
 * que nenhum leitor consegue interpretar.
 *
 * Registrar em 99 (depois do tema) e limpar o hook antes garante um renderizador só, o daqui,
 * nos dois ambientes. Quando o tema sair, a limpeza vira no-op e nada muda.
 */
add_action('init', function () {
    remove_all_actions('do_feed_feedbahiaba');
    add_feed('feedbahiaba', 'bahia_feeds_render');
}, 99);

/**
 * mu-plugins não têm hook de ativação, e `add_feed()` cria regra de reescrita: sem flush, o
 * endereço responde 404. Mesmo padrão do bahia-editorias-cpt.php — uma vez por versão.
 */
add_action('init', function () {
    if (get_option('bahia_feeds_flushed') !== BAHIA_FEEDS_VER) {
        flush_rewrite_rules(false);
        update_option('bahia_feeds_flushed', BAHIA_FEEDS_VER);
    }
}, 999);

/**
 * Renderiza o feed. Porte de `themes/bahia_refactor/rss-feedbahiaba.php`.
 *
 * UMA MUDANÇA DELIBERADA: a lista de post types.
 *
 * O template usava `global $POST_TYPES`, a lista escrita à mão em functions.php:56 — a mesma
 * que o commit 104be34f mostrou ter apodrecido (46 arquivos em post-types/, 24 na lista, com
 * repetição). Comparando as duas: os 23 tipos únicos do tema são um SUBCONJUNTO do mapa, que
 * tem 25. Faltavam no feed `dende_poder` (editoria nova) e `mais_gente`.
 *
 * Aqui a fonte passa a ser `bahia_editorias_map()`, que é mantida. Efeito prático: o feed
 * ganha as duas editorias que faltavam e perde a entrada repetida. Se um dia for preciso
 * excluir alguma editoria do feed, o lugar é aqui, com o motivo escrito.
 */
function bahia_feeds_tipos() {
    return function_exists('bahia_editorias_map') ? array_keys(bahia_editorias_map()) : array('post');
}

/**
 * A CONSULTA PRINCIPAL É MOLDADA AQUI — o template não faz consulta própria.
 *
 * O template do tema chamava `query_posts()` dentro de si, o que dá DUAS consultas por
 * requisição: a principal, que o WordPress monta sozinho antes de chegar ao template, e a do
 * `query_posts`. A principal era perda pura — ninguém usava o resultado dela:
 *
 *     SELECT SQL_CALC_FOUND_ROWS wp_posts.* FROM wp_posts
 *      WHERE post_type = 'post' AND (post_status='publish' OR post_status='acf-disabled')
 *      ORDER BY post_date DESC LIMIT 0, 10
 *
 * Medido em homolog em 18/08/2026, instrumentando o request de verdade: ela sozinha respondeu
 * por **58,77 s** — era ela que fazia o feed estourar, não a renderização. O
 * `SQL_CALC_FOUND_ROWS` obriga o MySQL a contar todas as linhas que casam antes de devolver as
 * 10, e `post_type = 'post'` não é sequer o que este feed publica.
 *
 * Fica registrado quanto tempo se perde confiando em medição isolada: a consulta do template
 * levava 0,02 s e o `get_lastpostmodified()` levava 59 s quando cronometrados fora do request.
 * Duas pistas boas, nenhuma das duas a causa. Só instrumentando o request real — `parse_request`
 * chegava, `wp` nunca chegava — é que o lugar apareceu.
 *
 * Moldando a principal, o feed passa a fazer UMA consulta, com os tipos certos e sem contagem.
 */
add_action('pre_get_posts', function ($q) {
    if (!$q->is_main_query() || 'feedbahiaba' !== $q->get('feed')) {
        return;
    }

    $q->set('post_type', bahia_feeds_tipos());
    $q->set('no_found_rows', true);
    $q->set('ignore_sticky_posts', true);

    // `posts_per_rss`, e NÃO `posts_per_page`: em requisição de feed o core sobrescreve o
    // segundo pelo primeiro (WP_Query::get_posts(), "This overrides posts_per_page"). Setar
    // posts_per_page aqui não tem efeito nenhum — foi o que fez o feed sair com os 10 da
    // option em vez dos 5 que o template do tema pedia via `showposts`.
    $q->set('posts_per_rss', BAHIA_FEEDS_ITENS);
    $q->set('posts_per_page', BAHIA_FEEDS_ITENS);
});

/**
 * O `Last-Modified` DO CORE — a chamada de 59 s que sobrou depois de todas as outras.
 *
 * `WP::send_headers()` (wp-includes/class-wp.php:491-500) chama `get_lastpostmodified('GMT')`
 * em TODA requisição de feed, para montar o `Last-Modified` e o `ETag`. Não adianta tirar a
 * chamada do template: ela acontece antes, no core, e não passa por aqui.
 *
 * O custo é o mesmo já medido: 0,67 s em produção e **59 s em homolog**. A função varre o
 * MAX(post_modified_gmt) de cada tipo público — trinta e tantos neste site, incluindo
 * `attachment` e `tdb_templates` — e guarda o resultado em `wp_cache`, que sem cache de
 * objeto persistente vive só a requisição. Robô que visita o feed paga a conta inteira, toda
 * vez. É o mesmo estrangulamento que derruba o sitemap em homolog.
 *
 * O WP 6.8 oferece `pre_get_lastpostmodified` para curto-circuitar. Aqui ele:
 *   - só age na requisição do `feedbahiaba` — fora dela, o core segue intocado;
 *   - só trata o fuso 'gmt', que é o que o `send_headers()` pede;
 *   - responde de um transient de 5 minutos e, quando frio, faz UMA consulta restrita aos
 *     tipos que este feed publica, em vez de uma por tipo público do site.
 *
 * Cinco minutos de defasagem no `Last-Modified` de um feed de 5 itens não têm consequência:
 * o cabeçalho serve para o leitor de RSS decidir se relê, e o conteúdo em si vem da consulta,
 * sempre fresco.
 */
define('BAHIA_FEEDS_LASTMOD_KEY', 'bahia_feeds_lastmod_gmt');

add_filter('pre_get_lastpostmodified', 'bahia_feeds_lastmod_barato', 10, 2);
function bahia_feeds_lastmod_barato($pre, $timezone) {
    if (false !== $pre || 'gmt' !== strtolower((string) $timezone)) {
        return $pre;
    }
    if (empty($GLOBALS['wp']->query_vars['feed']) || 'feedbahiaba' !== $GLOBALS['wp']->query_vars['feed']) {
        return $pre;
    }

    $cache = get_transient(BAHIA_FEEDS_LASTMOD_KEY);
    if ($cache) {
        return $cache;
    }

    /**
     * A CONSULTA PRECISA CASAR COM O ÍNDICE, e a primeira tentativa não casava.
     *
     * O óbvio era `SELECT MAX(post_modified_gmt) ... WHERE post_status='publish' AND post_type
     * IN (25)`. Medido em homolog: **28,83 s**. O índice que o WordPress mantém é
     * `type_status_date (post_type, post_status, post_date, ID)` — ordenado por post_**date**.
     * Pedir o máximo de post_**modified** obriga a varrer todas as ~430 mil linhas que casam.
     * Metade dos 59 s do core, e ainda assim inaceitável.
     *
     * A consulta abaixo ordena por `post_date DESC LIMIT 1`, que é exatamente o que o índice
     * já entrega: uma varredura de faixa por tipo, pegando a última linha de cada. Devolve a
     * data de modificação do post mais recentemente PUBLICADO, e não a modificação mais
     * recente do acervo.
     *
     * A diferença importa? Para este cabeçalho, não. `Last-Modified` diz ao leitor de RSS se
     * vale reler o canal, e o canal são os 5 itens mais recentes por data de publicação —
     * exatamente o conjunto que esta consulta acompanha. Uma edição feita numa matéria de
     * 2019 não muda o que o feed publica, e não deveria mesmo invalidá-lo.
     */
    global $wpdb;
    $in  = "'" . implode("','", array_map('esc_sql', bahia_feeds_tipos())) . "'";
    $val = $wpdb->get_var("SELECT post_modified_gmt FROM {$wpdb->posts}
                            WHERE post_status = 'publish' AND post_type IN ($in)
                            ORDER BY post_date DESC LIMIT 1");
    if (!$val) {
        return $pre;   // sem resposta: deixa o core fazer do jeito dele
    }

    set_transient(BAHIA_FEEDS_LASTMOD_KEY, $val, 5 * MINUTE_IN_SECONDS);

    return $val;
}

function bahia_feeds_render() {
    /**
     * `lastBuildDate` SAI DA CONSULTA QUE JÁ FIZEMOS, e não de `get_lastpostmodified('GMT')`.
     *
     * Medido em 18/08/2026, cronometrando o template peça por peça:
     *
     *     get_lastpostmodified('GMT')   produção 0,67 s  |  homolog 59,35 s
     *     todo o resto do feed somado   produção 0,06 s  |  homolog  0,18 s
     *
     * Uma linha respondia por praticamente todo o custo do feed nos DOIS ambientes. O core
     * varre o MAX(post_modified_gmt) de cada tipo público — e este site tem 30 e poucos,
     * incluindo `attachment` e `tdb_templates`, que nada têm a ver com este feed. O resultado
     * fica em `wp_cache`, que aqui é por requisição: sem cache de objeto persistente, é
     * recalculado em toda visita de robô.
     *
     * Em produção isso passava despercebido dentro dos ~2 s do feed. Em homolog derrubava a
     * página inteira. É a mesma disparidade de RDS já documentada no sitemap — mas aqui não
     * há motivo para pagar nem 0,67 s: o post mais recente do feed já está em `$wp_query`.
     *
     * Semanticamente também fica melhor: `lastBuildDate` passa a descrever ESTE canal, em vez
     * de um máximo global sobre tipos que o canal não publica.
     */
    $ultima = '';
    if (!empty($GLOBALS['wp_query']->posts[0]->post_modified_gmt)) {
        $ultima = $GLOBALS['wp_query']->posts[0]->post_modified_gmt;
    }
    $last_build = mysql2date('D, d M Y H:i:s +0000', $ultima ? $ultima : gmdate('Y-m-d H:i:s'), false);

    $charset = get_option('blog_charset');
    header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . $charset, true);
    echo '<?xml version="1.0" encoding="' . esc_attr($charset) . '"?' . '>';

    // `rss_language` é gravado pelo tema em admin_init; como é option, sobrevive a ele. O
    // fallback existe para o caso de a option nunca ter sido escrita no destino.
    $lang = get_option('rss_language');
    if (!$lang) {
        $lang = get_bloginfo('language');
    }
    ?>
<rss version="2.0"
     xmlns:content="http://purl.org/rss/1.0/modules/content/"
     xmlns:wfw="http://wellformedweb.org/CommentAPI/"
     xmlns:dc="http://purl.org/dc/elements/1.1/"
     xmlns:atom="http://www.w3.org/2005/Atom"
     xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
     xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
     <?php do_action('rss2_ns'); ?>>
<channel>
    <title><?php bloginfo_rss('name'); ?> - Feed</title>
    <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
    <link><?php bloginfo_rss('url'); ?></link>
    <description><?php bloginfo_rss('description'); ?></description>
    <lastBuildDate><?php echo esc_html($last_build); ?></lastBuildDate>
    <language><?php echo esc_html($lang); ?></language>
    <sy:updatePeriod><?php echo apply_filters('rss_update_period', 'hourly'); ?></sy:updatePeriod>
    <sy:updateFrequency><?php echo apply_filters('rss_update_frequency', '1'); ?></sy:updateFrequency>
    <?php do_action('rss2_head'); ?>
    <?php while (have_posts()) : the_post(); ?>
    <item>
        <title><?php the_title_rss(); ?></title>
        <link><?php the_permalink_rss(); ?></link>
        <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
        <dc:creator><?php the_author(); ?></dc:creator>
        <guid isPermaLink="false"><?php the_guid(); ?></guid>
        <description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
        <content:encoded><![CDATA[<?php the_excerpt_rss(); ?>]]></content:encoded>
        <?php rss_enclosure(); ?>
        <?php do_action('rss2_item'); ?>
    </item>
    <?php endwhile; ?>
</channel>
</rss>
    <?php
}
