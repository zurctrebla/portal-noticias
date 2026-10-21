<?php
/**
 * Plugin Name: Bahia.ba - Autoria pelo Co-Authors Plus (coautores e convidados)
 * Description: O bahia.ba marca a autoria pelo Co-Authors Plus (taxonomia `author`), mas o
 *              tema só conhece o autor NATIVO do WordPress (post_author). Duas situações
 *              saíam erradas no site:
 *
 *              - coautoria (KAN-9): matéria com 2+ repórteres mostrava só o 1º;
 *              - convidado (KAN-30): artigo assinado por um autor convidado (guest author,
 *                sem usuário no WordPress) mostrava o nativo, em geral "Redação".
 *
 *              A regra única está em bahia_coautores_substitui(): quando a lista do
 *              Co-Authors Plus não é exatamente o autor nativo, ela vale no lugar dele —
 *              na assinatura da matéria, nos cards (home, editorias, autor, busca, "Ver
 *              mais") e na caixa de autor do fim da matéria. Post de autor único nativo
 *              fica intacto. Display-time, sem escrever no banco.
 *
 *              Mapa de quem pergunta o autor e onde é tratado:
 *              - assinatura do single: filtros the_author/display_name (prioridade 20)
 *                + bahia-html-saida.php, que dá um link a cada autor;
 *              - cards td_module (home e blocos) e caixa de autor/metadado do single:
 *                filtros do core lidos pela pilha de chamadas (bahia_coautores_contexto);
 *              - cards tdb_module (editorias, autor, busca): construtor do módulo;
 *              - loop-archive.php e bahia-scroll-infinito.php: bahia_coautores_links().
 * Version: 1.4.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Nomes dos coautores (na ordem do Co-Authors Plus) para um post. */
