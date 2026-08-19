<?php
/**
 * Plugin Name: Bahia.ba - Contador interno de leituras (postmeta `views`)
 * Description: Incrementa o postmeta `views` na matéria aberta. Porte do que o tema
 *              bahia_refactor fazia em single_web.php:15 e single_mobile.php:15.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE ISTO EXISTE (19/08/2026).
 *
 * O bloco "+ Mais Lidas" tem três camadas: GA4, o contador interno `views`, e — se as duas
 * falharem — as matérias mais recentes. A redação reportou que a lista não bate com o Google
 * Analytics, e o levantamento achou as duas primeiras quebradas:
 *
 *   1. GA4: o Site Kit está SEM credenciais OAuth em produção (`is_authenticated()` falso, sem
 *      access_token e sem refresh_token). É anterior à virada e só se conserta reconectando o
 *      Site Kit pelo navegador — não há caminho por código.
 *
 *   2. `views`: o incremento vivia nos templates do tema antigo. Com o Newspaper ativo desde
 *      07:32 de 19/08, ele parou. O tagDiv não tem contador próprio (conferido: zero linhas em
 *      `td_post_views`, `td_post_theme_settings` e `_count-views_all`).
 *
 * Sem este arquivo, a camada 2 congela no retrato das 07:32 e, à medida que as matérias das
 * últimas 24 h envelhecem, o bloco escorrega para a camada 3 — "mais recentes", que não é
 * "mais lidas" de forma alguma.
 *
 * O QUE ISTO NÃO CONSERTA, e é importante não confundir: o contador interno **nunca** bateu
 * com o GA4, nem antes da virada. O motivo é o `fastcgi_cache`: acesso servido pelo cache não
 * chega ao PHP, então só as falhas de cache são contadas. O número é um proxy enviesado, útil
 * para ordenar na ausência de coisa melhor. Quem manda é o GA4 — e o GA4 depende da reconexão
 * do Site Kit.
 */

/** Chave histórica, a mesma que o tema usava e que `bahia-mais-lidas.php` consome. */
define('BAHIA_VIEWS_META', 'views');

add_action('template_redirect', 'bahia_views_contabiliza');

/**
 * Conta uma leitura da matéria aberta.
 *
 * Contextos excluídos pelos mesmos motivos de sempre: painel, AJAX, REST, cron, feed e
 * pré-visualização não são leitura de leitor. `is_main_query()` não se aplica aqui porque
 * `template_redirect` roda depois da consulta principal, e `is_singular()` já garante o
 * contexto.
 */
function bahia_views_contabiliza() {
    if (is_admin() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview()) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    if (!is_singular()) {
        return;
    }

    $id = (int) get_queried_object_id();
    if ($id <= 0) {
        return;
    }

    // Só editorias — é o que o tema contava, e é o universo do bloco "+ Mais Lidas".
    $tipos = function_exists('bahia_editorias_map') ? array_keys(bahia_editorias_map()) : array('post');
    if (!in_array(get_post_type($id), $tipos, true)) {
        return;
    }

    bahia_views_incrementa($id);
}

/**
 * INCREMENTO ATÔMICO, e não a leitura-e-escrita do original.
 *
 * O tema fazia `$views = get + 1; update_post_meta(...)`. Duas leituras simultâneas da mesma
 * matéria leem o mesmo N e ambas gravam N+1 — uma das duas se perde. Não é hipótese
 * acadêmica: matéria em alta é exatamente o caso de acessos simultâneos, e é justamente ela
 * que o bloco deveria ordenar no topo.
 *
 * `UPDATE ... SET meta_value = meta_value + 1` resolve no banco, numa operação só. O INSERT
 * cobre a primeira leitura da matéria, quando a linha ainda não existe — `wp_postmeta` não tem
 * chave única em (post_id, meta_key), então não dá para usar ON DUPLICATE KEY.
 */
function bahia_views_incrementa($id) {
    global $wpdb;

    $n = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1
          WHERE post_id = %d AND meta_key = %s",
        $id, BAHIA_VIEWS_META
    ));

    if (0 === $n) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %d)",
            $id, BAHIA_VIEWS_META, 1
        ));
    }

    // O valor mudou por SQL cru: o cache de meta do request precisa saber.
    wp_cache_delete($id, 'post_meta');
}
