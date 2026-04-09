<?php
/**
 * API Brasileirão
 * Serve /api_brasileirao.php via WordPress
 * Fonte: api.football-data.org v4
 * Saída: formato compatível com o front-end legado (estrutura UOL)
 */

add_action('init', function () {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($request_uri !== '/api_brasileirao.php') {
        return;
    }

    // delete_transient('brasileirao_estadios_A');
    // delete_transient('brasileirao_ftb_A');

    $serie = isset($_GET['serie']) ? strtoupper(sanitize_text_field($_GET['serie'])) : 'A';

    if ($serie !== 'A') {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Apenas Série A disponível. Use ?serie=A']);
        exit;
    }

    header('Content-Type: application/json');
    header('Cache-Control: max-age=1800');

    // $api_token      = 'aa11580137f54154b69714b0508e9cea';
    $api_token      = 'f5c2e920e49b4657b44ff0ef77c87350';
    $competition    = 'BSA';
    $cache_key      = 'brasileirao_ftb_' . $serie;
    $stale_key      = $cache_key . '_stale';
    $cache_duration = 30 * MINUTE_IN_SECONDS;

    // Retorna cache ativo se existir
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        echo $cached;
        exit;
    }

    // --- Busca classificação ---
    $standings_raw = wp_remote_get(
        "https://api.football-data.org/v4/competitions/{$competition}/standings",
        ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 15]
    );

    if (is_wp_error($standings_raw) || wp_remote_retrieve_response_code($standings_raw) !== 200) {
        // Fallback: retorna último cache salvo sem expiração
        $stale = get_option($stale_key);
        if ($stale) { echo $stale; exit; }
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar classificação. Código: ' . wp_remote_retrieve_response_code($standings_raw)]);
        exit;
    }

    $standings_data = json_decode(wp_remote_retrieve_body($standings_raw), true);
    $matchday       = $standings_data['season']['currentMatchday'] ?? 1;

    // --- Busca todos os jogos (todas as rodadas) ---
    $matches_raw = wp_remote_get(
        "https://api.football-data.org/v4/competitions/{$competition}/matches",
        ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 15]
    );

    if (is_wp_error($matches_raw) || wp_remote_retrieve_response_code($matches_raw) !== 200) {
        // Fallback: retorna último cache salvo sem expiração
        $stale = get_option($stale_key);
        if ($stale) { echo $stale; exit; }
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar jogos. Código: ' . wp_remote_retrieve_response_code($matches_raw)]);
        exit;
    }

    $matches_data = json_decode(wp_remote_retrieve_body($matches_raw), true);

    // --- Busca estádios via /v4/teams/{id} para cada time mandante único ---
    $estadios_cache_key = 'brasileirao_estadios_' . $serie;
    $team_estadios      = get_transient($estadios_cache_key);

    if ($team_estadios === false) {
        $team_estadios = [];
        $home_team_ids = [];

        foreach ($matches_data['matches'] ?? [] as $match) {
            $home_team_ids[$match['homeTeam']['id']] = true;
        }

        foreach (array_keys($home_team_ids) as $team_id) {
            $team_raw = wp_remote_get(
                "https://api.football-data.org/v4/teams/{$team_id}",
                ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 10]
            );
            if (!is_wp_error($team_raw) && wp_remote_retrieve_response_code($team_raw) === 200) {
                $team_data = json_decode(wp_remote_retrieve_body($team_raw), true);
                $venue     = $team_data['venue'] ?? null;
                $team_estadios[$team_id] = ($venue === 'A definir') ? null : $venue;
            }
        }

        set_transient($estadios_cache_key, $team_estadios, DAY_IN_SECONDS);
    }

    // --- Ajusta rodada atual: se todos os jogos já terminaram, avança para a próxima ---
    $jogos_rodada_atual = array_filter(
        $matches_data['matches'] ?? [],
        fn($m) => (int)$m['matchday'] === $matchday
    );
    $todos_encerrados = !empty($jogos_rodada_atual) && array_reduce(
        $jogos_rodada_atual,
        fn($carry, $m) => $carry && in_array($m['status'], ['FINISHED', 'POSTPONED', 'CANCELLED']),
        true
    );
    if ($todos_encerrados) {
        $matchday++;
    }

    // --- Overrides de nome/slug ---
    $team_overrides = [
        1766 => ['nome' => 'Atlético-MG', 'slug' => 'atletico-mg', 'sigla' => 'CAM'],
        1767 => ['nome' => 'Grêmio', 'slug' => 'gremio', 'sigla' => 'GRE'],
        1768 => ['nome' => 'Atlético-PR', 'slug' => 'athletico',   'sigla' => 'CAP'],
        1776 => ['nome' => 'São Paulo',   'slug' => 'sao-paulo',   'sigla' => 'SAO'],
        1780 => ['nome' => 'Vasco',       'slug' => 'vasco',       'sigla' => 'VAS'],
        4241 => ['nome' => 'Coritiba',    'slug' => 'coritiba',    'sigla' => 'CFC'],
        4287 => ['nome' => 'Remo',        'slug' => 'remo-',       'sigla' => 'REM'],
        6684 => ['nome' => 'Internacional','slug' => 'internacional','sigla' => 'INT'],
    ];

    // --- Helper slug ---
    $make_slug = function ($name) {
        $name = mb_strtolower($name, 'UTF-8');
        $name = strtr($name, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e', 'í' => 'i',
            'ó' => 'o', 'õ' => 'o', 'ô' => 'o',
            'ú' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
        $name = preg_replace('/[^a-z0-9\s-]/', '', $name);
        $name = preg_replace('/\s+/', '-', trim($name));
        return $name;
    };

    // --- Monta equipes ---
    $equipes = [];
    foreach ($standings_data['standings'][0]['table'] ?? [] as $row) {
        $t     = $row['team'];
        $ftbId = $t['id'];
        $short = $t['shortName'] ?? '';

        $front_id = $ftbId;
        if (stripos($short, 'Bahia') !== false)                                           $front_id = 30;
        if (stripos($short, 'Vitória') !== false || stripos($short, 'Vitoria') !== false) $front_id = 21;

        $equipes[$ftbId] = [
            'id'         => $front_id,
            'nome-comum' => $team_overrides[$ftbId]['nome'] ?? $short,
            'nome-slug'  => $team_overrides[$ftbId]['slug'] ?? $make_slug($short),
            'sigla'      => $team_overrides[$ftbId]['sigla'] ?? ($t['tla'] ?? ''),
        ];
    }

    // --- Monta classificação ---
    $classificacao = [];
    foreach ($standings_data['standings'][0]['table'] ?? [] as $row) {
        $t  = $row['team'];
        $j  = (int) $row['playedGames'];
        $pg = (int) $row['points'];
        $ap = $j > 0 ? round(($pg / ($j * 3)) * 100) : 0;

        $classificacao[] = [
            'id' => $t['id'],
            'pg' => ['total' => $pg],
            'j'  => ['total' => $j],
            'v'  => ['total' => (int) $row['won']],
            'e'  => ['total' => (int) $row['draw']],
            'd'  => ['total' => (int) $row['lost']],
            'gp' => ['total' => (int) $row['goalsFor']],
            'gc' => ['total' => (int) $row['goalsAgainst']],
            'sg' => ['total' => (int) $row['goalDifference']],
            'ap' => $ap,
        ];
    }

    // --- Monta jogoPorId e idJogosPorRodada ---
    $jogoPorId        = [];
    $idJogosPorRodada = [];

    foreach ($matches_data['matches'] ?? [] as $match) {
        $mid    = (string) $match['id'];
        $rodada = (int)    $match['matchday'];
        $homeId = $match['homeTeam']['id'];

        $dt = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

        $jogo = [
            'time1'   => $homeId,
            'time2'   => $match['awayTeam']['id'],
            'placar1' => $match['score']['fullTime']['home'],
            'placar2' => $match['score']['fullTime']['away'],
            'data'    => $dt->format('Y-m-d'),
            'horario' => $dt->format('H:i'),
            'estadio' => $team_estadios[$homeId] ?? null,
        ];

        if (in_array($match['status'], ['IN_PLAY', 'PAUSED'])) {
            $jogo['is-andamento'] = true;
        }

        // Garante equipes fora da tabela
        foreach (['homeTeam', 'awayTeam'] as $side) {
            $t     = $match[$side];
            $ftbId = $t['id'];
            if (!isset($equipes[$ftbId])) {
                $short    = $t['shortName'] ?? $t['name'] ?? '';
                $front_id = $ftbId;
                if (stripos($short, 'Bahia') !== false)                                           $front_id = 30;
                if (stripos($short, 'Vitória') !== false || stripos($short, 'Vitoria') !== false) $front_id = 21;
                $equipes[$ftbId] = [
                    'id'         => $front_id,
                    'nome-comum' => $team_overrides[$ftbId]['nome'] ?? $short,
                    'nome-slug'  => $team_overrides[$ftbId]['slug'] ?? $make_slug($short),
                    'sigla'      => $team_overrides[$ftbId]['sigla'] ?? ($t['tla'] ?? ''),
                ];
            }
        }

        $jogoPorId[$mid]             = $jogo;
        $idJogosPorRodada[$rodada][] = $mid;
    }

    $json_final = json_encode([
        'atualizacao'         => (new DateTime('now', new DateTimeZone('America/Sao_Paulo')))->format('d/m/Y H:i'),
        'faixasClassificacao' => [
            'classifica3' => ['faixa' => '17-20'],
        ],
        'rodada'              => ['atual' => $matchday],
        'classificacao'       => $classificacao,
        'equipes'             => $equipes,
        'idJogosPorRodada'    => $idJogosPorRodada,
        'jogoPorId'           => $jogoPorId,
    ]);

    set_transient($cache_key, $json_final, $cache_duration);
    update_option($stale_key, $json_final, false); // fallback sem expiração

    echo $json_final;
    exit;
}, 1);