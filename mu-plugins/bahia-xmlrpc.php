<?php
/**
 * Plugin Name: Bahia.ba - XML-RPC desligado
 * Description: Desliga os métodos autenticados do XML-RPC e remove o cabeçalho X-Pingback.
 *              Porte do que o tema bahia_refactor fazia em functions.php:6-15.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE ISTO SAIU DO TEMA (18/08/2026).
 *
 * Estava em `themes/bahia_refactor/functions.php:6` e `:8`, e ia embora com a virada —
 * devolvendo ao site uma superfície de ataque que alguém já tinha decidido fechar. Medido:
 * o filtro está pendurado em produção e ausente em homologação.
 *
 * O que o `xmlrpc_enabled => false` faz, com precisão: desliga os métodos AUTENTICADOS
 * (`wp.getUsersBlogs`, `metaWeblog.*` e afins), que é o caminho usado para adivinhação de
 * senha em massa — o `system.multicall` permite empacotar centenas de tentativas numa única
 * requisição, o que atravessa qualquer contagem de tentativas por request.
 *
 * O que ele NÃO faz: `/xmlrpc.php` continua existindo e o pingback continua atendendo. Por
 * isso o `X-Pingback` sai do cabeçalho — não fecha nada sozinho, mas para de anunciar.
 * Fechar o `/xmlrpc.php` de vez é decisão de nginx, não de PHP, e não foi feita aqui.
 *
 * NOTA SOBRE COMO CONFERIR: bater com GET em `/xmlrpc.php` NÃO serve de teste — a resposta é
 * "XML-RPC server accepts POST requests only" (405) esteja o filtro ligado ou desligado. Foi
 * o que aconteceu na varredura de 18/08, e o teste teve de ser descartado. O que vale é o
 * gancho:
 *
 *     has_filter('xmlrpc_enabled', '__return_false')
 */

add_filter('xmlrpc_enabled', '__return_false');

add_filter('wp_headers', 'bahia_xmlrpc_remove_pingback');
function bahia_xmlrpc_remove_pingback($headers) {
    unset($headers['X-Pingback']);

    return $headers;
}
