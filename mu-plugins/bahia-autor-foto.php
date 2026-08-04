<?php
/**
 * Plugin Name: Bahia.ba - Foto do repórter (avatar) a partir de "Quem Somos"
 * Description: O box de bio no fim da matéria (tdb_single_author_box) e o mini-avatar
 *              do byline (tdb_single_author author_photo="yes") usam get_avatar(), que
 *              devolve o Gravatar padrão (vazio) — deixando o espaço da foto à esquerda
 *              em branco. Este mu-plugin preenche esse avatar com a MESMA foto já
 *              cadastrada na página "Quem Somos" (mu-plugins/bahia-quem-somos.php),
 *              associando pelo nome do autor (display_name) ao nome da equipe.
 *
 *              Fonte única de verdade: o próprio conteúdo da página quem-somos
 *              (imgs .qs-photo + nomes .qs-name), lido uma vez e cacheado em transient.
 *              Só afeta autores cujo nome casa exatamente (accent/caixa-insensitive) com
 *              alguém de Quem Somos; os demais mantêm o placeholder padrão (nada é
 *              adivinhado). Display-time, sem escrever no banco.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

const BAHIA_AUTOR_FOTO_TRANSIENT = 'bahia_autor_foto_map_v1';

/** Normaliza nome p/ comparação: sem acento, minúsculo, só [a-z0-9 ]. */
function bahia_autor_foto_norm($s) {
    $s = (string) $s;
    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false) {
            $s = $t;
        }
    }
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9 ]/', '', $s);
    $s = preg_replace('/\s+/', ' ', trim($s));
    return $s;
}

/** Mapa nome-normalizado => url da foto, extraído da página "Quem Somos". */
function bahia_autor_foto_map() {
    $cached = get_transient(BAHIA_AUTOR_FOTO_TRANSIENT);
    if (is_array($cached)) {
        return $cached;
    }

    $map  = array();
    $page = get_page_by_path('quem-somos');
    if ($page instanceof WP_Post) {
        // Cada membro: <span class="qs-avatar"><img class="qs-photo" src="URL" ...></span>
        //              <span class="qs-name">NOME</span>
        if (preg_match_all(
            '/class="qs-photo"[^>]*src="([^"]+)"[^>]*>\s*<\/span>\s*<span class="qs-name">([^<]+)</i',
            $page->post_content,
            $mm,
            PREG_SET_ORDER
        )) {
            foreach ($mm as $m) {
                $url  = trim($m[1]);
                $name = bahia_autor_foto_norm(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
                if ($name !== '' && $url !== '' && !isset($map[$name])) {
                    $map[$name] = $url;
                }
            }
        }
    }

    // Cache 12h (curto o bastante p/ refletir edições em Quem Somos).
    set_transient(BAHIA_AUTOR_FOTO_TRANSIENT, $map, 12 * HOUR_IN_SECONDS);
    return $map;
}

/** Resolve o alvo do get_avatar() para um WP_User (ou null). */
function bahia_autor_foto_resolve_user($id_or_email) {
    if (is_numeric($id_or_email)) {
        return get_user_by('id', (int) $id_or_email);
    }
    if ($id_or_email instanceof WP_User) {
        return $id_or_email;
    }
    if ($id_or_email instanceof WP_Post) {
        return get_user_by('id', (int) $id_or_email->post_author);
    }
    if ($id_or_email instanceof WP_Comment) {
        if (!empty($id_or_email->user_id)) {
            return get_user_by('id', (int) $id_or_email->user_id);
        }
        if (!empty($id_or_email->comment_author_email)) {
            return get_user_by('email', $id_or_email->comment_author_email);
        }
        return null;
    }
    if (is_string($id_or_email) && strpos($id_or_email, '@') !== false) {
        return get_user_by('email', $id_or_email);
    }
    return null;
}

/**
 * Substitui a URL do avatar pela foto de "Quem Somos" quando o autor casa.
 * pre_get_avatar_data roda tanto no get_avatar() (bio box, byline) quanto no
 * get_avatar_url(); definindo $args['url'] o Gravatar é ignorado.
 */
add_filter('pre_get_avatar_data', function ($args, $id_or_email) {
    if (is_admin()) {
        return $args;
    }
    $user = bahia_autor_foto_resolve_user($id_or_email);
    if (!$user instanceof WP_User) {
        return $args;
    }
    $map = bahia_autor_foto_map();
    if (empty($map)) {
        return $args;
    }
    $key = bahia_autor_foto_norm($user->display_name);
    if ($key !== '' && isset($map[$key])) {
        $args['url']          = $map[$key];
        $args['found_avatar'] = true;
    }
    return $args;
}, 10, 2);

/** Foto é 3:4 (225x300); garante recorte sem distorcer no avatar quadrado. */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    echo "<style id=\"bahia-autor-foto\">.author-box-wrap img,.author-box-wrap .avatar,.tdb-author-photo img,.tdb_single_author_box img.avatar,.tdb-author-box-img img,.td-author-photo img{object-fit:cover !important;object-position:top center;}</style>\n";
}, 99);
