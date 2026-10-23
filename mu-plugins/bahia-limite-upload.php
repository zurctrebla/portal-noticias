<?php
/**
 * Plugin Name: Bahia.ba - Limite de tamanho no upload de imagens
 * Description: Recusa no upload imagens pesadas demais para o site, com uma mensagem que diz
 *              ao redator o que fazer.
 *
 *              POR QUE: em 22/09/2026 um banner GIF de 35 MB (600x500, 300 quadros) entrou num
 *              anúncio exibido em todo o site. No mesmo período o CloudFront passou de ~75 GB
 *              para 3 a 5 TB por dia, por 26 dias (relatório de custo AWS de 23/10/2026). O
 *              mesmo banner em MP4 ficou com 91 KB. Este limite impede que um arquivo assim
 *              volte a entrar pela biblioteca de mídia.
 *
 *              Limites (ajustáveis por constante no wp-config.php):
 *                BAHIA_UPLOAD_MAX_GIF_MB     padrão 1  — GIF animado é vídeo disfarçado;
 *                                            acima disso, o certo é MP4.
 *                BAHIA_UPLOAD_MAX_IMAGEM_MB  padrão 8  — demais imagens (JPG, PNG, WebP...).
 *
 *              Só imagens: vídeo, PDF e outros tipos seguem os limites normais do servidor.
 *              Não afeta arquivos já enviados.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

function bahia_limite_upload_mb($constante, $padrao) {
    return (defined($constante) && is_numeric(constant($constante))) ? (float) constant($constante) : (float) $padrao;
}

/**
 * Valida o arquivo antes de o WordPress aceitar o upload (biblioteca de mídia, editor,
 * AdRotate e qualquer outro caminho que passe por wp_handle_upload/sideload).
 */
function bahia_limite_upload_filtro($arquivo) {
    if (!empty($arquivo['error']) || empty($arquivo['tmp_name']) || empty($arquivo['size'])) {
        return $arquivo;
    }

    $tipo = wp_check_filetype_and_ext($arquivo['tmp_name'], $arquivo['name']);
    $mime = !empty($tipo['type']) ? $tipo['type'] : (isset($arquivo['type']) ? $arquivo['type'] : '');
    if (strpos($mime, 'image/') !== 0) {
        return $arquivo; // só imagens
    }

    $mb = $arquivo['size'] / 1048576;
    $formatar = function ($n) { return number_format_i18n($n, $n < 10 ? 1 : 0); };

    if ($mime === 'image/gif') {
        $max = bahia_limite_upload_mb('BAHIA_UPLOAD_MAX_GIF_MB', 1);
        if ($mb > $max) {
            $arquivo['error'] = sprintf(
                'GIF muito pesado: %s MB (o limite para GIF é %s MB). GIF animado pesa como vídeo: '
                . 'converta para MP4 (fica dezenas de vezes menor) ou reduza tamanho e número de quadros.',
                $formatar($mb), $formatar($max)
            );
        }
        return $arquivo;
    }

    $max = bahia_limite_upload_mb('BAHIA_UPLOAD_MAX_IMAGEM_MB', 8);
    if ($mb > $max) {
        $arquivo['error'] = sprintf(
            'Imagem muito pesada: %s MB (o limite é %s MB). Reduza as dimensões (2000 px de largura '
            . 'bastam para o site) ou exporte em JPG/WebP com qualidade 80.',
            $formatar($mb), $formatar($max)
        );
    }
    return $arquivo;
}
add_filter('wp_handle_upload_prefilter', 'bahia_limite_upload_filtro');
add_filter('wp_handle_sideload_prefilter', 'bahia_limite_upload_filtro');
