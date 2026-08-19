<?php
/**
 * Títulos das páginas de sistema do Yoast (404 e busca) — a cópia na indexable.
 *
 * Aplicado direto em produção em 19/08/2026, durante a virada, e versionado depois. Este
 * arquivo é o registro do que foi feito e a forma de repetir.
 *
 * O QUE ACONTECEU. Minutos depois da virada, com o Newspaper servindo, os títulos saíram em
 * inglês:
 *
 *     /nao-existe/   ->  "Page not found - bahia.ba"
 *     /?s=bahia      ->  "You searched for bahia - bahia.ba"
 *
 * E em homologação, mesmo código e mesma imagem, saíam em português. A investigação descartou,
 * uma a uma: os valores em `wpseo_titles` (idênticos e em português nos dois), os arquivos de
 * tradução (os mesmos 23 `.mo`, e nenhum dos dois tem `wordpress-seo-pt_BR.mo`), o mapa do
 * `bahia-traducoes.php` (contém as duas frases) e o próprio Yoast em CLI
 * (`WPSEO_Options::get('title-404-wpseo')` devolvia o texto em português nos DOIS ambientes).
 *
 * A diferença estava em `wp_yoast_indexable`, nas linhas `object_type = 'system-page'`:
 *
 *     produção   404            'Page not found %%sep%% %%sitename%%'
 *     homolog    404            'Página não encontrada %%sep%% %%sitename%%'
 *
 * É a MESMA armadilha da seção 1.7 (título da home) e da 1.8 (títulos das editorias): **o
 * Yoast copia o template do option para a linha da indexable quando a constrói, e serve a
 * partir da cópia.** Foi a terceira vez no mesmo dia, em três lugares diferentes. A regra
 * geral, então: em Yoast, mexer no option não muda a tela — sempre conferir a indexable.
 *
 * ESCOPO. Só as duas linhas `system-page`. As de `post-type-archive` são do
 * `titulos-editorias-apply.php`; a da home é a seção 1.7 do roteiro.
 *
 * USO (dentro do pod):
 *     php titulos-sistema-apply.php            # seco
 *     php titulos-sistema-apply.php --aplicar
 *
 * Idempotente. Reconhece o ambiente pelo `siteurl` e não inventa texto: copia o valor que já
 * está no option, que é a fonte da verdade.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

/** sub_type da indexable => chave correspondente em wpseo_titles */
$PARES = array(
    '404'           => 'title-404-wpseo',
    'search-result' => 'title-search-wpseo',
);

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

global $wpdb;
$tab = $wpdb->prefix . 'yoast_indexable';
if (!$wpdb->get_var("SHOW TABLES LIKE '$tab'")) {
    echo "tabela $tab ausente — nada a fazer\n";
    exit(0);
}

$titles = (array) get_option('wpseo_titles');
$mudou  = 0;

foreach ($PARES as $sub => $opt) {
    $novo = isset($titles[$opt]) ? $titles[$opt] : null;
    if ($novo === null || $novo === '') {
        printf("  %-14s option %s ausente — pulado\n", $sub, $opt);
        continue;
    }
    $atual = $wpdb->get_var($wpdb->prepare(
        "SELECT title FROM $tab WHERE object_type = 'system-page' AND object_sub_type = %s", $sub));

    if ($atual === null) {
        printf("  %-14s sem linha de indexable — o Yoast a cria no primeiro acesso\n", $sub);
        continue;
    }
    if ($atual === $novo) {
        printf("  %-14s ja igual ao option\n", $sub);
        continue;
    }

    printf("  %-14s %s\n%-16s -> %s\n", $sub, var_export($atual, true), '', var_export($novo, true));
    if ($aplicar) {
        $wpdb->update($tab, array('title' => $novo),
            array('object_type' => 'system-page', 'object_sub_type' => $sub), array('%s'), array('%s', '%s'));
    }
    $mudou++;
}

echo "\n--- conferencia ---\n";
foreach ($wpdb->get_results("SELECT object_sub_type, title FROM $tab WHERE object_type = 'system-page'") as $x) {
    printf("  %-14s %s\n", $x->object_sub_type, var_export($x->title, true));
}
$fora = 0;
foreach ($PARES as $sub => $opt) {
    $t = $wpdb->get_var($wpdb->prepare(
        "SELECT title FROM $tab WHERE object_type = 'system-page' AND object_sub_type = %s", $sub));
    if ($t !== null && isset($titles[$opt]) && $t !== $titles[$opt]) { $fora++; }
}
printf("  linhas fora do option: %d (esperado 0)\n", $fora);
echo "  alteracoes: $mudou\n";
echo ($fora === 0 ? "  OK\n" : "  FALHOU\n");
