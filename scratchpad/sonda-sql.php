<?php
/**
 * Sonda SQL — mede o BANCO, sem PHP do WordPress, sem nginx e sem FastCGI cache.
 * UPGRADE-MYSQL.md §1.6. Um processo = uma conexao. A concorrencia vem do driver,
 * que dispara N destes em paralelo.
 *
 * Env: SONDA_HOST SONDA_USER SONDA_PASS SONDA_DB SONDA_ROUNDS SONDA_ID
 * Saida (stdout), uma linha por consulta:  <classe>\t<ms>\t<linhas>
 */
$err = fopen("php://stderr", "w");
$host   = getenv("SONDA_HOST");
$rounds = (int) (getenv("SONDA_ROUNDS") ?: 3);
$id     = getenv("SONDA_ID") ?: "0";

$m = new mysqli($host, getenv("SONDA_USER"), getenv("SONDA_PASS"), getenv("SONDA_DB"));
if ($m->connect_errno) { fwrite($err, "w$id ERRO conexao: " . $m->connect_error . "\n"); exit(1); }
$m->set_charset("utf8mb4");

// Os 10 termos do carga.sh, na forma booleana que o mu-plugin gera: +termo*
$termos = ["bahia","salvador","carnaval","eleicao","praia","lula","chuva","festa","saude","escola"];
// As 10 editorias do carga.sh.
$editorias = ["politica","salvador","esporte","entretenimento","economia",
              "municipios","justica","mundo","bahia","brasil"];

// Tipos indexados, lidos do proprio banco para nao inventar lista.
$tipos = [];
$r = $m->query("SELECT post_type, COUNT(*) c FROM wp_posts WHERE post_status='publish'
                GROUP BY post_type HAVING c > 100 ORDER BY c DESC LIMIT 12");
while ($x = $r->fetch_row()) { $tipos[] = $x[0]; }
$lista = "'" . implode("','", array_map([$m, "real_escape_string"], $tipos)) . "'";

function cronometra($m, $classe, $sql) {
    $t0 = microtime(true);
    $res = $m->query($sql);
    $ms = (microtime(true) - $t0) * 1000;
    if ($res === false) { printf("%s\tERRO\t%s\n", $classe, $m->errno); return; }
    $n = $res->num_rows;
    $res->free();
    printf("%s\t%.2f\t%d\n", $classe, $ms, $n);
}

for ($r = 0; $r < $rounds; $r++) {
    // --- classe BUSCA: o MATCH real na tabela-sombra, teto de 500 como o mu-plugin ---
    foreach ($termos as $t) {
        $b = $m->real_escape_string("+" . $t . "*");
        cronometra($m, "busca", "
            SELECT p.ID FROM wp_posts p WHERE p.ID IN (
              SELECT bahia_ft_id FROM (
                SELECT s.ID AS bahia_ft_id FROM wp_bahia_search_idx s
                 WHERE MATCH(s.post_title, s.post_excerpt) AGAINST ('$b' IN BOOLEAN MODE)
                 ORDER BY s.post_date DESC LIMIT 500
              ) sub
            ) ORDER BY p.post_date DESC LIMIT 10");
    }
    // --- classe ARCHIVE: primeira pagina de cada editoria ---
    foreach ($editorias as $e) {
        $ee = $m->real_escape_string($e);
        cronometra($m, "archive", "
            SELECT p.ID, p.post_title, p.post_date FROM wp_posts p
             WHERE p.post_type = '$ee' AND p.post_status = 'publish'
             ORDER BY p.post_date DESC LIMIT 0, 10");
    }
    // --- classe HOME: os blocos, com o meta da imagem do ACF ---
    cronometra($m, "home", "
        SELECT p.ID, p.post_title, p.post_date, mi.meta_value AS imagem
          FROM wp_posts p
          LEFT JOIN wp_postmeta mi ON mi.post_id = p.ID AND mi.meta_key = 'imagem'
         WHERE p.post_type IN ($lista) AND p.post_status = 'publish'
         ORDER BY p.post_date DESC LIMIT 0, 15");
    // --- classe CONTAGEM: o COUNT que alimenta a paginacao ---
    cronometra($m, "contagem", "
        SELECT COUNT(*) FROM wp_posts
         WHERE post_type = 'politica' AND post_status = 'publish'");
}
fwrite($err, "w$id ok\n");
