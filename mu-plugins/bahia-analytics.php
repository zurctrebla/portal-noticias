<?php
/**
 * Plugin Name: Bahia.ba - Analytics: tag legada em produção, silêncio fora dela
 * Description: Duas coisas, uma de cada lado da mesma moeda.
 *
 *              1) PRODUÇÃO RECUPERA A TAG G-JBPJTKCCXY. Até a virada de 19/08/2026 o
 *              bahia.ba mandava dados a DUAS propriedades ao mesmo tempo. Uma delas, a
 *              G-JBPJTKCCXY, era emitida à mão pelo tema legado, em
 *              themes/bahia_refactor/header.php (linhas 55-62). Ao trocar o tema ativo para
 *              o Newspaper esse header parou de ser renderizado e a propriedade parou de
 *              receber acesso — sem erro nenhum, porque nada quebrou: só deixou de existir.
 *              A outra propriedade, G-96ZB07C336, continua sendo emitida normalmente pelo
 *              Site Kit e nunca parou; ela é de OUTRA conta do Google (390873476, contra
 *              67237036 da antiga) e foi criada em 10/04/2026, então não tem o histórico.
 *              Este arquivo devolve a tag antiga ao ar para a série histórica não ter um
 *              buraco a partir de 19/08.
 *
 *              POR QUE O SNIPPET INTEIRO E NÃO SÓ UM gtag('config'). Com o gtag.js do Site
 *              Kit já carregado, bastaria acrescentar o destino. Só que aí a tag legada
 *              passaria a depender de o Site Kit estar conectado e carregar primeiro — que é
 *              exatamente o modo de falha que estamos consertando. O snippet completo é o
 *              que estava na página antes da virada, com os dois carregadores dividindo o
 *              mesmo window.dataLayer, e é comprovadamente o que funcionava.
 *
 *              QUEM É CONTADO. A tag legada dispara para TODO MUNDO, inclusive usuário
 *              logado, porque era assim que o tema fazia. O Site Kit, por opção dele
 *              (trackingDisabled = loggedinUsers), não conta quem está logado. A diferença é
 *              proposital: mudar o público agora criaria um degrau nos números da
 *              propriedade antiga que não corresponde a nada do mundo real.
 *
 *              A UA-67237036-1 NÃO VOLTA. O tema legado também tinha o Universal Analytics
 *              (header.php:73-75). O Google parou de processar dados de UA em julho de 2023;
 *              é peso morto.
 *
 *              2) FORA DE PRODUÇÃO, O SITE KIT CALA A BOCA. Homologação apontava para a
 *              MESMA propriedade de produção (G-96ZB07C336, useSnippet ligado), então o
 *              tráfego de teste vinha sendo contado junto com o do site real.
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
 *              O QUE NÃO MUDA. O "Mais Lidas" lê o GA4 pela API do Site Kit, no servidor
 *              (bahia-mais-lidas.php usa $analytics->get_data('report')). Silenciar a tag do
 *              navegador não o afeta.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// Único ambiente que emite tag de analytics. Vários entram separados por vírgula.
define('BAHIA_ANALYTICS_AMBIENTES_AUTORIZADOS', 'https://bahia.ba');

// A propriedade que o tema legado emitia, conta 67237036.
define('BAHIA_ANALYTICS_MEDIDA_LEGADA', 'G-JBPJTKCCXY');

function bahia_analytics_ambiente_autorizado() {
    $lista = array_map('trim', explode(',', BAHIA_ANALYTICS_AMBIENTES_AUTORIZADOS));

    return in_array(untrailingslashit(get_option('siteurl')), $lista, true);
}

/**
 * Reemite a tag que morreu junto com o tema legado.
 *
 * Prioridade 20 para sair depois do snippet do Site Kit, reproduzindo a ordem que a página
 * tinha antes da virada.
 */
add_action('wp_head', 'bahia_analytics_tag_legada', 20);

function bahia_analytics_tag_legada() {
    if (!bahia_analytics_ambiente_autorizado()) {
        return;
    }

    $id = esc_js(BAHIA_ANALYTICS_MEDIDA_LEGADA);
    ?>
<!-- Tag legada bahia.ba (era do themes/bahia_refactor/header.php) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr(BAHIA_ANALYTICS_MEDIDA_LEGADA); ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?php echo $id; ?>');
</script>
<?php
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
