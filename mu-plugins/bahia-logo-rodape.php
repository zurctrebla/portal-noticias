<?php
/**
 * Plugin Name: Bahia.ba - Logo branca do rodapé por filtro CSS
 * Description: O rodapé passa a usar a logo COLORIDA (anexo 547365, 1151x229) renderizada
 *              em branco chapado por `filter: brightness(0) invert(1)`, no lugar da versão
 *              branca derivada por luminância (anexo 547458, 741x166).
 *
 * ---------------------------------------------------------------------------
 * POR QUE
 *
 * A 547458 tem exatamente a largura em que é exibida — 741px para 741px de caixa. Zero
 * folga: em tela retina (2x) o navegador amplia e a marca perde nitidez. A 547365 tem
 * 1151px, 55% a mais, e cobre o 2x com sobra. E não exige asset novo: as duas imagens
 * enviadas para esta rodada não serviam (ambas eram canvas 1080x1350 de post de rede
 * social, com placa retangular opaca atrás da marca — uma delas, inclusive, uma placa
 * branca vazia, sem wordmark nenhum).
 *
 * O ponto vermelho da marca vira branco junto com o resto. Isso foi aprovado.
 *
 * ---------------------------------------------------------------------------
 * DIMENSÃO: POR QUE O max-width DE 96,76%
 *
 * As duas imagens têm o MESMO wordmark, em proporções praticamente iguais — o que muda é
 * a moldura:
 *
 *     547458   canvas 741x166   wordmark 717x142   margem de 12px embutida nos 4 lados
 *     547365   canvas 1151x229  wordmark 1151x229  sem margem, sangra até a borda
 *
 * Razão do wordmark: 5,049 contra 5,026 — 0,5%, imperceptível.
 *
 * O detalhe que só apareceu ao medir o resultado: no rodapé quem manda no tamanho NÃO é o
 * atributo `width` da tag. O contêiner do bloco tem cerca de 260px e o `max-width:100%`
 * do tema vence — a imagem é reduzida para caber, qualquer que seja o `width`. Com a
 * antiga, o wordmark ocupava 717/741 = 96,76% dessa caixa, porque os 12px de margem
 * viajavam dentro do arquivo. Com a nova, que sangra até a borda, ele passaria a ocupar
 * 100%: medido, o logo saiu de 250x49 para 260x52, ~4% maior e sem a respiração lateral.
 *
 * Daí o `max-width:96.76%`, que devolve exatamente a mesma proporção — e por ser relativo
 * continua valendo em qualquer largura de contêiner, em vez de fixar um número de pixels
 * que quebraria no primeiro breakpoint diferente.
 *
 * Os atributos `width`/`height` passam a ser os intrínsecos (1151x229), que é o que eles
 * devem descrever: servem para o navegador reservar a caixa antes do download e evitar
 * salto de layout, não para dimensionar.
 *
 * ---------------------------------------------------------------------------
 * RISCO ACEITO, REGISTRADO EM CÓDIGO
 *
 * O rodapé passa a depender da logo colorida. Se alguém trocar o anexo 547365 — que é a
 * logo do CABEÇALHO —, o rodapé muda junto. O risco foi avaliado e aceito: é pequeno e
 * fica visível na hora, porque as duas aparecem na mesma página.
 *
 * Se a 547365 sumir, este filtro não faz nada e o rodapé continua com a 547458. Não há
 * estado quebrado possível.
 *
 * ---------------------------------------------------------------------------
 * POR QUE NO HTML DE SAÍDA E NÃO NO BANCO
 *
 * A logo do rodapé é um atributo salvo dentro do tdb_template 547416. Editá-lo pelo painel
 * faz o tagDiv RENUMERAR os `.tdi_NN` (a rodada 5 já pagou esse preço) e somaria mais uma
 * alteração ao inventário de migração para produção. Assim a mudança viaja no git.
 *
 * @see bahia-html-saida.php  buffer de saída único do site (filtro bahia_hs_html)
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Logo branca derivada por luminância, hoje no rodapé — o que sai. */
if (!defined('BAHIA_LOGO_RODAPE_ATUAL')) {
    define('BAHIA_LOGO_RODAPE_ATUAL', 547458);
}
/** Logo colorida oficial — o que entra, invertida por CSS. */
if (!defined('BAHIA_LOGO_RODAPE_NOVA')) {
    define('BAHIA_LOGO_RODAPE_NOVA', 547365);
}
/**
 * Proporção que a marca ocupava dentro da caixa com a imagem antiga: 717/741.
 * É o que devolve o tamanho renderizado de hoje, ver o cabeçalho deste arquivo.
 */
