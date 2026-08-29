<?php
/**
 * Fotografia do banco, para comparar antes e depois de uma subida de versao.
 * Somente leitura. Saida estavel e ordenada, para `diff` direto.
 * Env: WORDPRESS_DB_* do pod; SONDA_HOST sobrescreve o host.
 */
$host = getenv("SONDA_HOST") ?: getenv("WORDPRESS_DB_HOST");
$m = new mysqli($host, getenv("WORDPRESS_DB_USER"), getenv("WORDPRESS_DB_PASSWORD"), getenv("WORDPRESS_DB_NAME"));
if ($m->connect_errno) { echo "ERRO: " . $m->connect_error . "\n"; exit(1); }

echo "# host=$host\n";
echo "# schema=" . $m->query("SELECT DATABASE()")->fetch_row()[0] . "\n";
echo "# siteurl=" . $m->query("SELECT option_value FROM wp_options WHERE option_name='siteurl'")->fetch_row()[0] . "\n";
// A versao NAO entra na comparacao: e a unica coisa que deve mudar.

echo "\n## tabelas e contagem exata\n";
$tabelas = [];
$r = $m->query("SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME");
while ($x = $r->fetch_row()) { $tabelas[] = $x[0]; }
foreach ($tabelas as $t) {
    $n = $m->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0];
    printf("%-40s %s\n", $t, $n);
}

echo "\n## marcos por tabela (MAX do auto_increment)\n";
$marcos = ["wp_posts"=>"ID","wp_postmeta"=>"meta_id","wp_options"=>"option_id",
           "wp_terms"=>"term_id","wp_users"=>"ID","wp_comments"=>"comment_ID",
           "wp_bahia_search_idx"=>"ID","wp_yoast_indexable"=>"id","wp_as3cf_items"=>"id"];
foreach ($marcos as $t => $c) {
    $r = @$m->query("SELECT MAX(`$c`) FROM `$t`");
    printf("%-40s %s\n", "$t.$c", $r ? ($r->fetch_row()[0] ?? "NULL") : "(n/d)");
}

echo "\n## indices FULLTEXT\n";
$r = $m->query("SELECT TABLE_NAME, INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
                  FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA=DATABASE() AND INDEX_TYPE='FULLTEXT'
                 GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME, INDEX_NAME");
while ($x = $r->fetch_row()) { printf("%-30s %-20s %s\n", $x[0], $x[1], $x[2]); }

echo "\n## usuarios e plugin de autenticacao\n";
$r = $m->query("SELECT user, host, plugin FROM mysql.user ORDER BY user, host");
while ($x = $r->fetch_row()) { printf("%-24s %-16s %s\n", $x[0], $x[1], $x[2]); }

echo "\n## contagem de posts publicados por tipo\n";
$r = $m->query("SELECT post_type, COUNT(*) FROM wp_posts WHERE post_status='publish'
                 GROUP BY post_type ORDER BY post_type");
while ($x = $r->fetch_row()) { printf("%-30s %s\n", $x[0], $x[1]); }
