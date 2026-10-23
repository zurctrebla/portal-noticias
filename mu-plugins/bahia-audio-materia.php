<?php
/**
 * Plugin Name: Bahia.ba - Ouça este conteúdo (áudio da matéria)
 * Description: KAN-36. Player "ouça este conteúdo" logo abaixo da linha de autor/data
 *              das matérias, no modelo do leitor de áudio do Bahia Notícias: play/pausa,
 *              barra em forma de onda que se preenche com o progresso, tempo e
 *              velocidade (1.0x, 1.25x, 1.5x, 2x, 0.75x).
 *
 *              DUAS FONTES DE ÁUDIO:
 *
 *              1) MP3 do Amazon Polly, GERADO SOB DEMANDA: quando alguém dá play numa
 *                 matéria que ainda não tem MP3 (ou cujo MP3 é de outro texto, voz ou
 *                 motor), o navegador pede ao servidor (endpoint REST público, com nonce,
 *                 travas e teto diário — ver bloco do Polly), o servidor monta o texto,
 *                 gera no Polly, grava no S3 e devolve a URL. Só o PRIMEIRO ouvinte de cada
 *                 matéria espera alguns segundos ("preparando áudio…"); os seguintes já
 *                 recebem o MP3. Custo = só as matérias efetivamente ouvidas: teto de
 *                 ~US$ 19/mês se TODAS as ~2.300 matérias do mês fossem ouvidas (Camila
 *                 standard, US$ 4 por milhão de caracteres, ~4,7 milhões/mês).
 *                 A geração está PRONTA MAS DESLIGADA: só roda com BAHIA_AUDIO_POLLY e
 *                 BAHIA_AUDIO_PREFIX definidos como constante (wp-config) ou variável de
 *                 ambiente do pod. NUNCA por opção do banco: o banco é copiado entre
 *                 produção e homolog, e uma chave guardada nele ligaria a geração em
 *                 homolog, gravando no bucket que é COMPARTILHADO com produção.
 *
 *              2) Voz do navegador (Web Speech API): modo PROVISÓRIO enquanto o Polly
 *                 estiver desligado, e reserva se a geração falhar ou passar de 25 s. A
 *                 voz é a que o aparelho tiver e costuma soar robotizada (avaliação em
 *                 homolog, 23/10/2026); o script escolhe a melhor voz pt-BR disponível.
 *                 A qualidade de verdade vem do Polly, que depende da permissão IAM
 *                 polly:SynthesizeSpeech — o usuário bahia-pipeline não tinha nem
 *                 polly:DescribeVoices em 23/10/2026. Sem Polly e sem síntese de voz no
 *                 navegador, o player não aparece.
 *
 *              O texto lido é o MESMO nos dois modos e é montado aqui no servidor
 *              (título, subtítulo e o corpo em parágrafos/intertítulos/listas/citações),
 *              sem legendas de foto, posts incorporados, figuras, scripts e anúncios. O
 *              endpoint NUNCA aceita texto do cliente.
 *
 *              TROCAR A VOZ (do Polly). Escolha da redação em 23/10/2026, depois de ouvir
 *              amostras reais de Camila standard, neural e generativa: CAMILA STANDARD.
 *              Configuração por constante (wp-config.php) ou variável de ambiente do pod
 *              — nunca no banco:
 *                  BAHIA_AUDIO_POLLY_VOICE   padrão "Camila"
 *                  BAHIA_AUDIO_POLLY_ENGINE  padrão "standard"
 *              Vozes pt-BR válidas: Camila [generative, neural, standard], Vitoria
 *              [neural, standard], Thiago [neural], Carolina [generative], Leo
 *              [generative], Ricardo [standard]. Combinação inválida cai em
 *              Camila/standard com aviso no log. Teto de custo se todas as matérias do
 *              mês fossem ouvidas (~4,7 milhões de caracteres): standard ≈ US$ 19,
 *              neural ≈ US$ 75, generative ≈ US$ 140 (US$ 4 / 16 / 30 por milhão). Trocar
 *              voz ou motor invalida os MP3 antigos: cada matéria é regerada na próxima
 *              vez que alguém der play. Detalhes e passo a passo no bloco do Polly.
 *
 *              O player entra pelo filtro de saída único (bahia-html-saida.php), antes do
 *              </header> do cabeçalho da matéria: o single é montado pelo loop-single.php
 *              do td-composer, que não tem gancho nesse ponto (plugins/ não se edita).
 * Version: 1.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_AUDIO_VER', '1.1.0');

/* =========================================================================
 *  TEXTO DA MATÉRIA
 * ========================================================================= */

/** Tipos de post que têm o player: o 'post' nativo + as editorias. */
function bahia_audio_tipos() {
    if (function_exists('bahia_autor_archive_post_types')) {
        return bahia_autor_archive_post_types();
    }
    if (function_exists('bahia_editorias_map')) {
        return array_merge(array('post'), array_keys(bahia_editorias_map()));
    }
    return array('post');
}

/**
 * Texto corrido da matéria para leitura em voz alta, em blocos (um por parágrafo).
 *
 * Trabalha sobre o post_content cru (sem the_content): não carrega anúncios, botões de
 * compartilhar nem blocos de "relacionadas" que outros filtros acrescentam, e é o mesmo
 * em qualquer contexto (página, WP-cron). Shortcodes saem inteiros — inclusive o
 * [caption], que é legenda de foto.
 *
 * @return string[] blocos de texto, na ordem; o primeiro é o título.
 */
function bahia_audio_blocos($post) {
    $post = get_post($post);
    if (!$post) {
        return array();
    }
    $blocos = array();
    // get_the_title() passa pelo wptexturize e devolve aspas como entidade (&#8216;).
    $titulo = trim(html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($titulo !== '') {
        $blocos[] = $titulo;
    }
    $sub = bahia_audio_subtitulo($post);
    if ($sub !== '') {
        $blocos[] = $sub;
    }

    $html = (string) $post->post_content;
    $html = preg_replace('#\[caption[^\]]*\].*?\[/caption\]#is', '', $html);
    $html = strip_shortcodes($html);
    $html = preg_replace('#\[/?[a-z_][^\]]*\]#i', '', $html); // shortcode não registrado
    if (trim($html) === '') {
        return $blocos;
    }
    // Conteúdo antigo vem sem <p>: wpautop dá a estrutura de parágrafos.
    $html = wpautop($html);

    $dom = new DOMDocument();
    $anterior = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8"?><div id="bahia-audio-raiz">' . $html . '</div>');
    libxml_clear_errors();
    libxml_use_internal_errors($anterior);

    $xp = new DOMXPath($dom);
    // Fora da leitura: mídia, embeds, legendas, scripts e citações COM classe (são os
    // posts incorporados do X/Instagram/TikTok; a citação comum não tem classe).
    $fora = array(
        '//script', '//style', '//noscript', '//iframe', '//figure', '//figcaption',
        '//img', '//video', '//audio', '//object', '//embed', '//table', '//form',
        '//blockquote[@class]', '//*[contains(@class,"wp-caption")]',
        '//*[contains(@class,"adrotate") or contains(@class,"td-a-rec") or contains(@class,"td-a-ad")]',
    );
    foreach ($fora as $q) {
        foreach (iterator_to_array($xp->query($q)) as $n) {
            if ($n->parentNode) {
                $n->parentNode->removeChild($n);
            }
        }
    }

    $raiz = $dom->getElementById('bahia-audio-raiz');
    if (!$raiz) {
        return $blocos;
    }
    foreach ($xp->query('.//p|.//h2|.//h3|.//h4|.//h5|.//h6|.//li|.//blockquote', $raiz) as $n) {
        // Só os blocos "folha": um <p> dentro de <blockquote> ou <li> é lido uma vez.
        if ($xp->query('.//p|.//li|.//h2|.//h3|.//h4|.//blockquote', $n)->length > 0) {
            continue;
        }
        $t = html_entity_decode($n->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = trim(preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $t)));
        if ($t === '' || !preg_match('/[\p{L}\p{N}]/u', $t)) {
            continue;
        }
        // Créditos soltos de foto ("Foto: Fulano/Agência") não são parte do texto.
        if (preg_match('/^(foto|fotos|imagem|crédito|reprodução)\s*[:\-–]/iu', $t) && mb_strlen($t) < 120) {
            continue;
        }
        $blocos[] = $t;
    }
    return $blocos;
}

