<?php
/**
 * Aquecimento REAL — §3.3.
 *
 * `SELECT COUNT(*) FROM t` no InnoDB escolhe o MENOR indice disponivel, que quase sempre e
 * um secundario. Ele le so as paginas desse indice e NAO toca os dados da tabela — ou seja,
 * nao derrota o lazy loading do que o trafego real vai buscar.
 *
 * `FORCE INDEX (PRIMARY)` obriga a varredura do indice agrupado, que E a tabela: puxa todas
 * as paginas de dado do S3 para o EBS e para o buffer pool.
 *
 * Env: SONDA_HOST
 */
$m = new mysqli(getenv("SONDA_HOST"), getenv("WORDPRESS_DB_USER"),
                getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"));
if ($m->connect_errno) { echo "ERRO: " . $m->connect_error . "\n"; exit(1); }

function bp($m) {
    $r = $m->query("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_bytes_data'");
    return (float) $r->fetch_row()[1];
}
function reads($m) {
    $r = $m->query("SHOW GLOBAL STATUS LIKE 'Innodb_buffer_pool_reads'");
    return (float) $r->fetch_row()[1];
}

// As maiores por tamanho em disco, lidas do proprio banco — sem lista fixa.
$tab = [];
$r = $m->query("SELECT TABLE_NAME, ROUND((DATA_LENGTH+INDEX_LENGTH)/1024/1024,1) mb
                  FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE='BASE TABLE'
                   AND (DATA_LENGTH+INDEX_LENGTH) > 1*1024*1024
                 ORDER BY (DATA_LENGTH+INDEX_LENGTH) DESC");
while ($x = $r->fetch_row()) { $tab[$x[0]] = $x[1]; }

printf("%-30s %10s %10s %14s\n", "tabela", "MB disco", "tempo", "pool depois");
$ini = bp($m); $r0 = reads($m);
foreach ($tab as $t => $mb) {
    $t0 = microtime(true);
    $q = $m->query("SELECT COUNT(*) FROM `$t` FORCE INDEX (PRIMARY)");
    if ($q === false) { // sem PRIMARY: varre do jeito que der
        $q = $m->query("SELECT COUNT(*) FROM `$t`");
    }
    $s = microtime(true) - $t0;
    printf("%-30s %10s %9.1fs %11.3f GiB\n", $t, $mb, $s, bp($m) / 1073741824);
}
$fim = bp($m); $r1 = reads($m);
printf("\npool: %.3f -> %.3f GiB  (%+.3f)\n", $ini/1073741824, $fim/1073741824, ($fim-$ini)/1073741824);
printf("reads fisicos na passada: %s\n", number_format($r1 - $r0));
$sz = (float) $m->query("SELECT @@innodb_buffer_pool_size")->fetch_row()[0];
printf("ocupacao do pool: %.1f%% de %.2f GiB\n", 100*$fim/$sz, $sz/1073741824);
$disco = (float) $m->query("SELECT SUM(FILE_SIZE) FROM information_schema.INNODB_TABLESPACES
                             WHERE NAME LIKE CONCAT(DATABASE(),'/%')")->fetch_row()[0];
printf("dado vivo em disco: %.3f GiB  ->  residente: %.1f%%\n", $disco/1073741824, 100*$fim/$disco);
