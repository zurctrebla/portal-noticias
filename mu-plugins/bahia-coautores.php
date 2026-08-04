<?php
/**
 * Plugin Name: Bahia.ba - Autoria múltipla no post individual
 * Description: O bahia.ba usa Co-Authors Plus (taxonomia `author`) e há matérias com
 *              mais de um repórter (ex.: post 425868 = 4 autores). O tema Newspaper/
 *              tagDiv monta o byline via get_the_author_meta('display_name', post_author)
 *              — que devolve só o autor nativo (o 1º coautor). Este filtro faz o byline
 *              do POST PRINCIPAL exibir todos os coautores ("Por A, B e C") quando houver
 *              2+; posts de autor único ficam intactos.
 *
 *              Escopo restrito (não afeta cards de "relacionados"/próximo-anterior nem
 *              arquivos): só age quando estamos no contexto do post consultado
 *              (get_the_ID() === get_queried_object_id()) e o id pedido é o autor
 *              primário do post. Display-time, sem escrever no banco.
 * Version: 1.0.0
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

add_filter('get_the_author_display_name', function ($display_name, $user_id) {
    if (is_admin() || !is_singular() || !function_exists('get_coauthors')) {
        return $display_name;
    }
    $main_id = (int) get_queried_object_id();
    // Só o post principal — exclui loops internos (relacionados, next/prev) que
    // trocam o post corrente via the_post()/setup_postdata().
    if (!$main_id || (int) get_the_ID() !== $main_id) {
        return $display_name;
    }
    // Só quando o nome pedido é o do autor primário (nativo) deste post.
    if ((int) $user_id !== (int) get_post_field('post_author', $main_id)) {
        return $display_name;
    }
    $names = bahia_get_coauthor_names($main_id);
    if (count($names) < 2) {
        return $display_name; // autor único: não mexe
    }
    return bahia_join_author_names($names);
}, 20, 2);
