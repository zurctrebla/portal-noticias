<?php
/**
 * Plugin Name: Bahia.ba - Ouça este conteúdo (áudio da matéria)
 * Description: KAN-36. Player "ouça este conteúdo" logo abaixo da linha de autor/data
 *              das matérias, no modelo do leitor de áudio do Bahia Notícias: play/pausa,
 *              barra em forma de onda que se preenche com o progresso, tempo e
 *              velocidade (1.0x, 1.25x, 1.5x, 2x, 0.75x).
 *
 *              DUAS FONTES DE ÁUDIO, nesta ordem:
 *
 *              1) MP3 gerado pelo Amazon Polly (meta `_bahia_audio_url`). Voz neural
 *                 constante em qualquer aparelho. A geração está PRONTA MAS DESLIGADA:
 *                 só roda com BAHIA_AUDIO_POLLY e BAHIA_AUDIO_PREFIX definidos como
 *                 constante (wp-config) ou variável de ambiente do pod. NUNCA por opção
 *                 do banco: o banco é copiado entre produção e homolog, e uma chave
 *                 guardada nele ligaria a geração em homolog, gravando no bucket que é
 *                 COMPARTILHADO com produção.
 *
 *              2) Voz do navegador (Web Speech API), enquanto não houver MP3. É um modo
 *                 PROVISÓRIO: funciona desde já e sem custo, mas a voz é a que o aparelho
 *                 tiver e costuma soar robotizada (avaliação em homolog, 23/10/2026). O
 *                 script escolhe a melhor voz pt-BR disponível, mas a qualidade de verdade
 *                 vem do modo 1, Polly neural (Camila, Vitória ou Thiago), que depende da
 *                 permissão IAM polly:SynthesizeSpeech — o usuário bahia-pipeline não tinha
 *                 nem polly:DescribeVoices em 23/10/2026. Se o navegador não tiver síntese
 *                 de voz, o player não aparece.
 *
 *              O texto lido é o MESMO nos dois modos e é montado aqui no servidor
 *              (título, subtítulo e o corpo em parágrafos/intertítulos/listas/citações),
 *              sem legendas de foto, posts incorporados, figuras, scripts e anúncios.
 *
 *              O player entra pelo filtro de saída único (bahia-html-saida.php), antes do
 *              </header> do cabeçalho da matéria: o single é montado pelo loop-single.php
 *              do td-composer, que não tem gancho nesse ponto (plugins/ não se edita).
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_AUDIO_VER', '1.0.0');

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
    $post = get_queried_object();
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
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
    $mp3 = (string) get_post_meta($post->ID, '_bahia_audio_url', true);
    // O MP3 só vale se for do texto ATUAL (a matéria pode ter sido editada depois).
    if ($mp3 !== '' && get_post_meta($post->ID, '_bahia_audio_hash', true) !== bahia_audio_hash($blocos)) {
        $mp3 = '';
    }
    $dados = array(
        'blocos' => $blocos,
        'mp3'    => $mp3 !== '' ? esc_url_raw($mp3) : '',
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
        . '<div class="bahia-audio__topo"><span class="bahia-audio__rotulo">ouça este conteúdo</span></div>'
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
.bahia-audio.is-carregando .bahia-audio__play{opacity:.5;}
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
    var modo = dados.mp3 ? 'mp3' : (temSintese ? 'voz' : '');
    if (!modo) { return; } // sem MP3 e sem voz no navegador: nada a oferecer
    el.hidden = false;

    var btPlay = el.querySelector('.bahia-audio__play');
    var btVel = el.querySelector('.bahia-audio__vel');
    var onda = el.querySelector('.bahia-audio__onda');
    var feito = el.querySelector('.bahia-audio__ondas--feito');
    var tempo = el.querySelector('.bahia-audio__tempo');
    var VELS = [1, 1.25, 1.5, 2, 0.75];
    var iVel = 0;
    var tocando = false;

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

    /* ---------------- modo MP3 ---------------- */
    var audio, motor;
    if (modo === 'mp3') {
        audio = new Audio();
        audio.preload = 'none';
        audio.src = dados.mp3;
        audio.addEventListener('timeupdate', function () {
            pinta(audio.duration ? audio.currentTime / audio.duration : 0, audio.currentTime);
        });
        audio.addEventListener('ended', function () { estado(false); pinta(1, audio.duration); });
        audio.addEventListener('pause', function () { estado(false); });
        audio.addEventListener('play', function () { estado(true); });
        audio.addEventListener('error', function () {
            // MP3 indisponível: cai para a voz do navegador, se houver.
            if (temSintese && motor !== 'voz') { motor = 'voz'; modo = 'voz'; iniciaVoz(); if (tocando) { tocaVoz(); } }
        });
        motor = 'mp3';
    }

    /* ---------------- modo voz do navegador ---------------- */
    // Frases curtas: o Chrome corta falas longas (~15 s) e pause()/resume() é instável
    // nos navegadores. Pausar = cancelar e lembrar a frase; retomar = falar dela em diante.
    var frases = [], acum = [], total = 0, iFrase = 0, voz = null, falando = null;
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
        frases = [];
        (dados.blocos || []).forEach(function (b) { frases = frases.concat(fatia(b)); });
        acum = []; total = 0;
        frases.forEach(function (f) { acum.push(total); total += f.length; });
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

    if (modo === 'voz') {
        iniciaVoz();
        motor = 'voz';
        escolheVoz();
        if (window.speechSynthesis.addEventListener) {
            window.speechSynthesis.addEventListener('voiceschanged', escolheVoz);
        }
        pinta(0, 0);
        tempo.textContent = fmt(total / CPS); // duração estimada antes de começar
    }

    /* ---------------- controles ---------------- */
    btPlay.addEventListener('click', function () {
        if (motor === 'mp3') {
            if (audio.paused) { audio.playbackRate = VELS[iVel]; audio.play(); } else { audio.pause(); }
            return;
        }
        if (tocando) { paraVoz(); } else { window.speechSynthesis.cancel(); tocaVoz(); }
    });

    btVel.addEventListener('click', function () {
        iVel = (iVel + 1) % VELS.length;
        var r = VELS[iVel];
        var txt = (r % 1 === 0 ? r.toFixed(1) : String(r)) + 'x';
        btVel.textContent = txt;
        btVel.setAttribute('aria-label', 'Velocidade de leitura: ' + txt);
        if (motor === 'mp3') { audio.playbackRate = r; return; }
        if (tocando) { falando = null; window.speechSynthesis.cancel(); tocaVoz(); } // reinicia a frase na nova velocidade
    });

    function salta(fracao) {
        fracao = Math.max(0, Math.min(1, fracao));
        if (motor === 'mp3') {
            if (audio.duration) { audio.currentTime = fracao * audio.duration; }
            return;
        }
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
    window.addEventListener('pagehide', function () { if (motor === 'voz') { window.speechSynthesis.cancel(); } });
})();
JS;
}

