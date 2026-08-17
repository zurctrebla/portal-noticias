<?php
/**
 * Plugin Name: Bahia.ba - Contadores de título e subtítulo no painel
 * Description: Mostra na tela de edição quantos caracteres o título e o subtítulo já têm,
 *              contra os limites de 70 e 160, e avisa quando passam. NÃO bloqueia o
 *              salvamento e NÃO corta nada: quem corta é o bahia-limites-texto.php, na
 *              exibição. Só o editor Clássico — é onde a redação escreve.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tipos que ganham os contadores.
 *
 * São as editorias (18 CPTs) mais o `post`. Ficam de fora: `page`, cujo título nunca é
 * cortado (não entra em listagem e, no próprio single, o filtro de exibição pula o post
 * da página), e os tipos internos do tagDiv e de plugins, que não são jornalismo.
 */
function bahia_limites_painel_tipo_coberto($post_type) {
    static $fora = array(
        'page', 'attachment', 'revision', 'nav_menu_item',
        'tdb_templates', 'tdc-review', 'tdc-review-email',
        'guest-author', 'foogallery',
    );

    if (!$post_type || in_array($post_type, $fora, true)) {
        return false;
    }

    $objeto = get_post_type_object($post_type);

    return $objeto && !empty($objeto->public) && post_type_supports($post_type, 'title');
}

/**
 * Os campos vigiados e o que dizer sobre cada um.
 *
 * Os dois avisos são deliberadamente diferentes porque os dois efeitos são diferentes:
 *   - o título é mesmo cortado na exibição, pelo the_title (bahia-limites-texto.php:115);
 *   - o subtítulo NÃO é cortado em lugar nenhum, nem aqui nem na produção. Passar de 160
 *     só faz o texto ocupar mais uma linha abaixo do H1. Prometer um corte que não
 *     acontece queimaria o contador na primeira vez que o repórter conferisse.
 */
function bahia_limites_painel_campos() {
    return array(
        array(
            'seletor'  => '#title',
            'limite'   => (int) (defined('BAHIA_LIMITE_TITULO') ? BAHIA_LIMITE_TITULO : 70),
            'aviso'    => 'Acima de 70 caracteres o título será cortado na exibição, em palavra inteira.',
            // O título é BLOQUEADO no limite: o site corta de verdade, então deixar passar
            // só produziria manchete truncada na home.
            'bloqueia' => true,
            'travado'  => 'Limite de 70 caracteres atingido.',
        ),
        array(
            'seletor'  => '.acf-field[data-name="subtitulo"] input[type="text"]',
            'limite'   => (int) (defined('BAHIA_LIMITE_RESUMO') ? BAHIA_LIMITE_RESUMO : 160),
            'aviso'    => 'Acima de 160 caracteres o subtítulo ocupa mais uma linha na matéria.',
            // Bloqueado também, por decisão de 17/08/2026. O subtítulo não é cortado na
            // exibição — o limite aqui é editorial, não técnico: mantém a chamada dentro
            // do tamanho para o qual o layout foi desenhado, em vez de deixar passar e
            // depois ocupar mais uma linha na matéria.
            'bloqueia' => true,
            'travado'  => 'Limite de 160 caracteres atingido.',
        ),
    );
}

add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'post.php' && $hook !== 'post-new.php') {
        return;
    }

    $tela = get_current_screen();
    if (!$tela || !bahia_limites_painel_tipo_coberto($tela->post_type)) {
        return;
    }

    // Gutenberg tem outra árvore de campos e nenhum #title. As editorias são todas
    // Clássico; o `post` é Gutenberg mas está desativado na prática (1 publicado).
    if (method_exists($tela, 'is_block_editor') && $tela->is_block_editor()) {
        return;
    }

    $config = array('campos' => bahia_limites_painel_campos());

    $css = <<<CSS
