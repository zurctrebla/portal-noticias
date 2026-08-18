<?php
/**
 * RESTAURA o checkpoint pre-rodada 10 em hml.bahia.ba.
 *
 * Nao existe cliente mysql no pod — este script e o executor do arquivo .sql.
 *
 *   php restaurar.php checkpoint-r10-<ts>.sql              # DRY-RUN (aplica e da ROLLBACK)
 *   php restaurar.php checkpoint-r10-<ts>.sql --apply      # PARA VALER (COMMIT)
 *
 * Trava de seguranca: aborta se siteurl nao for https://hml.bahia.ba.
 * Rode SEMPRE o dry-run antes. Ele executa cada instrucao de verdade e desfaz.
 */
require_once '/var/www/html/wp-load.php';

$SITE = 'https://hml.bahia.ba';
if (get_option('siteurl') !== $SITE) {
    fwrite(STDERR, "ABORT: siteurl e '" . get_option('siteurl') . "', esperada '{$SITE}'. Nada foi feito.\n");
    exit(1);
}

$arq     = $argv[1] ?? '';
$aplicar = in_array('--apply', $argv, true);
if (!is_readable($arq)) {
    fwrite(STDERR, "Uso: php restaurar.php <arquivo.sql> [--apply]\n");
    exit(1);
}

global $wpdb;
$IDS = array(547432, 547414, 547416, 547422, 547428, 547430);
$in  = implode(',', $IDS);

function retrato() {
    global $wpdb, $IDS, $in;
    $r = array();
    foreach ($IDS as $id) $r["post {$id}"] = $wpdb->get_var($wpdb->prepare("SELECT MD5(post_content) FROM {$wpdb->posts} WHERE ID=%d", $id));
    foreach (array('td_011', 'wpseo_titles', 'td_011_settings') as $o)
        $r["opt {$o}"] = $wpdb->get_var($wpdb->prepare("SELECT MD5(option_value) FROM {$wpdb->options} WHERE option_name=%s", $o));
    $r['postmeta']  = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id IN ({$in})");
    $r['dup_meta']  = $wpdb->get_var("SELECT COUNT(*) FROM (SELECT post_id,meta_key FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) GROUP BY post_id,meta_key HAVING COUNT(*)>1) x");
    return $r;
}

echo $aplicar ? "MODO: APLICAR (vai dar COMMIT)\n" : "MODO: DRY-RUN (vai dar ROLLBACK)\n";
echo "Arquivo: {$arq}\n";
echo "Site:    {$SITE}\n\n";

$antes = retrato();
echo "Estado ANTES:\n";
foreach ($antes as $k => $v) printf("  %-20s %s\n", $k, $v);

/* Quebra em instrucoes. O payload e base64 (A-Za-z0-9+/=) e nunca contem ';'. */
$stmts = array(); $buf = '';
foreach (file($arq, FILE_IGNORE_NEW_LINES) as $ln) {
    $t = trim($ln);
    if ($t === '' || substr($t, 0, 2) === '--') continue;
    $buf .= ($buf === '' ? '' : "\n") . $ln;
    if (substr($t, -1) === ';') { $stmts[] = $buf; $buf = ''; }
}
if (trim($buf) !== '') $stmts[] = $buf;

/* A palavra-chave vem por regex: 'COMMIT;' nao tem espaco antes do ';' e um
   strtok(' ') devolveria 'COMMIT;', deixando o COMMIT escapar num dry-run. */
$wpdb->suppress_errors(true);
$erros = array(); $exec = 0; $vistos = array();
foreach ($stmts as $i => $s) {
    if (!preg_match('/^\s*([A-Za-z_]+)/', $s, $m)) continue;
    $k = strtoupper($m[1]);
    $vistos[$k] = ($vistos[$k] ?? 0) + 1;
    if ($k === 'COMMIT' || $k === 'SELECT') continue;   // COMMIT/ROLLBACK sao decididos aqui embaixo
    if ($wpdb->query($s) === false) {
        $erros[] = "#{$i} " . substr(preg_replace('/\s+/', ' ', $s), 0, 100) . " => " . $wpdb->last_error;
    } else { $exec++; }
}

echo "\nInstrucoes: " . count($stmts) . "   executadas: {$exec}   erros: " . count($erros) . "\n";
foreach (array_slice($erros, 0, 15) as $e) echo "  ERRO {$e}\n";
if (($vistos['COMMIT'] ?? 0) === 0 || ($vistos['START'] ?? 0) === 0) {
    $wpdb->query('ROLLBACK');
    fwrite(STDERR, "ABORT: arquivo sem START TRANSACTION/COMMIT reconhecidos. Desfeito.\n");
    exit(2);
}

if ($erros) {
    $wpdb->query('ROLLBACK');
    fwrite(STDERR, "\nHOUVE ERRO — ROLLBACK dado, banco intacto. Nada foi alterado.\n");
    exit(1);
}

$depois = retrato();
echo "\nEstado DEPOIS de aplicar:\n";
foreach ($depois as $k => $v) printf("  %-20s %s%s\n", $k, $v, $antes[$k] === $v ? '' : '   <= MUDOU');

if ($depois['dup_meta'] != 0) {
    $wpdb->query('ROLLBACK');
    fwrite(STDERR, "\nABORT: apareceu postmeta duplicada. ROLLBACK dado.\n");
    exit(1);
}

if ($aplicar) {
    $wpdb->query('COMMIT');
    wp_cache_flush();
    foreach ($IDS as $id) clean_post_cache($id);
    echo "\nCOMMIT dado. Estado restaurado.\n";
    echo "AGORA: purgar o cache do sidecar nginx —\n";
    echo "  kubectl exec -n bahia-wordpress \$POD -c nginx -- sh -lc 'rm -rf /tmp/nginx-cache/*'\n";
} else {
    $wpdb->query('ROLLBACK');
    $volta = retrato();
    $sujo = array();
    foreach ($antes as $k => $v) if ($volta[$k] !== $v) $sujo[] = $k;
    echo "\nROLLBACK dado. Banco intacto: " . (!$sujo ? "sim" : "NAO — " . implode(', ', $sujo)) . "\n";
    echo "Nada foi gravado. Repita com --apply para valer.\n";
}
