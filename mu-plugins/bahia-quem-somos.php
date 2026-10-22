<?php
/**
 * Plugin Name: Bahia.ba - Página Quem Somos (layout da equipe)
 * Description: Estilos da página "Quem Somos" (slug quem-somos): texto institucional +
 *              grade da equipe por cargo, com placeholder de avatar (foto pendente).
 *              O CSS fica aqui (e não inline no conteúdo) porque o KSES do WordPress
 *              remove `display:` e tags <svg> do post_content — então flex e o ícone
 *              do avatar precisam vir do stylesheet. Também desliga o wpautop nessa
 *              página (o HTML já é estruturado). Cores de marca: #13182B, #4371B6, #EDECED.
 *
 *              KAN-8: foto e nome de cada membro levam à página de autor dele
 *              (/colunistas/<nome>/), com tudo o que publicou, do mais recente ao mais
 *              antigo. O link é posto na exibição, casando o nome do cartão com o nome
 *              de exibição do usuário — o conteúdo da página no banco não muda, e quem
 *              entrar na equipe ganha o link sozinho. Sem usuário, ou com usuário sem
 *              nenhuma matéria (o CEO, a área comercial), o cartão fica como está, para
 *              não levar a uma página vazia.
 * Version: 1.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// wpautop atrapalha o HTML estruturado da página -> desliga só nela.
add_action('wp', function () {
    if (is_page('quem-somos')) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'shortcode_unautop');
    }
});

function bahia_qs_css() {
    // Silhueta do avatar via data-URI (fica no CSS -> não é removida pelo KSES).
    $svg  = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='#b8c0cc'>";
    $svg .= "<circle cx='12' cy='8.4' r='4.2'/><path d='M3.6 20.4c0-4.3 4.2-6.4 8.4-6.4s8.4 2.1 8.4 6.4v.9H3.6z'/></svg>";
    $data = 'data:image/svg+xml,' . rawurlencode($svg);

    return <<<CSS
.qs-intro{max-width:820px;margin:0 auto 42px;font-size:17px;line-height:1.7;color:#333;}
.qs-intro p{margin:0 0 18px;}
.qs-equipe{max-width:980px;margin:0 auto 30px;}
.qs-equipe .qs-title{text-align:center;color:#13182B !important;font-size:26px;margin:0 0 8px;}
.qs-equipe .qs-sub{text-align:center;color:#8a8f98;font-size:14px;margin:0 0 34px;}
.qs-role{color:#4371B6 !important;font-size:18px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #EDECED;padding-bottom:8px;margin:34px 0 22px;}
.qs-grid{display:flex;flex-wrap:wrap;gap:28px 22px;justify-content:flex-start;margin:0 0 6px;}
.qs-member{width:132px;text-align:center;}
.qs-avatar{display:block;width:96px;height:96px;border-radius:50%;background:#EDECED url("$data") no-repeat center;background-size:60px 60px;margin:0 auto 10px;overflow:hidden;}
.qs-avatar .qs-photo{display:block;width:100%;height:100%;border-radius:50%;object-fit:cover;object-position:top center;}
.qs-name{display:block;font-weight:600;font-size:14px;color:#13182B !important;line-height:1.3;}
.qs-member-link{display:block;text-decoration:none !important;}
.qs-member-link .qs-avatar{transition:box-shadow .2s;}
.qs-member-link:hover .qs-avatar,.qs-member-link:focus-visible .qs-avatar{box-shadow:0 0 0 3px #4371B6;}
.qs-member-link:hover .qs-name,.qs-member-link:focus-visible .qs-name{color:#4371B6 !important;}
.qs-cargo{display:block;font-size:12px;color:#8a8f98;margin-top:2px;}
@media (max-width:767px){.qs-grid{justify-content:center;gap:22px;}}
.qs-contact{max-width:820px;margin:34px auto 10px;text-align:center;font-size:17px;line-height:1.7;color:#333;}
.qs-contact a{color:#4371B6 !important;font-weight:600;text-decoration:none;}
.qs-contact a:hover{text-decoration:underline;}
CSS;
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !is_page('quem-somos')) {
        return;
    }
    wp_register_style('bahia-qs', false, array(), '1.0.0');
    wp_enqueue_style('bahia-qs');
    wp_add_inline_style('bahia-qs', bahia_qs_css());
}, 40);

/* -------------------------------------------------------------------------
 *  KAN-8: cartão da equipe vira link para a página de autor
 * ------------------------------------------------------------------------- */

/** URL da página de autor de quem tem esse nome de exibição e alguma matéria; ou ''. */
function bahia_qs_url_autor($nome) {
    static $memo = array();
    if (isset($memo[$nome])) {
        return $memo[$nome];
    }
    $url = '';
    $achados = get_users(array(
        'search'         => $nome,
        'search_columns' => array('display_name'),
        'number'         => 5,
    ));
    $usuarios = array_values(array_filter($achados, function ($u) use ($nome) {
        return mb_strtolower(trim($u->display_name)) === mb_strtolower(trim($nome));
    }));
    // Dois usuários com o mesmo nome: não há como saber qual é o do cartão.
    if (count($usuarios) === 1 && bahia_qs_tem_materia($usuarios[0])) {
        $url = get_author_posts_url($usuarios[0]->ID, $usuarios[0]->user_nicename);
    }
    return $memo[$nome] = $url;
}

/**
 * O usuário assina ao menos uma matéria publicada? Mesma regra da página de autor
 * (bahia-autor-archive.php): autor nativo OU coautor pelo Co-Authors Plus.
 */
function bahia_qs_tem_materia(WP_User $u) {
    global $wpdb;
    $tipos = function_exists('bahia_autor_archive_post_types')
        ? bahia_autor_archive_post_types()
        : array('post');
    $in = implode(',', array_map(function ($t) use ($wpdb) {
        return $wpdb->prepare('%s', $t);
    }, $tipos));
    $nativo = $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$wpdb->posts} WHERE post_author = %d AND post_status = 'publish' AND post_type IN ($in) LIMIT 1",
        $u->ID
    ));
    if ($nativo) {
        return true;
    }
    $tt = function_exists('bahia_autor_archive_term_id') ? bahia_autor_archive_term_id($u) : 0;
    if (!$tt) {
        return false;
    }
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$wpdb->term_relationships} tr
           JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_status = 'publish' AND p.post_type IN ($in)
          WHERE tr.term_taxonomy_id = %d LIMIT 1",
        $tt
    ));
}

add_filter('the_content', function ($html) {
    if (!is_page('quem-somos') || !in_the_loop() || strpos($html, 'qs-member') === false) {
        return $html;
    }
    return preg_replace_callback(
        '#<div class="qs-member">(.*?<span class="qs-name">(.*?)</span>.*?)</div>#s',
        function ($m) {
            if (strpos($m[1], '<a ') !== false) {
                return $m[0]; // já tem link (alguém pôs à mão): respeitar
            }
            $nome = trim(wp_strip_all_tags(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8')));
            $url  = $nome !== '' ? bahia_qs_url_autor($nome) : '';
            if ($url === '') {
                return $m[0];
            }
            return '<div class="qs-member"><a class="qs-member-link" href="' . esc_url($url) . '"'
                . ' aria-label="' . esc_attr('Ver as matérias de ' . $nome) . '">' . $m[1] . '</a></div>';
        },
        $html
    );
}, 20);