.bahia-contador{
    display:block;
    margin:6px 0 0;
    padding:3px 0;
    font-size:12px;
    line-height:1.6;
    color:#646970;
}
.bahia-contador-numero{
    font-variant-numeric:tabular-nums;
    font-weight:600;
}
.bahia-contador-aviso{
    margin-left:10px;
}
/* Âmbar de atenção do próprio admin, não o vermelho de erro: passar do limite não é
   engano do repórter, é informação sobre o que o site vai fazer com o texto. */
.bahia-contador-passou{
    padding:5px 9px;
    background:#fcf9e8;
    border-left:3px solid #dba617;
    border-radius:2px;
}
.bahia-contador-passou .bahia-contador-numero,
.bahia-contador-passou .bahia-contador-aviso{
    color:#996800;
}
/* Bloqueio acionado: vermelho por 1,6s. É o único momento em que a tela usa cor de
   erro, e é apropriado — aqui a digitação foi de fato recusada, ao contrário do aviso
   âmbar, que só informa. Sem isso o campo simplesmente para de responder e parece bug. */
.bahia-contador-travado{
    padding:5px 9px;
    background:#fcf0f1;
    border-left:3px solid #d63638;
    border-radius:2px;
}
.bahia-contador-travado .bahia-contador-numero,
.bahia-contador-travado .bahia-contador-aviso{
    color:#b32d2e;
    font-weight:600;
}
CSS;

    $js = <<<'JS'