if (!defined('BAHIA_LOGO_RODAPE_ESCALA')) {
    define('BAHIA_LOGO_RODAPE_ESCALA', '96.76%');
}

/**
 * Troca a origem da imagem e marca a tag para o filtro CSS.
 *
 * Localiza a logo pela URL do anexo 547458 — e não por posição no documento nem por um
 * `.tdi_NN`. É o critério mais estável disponível: se um dia o rodapé deixar de usar essa
 * imagem, o filtro simplesmente para de casar, em vez de mexer na tag errada.
 *
 * A troca cobre `src`, `data-src` (o lazyload do Smush serve a real por aqui, deixando um
 * SVG de 1x1 no `src`) e `srcset`.
 */
function bahia_logo_rodape_trocar($html) {
    $antiga = wp_get_attachment_url(BAHIA_LOGO_RODAPE_ATUAL);
    $nova   = wp_get_attachment_url(BAHIA_LOGO_RODAPE_NOVA);

    // Sem uma das duas, não faz nada: o rodapé segue como está.
    if (!$antiga || !$nova || strpos($html, $antiga) === false) {
        return $html;
    }

    // Dimensões intrínsecas da nova imagem, para o navegador reservar a caixa certa.
    $meta = wp_get_attachment_metadata(BAHIA_LOGO_RODAPE_NOVA);
    $w = isset($meta['width'])  ? (int) $meta['width']  : 1151;
    $h = isset($meta['height']) ? (int) $meta['height'] : 229;

    return preg_replace_callback('/<img\b[^>]*>/i', function ($m) use ($antiga, $nova, $w, $h) {
        $tag = $m[0];
        if (strpos($tag, $antiga) === false) {
            return $tag;
        }

        // 1. Origem. O srcset da antiga não vale para a nova (outras dimensões): sai fora,
        //    e o navegador usa o src/data-src, que é o arquivo em resolução plena.
        $tag = str_replace($antiga, $nova, $tag);
        $tag = preg_replace('/\s+(data-)?srcset\s*=\s*(["\']).*?\2/i', '', $tag);
        $tag = preg_replace('/\s+(data-)?sizes\s*=\s*(["\']).*?\2/i', '', $tag);

        // 2. Dimensões intrínsecas — quem dimensiona é o CSS (ver cabeçalho).
        $tag = preg_replace('/\bwidth\s*=\s*(["\'])\d+\1/i',  'width="' . $w . '"',  $tag, 1);
        $tag = preg_replace('/\bheight\s*=\s*(["\'])\d+\1/i', 'height="' . $h . '"', $tag, 1);

        // 3. O Smush reserva o espaço do placeholder por variáveis CSS; sem atualizar,
        //    o rodapé guarda a caixa da imagem antiga e o layout dá um salto ao carregar.
        $tag = preg_replace('/--smush-placeholder-width:\s*\d+px/i',
            '--smush-placeholder-width: ' . $w . 'px', $tag);
        $tag = preg_replace('/--smush-placeholder-aspect-ratio:\s*[\d\/\s]+/i',
            '--smush-placeholder-aspect-ratio: ' . $w . '/' . $h, $tag);

        // 4. Marca para o CSS.
        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/i', $tag, $c)) {
            $tag = str_replace($c[0], 'class="' . $c[2] . ' bahia-logo-branca"', $tag);
        } else {
            $tag = preg_replace('/^<img\b/i', '<img class="bahia-logo-branca"', $tag, 1);
        }

        return $tag;
    }, $html);
}
add_filter('bahia_hs_html', 'bahia_logo_rodape_trocar');

/**
 * O filtro que faz a marca colorida sair branca.
 *
 * `brightness(0)` leva todo pixel visível a preto preservando o alfa; `invert(1)` o vira
 * branco. Resultado: branco chapado na resolução original, sem halo — é o mesmo caminho
 * de uma máscara, sem precisar de arquivo.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-logo-rodape', false, array(), '1.0.0');
    wp_enqueue_style('bahia-logo-rodape');
    $escala = BAHIA_LOGO_RODAPE_ESCALA;
    wp_add_inline_style('bahia-logo-rodape',
        ".bahia-logo-branca{filter:brightness(0) invert(1);max-width:{$escala};height:auto;}\n");
}, 46);
