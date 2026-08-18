<?php
/**
 * Título da página "Quem Somos": tira o slogan que não é slogan.
 *
 * Estava no ar:  Quem Somos | bahia.ba – Jornalismo confiável e contextualizado
 * Fica:          Quem Somos - bahia.ba
 *
 * O QUE SE ENCONTRA NO BANCO (levantado em homologação, 18/08/2026):
 *
 *   O título NÃO vem de template. É um título de SEO digitado à mão no campo do Yoast da
 *   própria página, guardado em _yoast_wpseo_title e copiado para wp_yoast_indexable.title.
 *   Por isso ele traz separadores que não são os do site: um '|' e um travessão '–', onde o
 *   resto do site usa o separador configurado (sc-dash, '-').
 *
 *   A frase também não é o slogan do site: blogdescription = 'A notícia no ponto certo'.
 *   Era texto solto, só nesta página — nenhuma outra linha da indexable a carrega.
 *
 * POR QUE APAGAR O CAMPO EM VEZ DE EDITAR A FRASE:
 *
 *   title-page já vale '%%title%% %%page%% %%sep%% %%sitename%%'. Sem o campo manual, a
 *   página cai nesse template e sai 'Quem Somos - bahia.ba' — a frase some E o separador
 *   volta a ser o do site, no mesmo padrão das 25 editorias (titulos-editorias-apply.php).
 *   Editar a frase deixaria um título manual e o '|' de fora do padrão.
 *
 *   Reversível: basta redigitar o campo na tela do Yoast.
 *
 * NÃO TOCA na meta description, que continua a valer e é outro campo:
 *   'Conheça o bahia.ba: jornalismo confiável, claro e contextualizado sobre a Bahia...'
 *
 * USO (dentro do pod):
 *     php titulo-quem-somos-apply.php            # seco
 *     php titulo-quem-somos-apply.php --aplicar  # escreve
 *
 * Idempotente. Localiza a página pelo caminho, nunca por ID fixo — o ID é 9000079 em
 * homologação, da faixa renumerada, e não há garantia de que seja o mesmo na produção.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

const CAMINHO   = 'quem-somos';
const META_KEY  = '_yoast_wpseo_title';

$aplicar = in_array('--aplicar', $argv, true);
$siteurl = get_option('siteurl');

$ambiente = ($siteurl === 'https://hml.bahia.ba') ? 'HOMOLOGACAO'
          : (($siteurl === 'https://bahia.ba')    ? 'PRODUCAO' : null);

if ($ambiente === null) {
    echo "ABORTA: siteurl inesperado ($siteurl)\n";
    exit(1);
}

echo "ambiente: $ambiente ($siteurl)\n";
echo "modo: " . ($aplicar ? 'APLICAR' : 'seco (nada sera escrito)') . "\n\n";

$p = get_page_by_path(CAMINHO, OBJECT, 'page');
if (!$p) {
    echo "ABORTA: pagina '" . CAMINHO . "' nao encontrada neste ambiente\n";
    exit(1);
}

echo "pagina: #{$p->ID} \"{$p->post_title}\" ({$p->post_status})\n";

$meta = get_post_meta($p->ID, META_KEY, true);
echo "titulo de SEO manual: " . ($meta === '' ? '(vazio — ja esta no padrao)' : $meta) . "\n";

global $wpdb;
$tab  = $wpdb->prefix . 'yoast_indexable';
$rows = $wpdb->get_results($wpdb->prepare(
    "SELECT id, title FROM $tab WHERE object_id = %d AND object_type = 'post'", $p->ID));

echo "linhas de indexable: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "  #{$r->id} title=" . var_export($r->title, true) . "\n";
}

$precisa_meta = ($meta !== '');
$precisa_idx  = false;
foreach ($rows as $r) {
    if ($r->title !== null && $r->title !== '') {
        $precisa_idx = true;
    }
}

if (!$precisa_meta && !$precisa_idx) {
    echo "\nnada a fazer.\n";
    exit(0);
}

echo "\na fazer:\n";
echo "  - apagar a postmeta " . META_KEY . ": " . ($precisa_meta ? 'sim' : 'nao') . "\n";
echo "  - zerar o title das linhas de indexable: " . ($precisa_idx ? 'sim' : 'nao') . "\n";

if (!$aplicar) {
    exit(0);
}

if ($precisa_meta) {
    delete_post_meta($p->ID, META_KEY);
    echo "\npostmeta apagada.\n";
}
if ($precisa_idx) {
    $n = 0;
    foreach ($rows as $r) {
        $n += (int) $wpdb->update($tab, array('title' => null), array('id' => $r->id), array('%s'), array('%d'));
    }
    echo "linhas de indexable zeradas: $n\n";
}

/* ---------- conferencia ---------- */

$meta_dep = get_post_meta($p->ID, META_KEY, true);
$idx_dep  = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $tab WHERE object_id = %d AND object_type = 'post' AND title IS NOT NULL AND title <> ''", $p->ID));

echo "\n--- conferencia ---\n";
echo "  postmeta " . META_KEY . ": " . ($meta_dep === '' ? 'vazia (esperado)' : "AINDA CHEIA: $meta_dep") . "\n";
echo "  linhas de indexable com title: $idx_dep (esperado 0)\n";
echo (($meta_dep === '') && $idx_dep === 0) ? "  OK\n" : "  FALHOU\n";
