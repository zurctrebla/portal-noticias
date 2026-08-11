<?php
/**
 * Plugin Name: Bahia.ba - Archive de autor (editorias + coautoria)
 * Description: /author/<slug>/ não listava matéria nenhuma. São dois defeitos empilhados,
 *              e o segundo só apareceu depois de corrigir o primeiro.
 *
 *              1) POST TYPE. O archive de autor do WordPress consulta só post_type='post'
 *                 (padrão de WP_Query) e todo o conteúdo editorial do bahia.ba vive em CPTs
 *                 (politica, salvador, esporte, ...). A query rodava contra um post_type
 *                 sem matéria e devolvia zero para qualquer autor. Não é problema do
 *                 Co-Authors Plus: ele está ativo, a taxonomia `author` existe e as
 *                 relações estão gravadas (cap-neison-cerqueira tem 2.036 objetos).
 *
 *              2) DESEMPENHO. Corrigido o post_type, a página respondia — em ~39 s, à
 *                 beira do timeout. O SQL do CAP (posts_where_filter) resolve
 *                 "autor primário OU coautor" com LEFT JOIN em wp_term_relationships
 *                 (253 mil linhas) + GROUP BY + HAVING sobre wp_posts inteiro (435 mil
 *                 linhas). O OR impede o MySQL de usar índice nos dois lados, então
 *                 vira varredura. Medido: 31-39 s por autor.
 *
 *                 Aqui o mesmo conjunto é obtido por UNION de dois ramos, cada um
 *                 indexado — post_author de um lado, term_taxonomy_id do outro — e
 *                 injetado como subconsulta em wp_posts.ID IN (...). Medido: ~0,6 s,
 *                 com contagem idêntica à do CAP (validado em 4 autores).
 *
 *              SEMÂNTICA PRESERVADA. O ramo do autor primário só vale para posts que não
 *              têm nenhum termo `author` — que é exatamente o fallback do próprio CAP
 *              (get_coauthors() usa post_author quando não há termo). Isso importa porque
 *              28.379 posts publicados (11,7%) não têm o termo do autor primário, resíduo
 *              da importação: usar só a taxonomia esconderia essa fatia, e usar sempre o
 *              post_author contrariaria a reatribuição de coautoria feita na redação.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Post types que a página de autor deve listar: o 'post' nativo + as editorias.
 *
 * Filtrado por post_type_exists() porque o banco ainda guarda matérias em CPTs
 * aposentados (ex.: 'saude', 3 posts) que ninguém registra mais. Não se usa 'any':
 * arrastaria attachment, foogallery, tdb_templates e tdc-review para a listagem.
 */
function bahia_autor_archive_post_types() {
    $types = array('post');

    if (function_exists('bahia_editorias_map')) {
        $types = array_merge($types, array_keys(bahia_editorias_map()));
    }

    $types = array_values(array_filter(array_unique($types), 'post_type_exists'));

    /**
     * Permite ajustar a lista sem editar este mu-plugin.
     * @param string[] $types
     */
    return apply_filters('bahia_autor_archive_post_types', $types);
}

/**
 * Termo da taxonomia `author` (Co-Authors Plus) de um usuário, ou 0.
 * O CAP nomeia o termo como "cap-{user_nicename}".
 */
function bahia_autor_archive_term_id(WP_User $user) {
    $term = get_term_by('slug', 'cap-' . $user->user_nicename, 'author');
    return ($term && !is_wp_error($term)) ? (int) $term->term_id : 0;
}

/**
 * Reconfigura a query principal do archive de autor.
 *
 * Escopo estreito: só a query principal, só no front, só em is_author().
 */
function bahia_autor_archive_pre_get_posts($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_author()) {
        return;
    }

    // Resolve o usuário a partir de author_name (/author/slug/) ou author (?author=ID).
    $slug = $query->get('author_name');
    $user = $slug ? get_user_by('slug', $slug) : get_userdata((int) $query->get('author'));
    if (!$user instanceof WP_User) {
        return; // deixa o WordPress seguir para o 404
    }

    $query->set('post_type', bahia_autor_archive_post_types());

    // Os query vars de autor ficam INTACTOS de propósito. Limpá-los evitaria o
    // "AND post_author = X" nativo de graça, mas leva junto o $authordata global —
    // e aí o título do arquivo e o %%name%% do Yoast passam a refletir o autor do
    // primeiro post da lista, não o dono da página (observado: /author/lula-bonfim/
    // saía como "André Souza"). Em vez disso, a cláusula nativa é removida no SQL,
    // em bahia_autor_archive_where().

    // O SQL do CAP é justamente o que queremos substituir (ver cabeçalho). Removido
    // só nesta requisição e restaurado em 'the_posts', logo após a query rodar.
    bahia_autor_archive_toggle_cap(false);

    $GLOBALS['bahia_autor_archive_user'] = $user;
    add_filter('posts_where', 'bahia_autor_archive_where', 10, 2);
    add_filter('the_posts', 'bahia_autor_archive_cleanup', 10, 2);
}
add_action('pre_get_posts', 'bahia_autor_archive_pre_get_posts');

