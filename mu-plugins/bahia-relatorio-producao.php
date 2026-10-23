<?php
/**
 * Plugin Name: Bahia.ba - Relatório de produção por repórter (KAN-20)
 * Description: Painel > Relatórios > Produção por repórter. Quantas matérias cada
 *              pessoa publicou por dia num período, com total por pessoa, total de
 *              matérias por dia e exportação em CSV. Só para administradores
 *              (manage_options).
 *
 *              AUTORIA = a mesma regra da página de autor (bahia-autor-archive.php):
 *              vale o termo `author` do Co-Authors Plus; o post_author só conta em
 *              matéria que não tem termo `author` nenhum (o fallback do próprio CAP,
 *              resíduo da importação). Coautoria conta 1 para CADA autor — por isso a
 *              linha "Matérias publicadas" (matérias distintas) pode ser menor que a soma
 *              das pessoas. Autores convidados do CAP entram como qualquer autor.
 *
 *              DESEMPENHO: wp_posts tem ~435 mil linhas; as três consultas começam pelo
 *              índice type_status_date (post_type, post_status, post_date), então só o
 *              período pedido é lido. Nada de varrer a tabela, nada de N+1 por autor.
 *
 *              O relatório antigo "Notícias por dia" (plugin relatorios_bahiaba) fica
 *              como está; este não depende dele — só se pendura no mesmo menu quando ele
 *              existe.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_RP_SLUG', 'bahia-producao-reporter');
define('BAHIA_RP_MAX_DIAS', 366);

/* -------------------------------------------------------------------------
 *  DADOS
 * ------------------------------------------------------------------------- */

/** Post types contados: os mesmos da página de autor. */
function bahia_rp_post_types() {
    if (function_exists('bahia_autor_archive_post_types')) {
        return bahia_autor_archive_post_types();
    }
    return array('post');
}

/**
 * Período válido a partir de duas datas Y-m-d (strings do formulário).
 * Padrão: os últimos 7 dias, hoje incluído, no fuso do WordPress.
 * Devolve [inicio, fim] como Y-m-d, com inicio <= fim e no máximo BAHIA_RP_MAX_DIAS.
 */
function bahia_rp_periodo($ini = '', $fim = '') {
    $tz   = wp_timezone();
    $hoje = new DateTimeImmutable('today', $tz);
    $f = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $fim, $tz) ?: $hoje;
    $i = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $ini, $tz) ?: $f->modify('-6 days');
    if ($i > $f) {
        list($i, $f) = array($f, $i);
    }
    if ((int) $i->diff($f)->days + 1 > BAHIA_RP_MAX_DIAS) {
        $i = $f->modify('-' . (BAHIA_RP_MAX_DIAS - 1) . ' days');
    }
    return array($i->format('Y-m-d'), $f->format('Y-m-d'));
}

/**
 * Produção no período.
 *
 * Devolve array(
 *   'dias'    => ['Y-m-d', ...]                (todos os dias do período, em ordem)
 *   'autores' => [chave => ['nome', 'url', 'tipo', 'dias' => [dia => n], 'total']]
 *                ordenado pelo total, decrescente
 *   'materias_dia' => [dia => n]               (matérias distintas por dia)
 *   'materias'     => n                        (matérias distintas no período)
 *   'tempo'        => segundos gastos nas consultas
 * )
 */
