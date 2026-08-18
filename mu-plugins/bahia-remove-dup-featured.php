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
        // Caminho 1 — mesmo attachment. Cobre 96% dos casos medidos.
        if (strpos($m[0], 'wp-image-' . $thumb) !== false) {
            return preg_replace($pattern, '', $content, 1);
        }
        // Caminho 2 — MESMA FOTO em anexos diferentes (ver bloco abaixo).
        if (preg_match('/wp-image-(\d+)/', $m[0], $mi)
            && bahia_rdf_mesma_foto((int) $mi[1], $thumb)) {
            return preg_replace($pattern, '', $content, 1);
        }
    }

    return $content;
}, 9);

/**
 * A MESMA foto pode existir como DOIS anexos distintos — e aí a comparação por ID falha.
 *
 * Medido em homolog (amostra de 1.020 posts publicados, 60 por editoria):
 *
 *     mesmo attachment ...................  979  (96,0%)  <- o caminho 1 já resolvia
 *     mesma foto, anexos diferentes ......    8  ( 0,8%)  <- o defeito relatado
 *     foto genuinamente diferente ........   33  ( 3,2%)  <- tem de continuar aparecendo
 *
 * O caso do briefing (post 543869, /artigo/opiniao-advocacia-criminal-...) é do segundo
 * grupo: a destacada é o anexo 543875 (`...10.50.43-e1783519322829.jpeg`) e o corpo
 * embute o 543882 (`...10.50.43-1.jpeg`) — o mesmo arquivo enviado duas vezes, uma
 * recortada pelo editor e outra não.
 *
 * Vale sublinhar o que a investigação NÃO encontrou: isto não é o padrão do Pinterest.
 * O filtro roda em `the_content`, que vale para qualquer template — não dependia de
 * `single.php` ser do tema ou do plugin. A correção anterior está no lugar certo e
 * funciona; o que falhava era só a guarda por ID.
 *
 * Reduz o nome ao "tronco", tirando os sufixos que o WordPress acrescenta:
 *   -1024x897        recorte de tamanho registrado
 *   -e1783519322829  edição feita no painel
 *   -1, -2           reenvio do mesmo arquivo
 *
 * Conservador de propósito: exige tronco não vazio, com pelo menos 8 caracteres, e igual
 * nos dois lados. Nomes genéricos curtos ("foto", "img") não passam do piso e caem fora,
 * para não remover uma imagem legítima que só tem nome parecido.
 */
function bahia_rdf_mesma_foto($id_embutida, $id_destacada) {
    if ($id_embutida <= 0 || $id_destacada <= 0 || $id_embutida === $id_destacada) {
        return false;
    }
    $a = bahia_rdf_tronco(get_post_meta($id_destacada, '_wp_attached_file', true));
    $b = bahia_rdf_tronco(get_post_meta($id_embutida, '_wp_attached_file', true));

    return ($a !== '' && strlen($a) >= 8 && $a === $b);
}

/** Nome do arquivo sem diretório, sem extensão e sem os sufixos gerados pelo WordPress. */
function bahia_rdf_tronco($arquivo) {
    if (!is_string($arquivo) || $arquivo === '') {
        return '';
    }
    $b = basename($arquivo);
    $b = preg_replace('/\.[a-z0-9]+$/i', '', $b);   // extensão
    $b = preg_replace('/-\d+x\d+$/', '', $b);       // -1024x897
    $b = preg_replace('/-e\d{10,}$/', '', $b);      // -e1783519322829
    $b = preg_replace('/-\d{1,2}$/', '', $b);       // -1, -2

    return strtolower($b);
}
