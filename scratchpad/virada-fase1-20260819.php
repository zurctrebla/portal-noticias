<?php
/**
 * FASE 1 DA VIRADA — bahia_refactor -> Newspaper, em transação única.
 *
 * Roda DENTRO de um pod de produção, com o site já em manutenção.
 *
 *     php virada-fase1-20260819.php            # seco: mostra o antes/depois, não grava
 *     php virada-fase1-20260819.php --aplicar  # grava, em transação única
 *
 * Carga em /tmp/carga-virada.json, extraída de homolog e validada antes da janela:
 * as 15 chaves do td_011, o theme_mods_Newspaper e o wpseo_titles.
 *
 * NÃO toca em: tds_footer_page fora do td_011, options_slider_m1, options_semi_destaques_m1,
 * blogdescription, siteurl, home.
 */

define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';

$aplicar = in_array('--aplicar', $argv, true);

global $wpdb;
$T = $wpdb->options;

if (get_option('siteurl') !== 'https://bahia.ba') {
    fwrite(STDERR, "ABORTA: siteurl nao e producao\n");
    exit(1);
}

$carga = json_decode(file_get_contents('/tmp/carga-virada.json'), true);
if (!$carga || empty($carga['td_011']) || empty($carga['wpseo_titles'])) {
    fwrite(STDERR, "ABORTA: carga ausente ou incompleta\n");
    exit(1);
}

$PLUGINS_TAGDIV = array(
    'td-cloud-library/td-cloud-library.php',
    'td-composer/td-composer.php',
    'td-social-counter/td-social-counter.php',
);

/** Lê o valor cru, direto da tabela. */
function v_cru($nome) {
    global $wpdb, $T;
    $v = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM $T WHERE option_name = %s", $nome));
    return $v === null ? null : maybe_unserialize($v);
}

/** Grava por SQL cru para participar da transação; autoload só é definido ao CRIAR. */
function grava($nome, $valor, $autoload_novo = 'on') {
    global $wpdb, $T;
    $ser = maybe_serialize($valor);
    $existe = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $T WHERE option_name = %s", $nome));
    if ($existe) {
        return $wpdb->query($wpdb->prepare("UPDATE $T SET option_value = %s WHERE option_name = %s", $ser, $nome));
    }
    return $wpdb->query($wpdb->prepare(
        "INSERT INTO $T (option_name, option_value, autoload) VALUES (%s, %s, %s)", $nome, $ser, $autoload_novo));
}

/* ------------------------------------------------------------------ ANTES */

$antes = array(
    'template'             => v_cru('template'),
    'stylesheet'           => v_cru('stylesheet'),
    'show_on_front'        => v_cru('show_on_front'),
    'page_on_front'        => v_cru('page_on_front'),
    'site_icon'            => v_cru('site_icon'),
    'active_plugins'       => (array) v_cru('active_plugins'),
    'td_011'               => v_cru('td_011'),
    'theme_mods_Newspaper' => v_cru('theme_mods_Newspaper'),
    'wpseo_titles'         => (array) v_cru('wpseo_titles'),
);

echo "===== ANTES =====\n";
printf("  template/stylesheet ... %s / %s\n", var_export($antes['template'], true), var_export($antes['stylesheet'], true));
printf("  show_on_front ......... %s\n", var_export($antes['show_on_front'], true));
printf("  page_on_front ......... %s\n", var_export($antes['page_on_front'], true));
printf("  site_icon ............. %s\n", var_export($antes['site_icon'], true));
printf("  active_plugins ........ %d\n", count($antes['active_plugins']));
printf("  td_011 ................ %s\n", $antes['td_011'] === null ? 'AUSENTE' : 'existe');
printf("  theme_mods_Newspaper .. %s\n", $antes['theme_mods_Newspaper'] === null ? 'AUSENTE' : 'existe');
printf("  wpseo_titles .......... %d chaves\n", count($antes['wpseo_titles']));

/* ------------------------------------------------------------- O QUE ENTRA */

$ap_novo = $antes['active_plugins'];
foreach ($PLUGINS_TAGDIV as $p) {
    if (!in_array($p, $ap_novo, true)) { $ap_novo[] = $p; }
}
sort($ap_novo);   // é o que activate_plugin() do core faz

// União: produção é a base, homolog vence nos conflitos.
$wt_novo = array_merge($antes['wpseo_titles'], $carga['wpseo_titles']);

