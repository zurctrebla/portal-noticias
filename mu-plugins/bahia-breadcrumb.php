<?php
/**
 * Plugin Name: Bahia.ba - Breadcrumb dos archives de editoria
 * Description: Troca o "2026" da última migalha pelo nome da editoria, nos archives de CPT.
 *
 *              O DEFEITO. Em `/politica/`, `/esporte/`, `/municipios/` — em qualquer
 *              archive de editoria — o caminho sai como:
 *
 *                  Início › 2026
 *
 *              O ano não diz nada ao leitor, e o link aponta para `get_year_link(2026)`,
 *              que neste site é 404: os CPTs de editoria são registrados sem suporte a
 *              arquivo por data (ver AUDITORIA-templates.md §3.2). Ou seja, a única
 *              migalha clicável além de "Início" leva a lugar nenhum.
 *
 *              ATINGE DESKTOP E MOBILE por igual — conferido em 390px e 1280px, saída
 *              idêntica nas três editorias testadas. Não é defeito de mobile; foi só
 *              percebido na varredura de mobile.
 *
 *              A CAUSA. `themes/Newspaper/includes/tagdiv-page-generator.php:204`:
 *
 *                  private static function archive_breadcrumbs_array() {
 *                      $cur_archive_year = get_the_date('Y');
 *                      $breadcrumbs_array [] = array(
 *                          'url'          => get_year_link($cur_archive_year),
 *                          'display_name' => get_the_date('Y')      // <- sempre o ANO
 *                      );
 *                      if (is_month() or is_day()) { ... }
 *
 *              A função assume que TODO archive é archive de data. Não há ramo para
 *              `is_post_type_archive()`, então ela emite o ano do primeiro post do loop.
 *
 *              NÃO É a mesma família do title de archive corrigido na rodada 3, embora
 *              pareça: aquilo foi alteração da option `wpseo_titles` no banco, e não há
 *              nenhum mu-plugin de título de archive que se pudesse estender. Este é PHP
 *              do tema, chamado direto por `archive.php:26`, e `get_breadcrumbs()` não
 *              tem `apply_filters` nenhum — foi conferido. Daí o buffer.
 *
 *              POR QUE O BUFFER e não editar o tema: `themes/Newspaper/` é versionado,
 *              mas a regra do projeto (AUDITORIA-templates.md §6) manda tratar PHP de
 *              tema e de plugin por hook em mu-plugin. O buffer já existe e já tem cinco
 *              consumidores; este é o sexto.
 *
 * @see bahia-html-saida.php  buffer de saída único do site (filtro bahia_hs_html)
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nome de exibição da editoria do archive corrente, ou '' se não for esse caso.
 *
 * Usa o rótulo do próprio CPT, que é a mesma fonte do <h1> da página — assim migalha e
 * título não podem divergir.
 */
function bahia_bc_nome_editoria() {
    if (!is_post_type_archive()) {
        return '';
    }
    $pt = get_query_var('post_type');
    if (is_array($pt)) {
        $pt = reset($pt);
    }
    if (!$pt) {
        return '';
    }
    $obj = get_post_type_object($pt);
    if (!$obj) {
        return '';
    }
    // `label` é o plural exibido ("Política", "Municípios"), que é o que o <h1> mostra.
    $nome = isset($obj->labels->name) ? $obj->labels->name : $obj->label;
    return is_string($nome) ? trim($nome) : '';
}

/**
 * Reescreve a última migalha do archive.
 *
 * ANCORADA: age só dentro do `<div class="entry-crumbs">`, e só na ÚLTIMA migalha, que o
 * tema marca com a classe `td-bred-no-url-last`. Uma substituição, nada de varrer a
 * página — o `str_replace` amplo do `bahia-html-saida.php` já é o único do site e a
 * ressalva sobre ele está registrada no HANDOVER §3.
 *
 * Sem `is_post_type_archive()` a função sai antes de tocar em qualquer coisa, então
 * single, home, busca, autor e 404 passam intocados.
 */
function bahia_bc_corrigir($html) {
    $nome = bahia_bc_nome_editoria();
    if ($nome === '') {
        return $html;
    }

    $ini = strpos($html, '<div class="entry-crumbs">');
    if ($ini === false) {
        return $html;
    }
    $fim = strpos($html, '</div>', $ini);
    if ($fim === false) {
        return $html;
    }
    $trecho = substr($html, $ini, $fim - $ini);

    // A última migalha é `<span class="td-bred-no-url-last">2026</span>`. Casar o ano por
    // \d{4} e não por valor: o ano vem do primeiro post do loop e muda com o conteúdo.
    $novo = preg_replace(
        '#(<span class="td-bred-no-url-last">)\s*\d{4}\s*(</span>)#',
        '$1' . esc_html($nome) . '$2',
        $trecho,
        1,
        $n
    );
    if (!$n) {
        return $html;
    }

    return substr($html, 0, $ini) . $novo . substr($html, $fim);
}
add_filter('bahia_hs_html', 'bahia_bc_corrigir', 22);