function bahia_get_coauthor_names($post_id) {
    $names = array();
    foreach (bahia_coautores_lista($post_id) as $l) {
        $names[] = $l['nome_cru'];
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
 * Autores do post pelo Co-Authors Plus, na ordem dele. Cada item:
 * url, nome (escapado), nome_cru, nativo (é o próprio post_author?) e obj (o objeto do
 * Co-Authors Plus: WP_User ou guest author, com description/website/user_email).
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
    $post_author = (int) get_post_field('post_author', $post_id);
    $lista = array();
    // get_author_posts_url() abaixo dispara o filtro author_link deste arquivo, que
    // consulta esta lista: a trava impede a recursão.
    $GLOBALS['bahia_coautores_montando'] = true;
    foreach (get_coauthors($post_id) as $ca) {
        if (!is_object($ca) || empty($ca->display_name)) {
            continue;
        }
        $convidado = isset($ca->type) && $ca->type === 'guest-author';
        $lista[] = array(
            'url'      => get_author_posts_url(
                isset($ca->ID) ? $ca->ID : 0,
                isset($ca->user_nicename) ? $ca->user_nicename : ''
            ),
            'nome'     => esc_html($ca->display_name),
            'nome_cru' => $ca->display_name,
            'nativo'   => !$convidado && isset($ca->ID) && (int) $ca->ID === $post_author,
            'obj'      => $ca,
        );
    }
    $GLOBALS['bahia_coautores_montando'] = false;
    return $memo[$post_id] = $lista;
}

/**
 * A autoria do Co-Authors Plus deve substituir a nativa neste post?
 * Sim com 2+ autores, ou com um só que não seja o post_author (o caso do convidado).
 */
function bahia_coautores_substitui($post_id) {
    $lista = bahia_coautores_lista($post_id);
    if (count($lista) >= 2) {
        return true;
    }
    return count($lista) === 1 && !$lista[0]['nativo'];
}

/**
 * Nomes dos autores do post principal, ou '' quando não se deve intervir.
 *
 * Guarda o contexto: só o post consultado, fora do admin. Loops internos (relacionados,
 * next/prev, sidebar) trocam o post corrente e por isso não passam por aqui.
 */
function bahia_coautores_byline() {
    if (is_admin() || !is_singular()) {
        return '';
    }
    $main_id = (int) get_queried_object_id();
    if (!$main_id || (int) get_the_ID() !== $main_id) {
        return '';
    }
    if (!bahia_coautores_substitui($main_id)) {
        return ''; // autor único nativo: não mexe
    }
    return bahia_join_author_names(bahia_get_coauthor_names($main_id));
}

/**
 * the_author() / get_the_author(). Não recebe user_id; a guarda de contexto acima é o
 * que delimita o escopo.
 */
add_filter('the_author', function ($display_name) {
    $byline = bahia_coautores_byline();
    return $byline !== '' ? $byline : $display_name;
}, 20);

/**
 * get_the_author_meta('display_name') do autor nativo do post principal — é o que a
 * assinatura do single (td_module_single_base::get_author) imprime. Os links por autor
 * entram depois, no bahia-html-saida.php.
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
 * "<a urlA>A</a> e <a urlB>B</a>" (ou o link do convidado sozinho) quando a autoria do
 * Co-Authors Plus substitui a nativa; '' quando não. Para templates que montam o link do
 * autor na mão: com '' eles seguem imprimindo o autor nativo.
 */
function bahia_coautores_links($post_id) {
    if (!bahia_coautores_substitui($post_id)) {
        return '';
    }
    $links = array();
    foreach (bahia_coautores_lista($post_id) as $l) {
        $links[] = '<a href="' . esc_url($l['url']) . '">' . $l['nome'] . '</a>';
    }
    if (count($links) === 1) {
        return $links[0];
    }
    // O "e" vai num span próprio: blocos do tagDiv (busca, por exemplo) aplicam
    // text-transform:capitalize na linha de autoria inteira e ele virava "E".
    $ultimo = array_pop($links);
    return implode(', ', $links) . ' <span class="bahia-coautores-e" style="text-transform:none">e</span> ' . $ultimo;
}

/**
 * O miolo do link de autoria de um card, ou ''.
 *
 * Os módulos de card do tagDiv imprimem <a href="URL">NOME</a> sem gancho no meio.
 * Devolvendo como NOME a string "A</a> e <a href="urlB">B", o HTML final fica
 * "<a urlA>A</a> e <a urlB>B</a>": válido, um link por autor, separadores fora dos
 * links. A URL do 1º autor é trocada à parte (filtro author_link ou post_author_url),
 * porque o tema a monta a partir do autor nativo.
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
        $lista = bahia_coautores_lista($module->post['post_id']);
        $module->post['post_author_name'] = $html;
        $module->post['post_author_url']  = esc_url($lista[0]['url']);
    }
    return $module;
}
// Prioridade 1, não 10: td_unique_posts (prioridade 5) não devolve nada, e a partir dele
// o 1º argumento chega null aos filtros seguintes. Só antes dele o módulo está à mão.
add_filter('td_wp_booster_module_constructor', 'bahia_coautores_tdb_module', 1, 2);

/**
 * Quem está pedindo o autor agora, lido da pilha de chamadas: [onde, WP_Post] ou null.
 *
 * onde = 'card'  → td_module::get_author() de um card (home e blocos);
 *        'caixa' → td_module_single_base::get_author_box(), a caixa do fim da matéria;
 *        'meta'  → td_module_single_base::get_item_scope_meta(), o schema Person oculto.
 *
 * Os filtros do core (display_name, author_link, description...) recebem o user_id mas
 * não o post; o post vem do próprio módulo. Sem estado global: nada vaza para outras
 * consultas ao mesmo usuário. A assinatura do single (get_author do single_base) não
 * entra aqui: é tratada pelo filtro de prioridade 20 + bahia-html-saida.php.
 */
function bahia_coautores_contexto() {
    if (!empty($GLOBALS['bahia_coautores_montando']) || !class_exists('td_module', false)) {
        return null;
    }
    foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 12) as $quadro) {
        if (!isset($quadro['function'], $quadro['object']) || !($quadro['object'] instanceof td_module)) {
            continue;
        }
        $obj    = $quadro['object'];
        $single = class_exists('td_module_single_base', false) && $obj instanceof td_module_single_base;
        $onde   = null;
        if ($quadro['function'] === 'get_author') {
            $onde = $single ? null : 'card';
        } elseif ($single && $quadro['function'] === 'get_author_box') {
            $onde = 'caixa';
        } elseif ($single && $quadro['function'] === 'get_item_scope_meta') {
            $onde = 'meta';
        }
        if ($onde === null || !($obj->post instanceof WP_Post)) {
            return null;
        }
        return array($onde, $obj->post);
    }
    return null;
}

/**
 * O 1º autor do Co-Authors Plus quando o contexto é card/caixa/meta, o user_id é o autor
 * nativo do post e a autoria deve ser substituída; senão null.
 */
