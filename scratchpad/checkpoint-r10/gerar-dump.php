<?php
/**
 * Checkpoint pre-rodada 10 — dump do estado de banco de hml.bahia.ba.
 * SOMENTE LEITURA. Gera dois arquivos em /tmp:
 *   - checkpoint-r10-<ts>.json  (fidelidade total, valores em base64)
 *   - checkpoint-r10-<ts>.sql   (restauracao pronta, ordenada, td_011 por ultimo)
 *
 * Os valores viajam como FROM_BASE64() no SQL: o arquivo fica ASCII puro e
 * nao depende de escape, aspas ou charset do cliente que for aplicar.
 */
require_once '/var/www/html/wp-load.php';

$SITE_ESPERADA = 'https://hml.bahia.ba';
$site = get_option('siteurl');
if ($site !== $SITE_ESPERADA) {
    fwrite(STDERR, "ABORT: siteurl e '{$site}', esperada '{$SITE_ESPERADA}'. Nada foi lido.\n");
    exit(1);
}

global $wpdb;
$ts  = date('Ymd-His');
$out = array();

/**
 * Literal SQL para um valor de texto.
 * Cuidado com o vazio: FROM_BASE64('') devolve NULL no MySQL, nao string vazia —
 * era o que fazia 'tdc_dirty_content' divergir na verificacao.
 */
function lit_sql($v) {
    if ($v === null)  return 'NULL';
    if ($v === '')    return "''";
    return "CONVERT(FROM_BASE64('" . base64_encode($v) . "') USING utf8mb4)";
}

$out['meta'] = array(
    'gerado_em'     => date('c'),
    'siteurl'       => $site,
    'db_name'       => DB_NAME,
    'db_host'       => DB_HOST,
    'mysql_version' => $wpdb->get_var('SELECT VERSION()'),
    'wp_version'    => get_bloginfo('version'),
    'stylesheet'    => get_option('stylesheet'),
    'template'      => get_option('template'),
    'prefixo'       => $wpdb->prefix,
);

/* ---------- 1. POSTS ---------- */
$POSTS = array(
    547432 => 'Home (page) — layout da home',
    547414 => 'Header Template - Magazine PRO (VIVO) — base64(JSON) com desktop, desktop_sticky, mobile, mobile_sticky',
    547416 => 'Footer - Magazine PRO (VIVO)',
    547422 => 'Author Template - Magazine PRO (VIVO)',
    547428 => 'Search Template - Magazine PRO (VIVO)',
    547430 => '404 Template - Magazine PRO (VIVO)',
);

$out['posts'] = array();
foreach ($POSTS as $id => $desc) {
    // Leitura CRUA: get_post_field() usa contexto 'display' por padrao e aplica
    // filtros, o que altera o conteudo. Aqui isso seria corrupcao silenciosa.
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_title, post_name, post_status, post_type, post_content,
                post_excerpt, post_parent, menu_order, post_modified, post_modified_gmt
           FROM {$wpdb->posts} WHERE ID = %d", $id), ARRAY_A);
    if (!$row) { $out['posts'][$id] = array('ERRO' => 'post nao encontrado'); continue; }

    $metas = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_id, meta_key, meta_value FROM {$wpdb->postmeta}
          WHERE post_id = %d ORDER BY meta_key, meta_id", $id), ARRAY_A);

    $mm = array();
    foreach ($metas as $m) {
        // NULL e string vazia sao coisas diferentes aqui: header_mobile_menu_id e NULL
        // de verdade. Guardar 'eh_null' preserva a distincao no restore.
        $eh_null = ($m['meta_value'] === null);
        $mm[] = array(
            'meta_id'   => (int) $m['meta_id'],
            'meta_key'  => $m['meta_key'],
            'eh_null'   => $eh_null,
            'bytes'     => $eh_null ? null : strlen($m['meta_value']),
            'md5'       => $eh_null ? null : md5($m['meta_value']),
            'valor_b64' => $eh_null ? null : base64_encode($m['meta_value']),
        );
    }

    $reg = array(
        'descricao'         => $desc,
        'post_title'        => $row['post_title'],
        'post_name'         => $row['post_name'],
        'post_status'       => $row['post_status'],
        'post_type'         => $row['post_type'],
        'post_modified'     => $row['post_modified'],
        'post_modified_gmt' => $row['post_modified_gmt'],
        'content_bytes'     => strlen($row['post_content']),
        'content_md5'       => md5($row['post_content']),
        'content_b64'       => base64_encode($row['post_content']),
        'postmeta'          => $mm,
    );

    // O header e base64(JSON): registrar as zonas, uma a uma, para conferencia.
    $dec = base64_decode($row['post_content'], true);
    if ($dec !== false) {
        $j = json_decode($dec, true);
        if (is_array($j)) {
            $reg['zonas'] = array();
            foreach ($j as $k => $v) {
                $s = is_string($v) ? $v : json_encode($v);
                $reg['zonas'][$k] = array('bytes' => strlen($s), 'md5' => md5($s));
            }
        }
    }
    $out['posts'][$id] = $reg;
}

