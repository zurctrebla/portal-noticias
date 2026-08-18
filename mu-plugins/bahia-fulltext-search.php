<?php
/**
 * Plugin Name: Bahia FULLTEXT Search
 * Description: Busca rápida por MATCH..AGAINST (índice FULLTEXT) em vez do LIKE '%termo%'
 *   padrão do WP (que levava ~40s em wp_posts com centenas de milhares de linhas).
 *
 *   Ordenação por DATA (mais recente primeiro), como portal de notícias — o BOOLEAN MODE
 *   com "+palavra*" decide QUAIS linhas casam (match parcial/prefixo: "jero" acha
 *   "jeronimo") e a data ordena. A collation utf8*_general_ci deixa a busca accent/case
 *   insensitive de graça ("sao" acha "São").
 *
 *   Como ordenar por data TODAS as linhas casadas de um termo comum ("bahia" ~13k) em
 *   wp_posts faz filesort lendo linhas gigantes (post_content ~1GB) e leva 10-16s, usamos
 *   uma TABELA-SOMBRA enxuta ({prefix}bahia_search_idx: ID, post_date, post_title,
 *   post_excerpt + FULLTEXT + índice de data) — sem post_content. Nela o match + ordenação
 *   por data são rápidos (dezenas a ~1500ms). A tabela é mantida em sincronia via hooks
 *   de save/delete e pode ser reconstruída via bahia_ft_rebuild() (WP-CLI: wp eval).
 *
 *   Fallback seguro: se a tabela-sombra ainda não existir/estiver vazia (ex.: produção
 *   antes do rebuild), cai no MATCH sobre wp_posts (índice FULLTEXT bahia_ft_search),
 *   ordenado por relevância; e se nem esse índice existir, na busca padrão do WP.
 *
 * Índices necessários:
 *   - Tabela-sombra: criada automaticamente (estrutura) + bahia_ft_rebuild() para popular.
 *   - Fallback:  ALTER TABLE wp_posts ADD FULLTEXT INDEX bahia_ft_search (post_title, post_excerpt);
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('BAHIA_FT_MAX_COUNT')) {
    define('BAHIA_FT_MAX_COUNT', 500); // teto de resultados (mais recentes) exibidos/contados
}

/** Nome da tabela-sombra de busca. */
function bahia_ft_table() {
    global $wpdb;
    return $wpdb->prefix . 'bahia_search_idx';
}

/**
 * Post types indexados/buscáveis. É a UNIÃO de duas fontes, nunca uma no lugar da outra.
 *
 * A versão anterior escolhia entre elas: usava bahia_editorias_map() quando existisse
 * e, só na ausência dele, perguntava ao WordPress. Isso é uma armadilha, porque esta
 * lista não decide apenas o que a busca cobre — ela decide, em bahia_ft_sync(), o que
 * é APAGADO da tabela-sombra a cada save_post. Um tipo que exista no site e falte na
 * lista some do índice silenciosamente, uma matéria por vez, e um bahia_ft_rebuild()
 * o elimina de uma vez.
 *
 * Medido em 18/08/2026, na véspera da troca de tema: em produção o mapa não existia e
 * o WordPress devolvia 25 tipos; passar a usar só o mapa deixaria 'page' de fora, além
 * de qualquer editoria que o mapa não listasse. A união não perde nenhum dos dois lados
 * e degrada bem: se um deles ficar incompleto, o outro cobre.
 *
 *  - WordPress: a verdade sobre o que está registrado AGORA, seja pelo tema antigo
 *    (bahia_refactor registra as editorias sozinho) ou pelo mu-plugin de CPTs.
 *  - bahia_editorias_map(): garante as editorias mesmo em ordem de carregamento em que
 *    elas ainda não estejam registradas quando esta função for chamada.
 */
function bahia_ft_types() {
    $types = get_post_types(array('public' => true, 'exclude_from_search' => false), 'names');
    unset($types['attachment']);
    $types = array_values($types);

    if (function_exists('bahia_editorias_map')) {
        $types = array_merge($types, array_keys(bahia_editorias_map()));
    }

    $types[] = 'post';
    return array_values(array_unique($types));
}

