<?php
/**
 * Título das páginas de editoria: "Política" no lugar de "Política: últimas notícias".
 *
 * O rótulo da editoria basta. O sufixo ": últimas notícias" repete o óbvio, consome os
 * primeiros caracteres do título — que são os que o Google e o compartilhamento mostram —
 * e é o mesmo em 18 editorias, o que apaga a diferença entre elas no resultado de busca.
 * Decidido em 18/08/2026.
 *
 * O QUE SE ENCONTRA NO BANCO (levantado em homologação, 18/08):
 *
 *   1. wpseo_titles['title-ptarchive-<cpt>'] — o template. Estava em DOIS estados:
 *        18 editorias antigas: '%%pt_plural%%: últimas notícias %%page%% %%sep%% %%sitename%%'
 *         7 editorias novas:   '%%pt_plural%% Archive %%page%% %%sep%% %%sitename%%'
 *      As 7 novas são as que entraram no mapa hoje (commit 104be34f) e nasceram com o
 *      padrão do Yoast — em inglês. "Covid-19 Archive - bahia.ba" estava no ar.
 *
 *   2. wp_yoast_indexable — o Yoast COPIA o template do option para a linha da indexable
 *      quando a constrói, e serve a partir da cópia. Mudar só o option não muda a tela;
 *      foi o que já se aprendeu no título da home, na rodada 8.
 *
 *   3. Linhas DUPLICADAS: 16 dos sub_types têm duas linhas post-type-archive, uma antiga
 *      e uma de hoje, com templates diferentes. Vence a de id menor. Este script escreve
 *      nas DUAS, então qual delas vence deixa de importar — é o que torna o resultado
 *      previsível sem precisar apagar linha nenhuma.
 *
 * FORA DO ESCOPO, de propósito:
 *   - 'tdc-review': CPT interno do tagDiv, não é editoria.
 *   - title-tax-*: 73 chaves do option carregam a mesma expressão, para os arquivos de
 *     taxonomia (politica_cat, politica_tag...). Esses arquivos respondem 404 hoje nos dois
 *     ambientes (18 CPTs disputando o mesmo slug de reescrita), então não há título na tela
 *     para corrigir. Quando as taxonomias voltarem, isto tem de ser revisto junto.
 *
 * USO (dentro do pod):
 *     php titulos-editorias-apply.php            # seco: mostra o que faria, não escreve
 *     php titulos-editorias-apply.php --aplicar  # escreve
 *
 * Idempotente: rodar de novo depois de aplicado não muda mais nada.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

const TEMPLATE_NOVO = '%%pt_plural%% %%page%% %%sep%% %%sitename%%';

$aplicar = in_array('--aplicar', $argv, true);
$siteurl = get_option('siteurl');

$ambiente = ($siteurl === 'https://hml.bahia.ba') ? 'HOMOLOGACAO'
          : (($siteurl === 'https://bahia.ba')    ? 'PRODUCAO' : null);

if ($ambiente === null) {
    echo "ABORTA: siteurl inesperado ($siteurl)\n";
    exit(1);
}
if (!function_exists('bahia_editorias_map')) {
    echo "ABORTA: bahia_editorias_map() ausente — o mu-plugin das editorias nao carregou\n";
    exit(1);
}

echo "ambiente: $ambiente ($siteurl)\n";
echo "modo: " . ($aplicar ? 'APLICAR' : 'seco (nada sera escrito)') . "\n";
echo "template alvo: " . TEMPLATE_NOVO . "\n\n";

$editorias = array_keys(bahia_editorias_map());
echo "editorias no mapa: " . count($editorias) . "\n\n";

/* ---------- 1. o option ---------- */

$titles = (array) get_option('wpseo_titles');
$mudar  = array();

foreach ($editorias as $slug) {
    $k = 'title-ptarchive-' . $slug;
    $atual = isset($titles[$k]) ? $titles[$k] : null;
    if ($atual !== TEMPLATE_NOVO) {
        $mudar[$k] = $atual;
    }
}

echo "--- wpseo_titles ---\n";
echo "chaves a alterar: " . count($mudar) . " de " . count($editorias) . "\n";
foreach ($mudar as $k => $antes) {
    printf("  %-34s %s\n", $k, $antes === null ? '(AUSENTE)' : $antes);
}

if ($aplicar && $mudar) {
    foreach (array_keys($mudar) as $k) {
        $titles[$k] = TEMPLATE_NOVO;
    }
    $ok = update_option('wpseo_titles', $titles);
    echo "  update_option: " . ($ok ? 'gravado' : 'NAO gravado (valor identico?)') . "\n";
}

/* ---------- 2. as indexables ---------- */

global $wpdb;
$tab = $wpdb->prefix . 'yoast_indexable';

if (!$wpdb->get_var("SHOW TABLES LIKE '$tab'")) {
    echo "\n--- wp_yoast_indexable: tabela ausente, nada a fazer ---\n";
    exit(0);
}

$in   = "'" . implode("','", array_map('esc_sql', $editorias)) . "'";
$alvo = $wpdb->get_results(
    "SELECT id, object_sub_type, title FROM $tab
      WHERE object_type = 'post-type-archive'
        AND object_sub_type IN ($in)
      ORDER BY object_sub_type, id"
);

$fora = array_filter($alvo, function ($r) { return $r->title !== TEMPLATE_NOVO; });

echo "\n--- wp_yoast_indexable (post-type-archive) ---\n";
echo "linhas das editorias: " . count($alvo) . " | fora do padrao: " . count($fora) . "\n";
foreach ($fora as $r) {
    printf("  #%-7s %-16s %s\n", $r->id, $r->object_sub_type, $r->title);
}

if ($aplicar && $fora) {
    $n = 0;
    foreach ($fora as $r) {
        $n += (int) $wpdb->update($tab, array('title' => TEMPLATE_NOVO), array('id' => $r->id), array('%s'), array('%d'));
    }
    echo "  linhas atualizadas: $n\n";
}

/* ---------- 3. conferencia ---------- */

if ($aplicar) {
    $titles = (array) get_option('wpseo_titles');
    $falta_opt = 0;
    foreach ($editorias as $slug) {
        if (!isset($titles['title-ptarchive-' . $slug]) || $titles['title-ptarchive-' . $slug] !== TEMPLATE_NOVO) {
            $falta_opt++;
        }
    }
    $falta_idx = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM $tab
          WHERE object_type = 'post-type-archive' AND object_sub_type IN ($in) AND title <> '" . esc_sql(TEMPLATE_NOVO) . "'"
    );
    echo "\n--- conferencia ---\n";
    echo "  chaves do option fora do padrao: $falta_opt (esperado 0)\n";
    echo "  linhas de indexable fora do padrao: $falta_idx (esperado 0)\n";
    echo ($falta_opt === 0 && $falta_idx === 0) ? "  OK\n" : "  FALHOU\n";
}