function bahia_coautores_primeiro_no_contexto($user_id, $onde_aceitos) {
    $ctx = bahia_coautores_contexto();
    if (!$ctx || !in_array($ctx[0], $onde_aceitos, true)) {
        return null;
    }
    $post = $ctx[1];
    if ((int) $user_id !== (int) $post->post_author || !bahia_coautores_substitui($post->ID)) {
        return null;
    }
    $lista = bahia_coautores_lista($post->ID);
    return array('onde' => $ctx[0], 'post' => $post, 'autor' => $lista[0]);
}

/**
 * Nome do autor: no card, todos os autores com link; na caixa e no metadado, o 1º autor
 * (a caixa mostra a foto, a bio e o link de UM autor, então o nome tem que ser o dele).
 *
 * No card também desfaz o caso inverso: num post individual, o filtro de prioridade 20
 * troca o nome de qualquer card cujo autor nativo seja o da matéria aberta.
 */
function bahia_coautores_display_name_contexto($display_name, $user_id) {
    $ctx = bahia_coautores_contexto();
    if (!$ctx || (int) $user_id !== (int) $ctx[1]->post_author) {
        return $display_name;
    }
    list($onde, $post) = $ctx;
    if (!bahia_coautores_substitui($post->ID)) {
        if ($onde !== 'card') {
            return $display_name;
        }
        $usuario = get_userdata($user_id);
        return ($usuario && $usuario->display_name !== '') ? $usuario->display_name : $display_name;
    }
    if ($onde === 'card') {
        return bahia_coautores_card_html($post->ID);
    }
    $lista = bahia_coautores_lista($post->ID);
    return $lista[0]['nome_cru'];
}
add_filter('get_the_author_display_name', 'bahia_coautores_display_name_contexto', 30, 2);

/** Link do autor: o do 1º autor do Co-Authors Plus no card, na caixa e no metadado. */
add_filter('author_link', function ($link, $author_id) {
    $p = bahia_coautores_primeiro_no_contexto($author_id, array('card', 'caixa', 'meta'));
    return $p ? $p['autor']['url'] : $link;
}, 30, 2);

/**
 * Bio, site e e-mail (este último só alimenta o avatar) na caixa de autor: os do 1º
 * autor do Co-Authors Plus. Para convidado vêm do perfil de autor convidado; para
 * usuário, do próprio usuário.
 */
function bahia_coautores_campo_caixa($valor, $user_id, $campo) {
    $p = bahia_coautores_primeiro_no_contexto($user_id, array('caixa'));
    if (!$p || $p['autor']['nativo']) {
        return $valor;
    }
    $obj = $p['autor']['obj'];
    return isset($obj->$campo) ? (string) $obj->$campo : '';
}
add_filter('get_the_author_description', function ($v, $user_id) {
    return bahia_coautores_campo_caixa($v, $user_id, 'description');
}, 30, 2);
add_filter('get_the_author_user_url', function ($v, $user_id) {
    // Convidado guarda o site em `website`; usuário, em `user_url`.
    $p = bahia_coautores_primeiro_no_contexto($user_id, array('caixa'));
    if (!$p || $p['autor']['nativo']) {
        return $v;
    }
    $obj = $p['autor']['obj'];
    $site = isset($obj->website) ? $obj->website : (isset($obj->user_url) ? $obj->user_url : '');
    return (string) $site;
}, 30, 2);
add_filter('get_the_author_user_email', function ($v, $user_id) {
    return bahia_coautores_campo_caixa($v, $user_id, 'user_email');
}, 30, 2);

/**
 * Foto da caixa de autor para convidado: a imagem do perfil de convidado, quando houver.
 * Sem imagem, o get_avatar segue com o e-mail do convidado (filtro acima), e não com o
 * do autor nativo.
 */
add_filter('pre_get_avatar_data', function ($args, $id_or_email) {
    $ctx = bahia_coautores_contexto();
    if (!$ctx || $ctx[0] !== 'caixa' || !bahia_coautores_substitui($ctx[1]->ID)) {
        return $args;
    }
    $lista = bahia_coautores_lista($ctx[1]->ID);
    $obj   = $lista[0]['obj'];
    if (!isset($obj->type) || $obj->type !== 'guest-author') {
        return $args;
    }
    $tam = isset($args['size']) ? (int) $args['size'] : 96;
    $url = get_the_post_thumbnail_url($obj->ID, array($tam * 2, $tam * 2));
    if ($url) {
        $args['url'] = $url;
    }
    return $args;
}, 30, 2);

