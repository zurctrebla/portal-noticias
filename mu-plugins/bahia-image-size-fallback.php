<?php
/**
 * Plugin Name: Bahia.ba - Fallback de tamanho de imagem
 * Description: Quando o tema pede um tamanho que o anexo nao possui, o WordPress devolve o
 *              ARQUIVO ORIGINAL, mudando so os atributos width/height do HTML. Depois da
 *              virada para o Newspaper isso passou a valer para o acervo inteiro: o tema pede
 *              os dez tamanhos td_* e nenhum dos 153.842 anexos antigos os tem - foram gerados
 *              na epoca do bahia_refactor, com destaque_gigante/grande/pequeno/mini e news_home.
 *
 *              O efeito medido: pedir td_80x60 (uma miniatura de 80px) baixava o original de
 *              ~184 KB. Este filtro escolhe, no lugar do original, a MENOR derivada existente
 *              que ainda satisfaca as dimensoes pedidas.
 *
 *              A regra e generica de proposito - le o que existe em _wp_attachment_metadata e
 *              continua valendo se um dia surgirem outros tamanhos. Nao ha tabela fixa.
 *
 *              Duas travas, ambas para nao piorar o que ja funciona:
 *
 *              1. NUNCA escolhe derivada menor que o pedido. Isso viraria upscale e borrao.
 *
 *              2. Quando o tamanho pedido preserva a proporcao natural (uma das dimensoes e
 *                 zero, como td_696x0 ou td_0x420), a derivada escolhida tambem precisa
 *                 preservar. Sem esta trava, td_150x0 escolheria `thumbnail`, que e um
 *                 quadrado 150x150 cortado - e o card trocaria de enquadramento. Como o
 *                 metadata nao registra o flag de corte, a proporcao e comparada com a do
 *                 proprio original.
 *
 *              Se nada servir, devolve false e o fluxo segue no original, que e o
 *              comportamento de hoje. Casos em que, por construcao, nada muda (o original
 *              tipico do acervo e 600x420): td_324x400 (nenhuma derivada tem altura >= 400) e
 *              td_696x0 para cima (o proprio original ja e menor que o pedido). O ganho esta
 *              nos tamanhos pequenos, que sao a maioria das requisicoes.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Bahia_Image_Size_Fallback {

    /**
     * Tolerancia quando o alvo preserva a proporcao natural. 2% absorve arredondamento de
     * quem gerou a derivada sem deixar passar um corte de verdade.
     */
    const TOLERANCIA_NATURAL = 0.02;

    /**
     * Tolerancia quando o alvo E um recorte. Aqui a candidata nao precisa bater exatamente,
     * porque o consumidor vai recortar de novo — 10% aceita `destaque_grande` (1,44) no lugar
     * de `td_485x360` (1,35) e continua rejeitando com folga qualquer inversao de orientacao,
     * que e o que quebrou a producao em 25/08 (retrato 0,81 recebendo paisagem 1,92).
     */
    const TOLERANCIA_CORTE = 0.10;

    /**
     * Guarda contra reentrada. image_downsize() dispara o proprio filtro; sem isto a
     * substituicao chamaria a si mesma indefinidamente.
     */
    private static $reentrando = false;

    /** Cache por requisicao das dimensoes registradas, para nao remontar a cada imagem. */
    private static $registrados = null;

    public static function init() {
        // Prioridade 5: antes do WP Offload Media (10), que reescreve a URL para o S3/CloudFront.
        // A substituicao e feita re-chamando image_downsize() com o OUTRO nome de tamanho, de
        // modo que o offload faca o trabalho dele normalmente para o tamanho escolhido. Se
        // montassemos a URL na mao aqui, o CDN ficaria de fora.
        add_filter('image_downsize', array(__CLASS__, 'substituir'), 5, 3);
    }

    /**
     * @param bool|array   $out   false enquanto ninguem resolveu; array se ja resolvido.
     * @param int          $id    ID do anexo.
     * @param string|array $size  nome do tamanho, ou array de dimensoes cruas.
     * @return bool|array
     */
    public static function substituir($out, $id, $size) {

        if (false !== $out) {
            return $out; // alguem ja resolveu; nao interferimos
        }
        if (self::$reentrando) {
            return $out; // e a nossa propria chamada aninhada: deixa o fluxo normal seguir
        }
        if (!is_string($size) || '' === $size) {
            return $out; // array de dimensoes cruas nao passa por nome de tamanho
        }

        $meta = wp_get_attachment_metadata($id);
        if (!is_array($meta) || empty($meta['sizes']) || !is_array($meta['sizes'])) {
            return $out; // anexo sem derivada nenhuma: nao ha o que escolher (faixa 2017-2022)
        }
        if (isset($meta['sizes'][$size])) {
            return $out; // o tamanho pedido existe; nada a fazer
        }

        $alvo = self::dimensoes_registradas($size);
        if (null === $alvo) {
            return $out; // tamanho nao registrado (ex.: 'full'), fora do escopo
        }

        $proporcao_natural = null;
        if (!empty($meta['width']) && !empty($meta['height'])) {
            $proporcao_natural = (int) $meta['width'] / (int) $meta['height'];
        }

        /*
         * Qual proporcao a candidata precisa ter — e QUAL foi o erro que derrubou a producao
         * em 25/08.
         *
         * A versao anterior decidia isso por "uma das dimensoes e zero". Isso cobre td_696x0 e
         * td_0x420, mas deixa SEM VERIFICACAO NENHUMA os recortes com as duas dimensoes
         * definidas — td_218x150, td_324x400, td_485x360, que sao justamente os dos cards.
         * Resultado: um td_324x400 (retrato, 0,81) pedido para uma foto em pe recebia
         * `destaque_gigante` (paisagem, 1,92), o tema recortava de novo, e a foto perdia topo
         * e base. A redacao viu "cortando".
         *
         * O registro TEM a flag `crop`. A versao anterior a inferia em vez de le-la.
         *
         *   dimensao zero  -> o WordPress redimensiona preservando a proporcao do ORIGINAL
         *   crop = true    -> o alvo e um recorte: a proporcao PEDIDA e que manda
         *   crop = false   -> caixa maxima: preserva a proporcao do ORIGINAL
         */
        $uma_dimensao_livre = (0 === $alvo['width'] || 0 === $alvo['height']);

        if ($uma_dimensao_livre || empty($alvo['crop'])) {
            $proporcao_alvo = $proporcao_natural;
            $tolerancia     = self::TOLERANCIA_NATURAL;
        } else {
            $proporcao_alvo = $alvo['width'] / $alvo['height'];
            $tolerancia     = self::TOLERANCIA_CORTE;
        }

        $escolhido = self::melhor_derivada(
            $meta['sizes'],
            $alvo['width'],
            $alvo['height'],
            $proporcao_alvo,
            $tolerancia
        );

        if (null === $escolhido) {
            return $out; // nada satisfaz sem upscale ou sem trocar o enquadramento
        }

        self::$reentrando = true;
        $r = image_downsize($id, $escolhido);
        self::$reentrando = false;

        return $r;
    }

    /**
     * Menor derivada (por area) que cubra as duas dimensoes pedidas.
     *
     * Largura ou altura igual a zero significa "livre" - e como o WordPress representa os
     * tamanhos de uma dimensao so (td_696x0 restringe largura; td_0x420 restringe altura).
     *
     * @param array      $sizes          entradas de _wp_attachment_metadata['sizes'].
     * @param int        $w              largura pedida, 0 se livre.
     * @param int        $h              altura pedida, 0 se livre.
     * @param float|null $proporcao_alvo proporcao que a candidata precisa ter: a do ORIGINAL
     *                                   quando o alvo preserva a forma, ou a PEDIDA quando o
     *                                   alvo e um recorte. Sem ela, nao ha substituicao.
     * @param float      $tolerancia     desvio relativo aceito.
     * @return string|null
     */
    private static function melhor_derivada($sizes, $w, $h, $proporcao_alvo = null, $tolerancia = self::TOLERANCIA_NATURAL) {
        $melhor = null;
        $melhor_area = null;

        foreach ($sizes as $nome => $d) {
            if (empty($d['width']) || empty($d['height'])) {
                continue;
            }
            $cw = (int) $d['width'];
            $ch = (int) $d['height'];

            if ($w > 0 && $cw < $w) {
                continue; // estreita demais: upscale
            }
            if ($h > 0 && $ch < $h) {
                continue; // baixa demais: upscale
            }

            // Sem proporcao de referencia nao ha como julgar enquadramento — e sem julgar
            // enquadramento foi exatamente como a producao quebrou. Nao substitui.
            if (null === $proporcao_alvo || $proporcao_alvo <= 0) {
                continue;
            }
            $p = $cw / $ch;
            if (abs($p - $proporcao_alvo) / $proporcao_alvo > $tolerancia) {
                continue; // enquadramento incompativel: deixa o original, que e o certo
            }

            $area = $cw * $ch;
            if (null === $melhor_area || $area < $melhor_area) {
                $melhor_area = $area;
                $melhor = $nome;
            }
        }

        return $melhor;
    }

    /** Dimensoes com que o tamanho esta registrado nesta requisicao. */
    private static function dimensoes_registradas($size) {
        if (null === self::$registrados) {
            self::$registrados = wp_get_registered_image_subsizes();
        }
        if (!isset(self::$registrados[$size])) {
            return null;
        }
        return array(
            'width'  => (int) self::$registrados[$size]['width'],
            'height' => (int) self::$registrados[$size]['height'],
            // A flag que faltava. E o registro que diz se o tamanho e recorte ou caixa —
            // inferir isso por "dimensao zero" foi a causa da regressao de 25/08.
            'crop'   => !empty(self::$registrados[$size]['crop']),
        );
    }
}

Bahia_Image_Size_Fallback::init();
