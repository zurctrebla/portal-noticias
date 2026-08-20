<?php
/**
 * Plugin Name: Bahia.ba - Horário de publicação (matéria e home)
 * Description: O tema legado imprimia "Publicado em 28/07/2026 às 13h53" no topo da matéria
 *              (themes/bahia_refactor/functions.php, função data_post()). O Newspaper imprime
 *              só a data — "28 de julho de 2026". O horário sumiu na virada por causa do
 *              formato, não por perda de dado: o atributo datetime do <time> continua trazendo
 *              o horário completo (datetime="2026-07-28T13:53:21-03:00"); só o texto visível
 *              é que não o mostra.
 *
 *              ONDE NASCE O HTML. Dois lugares, com a MESMA linha:
 *                - matéria: plugins/td-composer/legacy/common/wp_booster/
 *                  td_module_single_base.php, get_date();
 *                - cards:   plugins/td-composer/legacy/common/wp_booster/
 *                  td_module.php, get_date().
 *              Ambos chamam get_the_time(get_option('date_format'), $post->ID), e o
 *              date_format do site é "j \d\e F \d\e Y", que não tem hora. Não existe filtro
 *              nesses métodos nem opção de horário no painel do tagDiv — a única opção
 *              relacionada, tds_p_show_date, apenas esconde a data inteira.
 *
 *              POR QUE NÃO MEXER NO date_format. Trocar a opção do WordPress atingiria tudo
 *              de uma vez: arquivos de editoria, busca, página de colunista e "Últimas
 *              Notícias" passariam a exibir hora junto. O pedido é a matéria e a home.
 *
 *              ESCOPO. O horário entra em três situações, e só nelas:
 *                1. a matéria aberta (o post consultado — cards de relacionado, mais lida e
 *                   barra lateral chegam ao filtro com OUTRO id e passam intocados);
 *                2. todos os cards da home (front page, id 9000142);
 *                3. os cards que o "Ver mais notícias" da home acrescenta.
 *
 *              O CASO 3 E O REFERER. O "Ver mais" da home é o load more nativo do tagDiv:
 *              vai a admin-ajax pela ação td_ajax_block, onde não existe consulta principal
 *              e is_front_page() é sempre falso. O tagDiv só manda o td_request_uri quando a
 *              paginação é "numbered" (js/tagdiv_theme.min.js, linha 198) — na home ela é
 *              "load_more", então esse campo não vem. Sobra o Referer, lido por
 *              wp_get_referer(), que já valida o host. Se o navegador não mandar Referer, os
 *              cards acrescentados saem só com a data, como saíam antes: degrada, não quebra.
 *              Prender-se ao id do bloco (hoje tdi_148) não serve — o tagDiv renumera os
 *              .tdi_NN a cada edição de template.
 *
 *              O QUE SEGUE SEM HORA, de propósito: arquivos de editoria, busca, página de
 *              colunista, "Últimas Notícias" e o endpoint próprio de scroll infinito dos
 *              arquivos (bahia-scroll-infinito.php), que é outra ação de ajax.
 *
 *              A DATA DE MODIFICAÇÃO NÃO É TOCADA. O legado também imprimia "Atualizado em"
 *              quando o meta exibir_atualizacao estava marcado. Isso não faz parte deste
 *              ajuste e continua desligado no Newspaper (tds_p_show_modified_date).
 * Version: 1.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// Mesmo formato do legado: 13h53. O escape "\h" é o "h" literal entre hora e minuto.
define('BAHIA_HORARIO_FORMATO', 'H\hi');

// Fica entre a data e a hora: "28 de julho de 2026 às 13h53".
define('BAHIA_HORARIO_LIGACAO', 'às');

// A ação de ajax do load more nativo do tagDiv, usada pelo "Ver mais notícias" da home.
define('BAHIA_HORARIO_ACAO_AJAX', 'td_ajax_block');

/**
 * O pedido de "Ver mais notícias" partiu da home?
 *
 * Em admin-ajax não há consulta principal, então nenhuma conditional tag responde por nós.
 * O Referer é o único sinal genérico disponível; wp_get_referer() já recusa host de fora.
 */
function bahia_horario_ajax_veio_da_home() {
    $acao = isset($_REQUEST['action']) ? sanitize_key($_REQUEST['action']) : '';
    if (BAHIA_HORARIO_ACAO_AJAX !== $acao) {
        return false;
    }

    $referer = wp_get_referer();
    if (empty($referer)) {
        return false;
    }

    $caminho = (string) wp_parse_url($referer, PHP_URL_PATH);
    $home    = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

    // A home pode ter querystring (utm, por exemplo); só o caminho interessa.
    return untrailingslashit($caminho) === untrailingslashit($home);
}

/**
 * Este post, neste pedido, recebe horário?
 */
function bahia_horario_deve_aplicar(WP_Post $post) {

    // Ajax primeiro: é o único ramo que não pode consultar conditional tags.
    if (wp_doing_ajax()) {
        return bahia_horario_ajax_veio_da_home();
    }

    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        return false;
    }

    // Conditional tags só são confiáveis depois que a consulta principal rodou.
    if (!did_action('wp') || is_feed()) {
        return false;
    }

    // Home: todos os cards.
    if (is_front_page()) {
        return true;
    }

    // Matéria aberta: só ela. Card de relacionado ou mais lida chega com outro id.
    if (is_singular() && (int) $post->ID === (int) get_queried_object_id()) {
        return true;
    }

    return false;
}

add_filter('get_the_time', function ($the_time, $format, $post) {

    if (!($post instanceof WP_Post) || !is_string($the_time) || '' === $the_time) {
        return $the_time;
    }

    // Os módulos do tagDiv pedem o date_format do site. Qualquer outro formato — o 'U' dos
    // "time ago", os 'Y-m-d' de comparação interna — passa intocado.
    $formato_do_site = (string) get_option('date_format');
    if ('' === $formato_do_site || $format !== $formato_do_site) {
        return $the_time;
    }

    if (!bahia_horario_deve_aplicar($post)) {
        return $the_time;
    }

    $hora = get_post_time(BAHIA_HORARIO_FORMATO, false, $post, true);
    if (empty($hora)) {
        return $the_time;
    }

    return $the_time . ' ' . BAHIA_HORARIO_LIGACAO . ' ' . $hora;
}, 10, 3);
