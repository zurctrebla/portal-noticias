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
 *
 *              CARDS (KAN-9, v1.2.0): os cards de home, editorias, busca e relacionados
 *              também passam a mostrar todos os coautores, cada um com seu link. Ver
 *              bahia_coautores_card_byline() mais abaixo.
 * Version: 1.2.0
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

/**
 * CARDS — [url, nome escapado] de cada coautor do post, na ordem do Co-Authors Plus.
 * Memorizado por request: home e arquivos repetem o mesmo post em mais de um bloco.
 */
function bahia_coautores_lista($post_id) {
    static $memo = array();
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('get_coauthors')) {
        return array();
    }
    if (isset($memo[$post_id])) {
        return $memo[$post_id];
    }
    $lista = array();
    foreach (get_coauthors($post_id) as $ca) {
        if (!is_object($ca) || empty($ca->display_name)) {
            continue;
        }
        $lista[] = array(
            'url'  => get_author_posts_url(
                isset($ca->ID) ? $ca->ID : 0,
                isset($ca->user_nicename) ? $ca->user_nicename : ''
            ),
            'nome' => esc_html($ca->display_name),
        );
    }
    return $memo[$post_id] = $lista;
}

/**
 * CARDS — "<a urlA>A</a> e <a urlB>B</a>" para post com 2+ coautores, ou ''.
 * Para templates que montam o link do autor na mão (loop-archive.php do tema e
 * bahia-scroll-infinito.php): com '' eles seguem imprimindo o autor nativo.
 */
function bahia_coautores_links($post_id) {
    $lista = bahia_coautores_lista($post_id);
    if (count($lista) < 2) {
        return '';
    }
    $links = array();
    foreach ($lista as $l) {
        $links[] = '<a href="' . esc_url($l['url']) . '">' . $l['nome'] . '</a>';
    }
    // O "e" vai num span próprio: blocos do tagDiv (busca, por exemplo) aplicam
    // text-transform:capitalize na linha de autoria inteira e ele virava "E".
    $ultimo = array_pop($links);
    return implode(', ', $links) . ' <span class="bahia-coautores-e" style="text-transform:none">e</span> ' . $ultimo;
}

/**
 * CARDS — o miolo do link de autoria de um card com 2+ coautores, ou ''.
 *
 * Os dois módulos de card do tagDiv imprimem <a href="URL">NOME</a> sem gancho no
 * meio. Devolvendo como NOME a string "A</a> e <a href="urlB">B", o HTML final fica
 * "<a urlA>A</a> e <a urlB>B</a>": válido, um link por autor, separadores fora dos
 * links. Como é montado na hora do card e não num buffer de saída, vale igual na
 * página e no "Ver mais" da home (admin-ajax).
 */
function bahia_coautores_card_html($post_id) {
    $completo = bahia_coautores_links($post_id);
    if ($completo === '') {
        return '';
    }
    // Tira o "<a href=...>" de abertura e o "</a>" final: o tema já os imprime.
    return substr(preg_replace('#^<a href="[^"]*">#', '', $completo), 0, -strlen('</a>'));
}

/**
 * CARDS das editorias, autor e busca (tdb_module do td-cloud-library).
 *
 * O módulo recebe pronto um array com post_author_name/post_author_url e imprime os dois
 * crus em tdb_module::get_author(). O array é público e o filtro do construtor recebe o
 * próprio módulo, então basta reescrever ali.
 */
function bahia_coautores_tdb_module($module, $post = null) {
    if (!is_object($module) || !class_exists('tdb_module', false) || !($module instanceof tdb_module)) {
        return $module;
    }
    if (!is_array($module->post) || empty($module->post['post_id'])) {
        return $module;
    }
    $html = bahia_coautores_card_html($module->post['post_id']);
    if ($html !== '') {
        $module->post['post_author_name'] = $html;
    }
    return $module;
}
// Prioridade 1, não 10: td_unique_posts (prioridade 5) não devolve nada, e a partir dele
// o 1º argumento chega null aos filtros seguintes. Só antes dele o módulo está à mão.
add_filter('td_wp_booster_module_constructor', 'bahia_coautores_tdb_module', 1, 2);

/**
 * CARDS da home e blocos (td_module do td-composer: flex, mobile, slide).
 *
 * Aqui o nome não vem num array: td_module::get_author() chama na hora
 * get_the_author_meta('display_name', post_author), cujo filtro recebe o user_id mas não
 * o post. O post é lido do próprio módulo, pela pilha de chamadas — sem estado global,
 * então nada vaza para foto do autor, caixa de autor, Yoast ou outra consulta ao mesmo
 * usuário. O post individual (td_module_single_base) fica de fora: lá quem age é o
 * bahia_coautores_byline() acima, mais o bahia-html-saida.php.
 *
 * Também desfaz o caso inverso: num post individual, o filtro de prioridade 20 troca o
 * nome de qualquer card cujo autor principal seja o autor da matéria aberta. No card, a
 * resposta certa é sempre a do post do card.
 */
function bahia_coautores_td_module_byline($display_name, $user_id) {
    if (!class_exists('td_module', false)) {
        return $display_name;
    }

    $post = null;
    foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 8) as $quadro) {
        if (
            isset($quadro['function'], $quadro['object'])
            && $quadro['function'] === 'get_author'
            && $quadro['object'] instanceof td_module
        ) {
            if (!($quadro['object'] instanceof td_module_single_base)) {
                $post = $quadro['object']->post;
            }
            break;
        }
    }
    if (!($post instanceof WP_Post) || (int) $user_id !== (int) $post->post_author) {
        return $display_name;
    }

    $html = bahia_coautores_card_html($post->ID);
    if ($html !== '') {
        return $html;
    }

    // Autor único: o nome cru, desfazendo o que o filtro de prioridade 20 possa ter posto.
    $usuario = get_userdata($user_id);
    return ($usuario && $usuario->display_name !== '') ? $usuario->display_name : $display_name;
}
add_filter('get_the_author_display_name', 'bahia_coautores_td_module_byline', 30, 2);
