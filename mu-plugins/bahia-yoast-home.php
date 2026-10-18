<?php
/**
 * Plugin Name: Bahia.ba - Endereço da home no Yoast
 * Description: Mantém o indexável do Yoast da página inicial com o endereço da home.
 *
 *              O DEFEITO, medido em produção em 18/10/2026: a home (página estática
 *              9000142, slug home-2) declarava ao Google que o endereço oficial dela era
 *              https://bahia.ba/home-2/ — no canonical, no og:url, no @id do WebPage e no
 *              breadcrumb do JSON-LD. E /home-2/ responde 301 para /. Canonical e redirect
 *              apontando um para o outro. O "Início" do breadcrumb de TODAS as matérias
 *              também levava a /home-2/.
 *
 *              A CAUSA: o Yoast não recalcula o endereço a cada página; ele lê o campo
 *              `permalink` do indexável (wp_yoast_indexable), gravado quando a página foi
 *              indexada. O de 9000142 ficou com /home-2/ — de quando ela ainda não era a
 *              página inicial — e nada o reconstruiu desde então (a indexação completa
 *              nunca terminou: indexables_indexing_completed = false). Em homolog o
 *              indexável foi refeito e lá o endereço já estava certo.
 *
 *              A CORREÇÃO: antes de o Yoast montar o <head>, compara o permalink gravado
 *              com o que o WordPress dá para a página inicial e, se diferir, grava o certo.
 *              Na prática roda UMA vez (a primeira visita à home ou a uma matéria depois do
 *              deploy) e depois só lê; se o indexável voltar a desatualizar — troca da
 *              página inicial, reindexação parcial —, ele se corrige sozinho de novo.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

function bahia_yoast_home_corrige_permalink() {
    if (is_admin() || 'page' !== get_option('show_on_front') || !function_exists('YoastSEO')) {
        return;
    }
    $front = (int) get_option('page_on_front');
    if (!$front) {
        return;
    }
    // A home e as matérias (breadcrumb "Início") são as páginas que leem esse indexável.
    if (!is_front_page() && !is_singular()) {
        return;
    }

    $certo = get_permalink($front);
    if (!$certo) {
        return;
    }

    $repo      = YoastSEO()->classes->get(\Yoast\WP\SEO\Repositories\Indexable_Repository::class);
    $indexavel = $repo->find_by_id_and_type($front, 'post', false);
    if (!$indexavel || $indexavel->permalink === $certo) {
        return;
    }

    $indexavel->permalink = $certo;
    $indexavel->save();
}
// `wp`: a query já foi resolvida (is_front_page funciona) e o Yoast ainda não montou nada.
add_action('wp', 'bahia_yoast_home_corrige_permalink', 0);