/* =========================================================================
 *  GERAÇÃO NO AMAZON POLLY (preparada, DESLIGADA por padrão)
 *
 *  Para ligar, no ambiente de PRODUÇÃO (manifesto do pod ou wp-config):
 *      BAHIA_AUDIO_POLLY=1
 *      BAHIA_AUDIO_PREFIX=audio/prod/
 *  e dar ao usuário da AWS a permissão polly:SynthesizeSpeech (a permissão de gravar
 *  no bucket o Offload Media já tem). Credenciais: BAHIA_POLLY_KEY/BAHIA_POLLY_SECRET
 *  se definidas; senão, as do WP Offload Media.
 *
 *  Homolog NÃO deve ligar (ou, se ligar, com outro prefixo, ex. audio/hml/): o bucket é
 *  compartilhado com produção (ver HANDOVER, bucket-compartilhado).
 * ========================================================================= */

/** Lê uma configuração de constante ou variável de ambiente — nunca do banco. */
function bahia_audio_cfg($nome, $padrao = '') {
    if (defined($nome)) {
        return constant($nome);
    }
    $v = getenv($nome);
    return ($v === false || $v === '') ? $padrao : $v;
}

function bahia_audio_polly_ativo() {
    $liga = bahia_audio_cfg('BAHIA_AUDIO_POLLY', false);
    $liga = ($liga === true || $liga === 1 || $liga === '1' || $liga === 'true');
    return $liga && bahia_audio_prefixo() !== '';
}