/* -------------------------------------------------------------------------
 *  Detecção de disponibilidade (cacheadas em transient)
 * ------------------------------------------------------------------------- */

/** A tabela-sombra existe e tem linhas? */
function bahia_ft_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $cached = get_transient('bahia_ft_shadow_ready');
    if ($cached === '1' || $cached === '0') {
        return $ready = ($cached === '1');
    }
    global $wpdb;
    $t = bahia_ft_table();
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
        $t
    ));
    $ready = false;
    if ($exists) {
        $ready = ((int) $wpdb->get_var("SELECT COUNT(1) FROM (SELECT 1 FROM {$t} LIMIT 1) x")) > 0;
    }
    set_transient('bahia_ft_shadow_ready', $ready ? '1' : '0', HOUR_IN_SECONDS);
    return $ready;
}

/** O índice FULLTEXT no wp_posts existe? (fallback) */
function bahia_ft_index_ready() {
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $cached = get_transient('bahia_ft_index_ready');
    if ($cached === '1' || $cached === '0') {
        return $ready = ($cached === '1');
    }
    global $wpdb;
    $exists = (int) $wpdb->get_var(
        "SELECT COUNT(1) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$wpdb->posts}' AND INDEX_NAME = 'bahia_ft_search'"
    );
    $ready = $exists > 0;
    set_transient('bahia_ft_index_ready', $ready ? '1' : '0', DAY_IN_SECONDS);
    return $ready;
}

/* -------------------------------------------------------------------------
 *  Normalização de termos
 * ------------------------------------------------------------------------- */

/** Termo limpo (para detectar se há busca). '' se vazio. */
function bahia_ft_terms($s) {
    $s = wp_unslash((string) $s);
    $s = str_replace(array('+', '-', '<', '>', '(', ')', '~', '*', '"', '@', '\\'), ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * Expressão BOOLEAN MODE com match parcial/prefixo: cada palavra vira "+palavra*".
 * Ex.: "jero" => "+jero*"; "sao paulo" => "+sao* +paulo*". Palavras < min token (3)
 * são ignoradas. '' se não sobrar termo utilizável.
 */
function bahia_ft_boolean($s) {
    $clean = bahia_ft_terms($s);
    if ($clean === '') {
        return '';
    }
    $out = array();
    foreach (explode(' ', $clean) as $w) {
        $len = function_exists('mb_strlen') ? mb_strlen($w) : strlen($w);
        if ($len >= 3) {
            $out[] = '+' . $w . '*';
        }
    }
    return implode(' ', $out);
}

/** A nossa busca se aplica? (é busca + tem alguma fonte pronta + termo não vazio) */
function bahia_ft_applies($wp_query) {
    if (empty($wp_query->query_vars['s'])) {
        return false;
    }

    // O PAINEL FICA DE FORA.
    //
    // A tabela-sombra guarda apenas post_status = 'publish'. Sem esta guarda, a
    // busca de /wp-admin/edit.php passava a ser respondida pela sombra e o
    // reporter NAO encontrava o proprio rascunho, pendente ou agendado — sem
    // erro nenhum, o que e pior. Conferido em producao: buscando "Chapecoense"
    // com post_status=any, o post agendado cujo titulo comeca com essa palavra
    // ficava de fora dos 50 resultados.
    //
    // O !wp_doing_ajax() e proposital: is_admin() tambem e verdadeiro em
    // admin-ajax.php, que e por onde o front-end carrega mais conteudo. Sem essa
    // parte, a busca do site perderia o FULLTEXT nessas chamadas.
    if (is_admin() && !wp_doing_ajax()) {
        return false;
    }

    // Cinto e suspensorio: qualquer consulta que peca status diferente de
    // 'publish' (o painel pede 'any') seria respondida errado pela sombra.
    $status = isset($wp_query->query_vars['post_status']) ? $wp_query->query_vars['post_status'] : '';
    if (!empty($status)) {
        foreach ((array) $status as $st) {
            if ($st !== 'publish') {
                return false;
            }
        }
    }

    if (!bahia_ft_ready() && !bahia_ft_index_ready()) {
        return false;
    }
    return bahia_ft_terms($wp_query->query_vars['s']) !== '';
}

/* -------------------------------------------------------------------------
 *  Reescrita da busca
 * ------------------------------------------------------------------------- */

/**
 * Substitui a cláusula LIKE por:
 *  - tabela-sombra pronta: ID IN (500 casamentos MAIS RECENTES) — data correta e rápido;
 *  - senão: MATCH em wp_posts (fallback).
 */
add_filter('posts_search', 'bahia_ft_posts_search', 10, 2);
function bahia_ft_posts_search($search, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $search;
    }
    $bool = bahia_ft_boolean($wp_query->query_vars['s']);
    if ($bool === '') {
        return ' AND 1=0 '; // só termos curtos -> vazio rápido
    }

    if (bahia_ft_ready()) {
        $t   = bahia_ft_table();
        $cap = (int) BAHIA_FT_MAX_COUNT;
        // Subquery na tabela enxuta: os N casamentos mais RECENTES (rápido). A query
        // externa restringe wp_posts a esses IDs e ordena por data.
        return $wpdb->prepare(
            " AND {$wpdb->posts}.ID IN (
                SELECT bahia_ft_id FROM (
                  SELECT {$t}.ID AS bahia_ft_id
                  FROM {$t}
                  WHERE MATCH({$t}.post_title, {$t}.post_excerpt) AGAINST (%s IN BOOLEAN MODE)
                  ORDER BY {$t}.post_date DESC
                  LIMIT %d
                ) bahia_ft_sub
            ) ",
            $bool, $cap
        );
    }

    // Fallback: MATCH direto em wp_posts.
    return $wpdb->prepare(
        " AND MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s IN BOOLEAN MODE) ",
        $bool
    );
}

