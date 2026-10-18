<?php
/**
 * Terceira camada de aquecimento — os INDICES SECUNDARIOS.
 *
 * `FORCE INDEX (PRIMARY)` varre o indice agrupado e carrega as paginas de DADO. Nao toca
 * as paginas dos indices secundarios, que sao justamente as que a busca, o archive e a
 * ordenacao por data usam. Sem esta passada o pool fica com o dado e sem os caminhos ate ele.
 *
 * Env: SONDA_HOST
 */
$m = new mysqli(getenv("SONDA_HOST"), getenv("WORDPRESS_DB_USER"),
                getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"));
if ($m->connect_errno) { echo "ERRO: " . $m->connect_error . "\n"; exit(1); }

function bp($m) { return (float) $m->query("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_bytes_data'")->fetch_row()[1]; }
function rd($m) { return (float) $m->query("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_reads'")->fetch_row()[1]; }

$r = $m->query("SELECT s.TABLE_NAME, s.INDEX_NAME, MIN(s.COLUMN_NAME) col
                  FROM information_schema.STATISTICS s
                  JOIN information_schema.TABLES t
                    ON t.TABLE_SCHEMA=s.TABLE_SCHEMA AND t.TABLE_NAME=s.TABLE_NAME
                 WHERE s.TABLE_SCHEMA=DATABASE() AND s.INDEX_NAME<>'PRIMARY'
                   AND s.INDEX_TYPE<>'FULLTEXT'
                   AND (t.DATA_LENGTH+t.INDEX_LENGTH) > 1*1024*1024
                 GROUP BY s.TABLE_NAME, s.INDEX_NAME
                 ORDER BY s.TABLE_NAME, s.INDEX_NAME");
$idx = [];
while ($x = $r->fetch_row()) { $idx[] = $x; }

$ini = bp($m); $r0 = rd($m); $n = 0; $falhas = 0;
foreach ($idx as $i) {
    list($t, $k, $c) = $i;
    // COUNT sobre a coluna do indice, forcando o indice: varre as folhas dele.
    $q = @$m->query("SELECT COUNT(`$c`) FROM `$t` FORCE INDEX (`$k`)");
    if ($q === false) { $falhas++; continue; }
    $n++;
}
$fim = bp($m); $r1 = rd($m);

printf("indices varridos: %d de %d (falhas: %d)\n", $n, count($idx), $falhas);
printf("pool: %.3f -> %.3f GiB  (%+.3f)\n", $ini/1073741824, $fim/1073741824, ($fim-$ini)/1073741824);
printf("reads fisicos na passada: %s\n", number_format($r1-$r0));

$sz = (float) $m->query("SELECT @@innodb_buffer_pool_size")->fetch_row()[0];
$logico = (float) $m->query("SELECT SUM(DATA_LENGTH+INDEX_LENGTH) FROM information_schema.TABLES
                              WHERE TABLE_SCHEMA=DATABASE()")->fetch_row()[0];
$fisico = (float) $m->query("SELECT SUM(FILE_SIZE) FROM information_schema.INNODB_TABLESPACES
                              WHERE NAME LIKE CONCAT(DATABASE(),'/%')")->fetch_row()[0];
printf("\nresidente          = %.3f GiB\n", $fim/1073741824);
printf("dado logico        = %.3f GiB  -> %.1f%%\n", $logico/1073741824, 100*$fim/$logico);
printf("arquivos em disco  = %.3f GiB  -> %.1f%%\n", $fisico/1073741824, 100*$fim/$fisico);
printf("ocupacao do pool   = %.1f%% de %.2f GiB\n", 100*$fim/$sz, $sz/1073741824);