function bahia_rp_dados($ini, $fim) {
    global $wpdb;
    $t0 = microtime(true);

    $tipos = bahia_rp_post_types();
    $in    = implode(',', array_map(function ($t) use ($wpdb) {
        return $wpdb->prepare('%s', $t);
    }, $tipos));
    $de  = $ini . ' 00:00:00';
    $ate = $fim . ' 23:59:59';

    // Base comum às três consultas: só o período, pelo índice type_status_date.
    $base = $wpdb->prepare(
        "p.post_status = 'publish' AND p.post_type IN ($in) AND p.post_date BETWEEN %s AND %s",
        $de, $ate
    );

    // 1) Autoria pelo termo `author` do Co-Authors Plus (coautoria inclusa).
    $por_termo = $wpdb->get_results(
        "SELECT DATE(p.post_date) AS dia, tt.term_id, COUNT(*) AS n
           FROM {$wpdb->posts} p
           JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
           JOIN {$wpdb->term_taxonomy} tt
             ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'author'
          WHERE $base
          GROUP BY dia, tt.term_id"
    );

    // 2) Matérias SEM termo `author` nenhum: vale o post_author (fallback do CAP).
    $por_nativo = $wpdb->get_results(
        "SELECT DATE(p.post_date) AS dia, p.post_author, COUNT(*) AS n
           FROM {$wpdb->posts} p
          WHERE $base
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                  JOIN {$wpdb->term_taxonomy} tt
                    ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'author'
                 WHERE tr.object_id = p.ID
            )
          GROUP BY dia, p.post_author"
    );

    // 3) Matérias distintas por dia (a coautoria não duplica aqui).
    $distintas = $wpdb->get_results(
        "SELECT DATE(p.post_date) AS dia, COUNT(*) AS n
           FROM {$wpdb->posts} p
          WHERE $base
          GROUP BY dia"
    );

    $tempo = microtime(true) - $t0;

    // Dias do período, mesmo os sem matéria.
    $dias = array();
    $d = new DateTimeImmutable($ini);
    $f = new DateTimeImmutable($fim);
    while ($d <= $f) {
        $dias[] = $d->format('Y-m-d');
        $d = $d->modify('+1 day');
    }

    $autores = array();
    $soma = function ($chave, $info, $dia, $n) use (&$autores) {
        if (!isset($autores[$chave])) {
            $autores[$chave] = $info + array('dias' => array(), 'total' => 0);
        }
        $autores[$chave]['dias'][$dia] = (isset($autores[$chave]['dias'][$dia]) ? $autores[$chave]['dias'][$dia] : 0) + (int) $n;
        $autores[$chave]['total'] += (int) $n;
    };

    foreach ($por_termo as $r) {
        $info  = bahia_rp_autor_do_termo((int) $r->term_id);
        $soma($info['chave'], $info, $r->dia, $r->n);
    }
    foreach ($por_nativo as $r) {
        $info = bahia_rp_autor_usuario((int) $r->post_author);
        $soma($info['chave'], $info, $r->dia, $r->n);
    }

    uasort($autores, function ($a, $b) {
        if ($a['total'] !== $b['total']) {
            return $b['total'] - $a['total'];
        }
        return strcasecmp(remove_accents($a['nome']), remove_accents($b['nome']));
    });

    $materias_dia = array();
    $materias = 0;
    foreach ($distintas as $r) {
        $materias_dia[$r->dia] = (int) $r->n;
        $materias += (int) $r->n;
    }

    return compact('dias', 'autores', 'materias_dia', 'materias', 'tempo');
}

/** Identidade de um usuário do WordPress (chave única com a do termo dele). */
function bahia_rp_autor_usuario($user_id) {
    static $memo = array();
    if (isset($memo[$user_id])) {
        return $memo[$user_id];
    }
    $u = $user_id ? get_userdata($user_id) : false;
    return $memo[$user_id] = array(
        'chave' => 'u' . (int) $user_id,
        'nome'  => $u ? $u->display_name : sprintf('Usuário #%d (removido)', $user_id),
        'url'   => $u ? get_author_posts_url($u->ID, $u->user_nicename) : '',
        'tipo'  => 'usuário',
    );
}

/**
 * Identidade por trás de um termo `author` do CAP ("cap-<nicename>"): o usuário, se
 * houver, ou o autor convidado. Usuário e termo dele caem na MESMA chave, para que as
 * duas consultas somem na mesma linha.
 */
