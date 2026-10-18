<?php
/**
 * Camada 3 do §2.3 — publicar materia de teste pela pilha do WordPress.
 * Subtitulo (ACF), imagem de destaque no campo ACF `imagem` (nao _thumbnail_id),
 * coautoria (Co-Authors Plus), e confirmacao de que entrou na tabela-sombra da busca.
 *
 * Uso no pod:  php materia-teste.php criar|apagar
 */
define("WP_USE_THEMES", false);
require_once "/var/www/html/wp-load.php";

global $wpdb;
$acao = $argv[1] ?? "criar";
$marca = "TESTE 8.4 — nao publicar";

if ($acao === "apagar") {
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts} WHERE post_title LIKE %s", $wpdb->esc_like($marca) . "%"));
    foreach ($ids as $id) { wp_delete_post($id, true); echo "apagado: $id\n"; }
    $rest = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}bahia_search_idx WHERE post_title LIKE %s",
        $wpdb->esc_like($marca) . "%"));
    echo "sobra na tabela-sombra: $rest\n";
    exit(0);
}

echo "ambiente: " . (function_exists("bahia_ambiente") ? bahia_ambiente() : "?") . "\n";
echo "siteurl:  " . get_option("siteurl") . "\n";
echo "mysql:    " . $wpdb->db_version() . "\n";

// Um CPT de editoria real, o que tiver mais posts publicados.
$tipo = $wpdb->get_var("SELECT post_type FROM {$wpdb->posts} WHERE post_status='publish'
                         GROUP BY post_type ORDER BY COUNT(*) DESC LIMIT 1");
echo "tipo:     $tipo\n";

$id = wp_insert_post([
    "post_title"   => $marca . " " . gmdate("Ymd-His"),
    "post_content" => "Materia criada para validar a subida do MySQL para 8.4.9. "
                    . "Contem os termos salvador e carnaval para cair na busca.",
    "post_excerpt" => "Resumo de teste com salvador e carnaval.",
    "post_status"  => "publish",
    "post_type"    => $tipo,
    "post_author"  => 1,
], true);

if (is_wp_error($id)) { echo "ERRO ao inserir: " . $id->get_error_message() . "\n"; exit(1); }
echo "post criado: $id\n";

// --- subtitulo e imagem, nos campos ACF que o tema usa de verdade ---
$anexo = $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_type='attachment'
                          AND post_mime_type LIKE 'image/%' ORDER BY ID DESC LIMIT 1");
if (function_exists("update_field")) {
    update_field("subtitulo", "Subtitulo de teste da subida 8.4", $id);
    if ($anexo) { update_field("imagem", (int) $anexo, $id); }
} else {
    update_post_meta($id, "subtitulo", "Subtitulo de teste da subida 8.4");
    if ($anexo) { update_post_meta($id, "imagem", (int) $anexo); }
}
echo "subtitulo: " . (get_post_meta($id, "subtitulo", true) ?: "(vazio)") . "\n";
echo "imagem:    " . (get_post_meta($id, "imagem", true) ?: "(vazia)") . " (anexo $anexo)\n";

// --- coautoria ---
if (function_exists("get_coauthors")) {
    global $coauthors_plus;
    $autores = $wpdb->get_col("SELECT slug FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id=t.term_id
        WHERE tt.taxonomy='author' ORDER BY tt.count DESC LIMIT 2");
    if ($autores && isset($coauthors_plus)) {
        $coauthors_plus->add_coauthors($id, $autores);
        echo "coautores atribuidos: " . implode(", ", $autores) . "\n";
    } else { echo "coautores: nenhum termo 'author' encontrado\n"; }
    $ca = get_coauthors($id);
    echo "coautores lidos: " . implode(", ", array_map(function ($a) {
        return is_object($a) ? ($a->user_nicename ?? $a->slug ?? "?") : (string) $a; }, $ca)) . "\n";
} else { echo "Co-Authors Plus nao esta ativo\n"; }

// --- portao: entrou na tabela-sombra da busca? ---
$na_busca = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}bahia_search_idx WHERE ID=%d", $id));
echo "na tabela-sombra: " . ($na_busca ? "SIM" : "NAO") . "\n";

echo "permalink: " . get_permalink($id) . "\n";
foreach (get_coauthors($id) as $a) {
    $u = get_author_posts_url($a->ID ?? 0, $a->user_nicename ?? ($a->slug ?? ""));
    if ($u) { echo "autor_url: $u\n"; }
}