/**
 * Liga/desliga os três filtros de SQL do Co-Authors Plus.
 */
function bahia_autor_archive_toggle_cap($ligar) {
    global $coauthors_plus;
    if (!is_object($coauthors_plus)) {
        return;
    }
    $filtros = array(
        'posts_where'   => 'posts_where_filter',
        'posts_join'    => 'posts_join_filter',
        'posts_groupby' => 'posts_groupby_filter',
    );
    foreach ($filtros as $hook => $metodo) {
        if (!method_exists($coauthors_plus, $metodo)) {
            continue;
        }
        if ($ligar) {
            add_filter($hook, array($coauthors_plus, $metodo), 10, 2);
        } else {
            remove_filter($hook, array($coauthors_plus, $metodo), 10);
        }
    }
}

/**
 * Injeta a condição de autoria como subconsulta indexada.
 *
 * Ramo A: autor primário, apenas em posts sem nenhum termo `author` (fallback do CAP).
 * Ramo B: qualquer post que carregue o termo `author` da pessoa (coautoria inclusive).
 *
 * Os ramos não filtram post_type nem post_status de propósito: a query externa já
 * aplica os dois, e repetir aqui só atrapalharia o plano de execução.
 */
function bahia_autor_archive_where($where, $query) {
    if (!$query->is_main_query() || empty($GLOBALS['bahia_autor_archive_user'])) {
        return $where;
    }
    global $wpdb;

    $user    = $GLOBALS['bahia_autor_archive_user'];
    $uid     = (int) $user->ID;
    $term_id = bahia_autor_archive_term_id($user);

    $ramo_a = "SELECT bp.ID FROM {$wpdb->posts} bp
               WHERE bp.post_author = {$uid}
                 AND NOT EXISTS (
                     SELECT 1 FROM {$wpdb->term_relationships} btr
                     JOIN {$wpdb->term_taxonomy} btt
                       ON btt.term_taxonomy_id = btr.term_taxonomy_id
                     WHERE btr.object_id = bp.ID AND btt.taxonomy = 'author'
                 )";

    if ($term_id > 0) {
        $ramo_b = "SELECT btr2.object_id AS ID
                   FROM {$wpdb->term_relationships} btr2
                   JOIN {$wpdb->term_taxonomy} btt2
                     ON btt2.term_taxonomy_id = btr2.term_taxonomy_id
                    AND btt2.taxonomy = 'author'
                    AND btt2.term_id = {$term_id}";
        $uniao = "{$ramo_a} UNION {$ramo_b}";
    } else {
        // Autor sem termo CAP (raro): só o autor primário, e aí sem a exigência de
        // "post sem termo algum" — do contrário a pessoa perderia os próprios posts.
        $uniao = "SELECT bp.ID FROM {$wpdb->posts} bp WHERE bp.post_author = {$uid}";
    }

    // Remove a cláusula de autor que o WordPress montou a partir do query var
    // (formato " AND (wp_posts.post_author = 139)", ou a variante IN (...) quando há
    // mais de um id). Sem isso ela faria AND com a nossa e a página voltaria a
    // esconder os posts em que a pessoa é apenas coautora.
    $padrao = '#\s+AND\s+\(\s*' . preg_quote($wpdb->posts, '#')
            . '\.post_author\s+(?:=\s*' . $uid . '|IN\s*\(\s*' . $uid . '\s*\))\s*\)#';
    $limpo  = preg_replace($padrao, '', $where, 1, $trocas);

    if ($trocas === 1) {
        $where = $limpo;
    }
    // Se não casou (mudança de formato numa atualização do core), seguimos assim
    // mesmo: a nossa condição entra por AND e o resultado degrada para "só os posts
    // em que é autor primário" — o comportamento anterior, nunca algo pior.

    return $where . " AND {$wpdb->posts}.ID IN ( SELECT bz.ID FROM ( {$uniao} ) bz )";
}

/**
 * Desmonta tudo assim que a query principal termina, para não afetar queries
 * secundárias da mesma página (blocos do TagDiv, "Mais Lidas", relacionados).
 */
function bahia_autor_archive_cleanup($posts, $query) {
    if (!$query->is_main_query()) {
        return $posts;
    }
    remove_filter('posts_where', 'bahia_autor_archive_where', 10);
    remove_filter('the_posts', 'bahia_autor_archive_cleanup', 10);
    unset($GLOBALS['bahia_autor_archive_user']);
    bahia_autor_archive_toggle_cap(true);
    return $posts;
}
