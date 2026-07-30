<?php
/**
 * Plugin Name: Bahia FULLTEXT Search
 * Description: Substitui a busca padrão do WordPress (LIKE '%termo%', que faz varredura
 *   completa e leva ~40s em wp_posts com ~435k linhas) por MATCH..AGAINST em NATURAL LANGUAGE
 *   MODE usando o índice FULLTEXT `bahia_ft_search` (post_title, post_excerpt), com resultados
 *   ordenados por relevância. Reduz a busca de dezenas de segundos para ~100ms — corrige a
 *   lupa do header que "travava" (timeout).
 *
 * OBS 1 (escopo do índice): cobre TÍTULO + RESUMO, não o corpo (post_content). O RDS homolog
 *   derruba conexões de DDL longo (~140s) e indexar o post_content (~957MB) estoura esse limite.
 *   Título+resumo cobre a maioria das buscas de notícia. Para busca no corpo do texto, usar
 *   Relevanssi ou uma janela de manutenção dedicada p/ o índice completo.
 *
 * OBS 2 (ordenação): resultados vêm por RELEVÂNCIA do FULLTEXT (não por data). É mais rápido
 *   (o MySQL curto-circuita com LIMIT) e normalmente mais útil numa busca. Ordenar por data
 *   forçaria filesort sobre milhares de linhas (lento no RDS pequeno).
 *
 * OBS 3 (termos curtos): palavras com menos de innodb_ft_min_token_size (3) são ignoradas pelo
 *   FULLTEXT; buscas só com termos curtos retornam vazio instantaneamente (em vez de cair no
 *   LIKE lento).
 *
 * Seguro por padrão: se o índice ainda não existir (ex.: produção antes do ALTER), o filtro
 * não altera nada e a busca cai no comportamento original. Rode o ALTER e limpe o transient
 * `bahia_ft_index_ready` para ativar.
 *
 * Índice necessário:
 *   ALTER TABLE wp_posts ADD FULLTEXT INDEX bahia_ft_search (post_title, post_excerpt);
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * O índice FULLTEXT existe? (cacheado em transient para não bater no information_schema a
 * cada request). Retorna false até o ALTER ser executado.
 */
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
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '{$wpdb->posts}'
           AND INDEX_NAME = 'bahia_ft_search'"
    );
    $ready = $exists > 0;
    set_transient('bahia_ft_index_ready', $ready ? '1' : '0', DAY_IN_SECONDS);
    return $ready;
}

/**
 * Normaliza o termo digitado para NATURAL LANGUAGE MODE: remove operadores do fulltext,
 * colapsa espaços. Retorna '' se vazio.
 */
function bahia_ft_terms($s) {
    $s = wp_unslash((string) $s);
    $s = str_replace(array('+', '-', '<', '>', '(', ')', '~', '*', '"', '@', '\\'), ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

/**
 * O nosso MATCH se aplica a esta query? (é busca + índice pronto + termo não vazio)
 */
function bahia_ft_applies($wp_query) {
    if (empty($wp_query->query_vars['s'])) {
        return false;
    }
    if (!bahia_ft_index_ready()) {
        return false;
    }
    return bahia_ft_terms($wp_query->query_vars['s']) !== '';
}

/**
 * Substitui a cláusula de busca (LIKE) por MATCH..AGAINST em NATURAL LANGUAGE MODE.
 * Aplica-se a qualquer WP_Query de busca (query principal, blocos do TagDiv e AJAX da lupa).
 */
add_filter('posts_search', 'bahia_ft_posts_search', 10, 2);
function bahia_ft_posts_search($search, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $search;
    }
    $terms = bahia_ft_terms($wp_query->query_vars['s']);
    return $wpdb->prepare(
        " AND MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s) ",
        $terms
    );
}

/**
 * Ordena por relevância do FULLTEXT (rápido, curto-circuita com LIMIT). Substitui o ORDER BY
 * da query de busca inteiro.
 */
add_filter('posts_orderby', 'bahia_ft_posts_orderby', 10, 2);
function bahia_ft_posts_orderby($orderby, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $orderby;
    }
    $terms = bahia_ft_terms($wp_query->query_vars['s']);
    return $wpdb->prepare(
        "MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s) DESC",
        $terms
    );
}

/**
 * Remove o ORDER BY de relevância padrão do WP (CASE..LIKE nos termos) — substituído pelo
 * ORDER BY de relevância do fulltext acima.
 */
add_filter('posts_search_orderby', 'bahia_ft_search_orderby', 10, 2);
function bahia_ft_search_orderby($orderby, $wp_query) {
    return bahia_ft_applies($wp_query) ? '' : $orderby;
}

/**
 * Teto de resultados contados. O RDS homolog é lento para contar TODAS as linhas de um termo
 * comum ("bahia"/"salvador" casam ~15k → ~10s). Como a query principal ordena por relevância e
 * corta no LIMIT (rápida, ~400ms), limitamos o total a este teto: o usuário vê até
 * BAHIA_FT_MAX_COUNT resultados (mais relevantes), o suficiente para uma busca de notícias.
 */
if (!defined('BAHIA_FT_MAX_COUNT')) {
    define('BAHIA_FT_MAX_COUNT', 500);
}

/**
 * Remove o SQL_CALC_FOUND_ROWS da query de busca (o total completo custava vários segundos em
 * termos comuns). O total passa a vir de um COUNT com teto (barato) via found_posts_query.
 */
add_filter('posts_request', 'bahia_ft_posts_request', 10, 2);
function bahia_ft_posts_request($request, $wp_query) {
    if (!bahia_ft_applies($wp_query)) {
        return $request;
    }
    return preg_replace('/^\s*SELECT\s+SQL_CALC_FOUND_ROWS\b/i', 'SELECT', $request, 1);
}

/**
 * Fornece found_posts via COUNT com teto (subquery LIMIT), que o MySQL encerra cedo — rápido e
 * estável mesmo em termo comum. Mantém a paginação (max_num_pages) funcionando.
 */
add_filter('found_posts_query', 'bahia_ft_found_posts_query', 10, 2);
function bahia_ft_found_posts_query($sql, $wp_query) {
    global $wpdb;
    if (!bahia_ft_applies($wp_query)) {
        return $sql;
    }
    $terms = bahia_ft_terms($wp_query->query_vars['s']);
    $cap = (int) BAHIA_FT_MAX_COUNT + 1;
    return $wpdb->prepare(
        "SELECT COUNT(*) FROM (SELECT 1 FROM {$wpdb->posts}
         WHERE post_status IN ('publish','acf-disabled')
         AND MATCH({$wpdb->posts}.post_title, {$wpdb->posts}.post_excerpt) AGAINST (%s)
         LIMIT %d) t",
        $terms,
        $cap
    );
}
