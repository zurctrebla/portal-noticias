<?php
/**
 * Plugin Name: Bahia.ba - Analytics só em produção
 * Description: Impede que qualquer ambiente que não seja o bahia.ba emita a tag do Google
 *              Analytics.
 *
 *              O PROBLEMA. Homologação apontava para a MESMA propriedade de produção
 *              (G-96ZB07C336, useSnippet ligado), então tráfego de teste vinha sendo contado
 *              junto com o do site real.
 *
 *              O Site Kit já prevê isso: Tag_Environment_Type_Guard só deixa a tag sair
 *              quando wp_get_environment_type() está na lista do filtro
 *              googlesitekit_allowed_tag_environment_types, cujo padrão é array('production').
 *              Como WP_ENVIRONMENT_TYPE não está definido em lugar nenhum, TODO ambiente se
 *              diz "production" e a guarda nunca barrou nada. Aqui a lista é esvaziada fora
 *              de produção, o que bloqueia a tag na origem.
 *
 *              POR QUE PELO FILTRO E NÃO POR useSnippet NO BANCO. O banco de homologação é
 *              recarregado de dumps de produção de vez em quando — a opção voltaria ligada e
 *              ninguém perceberia. O filtro viaja no git e vale em qualquer ambiente novo.
 *              Definir WP_ENVIRONMENT_TYPE=staging resolveria também, e de forma mais
 *              idiomática, mas mexe no comportamento de outros plugins de uma vez só; ficou
 *              de fora de propósito.
 *
 *              HISTÓRICO — A TAG LEGADA QUE ESTEVE AQUI. Entre 19 e 20/08/2026 este arquivo
 *              também reemitia a G-JBPJTKCCXY. Essa propriedade era escrita à mão pelo tema
 *              legado (themes/bahia_refactor/header.php:55-62) e morreu quando a virada
 *              trocou o tema ativo para o Newspaper — sem erro nenhum: o header simplesmente
 *              deixou de ser renderizado. Ela foi devolvida ao ar para preservar a série
 *              histórica e removida em seguida, quando ficou claro que NINGUÉM da equipe tem
 *              acesso a ela: a conta 390873476 (a que a redação usa) tem uma propriedade só,
 *              a 532492514 = G-96ZB07C336, e a legada nasceu na era de uma agência anterior,
 *              junto do UA-67237036-1. Alimentar uma propriedade que ninguém consegue abrir
 *              não servia a nada. Se o acesso for recuperado um dia, o commit ec4e02bc traz
 *              o snippet pronto.
 *
 *              Hoje o site tem UMA marcação: GT-PJWW3WSJ -> G-96ZB07C336, emitida pelo Site
 *              Kit, e só em produção.
 * Version: 2.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// Único ambiente que emite tag de analytics. Vários entram separados por vírgula.
define('BAHIA_ANALYTICS_AMBIENTES_AUTORIZADOS', 'https://bahia.ba');

function bahia_analytics_ambiente_autorizado() {
    $lista = array_map('trim', explode(',', BAHIA_ANALYTICS_AMBIENTES_AUTORIZADOS));

    return in_array(untrailingslashit(get_option('siteurl')), $lista, true);
}

/**
 * Fora de produção, nenhum ambiente pode emitir a tag do Site Kit.
 *
 * A guarda do Site Kit compara wp_get_environment_type() com esta lista; lista vazia não
 * casa com nada e a tag não chega a ser registrada.
 */
add_filter('googlesitekit_allowed_tag_environment_types', function ($ambientes) {
    return bahia_analytics_ambiente_autorizado() ? $ambientes : array();
});
