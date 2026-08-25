<?php
/**
 * Plugin Name: Bahia.ba - Converte PNG para WebP no upload
 * Description: PNG entrou em 47% dos uploads desde maio/2026 (era 21% ate marco), e 91% deles
 *              sao FOTOGRAFIAS SEM TRANSPARENCIA ALGUMA — 600x420 pesando 296 KB em media.
 *              Medido: convertidos para WebP ficam ~9x mais leves, com diferenca por pixel
 *              abaixo do limiar perceptivel. Ver scratchpad/DIAGNOSTICO-PNG.md.
 *
 *              A conversao acontece ANTES de o anexo existir, o que evita todo o problema de
 *              renomear arquivo depois: o anexo ja nasce .webp, e o WP Offload registra o
 *              caminho certo em wp_as3cf_items desde o primeiro momento.
 *
 *              O PNG original NAO e descartado: fica ao lado e e enviado ao S3 junto com o
 *              anexo, pelo filtro as3cf_attachment_file_paths. Custa centavos por ano e um
 *              original perdido nao volta.
 *
 *              REGRA DE OURO: qualquer falha aqui devolve o upload intacto. Nenhuma imagem
 *              pode ser perdida por causa desta otimizacao.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/*
 * DESLIGADO POR PADRAO.
 *
 * Qualquer arquivo em mu-plugins/ entra em vigor no instante em que a imagem e reconstruida —
 * nao ha "commitar sem instalar" nesta pasta. Como este plugin so deve entrar depois da
 * conversa com a redacao, ele fica versionado e inerte ate alguem ligar a chave.
 *
 * Para ligar, defina a constante antes do WordPress carregar os mu-plugins (wp-config.php ou
 * um mu-plugin de nome anterior a este na ordem alfabetica):
 *
 *     define( 'BAHIA_WEBP_UPLOAD_ATIVO', true );
 *
 * Desligar e remover a constante: nao ha estado a desfazer, porque nada roda enquanto ela
 * estiver ausente.
 */
if (!defined('BAHIA_WEBP_UPLOAD_ATIVO') || !BAHIA_WEBP_UPLOAD_ATIVO) {
    return;
}

final class Bahia_WebP_Upload {

    /** Qualidade do WebP com perdas. Medida: q85 = ~9x menor que PNG e mais fiel que JPEG q82. */
    const QUALIDADE = 85;

    /**
     * Economia minima para valer a troca. Abaixo disso mantemos o PNG: trocar formato por 5%
     * nao paga o risco nem a perda do original como arquivo servido.
     */
    const ECONOMIA_MINIMA = 0.15;

    /**
     * Teto de pixels. Um RGBA de 25 MP ocupa ~100 MB cru, e o Imagick trabalha com copias.
     * Acima disso nao arriscamos o memory_limit de 512M durante um upload.
     */
    const MAX_PIXELS = 25000000;

    /**
     * Mapa caminho_final_webp => nome_do_arquivo_original, preenchido no upload e consumido
     * quando o anexo e criado. Nao usamos transient porque os dois hooks correm no MESMO
     * request; um transient sobreviveria a falhas e sujaria o banco.
     */
    private static $originais = array();

    public static function init() {
        add_filter('wp_handle_upload', array(__CLASS__, 'converter'), 10, 2);
        add_action('add_attachment', array(__CLASS__, 'registrar_original'));
        add_filter('as3cf_attachment_file_paths', array(__CLASS__, 'incluir_original_no_offload'), 10, 3);
        // O envio e a remocao usam filtros DIFERENTES no Offload. Sem este segundo, apagar o
        // anexo apagava o WebP e as derivadas e deixava o PNG original orfao no S3, pagando
        // armazenamento para sempre. Descoberto na validacao em homolog: sobrou exatamente
        // um objeto por pasta, e era sempre o original.
        add_filter('as3cf_remove_source_files_from_provider', array(__CLASS__, 'incluir_original_na_remocao'), 10, 3);
    }