/* -------------------------------------------------------------------------
 *  UMA CAIXA DE AUTOR POR COAUTOR (KAN-21)
 *
 *  td_module_single_base::get_author_box() monta a caixa de UM autor, e os filtros
 *  acima fazem dela a do 1º coautor. Com 2+ autores, a caixa única é trocada, no
 *  buffer de saída (bahia-html-saida.php), por uma caixa para cada autor, na ordem
 *  do Co-Authors Plus e com a mesma marcação do tema (o CSS dele vale para todas).
 *  Autor único, nativo ou convidado, continua com a caixa do tema.
 * ------------------------------------------------------------------------- */

/** Caixa de um autor da lista de bahia_coautores_lista(), na marcação do tema. */
function bahia_coautores_caixa_html(array $autor) {
    $obj       = $autor['obj'];
    $convidado = isset($obj->type) && $obj->type === 'guest-author';
    $url       = esc_url($autor['url']);

    if ($convidado) {
        $foto = get_the_post_thumbnail_url($obj->ID, array(192, 192));
        $img  = $foto
            ? '<img alt="' . esc_attr($autor['nome_cru']) . '" src="' . esc_url($foto) . '" class="avatar avatar-96 photo" height="96" width="96" loading="lazy" decoding="async">'
            : get_avatar(isset($obj->user_email) ? $obj->user_email : '', 96, '', $autor['nome_cru'], array('loading' => 'lazy'));
        $site = isset($obj->website) ? (string) $obj->website : '';
        $bio  = isset($obj->description) ? (string) $obj->description : '';
    } else {
        $img  = get_avatar((int) $obj->ID, 96, '', $autor['nome_cru'], array('loading' => 'lazy'));
        $site = (string) get_the_author_meta('user_url', $obj->ID);
        $bio  = (string) get_the_author_meta('description', $obj->ID);
    }

    $h  = '<div class="author-box-wrap bahia-author-box">';
    $h .= '<a href="' . $url . '" aria-label="author-photo">' . $img . '</a>';
    $h .= '<div class="desc">';
    $h .= '<div class="td-author-name vcard author"><span class="fn"><a href="' . $url . '">' . $autor['nome'] . '</a></span></div>';
    if ($site !== '') {
        $h .= '<div class="td-author-url"><a href="' . esc_url($site) . '">' . esc_html($site) . '</a></div>';
    }
    $h .= '<div class="td-author-description">' . $bio . '</div>';
    $h .= '<div class="td-author-social">';
    if (!$convidado && class_exists('td_social_icons', false)) {
        foreach (td_social_icons::$td_social_icons_array as $rede => $nome_rede) {
            $valor = get_the_author_meta($rede, $obj->ID);
            if (empty($valor)) {
                continue;
            }
            // Mesmo tratamento do tema: o twitter pode vir como @usuario.
            if ($rede === 'twitter' && !filter_var($valor, FILTER_VALIDATE_URL)) {
                $valor = 'https://twitter.com/' . str_replace('@', '', $valor);
            }
            $h .= td_social_icons::get_icon($valor, $rede, true);
        }
    }
    $h .= '</div>';
    $h .= '<div class="clearfix"></div>';
    $h .= '</div></div>';
    return $h;
}

add_filter('bahia_hs_html', 'bahia_coautores_caixas', 15);

function bahia_coautores_caixas($html) {
    if (!is_string($html) || !is_singular()) {
        return $html;
    }
    $lista = bahia_coautores_lista((int) get_queried_object_id());
    if (count($lista) < 2) {
        return $html;
    }
    $abre = '<!-- author box --><div class="author-box-wrap">';
    $ini  = strpos($html, $abre);
    if ($ini === false) {
        return $html;
    }
    // A caixa do tema termina sempre assim (clearfix, fecha .desc, fecha .author-box-wrap).
    $fecha = '<div class="clearfix"></div></div></div>';
    $fim   = strpos($html, $fecha, $ini);
    if ($fim === false) {
        return $html;
    }
    $fim += strlen($fecha);

    $caixas = '';
    foreach ($lista as $autor) {
        $caixas .= bahia_coautores_caixa_html($autor);
    }
    // O contêiner herda os 48px que o tema dá embaixo da caixa; entre as caixas, 16px.
    $estilo = '<style id="bahia-author-boxes">.bahia-author-boxes{margin-bottom:48px}'
            . '.bahia-author-boxes .author-box-wrap{margin-bottom:16px}'
            . '.bahia-author-boxes .author-box-wrap:last-child{margin-bottom:0}</style>';
    return substr_replace($html, '<!-- author box -->' . $estilo . '<div class="bahia-author-boxes">' . $caixas . '</div>', $ini, $fim - $ini);
}
