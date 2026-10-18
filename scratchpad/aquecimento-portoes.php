<?php
/**
 * Portões de aquecimento — UPGRADE-MYSQL.md §3.3.
 *
 * modo=base    -> fotografa a instância de referência (produção/azul)
 * modo=janela  -> mede a taxa de acerto INCREMENTAL numa janela, no alvo
 *
 * Env: SONDA_HOST (alvo), MODO, JANELA (segundos, padrão 300), BASE_BYTES
 */
$err  = fopen("php://stderr", "w");
$host = getenv("SONDA_HOST");
$modo = getenv("MODO") ?: "base";
$m = new mysqli($host, getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"));
if ($m->connect_errno) { fwrite($err, "ERRO: " . $m->connect_error . "\n"); exit(1); }

function st($m, $n) {
    $r = $m->query("SHOW GLOBAL STATUS LIKE '$n'");
    return $r && ($x = $r->fetch_row()) ? (float) $x[1] : 0.0;
}

$dados = st($m, "Innodb_buffer_pool_bytes_data");
$pool  = (float) $m->query("SELECT @@innodb_buffer_pool_size")->fetch_row()[0];
$ver   = $m->server_info;

if ($modo === "base") {
    printf("host=%s\nversao=%s\n", $host, $ver);
    printf("Innodb_buffer_pool_bytes_data=%d\n", (int) $dados);
    printf("  = %.3f GiB  (%.1f%% de um pool de %.2f GiB)\n",
           $dados / 1073741824, 100 * $dados / $pool, $pool / 1073741824);
    printf("Innodb_buffer_pool_read_requests=%d\n", (int) st($m, "Innodb_buffer_pool_read_requests"));
    printf("Innodb_buffer_pool_reads=%d\n", (int) st($m, "Innodb_buffer_pool_reads"));
    exit(0);
}

// --- modo janela: taxa de acerto INCREMENTAL, nao a acumulada desde o boot ---
$janela = (int) (getenv("JANELA") ?: 300);
$base   = (float) (getenv("BASE_BYTES") ?: 0);

$rq0 = st($m, "Innodb_buffer_pool_read_requests");
$rd0 = st($m, "Innodb_buffer_pool_reads");
$t0  = microtime(true);
printf("janela de %ds iniciada em %s UTC\n", $janela, gmdate("H:i:s"));

while ((microtime(true) - $t0) < $janela) { usleep(500000); }

$rq1 = st($m, "Innodb_buffer_pool_read_requests");
$rd1 = st($m, "Innodb_buffer_pool_reads");
$dados = st($m, "Innodb_buffer_pool_bytes_data");

$drq = $rq1 - $rq0; $drd = $rd1 - $rd0;
$taxa = $drq > 0 ? 100 * (1 - $drd / $drq) : -1;

printf("fim em %s UTC\n\n", gmdate("H:i:s"));
printf("read_requests na janela = %s\n", number_format($drq));
printf("reads fisicos na janela = %s\n", number_format($drd));
printf("TAXA DE ACERTO INCREMENTAL = %s\n",
       $taxa < 0 ? "(sem trafego na janela)" : sprintf("%.4f%%", $taxa));
printf("  portao >= 99,9%%  -> %s\n",
       $taxa < 0 ? "INDETERMINADO" : ($taxa >= 99.9 ? "PASSA" : "REPROVA"));

printf("\nInnodb_buffer_pool_bytes_data = %d = %.3f GiB\n", (int) $dados, $dados / 1073741824);
if ($base > 0) {
    printf("  base (azul) = %.3f GiB\n", $base / 1073741824);
    printf("  razao = %.1f%% da base\n", 100 * $dados / $base);
    printf("  portao >= 95%%  -> %s\n", (100 * $dados / $base) >= 95 ? "PASSA" : "REPROVA");
}