    /**
     * Ponto de entrada. Roda depois de o arquivo estar validado e ja movido para uploads/,
     * e ANTES de o post do anexo existir.
     *
     * @param array  $upload array('file' => caminho, 'url' => ..., 'type' => mime)
     * @param string $contexto 'upload' ou 'sideload'
     * @return array o mesmo array, ou apontando para o .webp
     */
    public static function converter($upload, $contexto = 'upload') {

        // Tudo daqui para baixo e best-effort. Se qualquer coisa der errado, devolvemos o
        // upload como veio — o arquivo ja esta em disco e o WordPress segue o fluxo normal.
        try {
            if (!is_array($upload) || empty($upload['file']) || empty($upload['type'])) {
                return $upload;
            }
            if ('image/png' !== $upload['type']) {
                return $upload; // GIF (inclusive animado), JPEG e SVG ficam de fora de proposito
            }

            $origem = $upload['file'];
            if (!is_readable($origem)) {
                return $upload;
            }

            $info = @getimagesize($origem);
            if (!$info || empty($info[0]) || empty($info[1])) {
                return $upload;
            }
            if (($info[0] * $info[1]) > self::MAX_PIXELS) {
                return $upload;
            }

            $tamanho_origem = filesize($origem);
            if (!$tamanho_origem) {
                return $upload;
            }

            $tem_alfa = self::usa_alfa_de_verdade($origem);

            // Nao adivinhamos qual codificacao ganha: geramos as duas e ficamos com a menor.
            //
            // A heuristica que eu havia proposto — "menos de 3.000 cores unicas => sem perdas"
            // — foi REPROVADA na medicao. Uma captura de tela com 1.298 cores fica em 22 KB
            // com perdas e 68 KB sem perdas; uma arte com 2.381 cores, 9 KB contra 52 KB.
            // Sem perdas so ganhou no logo de 108 cores com transparencia. Contagem de cores
            // separa "arte" de "foto", mas nao responde qual codificacao comprime melhor.
            $tmp_perdas    = $origem . '.tmp-q' . self::QUALIDADE . '.webp';
            $tmp_semperdas = $origem . '.tmp-ll.webp';

            $ok_perdas    = self::codificar($origem, $tmp_perdas, false, $tem_alfa);
            $ok_semperdas = self::codificar($origem, $tmp_semperdas, true, $tem_alfa);

            $candidatos = array();
            if ($ok_perdas && file_exists($tmp_perdas) && filesize($tmp_perdas) > 0) {
                $candidatos[$tmp_perdas] = filesize($tmp_perdas);
            }
            if ($ok_semperdas && file_exists($tmp_semperdas) && filesize($tmp_semperdas) > 0) {
                $candidatos[$tmp_semperdas] = filesize($tmp_semperdas);
            }
            if (!$candidatos) {
                self::limpar(array($tmp_perdas, $tmp_semperdas));
                return $upload;
            }

            asort($candidatos);
            $vencedor = key($candidatos);
            $tam_novo = $candidatos[$vencedor];

            // Economia insuficiente: mantem o PNG e joga fora os temporarios.
            if ($tam_novo > $tamanho_origem * (1 - self::ECONOMIA_MINIMA)) {
                self::limpar(array($tmp_perdas, $tmp_semperdas));
                return $upload;
            }

            // Nome definitivo. wp_unique_filename() ja rodou para o .png, mas a troca de
            // extensao pode colidir com um .webp existente na mesma pasta.
            $dir       = dirname($origem);
            $base      = pathinfo($origem, PATHINFO_FILENAME);
            $nome_webp = wp_unique_filename($dir, $base . '.webp');
            $destino   = $dir . '/' . $nome_webp;

            if (!@rename($vencedor, $destino)) {
                self::limpar(array($tmp_perdas, $tmp_semperdas));
                return $upload;
            }
            self::limpar(array($tmp_perdas, $tmp_semperdas)); // o perdedor

            // Guarda o PNG ao lado. Vai para o S3 pelo filtro do Offload, mais abaixo.
            self::$originais[$destino] = basename($origem);

            $upload['file'] = $destino;
            $upload['url']  = dirname($upload['url']) . '/' . $nome_webp;
            $upload['type'] = 'image/webp';

            return $upload;

        } catch (\Throwable $e) {
            // Inclui Error, nao so Exception: falha de extensao ou memoria nao pode derrubar
            // o upload. O arquivo original continua em disco e valido.
            return is_array($upload) ? $upload : $upload;
        }
    }

    /**
     * O canal alfa existe E e usado?
     *
     * Um PNG do tipo RGBA nao significa transparencia: na amostra de agosto, CINCO arquivos
     * eram RGBA e NENHUM tinha um unico pixel translucido. Por isso a checagem varre pixels
     * em vez de confiar no tipo declarado.
     *
     * Amostragem em grade (~200x200 pontos) — suficiente para achar transparencia real, que
     * em logo e selo cobre area grande, e barata mesmo em imagem de 25 MP.
     */
    private static function usa_alfa_de_verdade($caminho) {
        $im = null;
        try {
            if (!function_exists('imagecreatefrompng')) {
                return true; // sem como verificar: assume que ha alfa e preserva
            }
            $im = @imagecreatefrompng($caminho);
            if (!$im) {
                return true;
            }
            $w = imagesx($im);
            $h = imagesy($im);
            $px = max(1, (int) floor($w / 200));
            $py = max(1, (int) floor($h / 200));

            for ($y = 0; $y < $h; $y += $py) {
                for ($x = 0; $x < $w; $x += $px) {
                    $cor = imagecolorat($im, $x, $y);
                    // Bits 24-30 guardam o alfa do GD: 0 = opaco, 127 = totalmente transparente.
                    if ((($cor >> 24) & 0x7F) > 3) {
                        imagedestroy($im);
                        return true;
                    }
                }
            }
            imagedestroy($im);
            return false;

        } catch (\Throwable $e) {
            if ($im) {
                @imagedestroy($im);
            }
            return true; // na duvida, preserva
        }
    }