(function () {
    'use strict';

    var cfg = window.bahiaLimitesPainel;
    if (!cfg || !cfg.campos || !cfg.campos.length) {
        return;
    }

    // textarea é elemento RCDATA: o innerHTML resolve entidade e não interpreta tag,
    // que é exatamente o que o html_entity_decode() faz do lado do PHP.
    var decodificador = document.createElement('textarea');

    /** trim() do PHP corta " \t\n\r\0\x0B"; o do JavaScript corta mais que isso. */
    function aparar(texto) {
        return texto.replace(/^[ \t\n\r\0\x0B]+/, '').replace(/[ \t\n\r\0\x0B]+$/, '');
    }

    /**
     * Mesma sequência do bahia_limites_cortar(), na mesma ordem:
     * wp_strip_all_tags() -> html_entity_decode() -> colapso de espaços -> trim.
     * A ordem importa: um "&lt;b&gt;" digitado sobrevive ao strip e só depois vira
     * "<b>", contando 3 caracteres — é assim que o site conta.
     */
    function limpar(valor) {
        var texto = String(valor === null || valor === undefined ? '' : valor);

        texto = texto.replace(/<(script|style)\b[^>]*>[\s\S]*?<\/\1>/gi, '');
        texto = texto.replace(/<[^>]*>/g, '');
        texto = aparar(texto);

        decodificador.innerHTML = texto;
        texto = decodificador.value;

        // O \s do PCRE sem PCRE_UCP não inclui espaço-duro; o do JavaScript inclui.
        // Listar os caracteres à mão evita o painel colapsar um espaço que o PHP conta.
        texto = texto.replace(/[ \t\n\r\f\v]+/g, ' ');

        return aparar(texto);
    }

    /** mb_strlen() conta pontos de código, não unidades UTF-16. */
    function medir(texto) {
        var n = 0;
        for (var i = 0; i < texto.length; i++) {
            var c = texto.charCodeAt(i);
            if (c >= 0xD800 && c <= 0xDBFF && i + 1 < texto.length) {
                var s = texto.charCodeAt(i + 1);
                if (s >= 0xDC00 && s <= 0xDFFF) {
                    i++;
                }
            }
            n++;
        }
        return n;
    }

    function montar(campo, entrada) {
        var caixa = document.createElement('div');
        caixa.className = 'bahia-contador';

        var numero = document.createElement('span');
        numero.className = 'bahia-contador-numero';

        var aviso = document.createElement('span');
        aviso.className = 'bahia-contador-aviso';
        aviso.textContent = campo.aviso;
        aviso.hidden = true;

        caixa.appendChild(numero);
        caixa.appendChild(aviso);
        caixa.setAttribute('aria-live', 'polite');

        entrada.parentNode.insertBefore(caixa, entrada.nextSibling);

        // Último valor aceito, e quanto ele media. Serve de ponto de retorno quando a
        // digitação passa do limite — é o que o maxlength nativo faria, só que contando
        // como o site conta, e não em unidades UTF-16.
        var ultimoValor = entrada.value;
        var ultimoLen   = medir(limpar(ultimoValor));
        var pisca       = null;

        function pintar(usado, travou) {
            numero.textContent = usado + '/' + campo.limite;

            if (travou) {
                aviso.textContent = campo.travado;
                aviso.hidden = false;
                caixa.className = 'bahia-contador bahia-contador-travado';
                if (pisca) { clearTimeout(pisca); }
                pisca = setTimeout(function () {
                    pisca = null;
                    atualizar();
                }, 1600);
                return;
            }

            var passou = usado > campo.limite;
            aviso.textContent = campo.aviso;
            aviso.hidden = !passou;
            caixa.className = passou ? 'bahia-contador bahia-contador-passou' : 'bahia-contador';
        }

        function atualizar() {
            var usado = medir(limpar(entrada.value));

            // Sem bloqueio: só conta e avisa.
            if (!campo.bloqueia) {
                ultimoValor = entrada.value;
                ultimoLen   = usado;
                pintar(usado, false);
                return;
            }

            // Dentro do limite, ou encolhendo um título antigo que já nasceu acima dele:
            // aceita. Matéria do acervo com 85 caracteres continua editável — só não pode
            // crescer.
            if (usado <= campo.limite || usado < ultimoLen) {
                ultimoValor = entrada.value;
                ultimoLen   = usado;
                pintar(usado, false);
                return;
            }

            // Cresceu além do que é permitido: corta o EXCEDENTE, preservando o começo.
            //
            // Cortar, e não restaurar o valor anterior: colar uma manchete de 110
            // caracteres num campo vazio devolvia o campo VAZIO, ou seja, o repórter
            // perdia o texto inteiro. Cortando, ele fica com os 70 primeiros e ajusta.
            //
            // O teto não é o limite, é o maior entre o limite e o que o campo já tinha:
            // matéria do acervo com 85 caracteres não pode crescer, mas também não pode
            // ser decepada para 70 porque alguém encostou numa tecla.
            var teto = Math.max(campo.limite, ultimoLen);
            var chars = Array.from(entrada.value);
            var giros = 0;

            while (chars.length > 0 && medir(limpar(chars.join(''))) > teto && giros < 5000) {
                chars.pop();
                giros++;
            }

            var cortado = chars.join('');
            var pos = entrada.selectionStart;

            entrada.value = cortado;
            if (typeof pos === 'number' && entrada.setSelectionRange) {
                var volta = Math.max(0, Math.min(cortado.length, pos));
                try { entrada.setSelectionRange(volta, volta); } catch (e) {}
            }

            ultimoValor = cortado;
            ultimoLen   = medir(limpar(cortado));
            pintar(ultimoLen, true);
        }

        entrada.addEventListener('input', atualizar);
        entrada.addEventListener('change', atualizar);
        pintar(ultimoLen, false);
    }

    function ligar() {
        for (var i = 0; i < cfg.campos.length; i++) {
            var campo = cfg.campos[i];
            var entrada = document.querySelector(campo.seletor);
            if (entrada && !entrada.dataset.bahiaContador) {
                entrada.dataset.bahiaContador = '1';
                montar(campo, entrada);
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ligar);
    } else {
        ligar();
    }
}());
JS;

    wp_register_style('bahia-limites-painel', false, array(), '1.0.0');
    wp_enqueue_style('bahia-limites-painel');
    wp_add_inline_style('bahia-limites-painel', $css);

    wp_register_script('bahia-limites-painel', false, array(), '1.0.0', true);
    wp_enqueue_script('bahia-limites-painel');
    wp_add_inline_script(
        'bahia-limites-painel',
        'window.bahiaLimitesPainel = ' . wp_json_encode($config) . ';',
        'before'
    );
    wp_add_inline_script('bahia-limites-painel', $js);
});