function bahia_rp_autor_do_termo($term_id) {
    static $memo = array();
    if (isset($memo[$term_id])) {
        return $memo[$term_id];
    }
    $termo = get_term($term_id, 'author');
    $nicename = ($termo && !is_wp_error($termo)) ? preg_replace('/^cap-/', '', $termo->slug) : '';

    $u = $nicename !== '' ? get_user_by('slug', $nicename) : false;
    if ($u) {
        return $memo[$term_id] = bahia_rp_autor_usuario($u->ID);
    }

    global $coauthors_plus;
    $g = null;
    if ($nicename !== '' && is_object($coauthors_plus) && isset($coauthors_plus->guest_authors)) {
        $g = $coauthors_plus->guest_authors->get_guest_author_by('user_nicename', $nicename);
    }
    if ($g) {
        return $memo[$term_id] = array(
            'chave' => 'g' . (int) $g->ID,
            'nome'  => $g->display_name,
            'url'   => get_author_posts_url(0, $g->user_nicename),
            'tipo'  => 'convidado',
        );
    }

    return $memo[$term_id] = array(
        'chave' => 't' . (int) $term_id,
        'nome'  => ($termo && !is_wp_error($termo)) ? $termo->name : sprintf('Autor #%d', $term_id),
        'url'   => '',
        'tipo'  => 'sem cadastro',
    );
}

/* -------------------------------------------------------------------------
 *  MENU E TELA
 * ------------------------------------------------------------------------- */

add_action('admin_menu', function () {
    // Pendura no menu "Relatórios" do plugin relatorios_bahiaba quando ele existe
    // (prioridade 20: o dele é registrado na 10); senão, menu próprio.
    $titulo = 'Produção por repórter';
    if (!empty($GLOBALS['admin_page_hooks']['relatorios'])) {
        add_submenu_page('relatorios', $titulo, $titulo, 'manage_options', BAHIA_RP_SLUG, 'bahia_rp_tela');
    } else {
        add_menu_page($titulo, $titulo, 'manage_options', BAHIA_RP_SLUG, 'bahia_rp_tela', 'dashicons-chart-bar', 26);
    }
}, 20);