    /**
     * Codifica em WebP. Imagick primeiro, GD como reserva.
     *
     * @param bool $sem_perdas true = lossless
     * @param bool $tem_alfa   quando true, garante que o canal seja preservado
     */
    private static function codificar($origem, $destino, $sem_perdas, $tem_alfa) {
        // --- Imagick ---
        if (class_exists('Imagick')) {
            $img = null;
            try {
                $img = new Imagick();
                $img->readImage($origem);
                $img->setImageFormat('webp');
                if ($sem_perdas) {
                    $img->setOption('webp:lossless', 'true');
                } else {
                    $img->setImageCompressionQuality(self::QUALIDADE);
                    $img->setOption('webp:method', '4');
                    if ($tem_alfa) {
                        // Sem isto o cwebp interno pode achatar o alfa no modo com perdas.
                        $img->setOption('webp:alpha-quality', '100');
                    }
                }
                if (!$tem_alfa) {
                    $img->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                }
                $img->stripImage(); // metadado nao serve para nada no site e pesa
                $ok = $img->writeImage($destino);
                $img->clear();
                $img->destroy();
                return (bool) $ok;
            } catch (\Throwable $e) {
                if ($img) {
                    @$img->clear();
                    @$img->destroy();
                }
                // cai para o GD
            }
        }

        // --- GD ---
        try {
            if (!function_exists('imagewebp') || !function_exists('imagecreatefrompng')) {
                return false;
            }
            $im = @imagecreatefrompng($origem);
            if (!$im) {
                return false;
            }
            if ($tem_alfa) {
                imagepalettetotruecolor($im);
                imagealphablending($im, false);
                imagesavealpha($im, true);
            }
            $ok = $sem_perdas && defined('IMG_WEBP_LOSSLESS')
                ? @imagewebp($im, $destino, IMG_WEBP_LOSSLESS)
                : @imagewebp($im, $destino, self::QUALIDADE);
            imagedestroy($im);
            return (bool) $ok;

        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function limpar($caminhos) {
        foreach ((array) $caminhos as $c) {
            if ($c && file_exists($c)) {
                @unlink($c);
            }
        }
    }

    /**
     * Liga o anexo recem-criado ao PNG que ficou ao lado.
     *
     * Guardamos o NOME, nao o caminho: o caminho muda quando o Offload move o arquivo, o
     * nome nao.
     */
    public static function registrar_original($post_id) {
        try {
            $arquivo = get_attached_file($post_id);
            if ($arquivo && isset(self::$originais[$arquivo])) {
                update_post_meta($post_id, '_bahia_webp_original', self::$originais[$arquivo]);
                unset(self::$originais[$arquivo]);
            }
        } catch (\Throwable $e) {
            // nao ha o que fazer aqui; o WebP ja esta correto e servindo
        }
    }

    /**
     * Manda o PNG original junto para o S3.
     *
     * O Offload sobe tudo o que estiver neste array, sob o MESMO prefixo com segmento de
     * versao do anexo, e registra em wp_as3cf_items.extra_info['objects']. Ou seja: o
     * original fica guardado, versionado junto e recuperavel por caminho deterministico —
     * sem virar um segundo item na biblioteca de midia.
     *
     * O proprio Offload descarta do array o que nao existir em disco, entao nao precisamos
     * checar aqui.
     */
    public static function incluir_original_no_offload($paths, $attachment_id, $meta) {
        try {
            $nome = get_post_meta($attachment_id, '_bahia_webp_original', true);
            if (!$nome || !is_array($paths)) {
                return $paths;
            }
            $ref = null;
            if (isset($paths['file'])) {
                $ref = $paths['file'];
            } elseif ($paths) {
                $ref = reset($paths);
            }
            if (!$ref) {
                return $paths;
            }
            $paths['bahia_original'] = dirname($ref) . '/' . $nome;
            return $paths;

        } catch (\Throwable $e) {
            return $paths;
        }
    }

    /**
     * Manda o PNG original embora junto com o resto, quando o anexo e apagado.
     *
     * Contraparte de incluir_original_no_offload(). O Offload usa um filtro para montar a
     * lista do que SOBE e outro para montar a lista do que SAI — quem so implementa o
     * primeiro deixa o original orfao no bucket.
     *
     * A meta ainda existe aqui: o Offload engancha em `delete_attachment` na prioridade 20, e
     * o WordPress so apaga os metadados depois de rodar essa acao.
     */
    public static function incluir_original_na_remocao($paths, $as3cf_item, $item_source) {
        try {
            $id = 0;
            if (is_array($item_source) && !empty($item_source['id'])) {
                $id = (int) $item_source['id'];
            } elseif (is_object($as3cf_item) && method_exists($as3cf_item, 'source_id')) {
                $id = (int) $as3cf_item->source_id();
            }
            if (!$id || !is_array($paths)) {
                return $paths;
            }
            $nome = get_post_meta($id, '_bahia_webp_original', true);
            if (!$nome) {
                return $paths;
            }
            $ref = isset($paths['file']) ? $paths['file'] : reset($paths);
            if (!$ref) {
                return $paths;
            }
            $paths['bahia_original'] = dirname($ref) . '/' . $nome;
            return $paths;

        } catch (\Throwable $e) {
            return $paths;
        }
    }
}

Bahia_WebP_Upload::init();