/**
 * Subtítulo da matéria: o campo da redação (bahia-subtitulo.php) ou, na falta dele, o
 * subtítulo do tagDiv — é este que o cabeçalho do single exibe.
 */
function bahia_audio_subtitulo($post) {
    $s = function_exists('bahia_subtitulo_texto') ? (string) bahia_subtitulo_texto($post->ID) : '';
    if ($s === '') {
        $td = get_post_meta($post->ID, 'td_post_theme_settings', true);
        if (is_array($td) && !empty($td['td_subtitle'])) {
            $s = $td['td_subtitle'];
        }
    }
    $s = html_entity_decode(wp_strip_all_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $s));
}

/* =========================================================================
 *  PLAYER
 * ========================================================================= */

/** A página é uma matéria que deve ter o player? Devolve o WP_Post ou null. */
function bahia_audio_post_da_pagina() {
    if (is_admin() || !is_singular(bahia_audio_tipos())) {
        return null;
    }
    // Nunca em prévia/rascunho nem em matéria com senha: o player poderia disparar uma
    // geração paga de um texto que não está publicado.
    if (is_preview()) {
        return null;
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post || $post->post_status !== 'publish' || post_password_required($post)) {
        return null;
    }
    /** Permite desligar o player numa matéria específica. */
    if (!apply_filters('bahia_audio_mostrar', true, $post)) {
        return null;
    }
    return $post;
}

add_filter('bahia_hs_html', 'bahia_audio_injetar', 25);

function bahia_audio_injetar($html) {
    if (!is_string($html) || strpos($html, 'class="bahia-audio"') !== false) {
        return $html;
    }
    $post = bahia_audio_post_da_pagina();
    if (!$post) {
        return $html;
    }
    $blocos = bahia_audio_blocos($post);
    // Só o título não vale um player.
    if (count($blocos) < 2) {
        return $html;
    }

    $cab = strpos($html, '<div class="td-post-header">');
    if ($cab === false) {
        return $html;
    }
    $fim = strpos($html, '</header>', $cab);
    if ($fim === false) {
        return $html;
    }
    return substr_replace($html, bahia_audio_player_html($post, $blocos), $fim, 0);
}

function bahia_audio_player_html(WP_Post $post, array $blocos) {
    // MP3 só vale se for do texto, da voz e do motor ATUAIS; senão, o play pede um novo.
    list($voz, $motor) = bahia_audio_voz_motor();
    $mp3 = bahia_audio_em_dia($post->ID, bahia_audio_hash($blocos), $voz, $motor)
        ? (string) get_post_meta($post->ID, '_bahia_audio_url', true) : '';
    $gerar = $mp3 === '' && bahia_audio_polly_ativo();
    $dados = array(
        'blocos' => $blocos,
        'mp3'    => $mp3 !== '' ? esc_url_raw($mp3) : '',
        'gerar'  => $gerar,
        'api'    => $gerar ? esc_url_raw(rest_url('bahia/v1/audio/' . $post->ID)) : '',
        'nonce'  => $gerar ? wp_create_nonce('bahia_audio_' . $post->ID) : '',
        'espera' => 25, // segundos até desistir do MP3 e usar a voz do navegador
    );
    $json = wp_json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);

    // Barras decorativas da "onda": alturas pseudoaleatórias estáveis por matéria.
    $barras = '';
    mt_srand($post->ID);
    for ($i = 0; $i < 64; $i++) {
        $h = 25 + mt_rand(0, 75);
        $barras .= '<span style="height:' . $h . '%"></span>';
    }
    mt_srand();

    $play  = '<svg class="bahia-audio__ico-play" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M10 8.2v7.6l6-3.8z" fill="currentColor"/></svg>';
    $pausa = '<svg class="bahia-audio__ico-pausa" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M9.3 8.3h1.9v7.4H9.3zm3.5 0h1.9v7.4h-1.9z" fill="currentColor"/></svg>';

    return '<div class="bahia-audio" hidden>'
        . '<div class="bahia-audio__topo"><span class="bahia-audio__rotulo">ouça este conteúdo</span>'
        . '<span class="bahia-audio__aviso" role="status" aria-live="polite"></span></div>'
        . '<div class="bahia-audio__barra">'
        . '<button type="button" class="bahia-audio__play" aria-label="Ouvir esta matéria" aria-pressed="false">' . $play . $pausa . '</button>'
        . '<div class="bahia-audio__onda" role="slider" tabindex="0" aria-label="Progresso do áudio" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
        . '<div class="bahia-audio__ondas">' . $barras . '</div>'
        . '<div class="bahia-audio__ondas bahia-audio__ondas--feito" aria-hidden="true">' . $barras . '</div>'
        . '</div>'
        . '<span class="bahia-audio__tempo" aria-live="off">0:00</span>'
        . '<button type="button" class="bahia-audio__vel" aria-label="Velocidade de leitura: 1.0x">1.0x</button>'
        . '</div>'
        . '<script type="application/json" class="bahia-audio__dados">' . $json . '</script>'
        . '</div>';
}

add_action('wp_enqueue_scripts', function () {
    if (!bahia_audio_post_da_pagina()) {
        return;
    }
    wp_register_style('bahia-audio', false, array(), BAHIA_AUDIO_VER);
    wp_enqueue_style('bahia-audio');
    wp_add_inline_style('bahia-audio', bahia_audio_css());

    wp_register_script('bahia-audio', false, array(), BAHIA_AUDIO_VER, true);
    wp_enqueue_script('bahia-audio');
    wp_add_inline_script('bahia-audio', bahia_audio_js());
}, 40);