echo "\n===== O QUE ENTRA =====\n";
printf("  td_011 ................ %d chaves (opção nova)\n", count($carga['td_011']));
printf("  theme_mods_Newspaper .. %s\n", json_encode($carga['theme_mods_Newspaper'], JSON_UNESCAPED_UNICODE));
printf("  active_plugins ........ %d -> %d (+%d)\n", count($antes['active_plugins']), count($ap_novo),
    count($ap_novo) - count($antes['active_plugins']));
printf("  wpseo_titles .......... %d -> %d chaves\n", count($antes['wpseo_titles']), count($wt_novo));

if (!$aplicar) {
    echo "\n(modo seco — nada gravado)\n";
    exit(0);
}

/* ------------------------------------------------------------- TRANSAÇÃO */

$t0 = microtime(true);
$wpdb->query('SET autocommit = 0');
$wpdb->query('START TRANSACTION');

$erro = null;
$passos = array();

try {
    // 1) td_011 ANTES da troca de tema: o tema não pode carregar sem as opções dele.
    $passos['td_011']               = grava('td_011', $carga['td_011'], 'auto');
    // 2) o tema
    $passos['template']             = grava('template', 'Newspaper');
    $passos['stylesheet']           = grava('stylesheet', 'Newspaper');
    // 3) os plugins do tagDiv
    $passos['active_plugins']       = grava('active_plugins', $ap_novo);
    // 4) mods do tema (menus)
    $passos['theme_mods_Newspaper'] = grava('theme_mods_Newspaper', $carga['theme_mods_Newspaper'], 'on');
    // 5) a home estática
    $passos['show_on_front']        = grava('show_on_front', 'page');
    $passos['page_on_front']        = grava('page_on_front', 9000142);
    // 6) favicon
    $passos['site_icon']            = grava('site_icon', 9000188);
    // 7) títulos do Yoast, por união
    $passos['wpseo_titles']         = grava('wpseo_titles', $wt_novo);

    foreach ($passos as $nome => $r) {
        if ($r === false) { throw new RuntimeException("falha ao gravar $nome: " . $wpdb->last_error); }
    }

    $wpdb->query('COMMIT');
    $ms = (microtime(true) - $t0) * 1000;
    printf("\n===== COMMIT em %.0f ms =====\n", $ms);
} catch (Throwable $e) {
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    fwrite(STDERR, "ROLLBACK: " . $e->getMessage() . "\n");
    exit(1);
}
$wpdb->query('SET autocommit = 1');

/* ------------------------------------------------------------- CONFERÊNCIA */

echo "\n===== DEPOIS =====\n";
$ok = true;
$esperado = array(
    'template'      => 'Newspaper',
    'stylesheet'    => 'Newspaper',
    'show_on_front' => 'page',
    'page_on_front' => '9000142',
    'site_icon'     => '9000188',
);
foreach ($esperado as $k => $exp) {
    $v = (string) v_cru($k);
    $bate = ($v === $exp);
    $ok = $ok && $bate;
    printf("  %-22s %-14s %s\n", $k, var_export($v, true), $bate ? 'ok' : '*** ESPERADO ' . $exp . ' ***');
}
$td = v_cru('td_011');
printf("  %-22s %d chaves %s\n", 'td_011', is_array($td) ? count($td) : -1, (is_array($td) && count($td) === 15) ? 'ok' : '*** ***');
$tm = v_cru('theme_mods_Newspaper');
printf("  %-22s %s\n", 'theme_mods_Newspaper', json_encode($tm, JSON_UNESCAPED_UNICODE));
$ap = (array) v_cru('active_plugins');
$falta = array_diff($PLUGINS_TAGDIV, $ap);
printf("  %-22s %d plugins, tagDiv: %s\n", 'active_plugins', count($ap), empty($falta) ? 'os 3 presentes' : 'FALTA ' . implode(',', $falta));
$ok = $ok && empty($falta);
$wt = (array) v_cru('wpseo_titles');
printf("  %-22s %d chaves\n", 'wpseo_titles', count($wt));

echo "\n===== INTOCADOS (conferencia) =====\n";
foreach (array('siteurl','home','blogdescription','options_slider_m1','options_semi_destaques_m1') as $k) {
    $v = v_cru($k);
    printf("  %-28s %s\n", $k, is_scalar($v) ? var_export($v, true) : json_encode($v, JSON_UNESCAPED_UNICODE));
}
printf("  %-28s %s\n", 'td_011[tds_footer_page]', var_export(isset($td['tds_footer_page']) ? $td['tds_footer_page'] : null, true));

echo "\n" . ($ok ? "RESULTADO: ok\n" : "RESULTADO: DIVERGENCIA — conferir acima\n");
exit($ok ? 0 : 2);