/**
 * Ordenação: por DATA quando temos a tabela-sombra (o IN já traz os mais recentes);
 * no fallback (wp_posts), por relevância (evita o filesort de milhares de linhas grandes).
 */
add_filter('posts_orderby', 'bahia_ft_posts_orderby', 10, 2);
function bahia_ft_posts_orderby($orderby, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $orderby;
    }
    if (bahia_ft_ready()) {
        return "{$wpdb->posts}.post_date DESC, {$wpdb->posts}.ID DESC";
    }
    $bool = bahia_ft_boolean($wp_query->query_vars['s']);
    if ($bool === '') {
        return $orderby;
    }
    return $wpdb->prepare(
        "MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s IN BOOLEAN MODE) DESC",
        $bool
    );
}

/** Remove o ORDER BY de relevância padrão do WP (CASE..LIKE). */
add_filter('posts_search_orderby', 'bahia_ft_search_orderby', 10, 2);
function bahia_ft_search_orderby($orderby, $wp_query) {
    return bahia_ft_applies($wp_query) ? '' : $orderby;
}

/** Remove SQL_CALC_FOUND_ROWS (caro). */
add_filter('posts_request', 'bahia_ft_posts_request', 10, 2);
function bahia_ft_posts_request($request, $wp_query) {
    if (!bahia_ft_applies($wp_query)) {
        return $request;
    }
    return preg_replace('/^\s*SELECT\s+SQL_CALC_FOUND_ROWS\b/i', 'SELECT', $request, 1);
}

