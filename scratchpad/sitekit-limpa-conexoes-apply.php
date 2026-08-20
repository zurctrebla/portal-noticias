<?php
/**
 * Limpa as conexões mortas do Site Kit, deixando só a do dono atual.
 *
 * CONTEXTO. Ao investigar por que o GA "parou de contar" apareceram TRÊS conexões do Site
 * Kit gravadas no WordPress, de três pessoas diferentes:
 *
 *   id 132  Marcela                        grupo@setembrina.com.br     sem papel, invalid_grant
 *   id 154  contato@hugohenrique.com.br    contato@hugohenrique.com.br sem papel, invalid_grant
 *   id 157  Albert Cruz                    arc.albert.cruz@gmail.com   administrator, token válido
 *
 * As duas primeiras já estavam mortas antes deste script: sem access_token, sem
 * refresh_token, com error_code = invalid_grant gravado, e sem nenhum papel no WordPress.
 * Apagá-las não revoga acesso nenhum — o acesso de verdade mora na conta do Google, não
 * aqui. O que se apaga é o ponteiro velho.
 *
 * TAMBÉM SAI O MÓDULO ANALYTICS ANTIGO. A opção googlesitekit_analytics_settings guarda
 * ownerID=132 e accountID=67237036: é a configuração do módulo Universal Analytics, o
 * último lugar do banco que ainda apontava para a Setembrina. Esse módulo NÃO EXISTE mais
 * no Site Kit instalado (1.180.0 só traz Analytics_4.php; não há Analytics.php), e o
 * "analytics" listado em googlesitekit_active_modules é referência a código que não é mais
 * carregado. O GA4 (googlesitekit_analytics-4_settings) não é tocado.
 *
 * O QUE ESTE SCRIPT NÃO FAZ:
 *   - não apaga usuário nenhum do WordPress (só o meta do Site Kit deles);
 *   - não mexe em googlesitekit_analytics-4_settings, credentials nem search-console;
 *   - não devolve nem tira acesso a propriedade nenhuma do Google Analytics.
 *
 * USO (a partir da raiz do WordPress, dentro do pod):
 *     php sitekit-limpa-conexoes-apply.php              # simulação, não grava nada
 *     php sitekit-limpa-conexoes-apply.php --aplicar    # grava
 */

// O diretório scratchpad/ viaja na imagem e fica alcançável pela web (os .php de lá
// respondem 500, porque o require abaixo falha). Esta linha garante que, alcançável ou
// não, nada aqui roda fora da linha de comando.
if (PHP_SAPI !== 'cli') {
    exit;
}

require_once __DIR__ . '/wp-load.php';

$aplicar = in_array('--aplicar', $argv, true);

$AMBIENTES = array('https://bahia.ba', 'https://hml.bahia.ba');
$siteurl   = untrailingslashit(get_option('siteurl'));

if (!in_array($siteurl, $AMBIENTES, true)) {
    fwrite(STDERR, "ABORTADO: siteurl inesperado ({$siteurl}).\n");
    exit(1);
}

echo "ambiente : {$siteurl}\n";
echo "modo     : " . ($aplicar ? 'APLICANDO' : 'simulação (use --aplicar para gravar)') . "\n\n";

global $wpdb;

$dono = (int) get_option('googlesitekit_owner_id');

// Trava: sem um dono com token válido, esta limpeza deixaria o site sem NENHUMA conexão.
$token_do_dono = $dono ? get_user_meta($dono, $wpdb->get_blog_prefix() . 'googlesitekit_access_token', true) : '';
if (!$dono || empty($token_do_dono)) {
    fwrite(STDERR, "ABORTADO: googlesitekit_owner_id={$dono} não tem access_token. Nada foi tocado.\n");
    exit(1);
}

$u = get_userdata($dono);
echo "dono que fica: id {$dono} (" . ($u ? $u->user_login : '?') . ")\n\n";

// ---------------------------------------------------------------- 1. contas a remover
$ids = $wpdb->get_col(
    "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key LIKE '%googlesitekit%' ORDER BY user_id"
);

echo "== conexões encontradas ==\n";
$remover = array();
foreach ($ids as $id) {
    $id      = (int) $id;
    $usuario = get_userdata($id);
    $perfil  = get_user_meta($id, $wpdb->get_blog_prefix() . 'googlesitekit_profile', true);
    $conta   = (is_array($perfil) && !empty($perfil['email'])) ? $perfil['email'] : '-';
    $n       = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $id, '%googlesitekit%'
    ));

    if ($id === $dono) {
        echo "  MANTÉM  id {$id} (" . ($usuario ? $usuario->user_login : '?') . ") {$conta} — {$n} chaves\n";
        continue;
    }

    $remover[] = $id;
    echo "  REMOVE   id {$id} (" . ($usuario ? $usuario->user_login : '?') . ") {$conta} — {$n} chaves\n";
}

echo "\n";

foreach ($remover as $id) {
    $chaves = $wpdb->get_col($wpdb->prepare(
        "SELECT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $id, '%googlesitekit%'
    ));
    foreach ($chaves as $chave) {
        echo "  - usermeta {$id}.{$chave}\n";
        if ($aplicar) {
            delete_user_meta($id, $chave);
        }
    }
}

// ------------------------------------------------- 2. módulo Universal Analytics, órfão
$ua = get_option('googlesitekit_analytics_settings');
if (false !== $ua) {
    echo "\n  - opção googlesitekit_analytics_settings (ownerID="
       . (isset($ua['ownerID']) ? $ua['ownerID'] : '?') . ", accountID="
       . (isset($ua['accountID']) ? $ua['accountID'] : '?') . ")\n";
    if ($aplicar) {
        delete_option('googlesitekit_analytics_settings');
    }
} else {
    echo "\n  (googlesitekit_analytics_settings já não existe)\n";
}

$modulos = (array) get_option('googlesitekit_active_modules', array());
if (in_array('analytics', $modulos, true)) {
    $novos = array_values(array_diff($modulos, array('analytics')));
    echo "  - active_modules: " . json_encode($modulos) . "  ->  " . json_encode($novos) . "\n";
    if ($aplicar) {
        update_option('googlesitekit_active_modules', $novos);
    }
} else {
    echo "  (active_modules já não tem 'analytics')\n";
}

echo "\n" . ($aplicar ? "GRAVADO.\n" : "NADA GRAVADO — simulação.\n");