function bahia_rp_tela() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Sem permissão.'));
    }
    list($ini, $fim) = bahia_rp_periodo(
        isset($_GET['inicio']) ? sanitize_text_field(wp_unslash($_GET['inicio'])) : '',
        isset($_GET['fim']) ? sanitize_text_field(wp_unslash($_GET['fim'])) : ''
    );
    $dados = bahia_rp_dados($ini, $fim);

    $csv = wp_nonce_url(
        add_query_arg(array('action' => 'bahia_rp_csv', 'inicio' => $ini, 'fim' => $fim), admin_url('admin-post.php')),
        'bahia_rp_csv'
    );
    $fmt = function ($dia) {
        return date_i18n('d/m', strtotime($dia));
    };
    $sem = function ($dia) {
        return date_i18n('D', strtotime($dia));
    };
    ?>
    <div class="wrap bahia-rp">
        <h1>Produção por repórter</h1>
        <p class="description">Matérias publicadas por pessoa, por dia. Coautoria conta para cada autor; por isso a soma das pessoas pode passar do total de matérias.</p>

        <form method="get" class="bahia-rp-filtro">
            <input type="hidden" name="page" value="<?php echo esc_attr(BAHIA_RP_SLUG); ?>">
            <label>De <input type="date" name="inicio" value="<?php echo esc_attr($ini); ?>"></label>
            <label>até <input type="date" name="fim" value="<?php echo esc_attr($fim); ?>"></label>
            <button type="submit" class="button button-primary">Ver</button>
            <a class="button" href="<?php echo esc_url($csv); ?>">Exportar CSV</a>
        </form>

        <p><strong><?php echo number_format_i18n($dados['materias']); ?></strong> matérias publicadas
            de <?php echo esc_html(date_i18n('d/m/Y', strtotime($ini))); ?>
            a <?php echo esc_html(date_i18n('d/m/Y', strtotime($fim))); ?>,
            por <strong><?php echo number_format_i18n(count($dados['autores'])); ?></strong> pessoas.</p>

        <?php if (empty($dados['autores'])) : ?>
            <p>Nenhuma matéria publicada no período.</p>
        <?php else : ?>
        <div class="bahia-rp-rolagem">
        <table class="widefat striped bahia-rp-tabela">
            <thead>
                <tr>
                    <th class="bahia-rp-nome">Repórter</th>
                    <th class="num bahia-rp-total">Total</th>
                    <?php foreach ($dados['dias'] as $dia) : ?>
                        <th class="num" title="<?php echo esc_attr(date_i18n('l, d/m/Y', strtotime($dia))); ?>"><?php echo esc_html($fmt($dia)); ?><br><small><?php echo esc_html($sem($dia)); ?></small></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($dados['autores'] as $a) : ?>
                <tr>
                    <td class="bahia-rp-nome">
                        <?php if ($a['url']) : ?><a href="<?php echo esc_url($a['url']); ?>" target="_blank" rel="noopener"><?php echo esc_html($a['nome']); ?></a><?php else : echo esc_html($a['nome']); endif; ?>
                        <?php if ($a['tipo'] !== 'usuário') : ?><span class="bahia-rp-tipo"><?php echo esc_html($a['tipo']); ?></span><?php endif; ?>
                    </td>
                    <td class="num bahia-rp-total"><?php echo (int) $a['total']; ?></td>
                    <?php foreach ($dados['dias'] as $dia) : $n = isset($a['dias'][$dia]) ? (int) $a['dias'][$dia] : 0; ?>
                        <td class="num<?php echo $n ? '' : ' zero'; ?>"><?php echo $n ? $n : '–'; ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th class="bahia-rp-nome">Matérias publicadas</th>
                    <th class="num bahia-rp-total"><?php echo (int) $dados['materias']; ?></th>
                    <?php foreach ($dados['dias'] as $dia) : ?>
                        <th class="num"><?php echo isset($dados['materias_dia'][$dia]) ? (int) $dados['materias_dia'][$dia] : 0; ?></th>
                    <?php endforeach; ?>
                </tr>
            </tfoot>
        </table>
        </div>
        <?php endif; ?>
    </div>
    <style>
        .bahia-rp-filtro{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin:14px 0;}
        .bahia-rp-rolagem{overflow-x:auto;max-width:100%;}
        .bahia-rp-tabela{width:auto;min-width:100%;}
        .bahia-rp-tabela th,.bahia-rp-tabela td{white-space:nowrap;}
        .bahia-rp-tabela .num{text-align:center;}
        .bahia-rp-tabela td.zero{color:#b0b0b0;}
        .bahia-rp-tabela .bahia-rp-nome{position:sticky;left:0;background:inherit;z-index:1;min-width:190px;}
        .bahia-rp-tabela thead th,.bahia-rp-tabela tfoot th{background:#f6f7f7;}
        .bahia-rp-tabela tbody tr:nth-child(odd) .bahia-rp-nome{background:#f6f7f7;}
        .bahia-rp-tabela tbody tr:nth-child(even) .bahia-rp-nome{background:#fff;}
        .bahia-rp-tabela .bahia-rp-total{font-weight:600;}
        .bahia-rp-tipo{margin-left:6px;font-size:11px;color:#757575;}
    </style>
    <?php
}

/* -------------------------------------------------------------------------
 *  CSV — pelo admin-post.php: exige login, capability e nonce.
 * ------------------------------------------------------------------------- */

add_action('admin_post_bahia_rp_csv', function () {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Sem permissão.'), 403);
    }
    check_admin_referer('bahia_rp_csv');

    list($ini, $fim) = bahia_rp_periodo(
        isset($_GET['inicio']) ? sanitize_text_field(wp_unslash($_GET['inicio'])) : '',
        isset($_GET['fim']) ? sanitize_text_field(wp_unslash($_GET['fim'])) : ''
    );
    $dados = bahia_rp_dados($ini, $fim);

    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="producao-reporteres_' . $ini . '_a_' . $fim . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM: o Excel abre os acentos certos

    $cab = array('Repórter', 'Tipo', 'Total');
    foreach ($dados['dias'] as $dia) {
        $cab[] = date('d/m/Y', strtotime($dia));
    }
    fputcsv($out, $cab, ';');

    foreach ($dados['autores'] as $a) {
        $linha = array($a['nome'], $a['tipo'], $a['total']);
        foreach ($dados['dias'] as $dia) {
            $linha[] = isset($a['dias'][$dia]) ? (int) $a['dias'][$dia] : 0;
        }
        fputcsv($out, $linha, ';');
    }

    $rod = array('Matérias publicadas (distintas)', '', $dados['materias']);
    foreach ($dados['dias'] as $dia) {
        $rod[] = isset($dados['materias_dia'][$dia]) ? (int) $dados['materias_dia'][$dia] : 0;
    }
    fputcsv($out, $rod, ';');
    fclose($out);
    exit;
});
