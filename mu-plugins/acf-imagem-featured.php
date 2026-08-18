<?php
/**
 * Bridge: ACF "imagem" -> featured image nativa (_thumbnail_id)
 *
 * O conteúdo do bahia.ba guarda a imagem de destaque no campo ACF `imagem`
 * (ID do attachment), e não na featured image nativa do WordPress. O tema
 * Newspaper/tagdiv monta o thumbnail dos cards a partir de has_post_thumbnail()
 * / get_post_thumbnail_id() (nativo), que fica vazio em todos os posts das
 * editorias — resultando no placeholder cinza (no-thumb/td_696x0.png).
 *
 * Este filtro intercepta a leitura de _thumbnail_id: quando o post NÃO tem
 * thumbnail nativo mas tem o ACF `imagem`, devolve o attachment do ACF. Se já
 * existir _thumbnail_id real, respeita o valor original.
 *
 * Não escreve no banco; é 100% reversível (basta remover o arquivo).
 */

add_filter('get_post_metadata', function ($value, $post_id, $meta_key, $single) {
    if ('_thumbnail_id' !== $meta_key) {
        return $value;
    }

    // Evita recursão: a chamada get_post_meta() abaixo reentra neste filtro.
    static $busy = false;
    if ($busy) {
        return $value;
    }

    $busy = true;
    $real = get_post_meta($post_id, '_thumbnail_id', true);
    $busy = false;

    // Já possui featured image nativa — não interfere.
    if (!empty($real)) {
        return $value;
    }

    // ACF `imagem`: normalmente o ID do attachment, mas pode vir como array
    // (return format "Image Array"/"Image Object") dependendo da config.
    $img = get_post_meta($post_id, 'imagem', true);
    $id  = is_array($img) ? ($img['ID'] ?? $img['id'] ?? 0) : $img;

    if (!is_numeric($id) || (int) $id <= 0) {
        return $value;
    }

    // Retorna array: o core usa o índice [0] quando $single é true.
    return array((int) $id);
}, 10, 4);
