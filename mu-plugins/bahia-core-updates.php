<?php
/**
 * Plugin Name: Bahia.ba - Core so pela imagem
 * Description: Desliga a atualizacao do nucleo do WordPress de dentro do pod — automatica e
 *              pelo painel. O core passa a mudar so por `ARG WP_VERSION` no Dockerfile.
 *              Tarefa B de scratchpad/UPGRADE-PHP-WORDPRESS.md.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POR QUE (18/10/2026).
 *
 * `/var/www/html` vive num `emptyDir`: cada pod tem o seu proprio core, e ele morre com o pod.
 * Com o auto-update ligado, o WP-Cron de cada pod atualiza o seu core quando quer. Medido em
 * 28/08: producao serviu 6.8.3 e 6.8.8 AO MESMO TEMPO, conforme o pod que o ALB escolhia, e
 * todo rollout devolvia a versao da imagem ate o cron rodar de novo.
 *
 * Desligar so era seguro depois que a imagem entregasse versao atual — desligar na 6.8.3
 * congelaria producao numa versao `insecure`. A janela de 18/10 levou a imagem a 7.1.0
 * (fixa), e essa era a condicao.
 *
 * POR QUE FILTRO E NAO `WP_AUTO_UPDATE_CORE` NO `WORDPRESS_CONFIG_EXTRA`: o efeito e o mesmo,
 * mas a constante moraria no configmap do infra-bahiaba, um por ambiente, aplicado a parte.
 * Aqui a trava viaja com a imagem, vale nos dois ambientes e fica no historico deste repo.
 *
 * O QUE CONTINUA LIGADO, DE PROPOSITO:
 *   - instalar e atualizar plugin/tema pelo painel. `DISALLOW_FILE_MODS` foi descartado em
 *     18/10 (decisao de processo). Saiba que o que se instala pelo painel tambem cai no
 *     `emptyDir` de UM pod so e some no proximo rollout — o caminho certo continua sendo git.
 *   - a consulta de versao (`wp_version_check`): a informacao de que ha versao nova segue
 *     util para quem decide subir o `ARG WP_VERSION`.
 *
 * PARA ATUALIZAR O CORE: trocar `ARG WP_VERSION` no Dockerfile, validar em homolog, merge.
 */

// Automatica: nenhum tipo de atualizacao de core, nem a de seguranca (minor).
add_filter('auto_update_core', '__return_false');
add_filter('allow_dev_auto_core_updates', '__return_false');
add_filter('allow_minor_auto_core_updates', '__return_false');
add_filter('allow_major_auto_core_updates', '__return_false');

// Pelo painel: o botao "Atualizar agora" da tela de atualizacoes produziria a mesma
// divergencia, num pod so. Sem a capacidade `update_core`, o botao e o aviso somem.
add_filter('map_meta_cap', function ($caps, $cap) {
    return 'update_core' === $cap ? array('do_not_allow') : $caps;
}, 10, 2);
