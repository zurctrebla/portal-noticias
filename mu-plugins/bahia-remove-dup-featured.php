<?php
/**
 * Plugin Name: Bahia.ba - Remove imagem duplicada no post individual
 * Description: No post individual, o tema Newspaper renderiza a featured image pelo
 *              bloco `tdb_single_featured_image` (topo) E o corpo do post (migrado do
 *              tema antigo) começa com a MESMA imagem embutida como [caption]/<img>
 *              (classe wp-image-{ID}). Com a ponte ACF `imagem` -> _thumbnail_id
 *              (mu-plugins/acf-imagem-featured.php), a featured passou a aparecer,
 *              resultando em DUAS imagens iguais. Este filtro remove, apenas na
 *              exibição do post principal, o bloco de imagem inicial do conteúdo
 *              QUANDO ele referencia o mesmo attachment da featured image.
 *
 *              100% display-time e reversível (não escreve no banco). Só age quando
 *              a 1ª imagem do corpo == featured; se forem diferentes, não mexe.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('the_content', function ($content) {
    // Só no corpo do post principal (não em related posts, widgets, feeds, excerpts).
    if (is_admin() || !is_singular()) {
        return $content;
    }
    if (get_the_ID() !== get_queried_object_id()) {
        return $content;
    }

    $thumb = (int) get_post_thumbnail_id(get_the_ID());
    if ($thumb <= 0) {
        return $content;
    }

    // Bloco de imagem no INÍCIO do conteúdo: um [caption]...[/caption] (shortcode
    // ainda não expandido, pois rodamos em prioridade 9 < do_shortcode) OU um <img>
    // solto — seguido de um &nbsp;/espacos opcionais. Tolera &nbsp;/espaco antes.
    $pattern = '/^(?:\s|&nbsp;|\xc2\xa0)*(?:\[caption\b[^\]]*\].*?\[\/caption\]|<img\b[^>]*>)\s*(?:&nbsp;|\xc2\xa0)*\s*/is';

    if (preg_match($pattern, $content, $m)) {
        // Só remove se o bloco inicial é a MESMA imagem da featured.
        if (strpos($m[0], 'wp-image-' . $thumb) !== false) {
            $content = preg_replace($pattern, '', $content, 1);
        }
    }

    return $content;
}, 9);
