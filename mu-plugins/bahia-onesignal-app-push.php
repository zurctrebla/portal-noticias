<?php
/**
 * Plugin Name: Bahia.ba - Push do app (OneSignal)
 * Description: Dispara, além do push do navegador que o plugin OneSignal já envia, uma
 *              notificação ADICIONAL para os aplicativos Android e iOS. Porte do que o tema
 *              bahia_refactor fazia em functions.php:182-210.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE ISTO SAIU DO TEMA (18/08/2026).
 *
 * O plugin OneSignal envia o push do NAVEGADOR sozinho. O push que chega ao APLICATIVO
 * (Android e iOS) dependia deste filtro, que vivia no tema antigo — e o tema morre na virada.
 * O plugin está ativo nos dois ambientes; o filtro estava pendurado só em produção.
 *
 * A perda seria silenciosa da pior maneira: nada quebra, nada erra, nenhum log. O repórter
 * marca "enviar notificação", o push do navegador sai normalmente, e quem tem o app
 * simplesmente não recebe. Ninguém associa a causa à troca de tema semanas depois.
 */

/**
 * A TRAVA DE AMBIENTE, e por que ela não é excesso de zelo.
 *
 * Medido em 18/08/2026: homologação usa **o mesmo `app_id` e a mesma chave REST** da
 * produção (`db07f370…2325`). Não é um app de testes — é o app real, com os assinantes
 * reais. Sem esta trava, um push disparado de homologação chegaria ao celular do leitor.
 *
 * O tema nunca precisou desta guarda porque ele só rodava em produção. O mu-plugin roda em
 * todo lugar; a guarda é o que devolve a garantia que a mudança de lugar tirou.
 *
 * Isto também preserva o estado de hoje em homologação, onde o push do app não sai.
 *
 * Para autorizar outro ambiente (um app de testes de verdade, por exemplo), acrescente o
 * siteurl à lista — e confira antes que o `app_id` daquele ambiente NÃO é o de produção.
 */
define('BAHIA_OS_AMBIENTES_AUTORIZADOS', 'https://bahia.ba');

function bahia_os_ambiente_autorizado() {
    $lista = array_map('trim', explode(',', BAHIA_OS_AMBIENTES_AUTORIZADOS));

    return in_array(untrailingslashit(get_option('siteurl')), $lista, true);
}

add_filter('onesignal_send_notification', 'bahia_os_push_do_app', 10, 4);

/**
 * Duplica o payload do push web, marca-o para Android/iOS e o envia direto à API do OneSignal.
 *
 * O `$fields` original é devolvido SEM alteração: o push do navegador tem de sair como sairia
 * sem este filtro. Por isso se trabalha sobre uma cópia.
 *
 * DOIS DEFEITOS DO ORIGINAL, corrigidos aqui — ambos no tratamento de erro, que é justamente
 * o caminho que ninguém testa:
 *
 *   1. `$response->get_error_code()` era chamado no ramo `is_wp_error($response) || !is_array
 *      ($response) || !isset($response['body'])`. Quando a resposta é um ARRAY sem 'body' —
 *      isto é, não é WP_Error —, `$response` não tem esse método: fatal dentro do save_post,
 *      derrubando a publicação da matéria. Agora o `get_error_*` só é chamado se for mesmo
 *      um WP_Error.
 *   2. O mesmo ramo fazia `return;` — devolvia null no lugar de `$fields`. Ou seja: se a
 *      chamada ao app falhasse, o push do NAVEGADOR ia junto, porque o plugin recebia null
 *      no filtro. Falha em um canal derrubava o outro. Agora devolve `$fields` sempre.
 *
 * CUIDADO AO TESTAR: este filtro FAZ CHAMADA EXTERNA. Chamar
 * `apply_filters('onesignal_send_notification', ...)` à mão em produção dispara um POST real
 * para a API do OneSignal. Aconteceu em 19/08, num script de validação: o payload sintético não
 * tinha `app_id` e a OneSignal recusou com HTTP 400, então nada foi enviado — mas a chamada
 * saiu. Em homologação isso não ocorre porque a trava de ambiente barra antes.
 *
 * Para conferir o porte sem disparar nada, verifique o GANCHO e a TRAVA, nunca o filtro:
 *
 *     has_filter('onesignal_send_notification', 'bahia_os_push_do_app')   // deve ser != false
 *     bahia_os_ambiente_autorizado()                                      // true só em produção
 *
 * @param array   $fields     payload que o plugin vai enviar ao navegador.
 * @param string  $new_status
 * @param string  $old_status
 * @param WP_Post $post
 * @return array o mesmo $fields, intacto.
 */
function bahia_os_push_do_app($fields, $new_status = null, $old_status = null, $post = null) {
    if (!is_array($fields) || !bahia_os_ambiente_autorizado() || !class_exists('OneSignal')) {
        return $fields;
    }

    $ajustes = OneSignal::get_onesignal_settings();
    if (empty($ajustes['app_rest_api_key'])) {
        error_log('[bahia-onesignal] chave REST ausente; push do app nao enviado');
        return $fields;
    }

    $copia = $fields;
    $copia['isAndroid'] = true;
    $copia['isIos']     = true;
    $copia['isAnyWeb']  = true;
    $copia['data']      = array('customkey' => isset($fields['url']) ? $fields['url'] : '');

    // web_url mantém o link funcionando no navegador; a ausência de 'url' é o que impede o
    // app de abrir o navegador por fora quando o leitor toca na notificação.
    if (isset($copia['url'])) {
        $copia['web_url'] = $copia['url'];
        unset($copia['url']);
    }

    $resposta = wp_remote_post('https://onesignal.com/api/v1/notifications', array(
        'headers' => array(
            'content-type'  => 'application/json;charset=utf-8',
            'Authorization' => 'Basic ' . $ajustes['app_rest_api_key'],
        ),
        'body'    => wp_json_encode($copia),
        'timeout' => 60,
    ));

    if (is_wp_error($resposta)) {
        error_log('[bahia-onesignal] erro ao enviar ao app: '
            . $resposta->get_error_code() . ' — ' . $resposta->get_error_message());
        return $fields;
    }
    $codigo = (int) wp_remote_retrieve_response_code($resposta);
    if ($codigo < 200 || $codigo >= 300) {
        error_log('[bahia-onesignal] OneSignal respondeu HTTP ' . $codigo . ': '
            . substr((string) wp_remote_retrieve_body($resposta), 0, 300));
    }

    return $fields;
}