function bahia_audio_css() {
    return <<<CSS
.bahia-audio{margin:14px 0 4px;max-width:100%;font-family:Verdana,Geneva,sans-serif;}
.bahia-audio[hidden]{display:none !important;}
.bahia-audio__topo{display:flex;justify-content:space-between;align-items:baseline;border-bottom:1px solid #e4e7ec;padding-bottom:4px;}
.bahia-audio__rotulo{font-size:11px;color:#15559e;letter-spacing:.2px;}
.bahia-audio__barra{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #e4e7ec;}
.bahia-audio button{background:none;border:0;padding:0;margin:0;cursor:pointer;color:#15559e;font:inherit;line-height:1;}
.bahia-audio button:focus-visible,.bahia-audio__onda:focus-visible{outline:2px solid #15559e;outline-offset:2px;border-radius:3px;}
.bahia-audio__play{flex:0 0 auto;width:28px;height:28px;display:flex;align-items:center;justify-content:center;}
.bahia-audio__play svg{width:28px;height:28px;}
.bahia-audio__ico-pausa{display:none;}
.bahia-audio.is-tocando .bahia-audio__ico-play{display:none;}
.bahia-audio.is-tocando .bahia-audio__ico-pausa{display:block;}
.bahia-audio__onda{position:relative;flex:1 1 auto;min-width:0;height:22px;cursor:pointer;}
.bahia-audio__ondas{position:absolute;inset:0;display:flex;align-items:center;gap:2px;color:#c9d6e6;}
.bahia-audio__ondas span{flex:1 1 0;min-width:1px;background:currentColor;border-radius:1px;}
.bahia-audio__ondas--feito{color:#15559e;clip-path:inset(0 100% 0 0);transition:clip-path .25s linear;}
.bahia-audio__tempo{flex:0 0 auto;min-width:34px;text-align:right;font-size:11px;color:#555;font-variant-numeric:tabular-nums;}
.bahia-audio__vel{flex:0 0 auto;background:#15559e !important;color:#fff !important;font-size:11px !important;font-weight:700;padding:4px 6px !important;border-radius:3px;min-width:38px;}
/* Preparando o MP3 (primeiro ouvinte): o círculo do play gira e o aviso aparece no topo. */
.bahia-audio__aviso{font-size:11px;color:#555;text-align:right;margin-left:10px;}
.bahia-audio.is-preparando .bahia-audio__play{cursor:progress;}
.bahia-audio.is-preparando .bahia-audio__play svg{animation:bahia-audio-gira 1s linear infinite;}
.bahia-audio.is-preparando .bahia-audio__ico-play path{opacity:.35;}
.bahia-audio.is-preparando .bahia-audio__ico-play circle{stroke-dasharray:48 18;}
@keyframes bahia-audio-gira{to{transform:rotate(360deg);}}
@media (prefers-reduced-motion:reduce){.bahia-audio.is-preparando .bahia-audio__play svg{animation:none;}}
/* Modo escuro (KAN-15 põe a classe bahia-dark no <html>). Cores próprias, sem depender
   do arquivo do modo escuro; contraste medido sobre o fundo #121417 da paleta escura:
   rótulo, ícone e onda tocada 8,98:1; tempo 9,62:1; onda base 3,26:1; botão de
   velocidade 9,2:1 (texto escuro sobre azul claro). */
html.bahia-dark .bahia-audio__topo,html.bahia-dark .bahia-audio__barra{border-bottom-color:#3a424c;}
html.bahia-dark .bahia-audio__rotulo{color:#8ab8f2;}
html.bahia-dark .bahia-audio__tempo,html.bahia-dark .bahia-audio__aviso{color:#b4bcc6;}
html.bahia-dark .bahia-audio button{color:#8ab8f2;}
html.bahia-dark .bahia-audio__ondas{color:#5d6876;}
html.bahia-dark .bahia-audio__ondas--feito{color:#8ab8f2;}
html.bahia-dark .bahia-audio__vel{background:#8ab8f2 !important;color:#0f1115 !important;}
html.bahia-dark .bahia-audio button:focus-visible,html.bahia-dark .bahia-audio__onda:focus-visible{outline-color:#8ab8f2;}
@media (max-width:767px){
  .bahia-audio{margin:12px 0 2px;}
  .bahia-audio__barra{gap:8px;}
  .bahia-audio__ondas span:nth-child(2n){display:none;}
}
CSS;
}

function bahia_audio_js() {
    return <<<'JS'
(function () {
    'use strict';
    var el = document.querySelector('.bahia-audio');
    if (!el) { return; }
    var dados;
    try { dados = JSON.parse(el.querySelector('.bahia-audio__dados').textContent); } catch (e) { return; }
    var temSintese = 'speechSynthesis' in window && 'SpeechSynthesisUtterance' in window;
    // Sem MP3, sem Polly ligado e sem voz no navegador: nada a oferecer.
    if (!dados.mp3 && !dados.gerar && !temSintese) { return; }
    el.hidden = false;

    var btPlay = el.querySelector('.bahia-audio__play');
    var btVel = el.querySelector('.bahia-audio__vel');
    var onda = el.querySelector('.bahia-audio__onda');
    var feito = el.querySelector('.bahia-audio__ondas--feito');
    var tempo = el.querySelector('.bahia-audio__tempo');
    var aviso = el.querySelector('.bahia-audio__aviso');
    var VELS = [1, 1.25, 1.5, 2, 0.75];
    var iVel = 0;
    var tocando = false;
    // motor: '' (nada escolhido), 'mp3', 'voz' ou 'preparando' (esperando o servidor gerar)
    var motor = dados.mp3 ? 'mp3' : '';

    function fmt(s) {
        s = Math.max(0, Math.round(s || 0));
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2);
    }
    function pinta(fracao, seg) {
        var p = Math.max(0, Math.min(1, fracao || 0));
        feito.style.clipPath = 'inset(0 ' + (100 - p * 100).toFixed(2) + '% 0 0)';
        onda.setAttribute('aria-valuenow', Math.round(p * 100));
        tempo.textContent = fmt(seg);
    }
    function estado(t) {
        tocando = t;
        el.classList.toggle('is-tocando', t);
        btPlay.setAttribute('aria-pressed', t ? 'true' : 'false');
        btPlay.setAttribute('aria-label', t ? 'Pausar a leitura' : 'Ouvir esta matéria');
    }
    function avisa(txt) { aviso.textContent = txt || ''; }

    /* ---------------- MP3 ---------------- */
    // O <audio> é criado já no primeiro clique e "destravado" com um som vazio: no iPhone,
    // tocar depois da espera do servidor exige que o elemento tenha sido iniciado por um
    // gesto do usuário.
    var audio = null;
    var MUDO = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQAAAAA=';
    function criaAudio() {
        if (audio) { return audio; }
        audio = new Audio();
        audio.preload = 'none';
        audio.addEventListener('timeupdate', function () {
            if (motor !== 'mp3') { return; }
            pinta(audio.duration ? audio.currentTime / audio.duration : 0, audio.currentTime);
        });
        audio.addEventListener('ended', function () { if (motor === 'mp3') { estado(false); pinta(1, audio.duration); } });
        audio.addEventListener('pause', function () { if (motor === 'mp3') { estado(false); } });
        audio.addEventListener('play', function () { if (motor === 'mp3') { estado(true); } });
        audio.addEventListener('error', function () {
            if (motor !== 'mp3' || !audio.getAttribute('src') || audio.getAttribute('src') === MUDO) { return; }
            reserva('Áudio indisponível; lendo com a voz do navegador.');
        });
        return audio;
    }
    function destrava() {
        var a = criaAudio();
        if (a.getAttribute('src')) { return; }
        a.src = MUDO;
        var p = a.play();
        if (p && p.catch) { p.then(function () { a.pause(); }).catch(function () {}); }
    }
    function tocaMp3(url) {
        var a = criaAudio();
        motor = 'mp3';
        el.classList.remove('is-preparando');
        if (a.getAttribute('src') !== url) { a.src = url; }
        a.playbackRate = VELS[iVel];
        var p = a.play();
        if (p && p.catch) {
            p.catch(function () {
                // Navegador recusou tocar sem novo gesto: fica pronto para o próximo clique.
                estado(false);
                avisa('Áudio pronto: toque no play.');
            });
        }
    }

    /* ---------------- geração sob demanda ---------------- */
    var pedido = null;
    function prepara() {
        motor = 'preparando';
        el.classList.add('is-preparando');
        btPlay.setAttribute('aria-label', 'Preparando o áudio');
        avisa('preparando áudio…');
        var limite = Date.now() + (dados.espera || 25) * 1000;
        var meu = pedido = {};
        function tenta() {
            if (pedido !== meu) { return; }
            if (Date.now() > limite) { return reserva('O áudio demorou; lendo com a voz do navegador.'); }
            var x = new XMLHttpRequest();
            x.open('POST', dados.api, true);
            x.setRequestHeader('Content-Type', 'application/json');
            x.timeout = Math.max(3000, limite - Date.now());
            x.onload = function () {
                if (pedido !== meu) { return; }
                var r = null;
                try { r = JSON.parse(x.responseText); } catch (e) {}
                if (x.status === 200 && r && r.url) { pedido = null; avisa(''); return tocaMp3(r.url); }
                // 202: outra pessoa já está gerando este áudio — consulta de novo em 2 s.
                if (x.status === 202) { return setTimeout(tenta, 2000); }
                reserva('Áudio indisponível agora; lendo com a voz do navegador.');
            };
            x.onerror = function () {
                if (pedido === meu) { reserva('Áudio indisponível agora; lendo com a voz do navegador.'); }
            };
            // O servidor segue gerando (ignore_user_abort): o próximo ouvinte já recebe o MP3.
            x.ontimeout = function () {
                if (pedido === meu) { reserva('O áudio demorou; lendo com a voz do navegador.'); }
            };
            x.send(JSON.stringify({ nonce: dados.nonce }));
        }
        tenta();
    }
    function reserva(msg) {
        pedido = null;
        el.classList.remove('is-preparando');
        if (!temSintese) { motor = ''; estado(false); avisa('Áudio indisponível no momento.'); return; }
        motor = 'voz';
        avisa(msg);
        iniciaVoz();
        tocaVoz();
    }

    /* ---------------- voz do navegador ---------------- */
    // Frases curtas: o Chrome corta falas longas (~15 s) e pause()/resume() é instável
    // nos navegadores. Pausar = cancelar e lembrar a frase; retomar = falar dela em diante.
    var frases = [], acum = [], total = 0, iFrase = 0, voz = null, falando = null, vozPronta = false;
    var CPS = 14.5; // caracteres por segundo a 1x (estimativa do tempo)

    function fatia(texto) {
        var out = [];
        (texto.match(/[^.!?…;:]+[.!?…;:]*["'”’»)]*\s*/g) || [texto]).forEach(function (f) {
            f = f.trim();
            while (f.length > 220) {
                var corte = f.lastIndexOf(', ', 200);
                if (corte < 60) { corte = f.lastIndexOf(' ', 200); }
                if (corte < 60) { corte = 200; }
                out.push(f.slice(0, corte + 1).trim());
                f = f.slice(corte + 1).trim();
            }
            if (f) { out.push(f); }
        });
        return out;
    }
    function iniciaVoz() {
        if (vozPronta || !temSintese) { return; }
        vozPronta = true;
        frases = [];
        (dados.blocos || []).forEach(function (b) { frases = frases.concat(fatia(b)); });
        acum = []; total = 0;
        frases.forEach(function (f) { acum.push(total); total += f.length; });
        escolheVoz();
        if (window.speechSynthesis.addEventListener) {
            window.speechSynthesis.addEventListener('voiceschanged', escolheVoz);
        }
    }
    // Escolha da voz: o navegador lista dezenas de vozes em português e a primeira costuma
    // ser a pior. Pontua cada uma e fica com a melhor que o aparelho tiver.
    //   + vozes neurais/naturais/online/premium/aprimoradas (Edge, Chrome, Apple baixadas)
    //   + Google português do Brasil, Microsoft Francisca/Antonio/Thalita, Apple Luciana/Felipe
    //   - vozes "novidade" da Apple (Eddy, Flo, Grandma, Rocko...), que são as robóticas
    //   + pt-BR antes de pt-PT; online (localService=false) leva vantagem de desempate.
    var VOZ_BOA = /natural|neural|online|premium|enhanced|aprimorad|melhorad/i;
    var VOZ_CONHECIDA = /google portugu[eê]s do brasil|francisca|antonio|thalita|luciana|felipe|fernanda|camila|vit[oó]ria|thiago/i;
    var VOZ_NOVIDADE = /\b(eddy|flo|grandma|grandpa|reed|rocko|sandy|shelley|albert|bad news|bahh|bells|boing|bubbles|cellos|wobble|jester|organ|superstar|trinoids|whisper|zarvox|fred|junior|kathy|ralph)\b/i;
    function nota(v) {
        var n = 0;
        if (/^pt[-_]BR/i.test(v.lang)) { n += 30; } else if (/^pt/i.test(v.lang)) { n += 5; } else { return -1000; }
        if (VOZ_BOA.test(v.name)) { n += 100; }
        if (/google/i.test(v.name)) { n += 80; }
        if (VOZ_CONHECIDA.test(v.name)) { n += 60; }
        if (VOZ_NOVIDADE.test(v.name)) { n -= 200; }
        if (v.localService === false) { n += 10; }
        return n;
    }
    function escolheVoz() {
        var vs = (window.speechSynthesis.getVoices() || []).slice();
        vs.sort(function (a, b) { return nota(b) - nota(a); });
        voz = (vs[0] && nota(vs[0]) > -1000) ? vs[0] : null;
    }
    function progressoVoz(extra) {
        var chars = (acum[iFrase] || (iFrase >= frases.length ? total : 0)) + (extra || 0);
        pinta(total ? chars / total : 0, chars / CPS / VELS[iVel]);
    }
    function tocaVoz() {
        var s = window.speechSynthesis;
        if (iFrase >= frases.length) { iFrase = 0; }
        if (!voz) { escolheVoz(); }
        var u = new SpeechSynthesisUtterance(frases[iFrase]);
        u.lang = voz ? voz.lang : 'pt-BR';
        if (voz) { u.voice = voz; }
        u.rate = VELS[iVel]; // 1.0 por padrão; muda só pelo botão de velocidade
        u.pitch = 1;
        u.onboundary = function (ev) { if (falando === u && ev.charIndex) { progressoVoz(ev.charIndex); } };
        u.onend = function () {
            if (falando !== u) { return; } // cancelada por pausa/velocidade/salto
            iFrase++;
            progressoVoz(0);
            if (iFrase < frases.length && tocando) { tocaVoz(); } else { falando = null; estado(false); if (iFrase >= frases.length) { iFrase = 0; } }
        };
        u.onerror = function (ev) {
            if (falando !== u || ev.error === 'interrupted' || ev.error === 'canceled') { return; }
            falando = null; estado(false);
        };
        falando = u;
        estado(true);
        progressoVoz(0);
        s.speak(u);
    }
    function paraVoz() {
        falando = null;
        window.speechSynthesis.cancel();
        estado(false);
    }

    // Antes do primeiro play: com o Polly desligado, mostra a duração estimada da leitura.
    if (!dados.mp3 && !dados.gerar && temSintese) {
        iniciaVoz();
        pinta(0, 0);
        tempo.textContent = fmt(total / CPS);
    }

    /* ---------------- controles ---------------- */
    btPlay.addEventListener('click', function () {
        if (motor === 'preparando') { return; } // já pedido; não sobrepõe a voz do navegador
        if (motor === 'mp3') {
            var a = criaAudio();
            if (!a.getAttribute('src') || a.getAttribute('src') === MUDO) { return tocaMp3(dados.mp3); }
            if (a.paused) { a.playbackRate = VELS[iVel]; a.play(); } else { a.pause(); }
            return;
        }
        if (motor === 'voz') {
            if (tocando) { paraVoz(); } else { window.speechSynthesis.cancel(); tocaVoz(); }
            return;
        }
        // Primeiro play sem MP3 válido: gera no servidor (Polly ligado) ou usa a voz.
        if (dados.gerar && dados.api) { destrava(); return prepara(); }
        if (temSintese) { motor = 'voz'; iniciaVoz(); window.speechSynthesis.cancel(); tocaVoz(); }
    });

    btVel.addEventListener('click', function () {
        iVel = (iVel + 1) % VELS.length;
        var r = VELS[iVel];
        var txt = (r % 1 === 0 ? r.toFixed(1) : String(r)) + 'x';
        btVel.textContent = txt;
        btVel.setAttribute('aria-label', 'Velocidade de leitura: ' + txt);
        if (motor === 'mp3' && audio) { audio.playbackRate = r; return; }
        if (motor === 'voz' && tocando) { falando = null; window.speechSynthesis.cancel(); tocaVoz(); } // reinicia a frase na nova velocidade
    });

    function salta(fracao) {
        fracao = Math.max(0, Math.min(1, fracao));
        if (motor === 'mp3') {
            if (audio && audio.duration) { audio.currentTime = fracao * audio.duration; }
            return;
        }
        // Na voz do navegador o salto escolhe a frase; antes do primeiro play com Polly
        // ligado, não há o que saltar ainda.
        if (motor !== 'voz' && (motor !== '' || dados.gerar)) { return; }
        iniciaVoz();
        var alvo = fracao * total, i = 0;
        while (i + 1 < acum.length && acum[i + 1] <= alvo) { i++; }
        iFrase = i;
        if (tocando) { falando = null; window.speechSynthesis.cancel(); tocaVoz(); } else { progressoVoz(0); }
    }
    onda.addEventListener('click', function (ev) {
        var r = onda.getBoundingClientRect();
        salta((ev.clientX - r.left) / r.width);
    });
    onda.addEventListener('keydown', function (ev) {
        var atual = (parseInt(onda.getAttribute('aria-valuenow'), 10) || 0) / 100;
        if (ev.key === 'ArrowRight') { salta(atual + 0.05); ev.preventDefault(); }
        else if (ev.key === 'ArrowLeft') { salta(atual - 0.05); ev.preventDefault(); }
        else if (ev.key === ' ' || ev.key === 'Enter') { btPlay.click(); ev.preventDefault(); }
    });

    // Sair da página não pode deixar a voz falando sozinha.
    window.addEventListener('pagehide', function () { if (temSintese) { window.speechSynthesis.cancel(); } });
})();
JS;
}

/* =========================================================================
 *  GERAÇÃO NO AMAZON POLLY — SOB DEMANDA (preparada, DESLIGADA por padrão)
 *
 *  COMO FUNCIONA
 *    O MP3 é gerado quando alguém dá PLAY numa matéria sem MP3 válido (texto, voz e
 *    motor atuais). O player chama POST /wp-json/bahia/v1/audio/<id>; o servidor monta o
 *    texto (nunca aceita texto do cliente), chama o Polly, grava no S3 e devolve a URL.
 *    Só o primeiro ouvinte espera (alguns segundos; o player mostra "preparando áudio…" e,
 *    passados 25 s ou havendo erro, lê com a voz do navegador). Editar a matéria muda o
 *    texto e invalida o MP3: a próxima escuta regera.
 *    Custo = só as matérias ouvidas. Teto, se TODAS as ~2.300 matérias do mês fossem
 *    ouvidas (~4,7 milhões de caracteres): ~US$ 19/mês com Camila standard.
 *
 *  PROTEÇÕES (o endpoint é público e cada geração custa dinheiro)
 *    - só matéria publicada, de tipo editorial, sem senha; nunca prévia/rascunho;
 *    - nonce por matéria, emitido na página;
 *    - uma geração por matéria por vez (trava atômica em wp_options; quem chega durante a
 *      geração recebe 202 e o player consulta de novo em 2 s — sem stampede);
 *    - teto diário de gerações (BAHIA_AUDIO_COTA_DIARIA, padrão 150 ≈ 300 mil caracteres
 *      ≈ US$ 1,20/dia em standard); ao bater, responde 429 e registra no error_log;
 *    - teto de tamanho por matéria (BAHIA_AUDIO_MAX_CHARS, padrão 20000).
 *
 *  LIGAR EM PRODUÇÃO
 *    1. IAM: dar polly:SynthesizeSpeech à credencial do site — a do WP Offload Media
 *       (que já grava no bucket) ou uma dedicada em BAHIA_POLLY_KEY / BAHIA_POLLY_SECRET
 *       (essa precisa também de s3:PutObject e s3:PutObjectAcl no prefixo do áudio).
 *    2. No ambiente de PRODUÇÃO (variável de ambiente do pod ou wp-config.php):
 *           BAHIA_AUDIO_POLLY=true
 *           BAHIA_AUDIO_PREFIX=audio/prod/
 *    Homolog NÃO deve ligar (ou, se ligar, com prefixo próprio, ex. audio/hml/): o bucket
 *    de mídia é compartilhado com produção (ver HANDOVER, bucket-compartilhado).
 *
 *  TROCAR A VOZ
 *    Escolha da redação em 23/10/2026, depois de ouvir amostras reais de Camila standard,
 *    neural e generativa: CAMILA STANDARD. Voz e motor vêm de constante ou variável de
 *    ambiente — nunca do banco:
 *        BAHIA_AUDIO_POLLY_VOICE   padrão "Camila"
 *        BAHIA_AUDIO_POLLY_ENGINE  padrão "standard"
 *    Vozes pt-BR do Polly (describe-voices, 23/10/2026) e motores de cada uma:
 *        Camila    generative, neural, standard
 *        Vitoria   neural, standard
 *        Thiago    neural
 *        Carolina  generative
 *        Leo       generative
 *        Ricardo   standard
 *    Combinação inválida cai em Camila/standard e registra aviso no log, sem quebrar nada.
 *    Exemplos:
 *        wp-config.php:   define('BAHIA_AUDIO_POLLY_ENGINE', 'neural');
 *        pod (env):       BAHIA_AUDIO_POLLY_VOICE=Camila  BAHIA_AUDIO_POLLY_ENGINE=neural
 *    Teto de custo por motor (tabela da AWS, por milhão de caracteres), se todas as
 *    matérias do mês fossem ouvidas (~4,7 milhões de caracteres):
 *        standard   US$ 4   → ≈ US$ 19/mês   (padrão)
 *        neural     US$ 16  → ≈ US$ 75/mês
 *        generative US$ 30  → ≈ US$ 140/mês
 *    Cada MP3 registra voz e motor (metas _bahia_audio_voz e _bahia_audio_motor), além do
 *    hash do texto: trocar voz ou motor faz cada matéria ser regerada na próxima escuta.
 *    MP3 do standard sai a 22050 Hz (máximo do motor); neural e generativa, a 24000 Hz.
 *
 *  OPCIONAIS
 *    - BAHIA_AUDIO_GERAR_AO_PUBLICAR=true: volta a gerar no WP-cron ao publicar/atualizar
 *      (custo de TODAS as matérias, não só das ouvidas). Desligado por padrão.
 *    - Pré-gerar em lote (ex. depois de trocar a voz, para ninguém esperar):
 *          wp bahia-audio regerar --desde=2026-10-01 --dry-run   # conta e estima o custo
 *          wp bahia-audio regerar --desde=2026-10-01 --limite=500
 *      Custo = caracteres regerados × preço do motor; um mês de acervo (~4,7 M
 *      caracteres) ≈ US$ 19 em standard. Use sempre --desde e --dry-run.
 * ========================================================================= */

/** Lê uma configuração de constante ou variável de ambiente — nunca do banco. */
function bahia_audio_cfg($nome, $padrao = '') {
    if (defined($nome)) {
        return constant($nome);
    }
    $v = getenv($nome);
    return ($v === false || $v === '') ? $padrao : $v;
}

function bahia_audio_cfg_bool($nome) {
    $v = bahia_audio_cfg($nome, false);
    return $v === true || $v === 1 || $v === '1' || strtolower((string) $v) === 'true';
}

function bahia_audio_polly_ativo() {
    return bahia_audio_cfg_bool('BAHIA_AUDIO_POLLY') && bahia_audio_prefixo() !== '';
}

/** Prefixo do objeto no bucket, por AMBIENTE. Vazio = geração desligada. */
function bahia_audio_prefixo() {
    $p = trim((string) bahia_audio_cfg('BAHIA_AUDIO_PREFIX', ''), '/');
    return $p === '' ? '' : $p . '/';
}

/** Vozes pt-BR do Polly e os motores de cada uma (describe-voices, 23/10/2026). */
function bahia_audio_vozes_polly() {
    return array(
        'Camila'   => array('generative', 'neural', 'standard'),
        'Vitoria'  => array('neural', 'standard'),
        'Thiago'   => array('neural'),
        'Carolina' => array('generative'),
        'Leo'      => array('generative'),
        'Ricardo'  => array('standard'),
    );
}

/**
 * Voz e motor em uso: [voz, motor]. Lidos de BAHIA_AUDIO_POLLY_VOICE/_ENGINE (constante
 * ou ambiente) e validados contra bahia_audio_vozes_polly(). Combinação inválida volta a
 * Camila/standard com aviso no log — nunca derruba a página nem a geração.
 */
function bahia_audio_voz_motor() {
    $vozes = bahia_audio_vozes_polly();
    $pedida = trim((string) bahia_audio_cfg('BAHIA_AUDIO_POLLY_VOICE', 'Camila'));
    $motor = strtolower(trim((string) bahia_audio_cfg('BAHIA_AUDIO_POLLY_ENGINE', 'standard')));
    // Aceita "camila", "Vitória" etc.: normaliza para o nome que o Polly espera.
    $voz = null;
    foreach (array_keys($vozes) as $nome) {
        if (strcasecmp(remove_accents($pedida), $nome) === 0) {
            $voz = $nome;
        }
    }
    if ($voz === null || !in_array($motor, $vozes[$voz], true)) {
        static $avisou = false;
        if (!$avisou) {
            error_log('[bahia-audio] voz/motor inválidos (' . $pedida . '/' . $motor . '); usando Camila/standard.');
            $avisou = true;
        }
        return array('Camila', 'standard');
    }
    return array($voz, $motor);
}

/** Hash do TEXTO lido. */
function bahia_audio_hash(array $blocos) {
    return md5(implode("\n", $blocos));
}

/** O MP3 da matéria está em dia com o texto, a voz e o motor atuais? */
function bahia_audio_em_dia($post_id, $hash, $voz, $motor) {
    return get_post_meta($post_id, '_bahia_audio_url', true)
        && get_post_meta($post_id, '_bahia_audio_hash', true) === $hash
        && get_post_meta($post_id, '_bahia_audio_voz', true) === $voz
        && get_post_meta($post_id, '_bahia_audio_motor', true) === $motor;
}

/** Matéria que pode ter áudio gerado: publicada, editorial, sem senha. */
function bahia_audio_post_geravel($post_id) {
    $post = get_post((int) $post_id);
    if (!$post || $post->post_status !== 'publish' || !empty($post->post_password)) {
        return null;
    }
    return in_array($post->post_type, bahia_audio_tipos(), true) ? $post : null;
}

/* ---------------- endpoint público do player ---------------- */

add_action('rest_api_init', function () {
    register_rest_route('bahia/v1', '/audio/(?P<id>\d+)', array(
        'methods'             => 'POST',
        'callback'            => 'bahia_audio_rest',
        'permission_callback' => '__return_true', // público; a proteção está no callback
        'args'                => array('id' => array('validate_callback' => function ($v) { return ctype_digit((string) $v); })),
    ));
});

/**
 * POST /wp-json/bahia/v1/audio/<id>  corpo: {"nonce": "..."}
 *   200 {url}  MP3 pronto (já existia ou acabou de ser gerado)
 *   202        outra requisição está gerando este áudio: tente de novo em ~2 s
 *   403/404    nonce inválido / matéria que não pode ter áudio
 *   429        teto diário de gerações atingido
 *   503        geração desligada neste ambiente ou falha no Polly/S3
 */
function bahia_audio_rest(WP_REST_Request $req) {
    $id = (int) $req['id'];
    $nonce = (string) $req->get_param('nonce');
    if (!wp_verify_nonce($nonce, 'bahia_audio_' . $id)) {
        return new WP_REST_Response(array('erro' => 'nonce'), 403);
    }
    $post = bahia_audio_post_geravel($id);
    if (!$post) {
        return new WP_REST_Response(array('erro' => 'materia'), 404);
    }
    if (!bahia_audio_polly_ativo()) {
        return new WP_REST_Response(array('erro' => 'desligado'), 503);
    }
    $r = bahia_audio_gerar_sob_demanda($post);
    if (is_string($r)) {
        return new WP_REST_Response(array('url' => $r), 200, array('Cache-Control' => 'no-store'));
    }
    $cod = $r->get_error_code();
    $status = $cod === 'gerando' ? 202 : ($cod === 'cota' ? 429 : 503);
    return new WP_REST_Response(array('erro' => $cod), $status, array('Cache-Control' => 'no-store'));
}

/**
 * Devolve a URL do MP3 em dia, gerando-o se preciso, com trava por matéria e teto diário.
 *
 * @return string|WP_Error  URL, ou WP_Error 'gerando' | 'cota' | falha
 */
function bahia_audio_gerar_sob_demanda(WP_Post $post) {
    list($voz, $motor) = bahia_audio_voz_motor();
    $blocos = bahia_audio_blocos($post);
    $hash = bahia_audio_hash($blocos);
    if (bahia_audio_em_dia($post->ID, $hash, $voz, $motor)) {
        return (string) get_post_meta($post->ID, '_bahia_audio_url', true);
    }
    if (!bahia_audio_trava($post->ID)) {
        return new WP_Error('gerando', 'Já há uma geração em andamento para esta matéria.');
    }
    // A partir daqui, o cliente pode desistir (fallback de 25 s): a geração continua e
    // fica pronta para o próximo ouvinte.
    ignore_user_abort(true);
    try {
        // Conferido de novo DENTRO da trava: quem esperou o 202 não gera em dobro.
        wp_cache_delete($post->ID, 'post_meta');
        if (bahia_audio_em_dia($post->ID, $hash, $voz, $motor)) {
            return (string) get_post_meta($post->ID, '_bahia_audio_url', true);
        }
        if (!bahia_audio_cota_consome()) {
            return new WP_Error('cota', 'Teto diário de gerações atingido.');
        }
        $r = bahia_audio_gerar($post->ID);
        if (is_wp_error($r)) {
            return $r;
        }
        return (string) get_post_meta($post->ID, '_bahia_audio_url', true);
    } finally {
        bahia_audio_destrava($post->ID);
    }
}

/**
 * Trava atômica por matéria: add_option() falha se a linha já existe (chave única em
 * wp_options), então só uma requisição ganha. Trava com mais de 120 s é tida como órfã
 * (processo morto) e é retomada.
 */
function bahia_audio_trava($post_id) {
    $nome = '_bahia_audio_trava_' . (int) $post_id;
    if (add_option($nome, time(), '', 'no')) {
        return true;
    }
    global $wpdb;
    $desde = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $nome));
    if ($desde && time() - $desde > 120) {
        // Retoma só se ninguém retomou antes (UPDATE condicionado ao valor lido).
        return (bool) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
            time(), $nome, $desde
        ));
    }
    return false;
}

function bahia_audio_destrava($post_id) {
    delete_option('_bahia_audio_trava_' . (int) $post_id);
}

/**
 * Consome uma geração da cota do dia (contador atômico em wp_options). Ao estourar,
 * registra uma vez no error_log e devolve false.
 */
function bahia_audio_cota_consome() {
    global $wpdb;
    $teto = max(0, (int) bahia_audio_cfg('BAHIA_AUDIO_COTA_DIARIA', 150));
    $nome = '_bahia_audio_cota_' . wp_date('Ymd');
    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, '1', 'no')
         ON DUPLICATE KEY UPDATE option_value = option_value + 1",
        $nome
    ));
    $usado = (int) $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $nome));
    if ($usado > $teto) {
        if ($usado === $teto + 1) {
            error_log('[bahia-audio] teto diário de ' . $teto . ' gerações atingido (BAHIA_AUDIO_COTA_DIARIA).');
        }
        return false;
    }
    return true;
}

/* ---------------- geração ao publicar (opcional, desligada) ---------------- */

add_action('save_post', function ($post_id, $post) {
    if (!bahia_audio_cfg_bool('BAHIA_AUDIO_GERAR_AO_PUBLICAR') || !bahia_audio_polly_ativo()) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || !bahia_audio_post_geravel($post_id)) {
        return;
    }
    // Um minuto de folga: edições seguidas viram uma geração só.
    wp_clear_scheduled_hook('bahia_audio_gerar', array((int) $post_id));
    wp_schedule_single_event(time() + 60, 'bahia_audio_gerar', array((int) $post_id));
}, 20, 2);

add_action('bahia_audio_gerar', 'bahia_audio_gerar');

/**
 * Gera o MP3 da matéria no Polly e grava no bucket. Idempotente: se texto, voz e motor
 * não mudaram desde a última geração, não chama o Polly de novo — não paga duas vezes.
 *
 * @return true|WP_Error
 */
function bahia_audio_gerar($post_id) {
    if (!bahia_audio_polly_ativo()) {
        return new WP_Error('desligado', 'Geração de áudio desligada neste ambiente.');
    }
    $post = bahia_audio_post_geravel($post_id);
    if (!$post) {
        return new WP_Error('post', 'Matéria inexistente, não publicada ou com senha.');
    }
    list($voz, $motor) = bahia_audio_voz_motor();
    $blocos = bahia_audio_blocos($post);
    $hash = bahia_audio_hash($blocos);
    if (bahia_audio_em_dia($post_id, $hash, $voz, $motor)) {
        return true;
    }
    $texto = implode("\n\n", $blocos);
    $teto = (int) bahia_audio_cfg('BAHIA_AUDIO_MAX_CHARS', 20000);
    if (mb_strlen($texto) > $teto) {
        return bahia_audio_falha($post_id, new WP_Error('grande', 'Texto acima do teto de ' . $teto . ' caracteres.'));
    }

    $cred = bahia_audio_credenciais();
    if (is_wp_error($cred)) {
        return bahia_audio_falha($post_id, $cred);
    }
    $cred['voz'] = $voz;
    $cred['motor'] = $motor;

    $mp3 = '';
    foreach (bahia_audio_partes($texto, 2800) as $parte) {
        $r = bahia_audio_polly($parte, $cred);
        if (is_wp_error($r)) {
            return bahia_audio_falha($post_id, $r);
        }
        $mp3 .= $r; // quadros MP3 concatenam sem recodificar
    }

    // Nome leva texto+voz+motor: arquivo novo a cada troca, sem colisão com o cache da CDN.
    $marca = substr(md5($hash . '|' . $voz . '|' . $motor), 0, 10);
    $chave = bahia_audio_prefixo() . get_post_time('Y/m', true, $post) . '/' . $post_id . '-' . $marca . '.mp3';
    $url = bahia_audio_s3_put($chave, $mp3, $cred);
    if (is_wp_error($url)) {
        return bahia_audio_falha($post_id, $url);
    }
    update_post_meta($post_id, '_bahia_audio_url', esc_url_raw($url));
    update_post_meta($post_id, '_bahia_audio_hash', $hash);
    update_post_meta($post_id, '_bahia_audio_voz', $voz);
    update_post_meta($post_id, '_bahia_audio_motor', $motor);
    update_post_meta($post_id, '_bahia_audio_caracteres', mb_strlen($texto));
    update_post_meta($post_id, '_bahia_audio_gerado', gmdate('c'));
    delete_post_meta($post_id, '_bahia_audio_erro');
    // O arquivo anterior NÃO é apagado: o bucket é compartilhado entre ambientes e
    // apagar ali já custou arquivo de produção (portão de contagem para apagar).
    return true;
}

/**
 * Pré-geração em lote (opcional): agenda no WP-cron, espaçadas, as matérias publicadas
 * desde $desde cujo MP3 não está em dia. Em $simular, só conta e estima o custo.
 *
 * @return array{matérias:int, caracteres:int, custo_usd:float, voz:string, motor:string}
 */
function bahia_audio_regerar_lote($desde, $limite = 500, $simular = true) {
    global $wpdb;
    list($voz, $motor) = bahia_audio_voz_motor();
    $preco = array('standard' => 4, 'neural' => 16, 'generative' => 30);
    $tipos = bahia_audio_tipos();
    $in = implode(',', array_fill(0, count($tipos), '%s'));
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ($in) AND post_date >= %s ORDER BY post_date DESC LIMIT %d",
        array_merge($tipos, array($desde, (int) $limite))
    ));
    $n = 0; $chars = 0; $quando = time() + 60;
    foreach ($ids as $id) {
        $blocos = bahia_audio_blocos($id);
        if (count($blocos) < 2 || bahia_audio_em_dia($id, bahia_audio_hash($blocos), $voz, $motor)) {
            continue;
        }
        $n++;
        $chars += mb_strlen(implode("\n\n", $blocos));
        if (!$simular && bahia_audio_polly_ativo()) {
            wp_schedule_single_event($quando, 'bahia_audio_gerar', array((int) $id));
            $quando += 20; // espaça as chamadas: sem rajada no Polly nem no PHP-FPM
        }
    }
    return array(
        'matérias'   => $n,
        'caracteres' => $chars,
        'custo_usd'  => round($chars / 1000000 * $preco[$motor], 2),
        'voz'        => $voz,
        'motor'      => $motor,
    );
}

if (defined('WP_CLI') && WP_CLI) {
    /**
     * wp bahia-audio regerar --desde=AAAA-MM-DD [--limite=500] [--dry-run]
     */
    WP_CLI::add_command('bahia-audio regerar', function ($args, $assoc) {
        $desde = isset($assoc['desde']) ? $assoc['desde'] : '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            WP_CLI::error('Informe --desde=AAAA-MM-DD (nunca o acervo inteiro sem querer).');
        }
        $simular = isset($assoc['dry-run']);
        if (!$simular && !bahia_audio_polly_ativo()) {
            WP_CLI::error('Geração desligada neste ambiente (BAHIA_AUDIO_POLLY / BAHIA_AUDIO_PREFIX).');
        }
        $r = bahia_audio_regerar_lote($desde . ' 00:00:00', isset($assoc['limite']) ? (int) $assoc['limite'] : 500, $simular);
        WP_CLI::success(sprintf('%s%d matérias, %d caracteres, ~US$ %.2f (%s/%s).',
            $simular ? '[simulação] ' : 'Agendadas: ', $r['matérias'], $r['caracteres'], $r['custo_usd'], $r['voz'], $r['motor']));
    });
}

function bahia_audio_falha($post_id, WP_Error $e) {
    update_post_meta($post_id, '_bahia_audio_erro', gmdate('c') . ' ' . $e->get_error_code() . ': ' . $e->get_error_message());
    error_log('[bahia-audio] post ' . $post_id . ': ' . $e->get_error_message());
    return $e;
}

/** Fatia o texto em pedaços de até $max caracteres, quebrando em fim de frase. */
function bahia_audio_partes($texto, $max) {
    $partes = array();
    $atual = '';
    foreach (preg_split('/(?<=[.!?…])\s+/u', $texto) as $frase) {
        while (mb_strlen($frase) > $max) { // frase gigante: corta no espaço
            $corte = mb_strrpos(mb_substr($frase, 0, $max), ' ');
            $corte = $corte ? $corte : $max;
            $partes[] = trim(($atual !== '' ? $atual . ' ' : '') . mb_substr($frase, 0, $corte));
            $atual = '';
            $frase = trim(mb_substr($frase, $corte));
        }
        if ($atual !== '' && mb_strlen($atual) + 1 + mb_strlen($frase) > $max) {
            $partes[] = $atual;
            $atual = $frase;
        } else {
            $atual = $atual === '' ? $frase : $atual . ' ' . $frase;
        }
    }
    if (trim($atual) !== '') {
        $partes[] = trim($atual);
    }
    return $partes;
}

/** Credenciais e destino: BAHIA_POLLY_* se houver, senão as do WP Offload Media. */
function bahia_audio_credenciais() {
    $s3 = get_site_option('tantan_wordpress_s3');
    $s3 = is_array($s3) ? $s3 : array();
    $key = bahia_audio_cfg('BAHIA_POLLY_KEY', isset($s3['access-key-id']) ? $s3['access-key-id'] : '');
    $sec = bahia_audio_cfg('BAHIA_POLLY_SECRET', isset($s3['secret-access-key']) ? $s3['secret-access-key'] : '');
    if ($key === '' || $sec === '' || empty($s3['bucket'])) {
        return new WP_Error('credenciais', 'Credenciais da AWS ou bucket ausentes.');
    }
    $entrega = !empty($s3['delivery-domain']) ? $s3['delivery-domain'] : (!empty($s3['cloudfront']) ? $s3['cloudfront'] : '');
    return array(
        'key'          => $key,
        'secret'       => $sec,
        'polly_region' => bahia_audio_cfg('BAHIA_POLLY_REGION', 'us-east-1'),
        'bucket'       => $s3['bucket'],
        's3_region'    => !empty($s3['region']) ? $s3['region'] : 'us-east-1',
        'entrega'      => $entrega,
    );
}

/** Uma chamada ao SynthesizeSpeech. Devolve os bytes do MP3. */
function bahia_audio_polly($texto, array $cred) {
    /**
     * Ponto de simulação para testes: um filtro que devolva bytes (string) ou WP_Error
     * substitui a chamada à AWS. Sem filtro registrado, nada muda.
     */
    $simulado = apply_filters('bahia_audio_polly_simulado', null, $texto, $cred);
    if ($simulado !== null) {
        return $simulado;
    }
    $host = 'polly.' . $cred['polly_region'] . '.amazonaws.com';
    $motor = isset($cred['motor']) ? $cred['motor'] : 'standard';
    $corpo = wp_json_encode(array(
        'Engine'       => $motor,
        'LanguageCode' => 'pt-BR',
        'OutputFormat' => 'mp3',
        // MP3: o motor standard aceita até 22050 Hz; neural e generativo, 24000 Hz.
        'SampleRate'   => $motor === 'standard' ? '22050' : '24000',
        'Text'         => $texto,
        'TextType'     => 'text',
        'VoiceId'      => isset($cred['voz']) ? $cred['voz'] : 'Camila',
    ));
    $cab = bahia_audio_sigv4('POST', $host, '/v1/speech', '', array('content-type' => 'application/json'), $corpo, $cred['polly_region'], 'polly', $cred['key'], $cred['secret']);
    $r = wp_remote_post('https://' . $host . '/v1/speech', array('headers' => $cab, 'body' => $corpo, 'timeout' => 60));
    if (is_wp_error($r)) {
        return $r;
    }
    $cod = (int) wp_remote_retrieve_response_code($r);
    if ($cod !== 200) {
        return new WP_Error('polly_' . $cod, 'Polly respondeu ' . $cod . ': ' . substr(wp_remote_retrieve_body($r), 0, 300));
    }
    return wp_remote_retrieve_body($r);
}

/**
 * PUT do MP3 no bucket. Endereço em estilo de CAMINHO (s3.<região>.amazonaws.com/<bucket>/):
 * o bucket tem pontos no nome (static.bahia.ba) e o estilo virtual-host quebraria o TLS.
 * Devolve a URL pública pelo domínio de entrega do Offload Media.
 */
function bahia_audio_s3_put($chave, $bytes, array $cred) {
    /** Ponto de simulação para testes: um filtro que devolva URL (string) ou WP_Error. */
    $simulado = apply_filters('bahia_audio_s3_simulado', null, $chave, $bytes, $cred);
    if ($simulado !== null) {
        return $simulado;
    }
    $host = 's3.' . $cred['s3_region'] . '.amazonaws.com';
    $caminho = '/' . $cred['bucket'] . '/' . implode('/', array_map('rawurlencode', explode('/', $chave)));
    // Mesmo conjunto de cabeçalhos assinados que o S3SignatureV4 do SDK assinaria
    // (conferido assinatura por assinatura no pod). O nome do arquivo leva o hash do
    // texto, então o cache longo da CDN não serve áudio velho.
    $extra = array(
        'content-type' => 'audio/mpeg',
        'x-amz-acl'    => 'public-read', // o bucket usa ACL (use-bucket-acls no Offload Media)
    );
    $cab = bahia_audio_sigv4('PUT', $host, $caminho, '', $extra, $bytes, $cred['s3_region'], 's3', $cred['key'], $cred['secret']);
    $r = wp_remote_request('https://' . $host . $caminho, array('method' => 'PUT', 'headers' => $cab, 'body' => $bytes, 'timeout' => 60));
    if (is_wp_error($r)) {
        return $r;
    }
    $cod = (int) wp_remote_retrieve_response_code($r);
    if ($cod !== 200) {
        return new WP_Error('s3_' . $cod, 'S3 respondeu ' . $cod . ': ' . substr(wp_remote_retrieve_body($r), 0, 300));
    }
    $base = $cred['entrega'] !== '' ? 'https://' . $cred['entrega'] : 'https://' . $host . '/' . $cred['bucket'];
    return $base . '/' . $chave;
}

/**
 * Assinatura AWS Signature Version 4 (cabeçalho Authorization).
 *
 * Implementada aqui para não trazer SDK novo: o SDK embutido no Offload Media não tem
 * cliente do Polly. Conferida contra o vetor "get-vanilla" da suíte oficial da AWS e
 * contra a SignatureV4 do próprio SDK do Offload Media (ver PR da KAN-36).
 *
 * @return array cabeçalhos a enviar (inclui host, x-amz-date, x-amz-content-sha256 e Authorization)
 */
function bahia_audio_sigv4($metodo, $host, $caminho, $query, array $cabecalhos, $corpo, $regiao, $servico, $key, $secret, $agora = null) {
    $agora = $agora ? $agora : time();
    $amzdate = gmdate('Ymd\THis\Z', $agora);
    $dia = gmdate('Ymd', $agora);
    $hash_corpo = hash('sha256', (string) $corpo);

    $cab = array('host' => $host, 'x-amz-date' => $amzdate);
    if ($servico === 's3') {
        $cab['x-amz-content-sha256'] = $hash_corpo;
    }
    foreach ($cabecalhos as $k => $v) {
        $cab[strtolower($k)] = trim(preg_replace('/\s+/', ' ', (string) $v));
    }
    ksort($cab);
    $canon_cab = '';
    foreach ($cab as $k => $v) {
        $canon_cab .= $k . ':' . $v . "\n";
    }
    $assinados = implode(';', array_keys($cab));

    $canonico = implode("\n", array($metodo, $caminho === '' ? '/' : $caminho, $query, $canon_cab, $assinados, $hash_corpo));
    $escopo = $dia . '/' . $regiao . '/' . $servico . '/aws4_request';
    $a_assinar = "AWS4-HMAC-SHA256\n" . $amzdate . "\n" . $escopo . "\n" . hash('sha256', $canonico);

    $k = hash_hmac('sha256', $dia, 'AWS4' . $secret, true);
    $k = hash_hmac('sha256', $regiao, $k, true);
    $k = hash_hmac('sha256', $servico, $k, true);
    $k = hash_hmac('sha256', 'aws4_request', $k, true);
    $assinatura = hash_hmac('sha256', $a_assinar, $k);

    $cab['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $key . '/' . $escopo
        . ', SignedHeaders=' . $assinados . ', Signature=' . $assinatura;
    unset($cab['host']); // o transporte HTTP põe o Host sozinho
    return $cab;
}