/** Prefixo do objeto no bucket, por AMBIENTE. Vazio = geração desligada. */
function bahia_audio_prefixo() {
    $p = trim((string) bahia_audio_cfg('BAHIA_AUDIO_PREFIX', ''), '/');
    return $p === '' ? '' : $p . '/';
}

function bahia_audio_hash(array $blocos) {
    return md5(bahia_audio_cfg('BAHIA_POLLY_VOICE', 'Camila') . '|' . implode("\n", $blocos));
}

/** Agenda a geração quando uma matéria é publicada ou atualizada. */
add_action('save_post', function ($post_id, $post) {
    if (!bahia_audio_polly_ativo() || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    if ($post->post_status !== 'publish' || !in_array($post->post_type, bahia_audio_tipos(), true)) {
        return;
    }
    // Um minuto de folga: edições seguidas viram uma geração só.
    wp_clear_scheduled_hook('bahia_audio_gerar', array((int) $post_id));
    wp_schedule_single_event(time() + 60, 'bahia_audio_gerar', array((int) $post_id));
}, 20, 2);

add_action('bahia_audio_gerar', 'bahia_audio_gerar');

/**
 * Gera o MP3 da matéria no Polly e grava no bucket. Idempotente: se o texto não mudou
 * desde a última geração (hash), não chama o Polly de novo — não paga duas vezes.
 *
 * @return true|WP_Error
 */
function bahia_audio_gerar($post_id) {
    if (!bahia_audio_polly_ativo()) {
        return new WP_Error('desligado', 'Geração de áudio desligada neste ambiente.');
    }
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return new WP_Error('post', 'Matéria inexistente ou não publicada.');
    }
    $blocos = bahia_audio_blocos($post);
    $hash = bahia_audio_hash($blocos);
    if (get_post_meta($post_id, '_bahia_audio_hash', true) === $hash && get_post_meta($post_id, '_bahia_audio_url', true)) {
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

    $mp3 = '';
    foreach (bahia_audio_partes($texto, 2800) as $parte) {
        $r = bahia_audio_polly($parte, $cred);
        if (is_wp_error($r)) {
            return bahia_audio_falha($post_id, $r);
        }
        $mp3 .= $r; // quadros MP3 concatenam sem recodificar
    }

    $chave = bahia_audio_prefixo() . get_post_time('Y/m', true, $post) . '/' . $post_id . '-' . substr($hash, 0, 10) . '.mp3';
    $url = bahia_audio_s3_put($chave, $mp3, $cred);
    if (is_wp_error($url)) {
        return bahia_audio_falha($post_id, $url);
    }
    update_post_meta($post_id, '_bahia_audio_url', esc_url_raw($url));
    update_post_meta($post_id, '_bahia_audio_hash', $hash);
    update_post_meta($post_id, '_bahia_audio_gerado', gmdate('c'));
    delete_post_meta($post_id, '_bahia_audio_erro');
    // O arquivo anterior NÃO é apagado: o bucket é compartilhado entre ambientes e
    // apagar ali já custou arquivo de produção (portão de contagem para apagar).
    return true;
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
        'voz'          => bahia_audio_cfg('BAHIA_POLLY_VOICE', 'Camila'),
        'bucket'       => $s3['bucket'],
        's3_region'    => !empty($s3['region']) ? $s3['region'] : 'us-east-1',
        'entrega'      => $entrega,
    );
}

/** Uma chamada ao SynthesizeSpeech. Devolve os bytes do MP3. */
function bahia_audio_polly($texto, array $cred) {
    $host = 'polly.' . $cred['polly_region'] . '.amazonaws.com';
    $corpo = wp_json_encode(array(
        'Engine'       => 'neural',
        'LanguageCode' => 'pt-BR',
        'OutputFormat' => 'mp3',
        'SampleRate'   => '24000',
        'Text'         => $texto,
        'TextType'     => 'text',
        'VoiceId'      => $cred['voz'],
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
