<?php
/**
 * API Copa do Mundo 2026
 * Serve /api_copa_mundo.php via WordPress
 * Fonte: api.football-data.org v4 (competição WC)
 */

require_once __DIR__ . '/selecoes_pt.php';

add_action('init', function () {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($request_uri !== '/api_copa_mundo.php') {
        return;
    }

    header('Content-Type: application/json');
    header('Cache-Control: max-age=1800');

    $api_token      = 'f5c2e920e49b4657b44ff0ef77c87350';
    $competition    = 'WC';
    $cache_key      = 'copa_mundo_ftb_v3';
    $stale_key      = $cache_key . '_stale';
    $cache_duration = 30 * MINUTE_IN_SECONDS;

    $cached = get_transient($cache_key);
    if ($cached !== false) {
        echo $cached;
        exit;
    }

    // --- Busca classificação (grupos) ---
    $standings_raw = wp_remote_get(
        "https://api.football-data.org/v4/competitions/{$competition}/standings",
        ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 15]
    );

    if (is_wp_error($standings_raw) || wp_remote_retrieve_response_code($standings_raw) !== 200) {
        $stale = get_option($stale_key);
        if ($stale) { echo $stale; exit; }
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar classificação da Copa. Código: ' . wp_remote_retrieve_response_code($standings_raw)]);
        exit;
    }

    $standings_data = json_decode(wp_remote_retrieve_body($standings_raw), true);

    // --- Busca jogos ---
    $matches_raw = wp_remote_get(
        "https://api.football-data.org/v4/competitions/{$competition}/matches",
        ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 15]
    );

    if (is_wp_error($matches_raw) || wp_remote_retrieve_response_code($matches_raw) !== 200) {
        $stale = get_option($stale_key);
        if ($stale) { echo $stale; exit; }
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar jogos da Copa. Código: ' . wp_remote_retrieve_response_code($matches_raw)]);
        exit;
    }

    $matches_data = json_decode(wp_remote_retrieve_body($matches_raw), true);

    // --- Monta grupos ---
    $grupos = [];
    foreach ($standings_data['standings'] ?? [] as $standing) {
        if (($standing['type'] ?? '') !== 'TOTAL') {
            continue;
        }
        $tabela = [];
        foreach ($standing['table'] ?? [] as $row) {
            $t        = $row['team'];
            $fallback = $t['shortName'] ?? $t['name'] ?? '';
            $info     = bahia_selecao_info($t['tla'] ?? '', $fallback);
            $tabela[] = [
                'id'    => $t['id'],
                'nome'  => $info['nome'],
                'sigla' => $t['tla'] ?? '',
                'flag'  => $info['flag'],
                'crest' => $t['crest'] ?? '',
                'pg'    => (int) $row['points'],
                'j'     => (int) $row['playedGames'],
                'v'     => (int) $row['won'],
                'e'     => (int) $row['draw'],
                'd'     => (int) $row['lost'],
                'gp'    => (int) $row['goalsFor'],
                'gc'    => (int) $row['goalsAgainst'],
                'sg'    => (int) $row['goalDifference'],
            ];
        }
        $grupos[] = [
            'nome'          => bahia_traduz_grupo($standing['group'] ?? ''),
            'classificacao' => $tabela,
        ];
    }

    // --- Monta jogos ---
    $jogos = [];
    foreach ($matches_data['matches'] ?? [] as $match) {
        $dt = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

        $home_fallback = $match['homeTeam']['shortName'] ?? $match['homeTeam']['name'] ?? 'A definir';
        $away_fallback = $match['awayTeam']['shortName'] ?? $match['awayTeam']['name'] ?? 'A definir';
        $home_info     = bahia_selecao_info($match['homeTeam']['tla'] ?? '', $home_fallback);
        $away_info     = bahia_selecao_info($match['awayTeam']['tla'] ?? '', $away_fallback);

        $jogos[] = [
            'id'      => (string) $match['id'],
            'fase'    => bahia_traduz_fase($match['stage'] ?? ''),
            'grupo'   => bahia_traduz_grupo($match['group'] ?? ''),
            'time1'   => $home_info['nome'],
            'sigla1'  => $match['homeTeam']['tla'] ?? '',
            'flag1'   => $home_info['flag'],
            'crest1'  => $match['homeTeam']['crest'] ?? '',
            'time2'   => $away_info['nome'],
            'sigla2'  => $match['awayTeam']['tla'] ?? '',
            'flag2'   => $away_info['flag'],
            'crest2'  => $match['awayTeam']['crest'] ?? '',
            'placar1' => $match['score']['fullTime']['home'] ?? null,
            'placar2' => $match['score']['fullTime']['away'] ?? null,
            'data'    => $dt->format('Y-m-d'),
            'horario' => $dt->format('H:i'),
            'status'  => $match['status'] ?? '',
        ];
    }

    $json_final = json_encode([
        'atualizacao' => (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'),
        'grupos'      => $grupos,
        'jogos'       => $jogos,
    ]);

    set_transient($cache_key, $json_final, $cache_duration);
    update_option($stale_key, $json_final, false);

    echo $json_final;
    exit;
}, 1);