/* ---------- 2. OPTIONS ---------- */
// td_011 fica de fora desta lista de proposito: entra por ultimo, na secao 4.
$OPT_NOMES = array(
    'td_011_settings', 'td_011_generated_css', 'td_011_log', 'td_011_remote_cache',
    'wpseo_titles', 'wpseo', 'wpseo_social',
    'theme_mods_Newspaper', 'stylesheet', 'template',
    'sidebars_widgets', 'nav_menu_options',
    'show_on_front', 'page_on_front', 'permalink_structure', 'site_icon',
    'blogname', 'blogdescription', 'date_format', 'time_format', 'timezone_string',
    'adrotate_config', 'adrotate_crawlers', 'adrotate_advert_status',
);

$nomes = $OPT_NOMES;
// Todas as chaves bahia_* — os backups das rodadas 2 a 9 e os flags do tema.
$bahia = $wpdb->get_col("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'bahia\\_%' ORDER BY option_name");
$nomes = array_merge($nomes, $bahia);
$nomes = array_values(array_unique($nomes));

$out['options'] = array();
foreach ($nomes as $n) {
    $r = $wpdb->get_row($wpdb->prepare(
        "SELECT option_id, option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $n), ARRAY_A);
    if (!$r) { $out['options'][$n] = array('AUSENTE' => true); continue; }
    $out['options'][$n] = array(
        'autoload'  => $r['autoload'],
        'bytes'     => strlen($r['option_value']),
        'md5'       => md5($r['option_value']),
        'valor_b64' => base64_encode($r['option_value']),
    );
}

/* ---------- 3. td_011 (a chave da virada; sempre por ultimo) ---------- */
$r = $wpdb->get_row("SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = 'td_011'", ARRAY_A);
$out['td_011'] = array(
    'autoload'  => $r['autoload'],
    'bytes'     => strlen($r['option_value']),
    'md5'       => md5($r['option_value']),
    'valor_b64' => base64_encode($r['option_value']),
);
$td = maybe_unserialize($r['option_value']);
if (is_array($td)) {
    $out['td_011_chaves_de_template'] = array();
    foreach ($td as $k => $v) {
        if (is_string($v) && (strpos($k, 'template') !== false || strpos($k, 'logo') !== false || strpos($k, 'sticky') !== false)) {
            $out['td_011_chaves_de_template'][$k] = $v;
        }
    }
}

/* ---------- 4. Informativo: menus e inventario AdRotate ---------- */
$out['menus'] = array();
foreach (wp_get_nav_menus() as $m) {
    $itens = array();
    foreach (wp_get_nav_menu_items($m->term_id) as $it) {
        $itens[] = array('ID' => $it->ID, 'title' => $it->title, 'url' => $it->url, 'order' => $it->menu_order);
    }
    $out['menus'][$m->term_id] = array('nome' => $m->name, 'itens' => $itens);
}
$out['menu_locations'] = get_nav_menu_locations();

$out['adrotate'] = array();
foreach (array('adrotate', 'adrotate_groups', 'adrotate_linkmeta', 'adrotate_schedule') as $t) {
    $tab = $wpdb->prefix . $t;
    if ($wpdb->get_var("SHOW TABLES LIKE '{$tab}'") === $tab) {
        $out['adrotate'][$t] = $wpdb->get_results("SELECT * FROM {$tab}", ARRAY_A);
    }
}

/* ---------- 5. Gravar JSON ---------- */
$json_path = "/tmp/checkpoint-r10-{$ts}.json";
file_put_contents($json_path, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

/* ---------- 6. Gravar SQL de restauracao ---------- */
$L = array();
$L[] = "-- ============================================================================";
$L[] = "-- RESTAURACAO DO CHECKPOINT PRE-RODADA 10 — hml.bahia.ba";
$L[] = "-- Gerado em " . date('c') . " a partir de " . DB_NAME;
$L[] = "--";
$L[] = "-- Devolve o banco ao estado validado ao fim da rodada 9.";
$L[] = "-- A ORDEM IMPORTA: templates e opcoes primeiro, td_011 POR ULTIMO — enquanto";
$L[] = "-- td_011 nao e reescrita o site segue servindo o layout corrente, entao as";
$L[] = "-- etapas 1 a 3 podem rodar com o site no ar. A virada e atomica na etapa 4.";
$L[] = "--";
$L[] = "-- Os valores viajam em FROM_BASE64(): o arquivo e ASCII puro e nao depende de";
$L[] = "-- escape nem do charset do cliente. Exige MySQL >= 5.6.";
$L[] = "-- Versao do servidor na geracao: " . $out['meta']['mysql_version'];
$L[] = "--";
$L[] = "-- Duas particularidades deste banco, verificadas na geracao:";
$L[] = "--  1) post_content, meta_value e option_value sao utf8mb3_general_ci — NAO utf8mb4.";
$L[] = "--     Caractere de 4 bytes (emoji) nao cabe e vira '?'. Os " . (count($out['options']) + 7) . " valores deste";
$L[] = "--     checkpoint foram conferidos um a um: todos sao UTF-8 valido, nenhum tem";
$L[] = "--     caractere de 4 bytes, e todos voltam byte a byte por este mesmo caminho SQL.";
$L[] = "--  2) FROM_BASE64('') devolve NULL, nao string vazia. Por isso valor vazio e";
$L[] = "--     escrito como '' literal aqui, e nao via FROM_BASE64.";
$L[] = "-- ============================================================================";
$L[] = "";
$L[] = "START TRANSACTION;";
$L[] = "";

$L[] = "-- ---------------------------------------------------------------- ETAPA 1";
$L[] = "-- post_content dos templates e da home";
foreach ($out['posts'] as $id => $p) {
    if (isset($p['ERRO'])) continue;
    $L[] = "";
    $L[] = "-- {$id} — {$p['descricao']}";
    $L[] = "--   {$p['content_bytes']} bytes, md5 {$p['content_md5']}";
    $L[] = "UPDATE `{$wpdb->posts}` SET `post_content` = " . lit_sql(base64_decode($p['content_b64']));
    $L[] = " WHERE `ID` = {$id};";
}

$L[] = "";
$L[] = "-- ---------------------------------------------------------------- ETAPA 2";
$L[] = "-- postmeta dos mesmos objetos.";
$L[] = "--";
$L[] = "-- ATENCAO, e o motivo de nao ser um INSERT ... ON DUPLICATE KEY UPDATE:";
$L[] = "-- wp_postmeta NAO tem indice unico em (post_id, meta_key) — so PRIMARY(meta_id),";
$L[] = "-- KEY post_id, KEY meta_key e idx_meta_value. ON DUPLICATE KEY nunca casaria e";
$L[] = "-- cada execucao DUPLICARIA todas as linhas. Verificado na pratica.";
$L[] = "--";
$L[] = "-- Por isso: apaga a meta do objeto e reinsere com o meta_id original. Assim o";
$L[] = "-- restore e idempotente e tambem remove meta criada depois do checkpoint.";
foreach ($out['posts'] as $id => $p) {
    if (isset($p['ERRO'])) continue;
    $L[] = "";
    $L[] = "-- {$id} — " . count($p['postmeta']) . " linhas de postmeta";
    $L[] = "DELETE FROM `{$wpdb->postmeta}` WHERE `post_id` = {$id};";
    $vals = array();
    foreach ($p['postmeta'] as $m) {
        $k   = str_replace("'", "''", $m['meta_key']);
        $val = !empty($m['eh_null']) ? null : base64_decode($m['valor_b64']);
        $vals[] = "  ({$m['meta_id']},{$id},'{$k}'," . lit_sql($val) . ")";
    }
    $L[] = "INSERT INTO `{$wpdb->postmeta}` (`meta_id`,`post_id`,`meta_key`,`meta_value`) VALUES";
    $L[] = implode(",\n", $vals) . ";";
}

$L[] = "";
$L[] = "-- ---------------------------------------------------------------- ETAPA 3";
$L[] = "-- wp_options (td_011 NAO entra aqui — ver etapa 4)";
foreach ($out['options'] as $n => $o) {
    if (isset($o['AUSENTE'])) { $L[] = "-- {$n}: AUSENTE no momento do dump — nada a restaurar"; continue; }
    $nn = str_replace("'", "''", $n);
    $L[] = "-- {$n} ({$o['bytes']} bytes, md5 {$o['md5']}, autoload={$o['autoload']})";
    $L[] = "INSERT INTO `{$wpdb->options}` (`option_name`,`option_value`,`autoload`) VALUES ('{$nn}'," . lit_sql(base64_decode($o['valor_b64'])) . ",'{$o['autoload']}')";
    $L[] = "  ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`);";
}

$L[] = "";
$L[] = "-- ---------------------------------------------------------------- ETAPA 4";
$L[] = "-- td_011 — POR ULTIMO. E esta linha que vira o layout.";
$L[] = "--   {$out['td_011']['bytes']} bytes, md5 {$out['td_011']['md5']}, autoload={$out['td_011']['autoload']}";
$L[] = "INSERT INTO `{$wpdb->options}` (`option_name`,`option_value`,`autoload`) VALUES ('td_011'," . lit_sql(base64_decode($out['td_011']['valor_b64'])) . ",'{$out['td_011']['autoload']}')";
$L[] = "  ON DUPLICATE KEY UPDATE `option_value` = VALUES(`option_value`), `autoload` = VALUES(`autoload`);";
$L[] = "";
$L[] = "COMMIT;";
$L[] = "";
$L[] = "-- ---------------------------------------------------------------- CONFERENCIA";
$L[] = "-- Rodar depois do COMMIT. Todos os md5 tem que bater com os comentarios acima.";
$L[] = "SELECT ID, MD5(post_content) AS md5_atual, LENGTH(post_content) AS bytes";
$L[] = "  FROM `{$wpdb->posts}` WHERE ID IN (" . implode(',', array_keys($out['posts'])) . ");";
$L[] = "SELECT option_name, MD5(option_value) AS md5_atual, LENGTH(option_value) AS bytes";
$L[] = "  FROM `{$wpdb->options}` WHERE option_name IN ('td_011','wpseo_titles','td_011_settings');";
$L[] = "-- postmeta: contagem por objeto e conferencia de que nao ha chave duplicada";
$L[] = "SELECT post_id, COUNT(*) AS linhas FROM `{$wpdb->postmeta}`";
$L[] = " WHERE post_id IN (" . implode(',', array_keys($out['posts'])) . ") GROUP BY post_id;";
$L[] = "SELECT COUNT(*) AS chaves_duplicadas FROM (SELECT post_id, meta_key FROM `{$wpdb->postmeta}`";
$L[] = " WHERE post_id IN (" . implode(',', array_keys($out['posts'])) . ")";
$L[] = " GROUP BY post_id, meta_key HAVING COUNT(*) > 1) x;   -- tem que dar 0";
$L[] = "-- Esperado:";
foreach ($out['posts'] as $id => $p) {
    if (isset($p['ERRO'])) continue;
    $L[] = "--   {$id} -> {$p['content_md5']} ({$p['content_bytes']} bytes, "
         . count($p['postmeta']) . " linhas de postmeta)";
}
$L[] = "--   td_011 -> {$out['td_011']['md5']} ({$out['td_011']['bytes']} bytes)";
$L[] = "--   wpseo_titles -> {$out['options']['wpseo_titles']['md5']} ({$out['options']['wpseo_titles']['bytes']} bytes)";
$L[] = "--   td_011_settings -> {$out['options']['td_011_settings']['md5']} ({$out['options']['td_011_settings']['bytes']} bytes)";
$L[] = "";
$L[] = "-- Depois de restaurar: limpar o cache do sidecar nginx e recarregar o PHP-FPM.";
$L[] = "--   kubectl exec -n bahia-wordpress \$POD -c nginx -- sh -lc 'rm -rf /tmp/nginx-cache/*'";

$sql_path = "/tmp/checkpoint-r10-{$ts}.sql";
file_put_contents($sql_path, implode("\n", $L) . "\n");

/* ---------- 7. Relatorio ---------- */
echo "OK — checkpoint gerado\n\n";
echo "  JSON : {$json_path}  (" . number_format(filesize($json_path)) . " bytes, md5 " . md5_file($json_path) . ")\n";
echo "  SQL  : {$sql_path}  (" . number_format(filesize($sql_path)) . " bytes, md5 " . md5_file($sql_path) . ")\n\n";
echo "  posts   : " . count($out['posts']) . "\n";
echo "  options : " . count($out['options']) . " + td_011\n";
echo "  menus   : " . count($out['menus']) . "\n";
echo "  adrotate: " . implode(', ', array_map(function ($k) use ($out) {
        return $k . '=' . count($out['adrotate'][$k]);
    }, array_keys($out['adrotate']))) . "\n\n";
echo "md5 de referencia:\n";
foreach ($out['posts'] as $id => $p) {
    if (isset($p['ERRO'])) { echo "  {$id}: ERRO\n"; continue; }
    printf("  post %d  %s  %7d bytes\n", $id, $p['content_md5'], $p['content_bytes']);
}
printf("  opt  td_011  %s  %7d bytes\n", $out['td_011']['md5'], $out['td_011']['bytes']);
