<?php
/**
 * Plugin Name: Bahia.ba - Autoria múltipla no post individual
 * Description: O bahia.ba usa Co-Authors Plus (taxonomia `author`) e há matérias com
 *              mais de um repórter (ex.: post 425868 = 4 autores). O byline do tema
 *              devolve só o autor nativo (o 1º coautor). Este filtro faz o byline do
 *              POST PRINCIPAL exibir todos os coautores ("Por A, B e C") quando houver
 *              2+; posts de autor único ficam intactos.
 *
 *              ADAPTAÇÃO PARA O bahia_refactor: o tema monta o byline com the_author()
 *              (single_web.php, single_mobile.php), que aplica o filtro `the_author` —
 *              e NÃO `get_the_author_display_name`, usado pelo Newspaper via
 *              get_the_author_meta('display_name'). Enganchar só naquele filtro seria
 *              um no-op silencioso aqui. Registramos os dois: o do core para o tema
 *              atual e o de meta para qualquer plugin que consulte display_name.
 *
 *              Escopo restrito (não afeta cards de "relacionados"/próximo-anterior nem
 *              arquivos nem o feed RSS): só age no contexto do post consultado
 *              (get_the_ID() === get_queried_object_id()). Display-time, sem escrever
 *              no banco.
 * Version: 1.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Nomes dos coautores (na ordem do Co-Authors Plus) para um post. */
function bahia_get_coauthor_names($post_id) {
    if (!function_exists('get_coauthors')) {
        return array();
    }
    $names = array();
    foreach (get_coauthors($post_id) as $ca) {
        if (is_object($ca) && !empty($ca->display_name)) {
            $names[] = $ca->display_name;
        }
    }
    return $names;
}

/** Junta nomes no padrão pt-BR: "A", "A e B", "A, B e C". */
function bahia_join_author_names(array $names) {
    $n = count($names);
    if ($n === 0) return '';
    if ($n === 1) return $names[0];
    $last = array_pop($names);
    return implode(', ', $names) . ' e ' . $last;
}

/**
 * Nomes dos coautores do post principal, ou '' quando não se deve intervir.
 *
 * Guarda o contexto: só o post consultado, fora do admin, com Co-Authors Plus
 * disponível e 2+ coautores. Loops internos (relacionados, next/prev, sidebar)
 * trocam o post corrente e por isso não passam por aqui.
 */
function bahia_coautores_byline() {
    if (is_admin() || !is_singular() || !function_exists('get_coauthors')) {
        return '';
    }
    $main_id = (int) get_queried_object_id();
    if (!$main_id || (int) get_the_ID() !== $main_id) {
        return '';
    }
    $names = bahia_get_coauthor_names($main_id);
    if (count($names) < 2) {
        return ''; // autor único: não mexe
    }
    return bahia_join_author_names($names);
}

/**
 * the_author() / get_the_author() — caminho usado pelo byline do bahia_refactor.
 * Não recebe user_id; a guarda de contexto acima é o que delimita o escopo.
 */
add_filter('the_author', function ($display_name) {
    $byline = bahia_coautores_byline();
    return $byline !== '' ? $byline : $display_name;
}, 20);

/**
 * get_the_author_meta('display_name') — mantido para plugins que consultem por
 * esse caminho. Aqui temos o user_id, então exigimos que seja o autor primário.
 */
add_filter('get_the_author_display_name', function ($display_name, $user_id) {
    $main_id = (int) get_queried_object_id();
    if (!$main_id || (int) $user_id !== (int) get_post_field('post_author', $main_id)) {
        return $display_name;
    }
    $byline = bahia_coautores_byline();
    return $byline !== '' ? $byline : $display_name;
}, 20, 2);
