<?php
/**
 * Plugin Name: Bahia.ba - Chaves de funcionalidade
 * Description: Liga funcionalidades que estao versionadas mas nao devem valer em todo ambiente.
 *
 *              Existe por causa de uma lição que custou uma correcao as pressas: em
 *              `mu-plugins/` **commitar e instalar**. A `develop` reconstroi homolog e a `main`
 *              reconstroi producao; tudo nessa pasta entra em vigor quando o pod sobe. Codigo
 *              que ainda nao deve rodar em todo lugar precisa de trava explicita — e a trava
 *              precisa morar em algum lugar versionado, senao vira ajuste manual em pod, que
 *              some no proximo deploy.
 *
 *              O nome do arquivo importa: os mu-plugins sao carregados em ordem alfabetica, e
 *              `bahia-flags` vem antes de `bahia-webp-upload`. Uma constante definida aqui ja
 *              existe quando o outro arquivo e compilado.
 *
 *              Para promover uma funcionalidade a producao, acrescente o dominio na condicao —
 *              a mudanca fica no historico do git, com data e autor, em vez de perdida num
 *              `kubectl exec`.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Ambiente atual, pelo siteurl. Neste ponto do carregamento o banco ja esta disponivel —
 * mu-plugins entram depois de `wp_start_object_cache()` em `wp-settings.php`.
 *
 * Usamos o siteurl e nao variavel de ambiente porque e o mesmo valor que ja distingue os
 * ambientes em toda a base de codigo, e porque um pod de homolog apontado por engano para o
 * banco de producao seria detectado aqui — que e exatamente o acidente que se quer evitar.
 */
if (!function_exists('bahia_ambiente')) {
    function bahia_ambiente() {
        static $amb = null;
        if (null !== $amb) {
            return $amb;
        }
        $url = get_option('siteurl');
        if (is_string($url) && false !== strpos($url, 'hml.bahia.ba')) {
            $amb = 'homolog';
        } elseif (is_string($url) && false !== strpos($url, 'bahia.ba')) {
            $amb = 'producao';
        } else {
            $amb = 'desconhecido';
        }
        return $amb;
    }
}

/*
 * Conversao de PNG para WebP no upload (`bahia-webp-upload.php`).
 *
 * HOMOLOG apenas, ate a validacao terminar e a redacao responder sobre a ferramenta que exporta
 * em 620x400. Ver scratchpad/WEBP-UPLOAD-DESENHO.md.
 */
if ('homolog' === bahia_ambiente() && !defined('BAHIA_WEBP_UPLOAD_ATIVO')) {
    define('BAHIA_WEBP_UPLOAD_ATIVO', true);
}
