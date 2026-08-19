<?php
/**
 * Vazamento de homologação em produção — URLs hml.bahia.ba deixadas pela Fase 3.
 *
 * Relatado pela redação em 19/08/2026, logo após a virada: clicar em "Quem Somos" no menu
 * levava para `https://hml.bahia.ba/quem-somos/`. A Fase 3 importou os itens de menu, as
 * páginas, os templates e os anexos de homolog, e alguns campos carregavam a URL de origem.
 *
 * LEVANTAMENTO COMPLETO (produção, 19/08):
 *
 *   _menu_item_url ........  2  posts 9000014 e 9000040 — "Quem Somos" no menu Principal e no
 *                              Rodapé. É o defeito relatado, e aparece no cabeçalho de TODA
 *                              página, então o cache inteiro carrega o link errado.
 *   post_content ..........  2  #9000140 (template do 404, botão para a home) e #549266
 *                              (matéria de esporte publicada, link no corpo)
 *   guid .................. 69  anexos 41, nav_menu_item 22, tdb_templates 4, page 2
 *   amazonS3_cache ........  1  post 9000001, cache do offload
 *   options / termmeta ....  0
 *
 * O QUE ESTE SCRIPT CORRIGE, e por quê só isto:
 *
 *   Os 5 primeiros — menu, conteúdo e cache — porque viram link na tela ou no HTML servido.
 *
 *   Os 69 `guid` ficam de FORA por decisão consciente. O guid não é usado como link pelo
 *   WordPress: permalinks vêm das rewrite rules e URLs de anexo vêm de `_wp_attached_file`
 *   mais o offload para o S3 — conferido na Quem Somos, cujas 22 fotos e o favicon carregam
 *   certo. Mexer em guid tem custo real: leitores de RSS usam o guid como identidade do item,
 *   e reescrevê-lo faz 69 itens reaparecerem como novos para quem já os tinha lido. O único
 *   lugar em que o guid errado escapa é dentro do feed — e os feeds devem estar DESLIGADOS
 *   (ver mu-plugins/bahia-feeds.php, hoje só em staging). Ligar aquele porte resolve o
 *   sintoma sem tocar em identidade nenhuma.
 *
 *   Se um dia decidir-se reescrever os guid mesmo assim, o lugar é aqui, com o motivo escrito.
 *
 * USO (dentro do pod de produção):
 *     php hml-vazamento-apply.php            # seco
 *     php hml-vazamento-apply.php --aplicar  # grava
 *
 * DEPOIS DE APLICAR: purgar o fastcgi_cache de TODOS os pods. O menu está no cabeçalho de
 * todas as páginas, então todo o cache carrega o link antigo.
 *
 * Idempotente.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

const HOST_ERRADO = 'hml.bahia.ba';
const HOST_CERTO  = 'bahia.ba';

$aplicar = in_array('--aplicar', $argv, true);

if (get_option('siteurl') !== 'https://' . HOST_CERTO) {
    fwrite(STDERR, "ABORTA: siteurl nao e producao\n");
    exit(1);
}

global $wpdb;
$H = HOST_ERRADO;

echo "modo: " . ($aplicar ? 'APLICAR' : 'seco (nada sera escrito)') . "\n\n";

/** Troca o host preservando o resto da URL. */
function troca($texto) {
    return str_replace(
        array('https://' . HOST_ERRADO, 'http://' . HOST_ERRADO, '//' . HOST_ERRADO),
        array('https://' . HOST_CERTO,  'http://' . HOST_CERTO,  '//' . HOST_CERTO),
        $texto
    );
}

$mudancas = 0;

/* ---------- 1. itens de menu ---------- */

echo "--- _menu_item_url ---\n";
$linhas = $wpdb->get_results("SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
                               WHERE meta_key = '_menu_item_url' AND meta_value LIKE '%$H%'");
foreach ($linhas as $l) {
    // Canônico: o permalink real da página, com a barra final, para não pagar um redirect.
    $novo  = troca($l->meta_value);
    $pagina = get_page_by_path('quem-somos', OBJECT, 'page');
    if ($pagina && strpos($novo, '/quem-somos') !== false) {
        $novo = get_permalink($pagina->ID);
    }
    printf("  post %-9s %s\n            -> %s\n", $l->post_id, $l->meta_value, $novo);
    if ($aplicar) {
        $wpdb->update($wpdb->postmeta, array('meta_value' => $novo), array('meta_id' => $l->meta_id), array('%s'), array('%d'));
    }
    $mudancas++;
}
if (!$linhas) { echo "  nada a fazer\n"; }

/* ---------- 2. corpo de posts ---------- */

echo "\n--- post_content ---\n";
$posts = $wpdb->get_results("SELECT ID, post_type, post_title, post_content FROM {$wpdb->posts}
                              WHERE post_content LIKE '%$H%'");
foreach ($posts as $p) {
    $novo = troca($p->post_content);
    $n = substr_count($p->post_content, $H);
    printf("  #%-9s %-14s %-40s %d ocorrencia(s)\n", $p->ID, $p->post_type, mb_substr($p->post_title, 0, 38), $n);
    if ($aplicar) {
        $wpdb->update($wpdb->posts, array('post_content' => $novo), array('ID' => $p->ID), array('%s'), array('%d'));
        clean_post_cache($p->ID);
    }
    $mudancas += $n;
}
if (!$posts) { echo "  nada a fazer\n"; }

/* ---------- 3. cache do offload ---------- */

echo "\n--- amazonS3_cache ---\n";
$metas = $wpdb->get_results("SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta}
                              WHERE meta_key = 'amazonS3_cache' AND meta_value LIKE '%$H%'");
foreach ($metas as $m) {
    printf("  post %-9s %d bytes\n", $m->post_id, strlen($m->meta_value));
    if ($aplicar) {
        $wpdb->update($wpdb->postmeta, array('meta_value' => troca($m->meta_value)), array('meta_id' => $m->meta_id), array('%s'), array('%d'));
    }
    $mudancas++;
}
if (!$metas) { echo "  nada a fazer\n"; }

/* ---------- conferência ---------- */

echo "\n--- conferencia ---\n";
$rest_meta  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_value LIKE '%$H%'");
$rest_cont  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE '%$H%'");
$rest_guid  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE guid LIKE '%$H%'");
printf("  postmeta restantes ....... %d (esperado 0)\n", $rest_meta);
printf("  post_content restantes ... %d (esperado 0)\n", $rest_cont);
printf("  guid restantes ........... %d (mantidos de proposito — ver cabecalho)\n", $rest_guid);
echo "  alteracoes: $mudancas\n";
echo (($rest_meta === 0 && $rest_cont === 0) ? "  OK\n" : ($aplicar ? "  FALHOU\n" : "  (seco)\n"));