/** found_posts via COUNT com teto (barato), da fonte disponível. */
add_filter('found_posts_query', 'bahia_ft_found_posts_query', 10, 2);
function bahia_ft_found_posts_query($sql, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $sql;
    }
    $bool = bahia_ft_boolean($wp_query->query_vars['s']);
    if ($bool === '') {
        return "SELECT 0";
    }
    $cap = (int) BAHIA_FT_MAX_COUNT + 1;
    if (bahia_ft_ready()) {
        $t = bahia_ft_table();
        return $wpdb->prepare(
            "SELECT COUNT(*) FROM (SELECT 1 FROM {$t}
             WHERE MATCH({$t}.post_title, {$t}.post_excerpt) AGAINST (%s IN BOOLEAN MODE) LIMIT %d) x",
            $bool, $cap
        );
    }
    return $wpdb->prepare(
        "SELECT COUNT(*) FROM (SELECT 1 FROM {$wpdb->posts}
         WHERE post_status IN ('publish','acf-disabled')
         AND MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s IN BOOLEAN MODE)
         LIMIT %d) t",
        $bool, $cap
    );
}

/* -------------------------------------------------------------------------
 *  Manutenção da tabela-sombra
 * ------------------------------------------------------------------------- */

/** Cria a estrutura da tabela-sombra se não existir (barato; NÃO popula). */
function bahia_ft_maybe_create_table() {
    global $wpdb;
    $t = bahia_ft_table();
    $exists = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
        $t
    ));
    if ($exists) {
        return;
    }
    $wpdb->query(
        "CREATE TABLE {$t} (
            ID bigint unsigned NOT NULL PRIMARY KEY,
            post_date datetime NOT NULL,
            post_title text,
            post_excerpt text,
            KEY date_idx (post_date),
            FULLTEXT KEY ft (post_title, post_excerpt)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    delete_transient('bahia_ft_shadow_ready');
}
add_action('init', 'bahia_ft_maybe_create_table', 1);

/** Sincroniza UM post na tabela-sombra (upsert se buscável+publicado; senão remove). */
function bahia_ft_sync($post_id) {
    if (wp_is_post_revision($post_id) || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    global $wpdb;
    $t = bahia_ft_table();
    $p = get_post($post_id);
    $ok = $p && $p->post_status === 'publish' && in_array($p->post_type, bahia_ft_types(), true);
    if ($ok) {
        $wpdb->query($wpdb->prepare(
            "REPLACE INTO {$t} (ID, post_date, post_title, post_excerpt) VALUES (%d, %s, %s, %s)",
            $p->ID, $p->post_date, $p->post_title, $p->post_excerpt
        ));
    } else {
        $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE ID = %d", (int) $post_id));
    }
}
add_action('save_post', 'bahia_ft_sync', 20, 1);
add_action('edit_post', 'bahia_ft_sync', 20, 1);
add_action('transition_post_status', function ($new, $old, $post) {
    bahia_ft_sync($post->ID);
}, 20, 3);
add_action('deleted_post', function ($post_id) {
    global $wpdb;
    $wpdb->query($wpdb->prepare("DELETE FROM " . bahia_ft_table() . " WHERE ID = %d", (int) $post_id));
});

/**
 * (Re)constrói a tabela-sombra do zero. Rodar via WP-CLI:
 *   wp eval 'bahia_ft_rebuild();'
 * Retorna o número de linhas indexadas.
 */
function bahia_ft_rebuild() {
    global $wpdb;
    $t = bahia_ft_table();
    $types = bahia_ft_types();
    $place = implode(',', array_fill(0, count($types), '%s'));

    $wpdb->query("DROP TABLE IF EXISTS {$t}");
    $wpdb->query(
        "CREATE TABLE {$t} (
            ID bigint unsigned NOT NULL PRIMARY KEY,
            post_date datetime NOT NULL,
            post_title text,
            post_excerpt text,
            KEY date_idx (post_date)
         ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    $n = $wpdb->query($wpdb->prepare(
        "INSERT INTO {$t} (ID, post_date, post_title, post_excerpt)
         SELECT ID, post_date, post_title, post_excerpt FROM {$wpdb->posts}
         WHERE post_status = 'publish' AND post_type IN ($place)",
        $types
    ));
    // FULLTEXT depois do bulk insert (mais rápido).
    $wpdb->query("ALTER TABLE {$t} ADD FULLTEXT KEY ft (post_title, post_excerpt)");

    delete_transient('bahia_ft_shadow_ready');
    return (int) $n;
}
