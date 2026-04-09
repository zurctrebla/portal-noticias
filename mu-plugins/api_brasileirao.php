<?php
/**
 * API Brasileirão
 * Serve /api_brasileirao.php via WordPress
 */

add_action('init', function () {
    $request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if ($request_uri !== '/api_brasileirao.php') {
        return;
    }

    $serie = isset($_GET['serie']) ? strtoupper(sanitize_text_field($_GET['serie'])) : 'A';

    if ($serie === 'A') {
        $idCampeonato    = 30;
        $numFase         = 4139;
    } elseif ($serie === 'B') {
        $idCampeonato    = 112;
        $numFase         = 4138;
    } else {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Serie inválida. Use A ou B.']);
        exit;
    }

    header('Content-Type: application/json');
    header('Cache-Control: max-age=60');

    $datetime  = time();
    $dados_url = 'http://jsuol.com.br/c/monaco/utils/gestor/commons.js?file=commons.uol.com.br/sistemas/esporte/modalidades/futebol/campeonatos/dados/2025/' . $idCampeonato . '/dados.json&time=' . $datetime;

    $json_dados_raw = @file_get_contents($dados_url);

    if (!$json_dados_raw) {
        http_response_code(502);
        echo json_encode(['error' => 'Falha ao buscar dados do Brasileirão.']);
        exit;
    }

    $json_dados = json_decode($json_dados_raw, true);

    if (!$json_dados || !isset($json_dados['fases'][$numFase])) {
        http_response_code(502);
        echo json_encode(['error' => 'Dados inválidos ou fase não encontrada.']);
        exit;
    }

    $fase = $json_dados['fases'][$numFase];

    $json_final = [];
    $json_final['atualizacao']       = $json_dados['atualizacao'];
    $json_final['faixasClassificacao'] = $fase['faixas-classificacao'];
    $json_final['rodada']            = $fase['rodada'];
    $json_final['idJogosPorRodada']  = $fase['jogos']['rodada'];
    $json_final['jogoPorId']         = $fase['jogos']['id'];
    $json_final['classificacao']     = $fase['classificacao']['equipe'];
    $json_final['equipes']           = $json_dados['equipes'];

    foreach ($json_final['classificacao'] as $key => $i) {
        unset(
            $json_final['classificacao'][$key]['pg']['mandante'],
            $json_final['classificacao'][$key]['pg']['visitante'],
            $json_final['classificacao'][$key]['j']['mandante'],
            $json_final['classificacao'][$key]['j']['visitante'],
            $json_final['classificacao'][$key]['v']['mandante'],
            $json_final['classificacao'][$key]['v']['visitante'],
            $json_final['classificacao'][$key]['e']['mandante'],
            $json_final['classificacao'][$key]['e']['visitante'],
            $json_final['classificacao'][$key]['d']['mandante'],
            $json_final['classificacao'][$key]['d']['visitante'],
            $json_final['classificacao'][$key]['cd'],
            $json_final['classificacao'][$key]['obs']
        );
    }

    foreach ($json_final['equipes'] as $key => $i) {
        unset(
            $json_final['equipes'][$key]['uri'],
            $json_final['equipes'][$key]['brasao'],
            $json_final['equipes'][$key]['tag'],
            $json_final['equipes'][$key]['tipo'],
            $json_final['equipes'][$key]['cor'],
            $json_final['equipes'][$key]['nome']
        );
    }

    foreach ($json_final['jogoPorId'] as $key => $i) {
        unset(
            $json_final['jogoPorId'][$key]['posicao'],
            $json_final['jogoPorId'][$key]['njogo'],
            $json_final['jogoPorId'][$key]['penalti1'],
            $json_final['jogoPorId'][$key]['penalti2'],
            $json_final['jogoPorId'][$key]['desempate_time1'],
            $json_final['jogoPorId'][$key]['desempate_time2'],
            $json_final['jogoPorId'][$key]['url-prejogo'],
            $json_final['jogoPorId'][$key]['url-posjogo'],
            $json_final['jogoPorId'][$key]['url-video'],
            $json_final['jogoPorId'][$key]['eliminou-jogo-volta'],
            $json_final['jogoPorId'][$key]['classificou-gols-fora'],
            $json_final['jogoPorId'][$key]['local']
        );
        if (isset($json_final['jogoPorId'][$key]['estadio']) && $json_final['jogoPorId'][$key]['estadio'] === 'A definir') {
            $json_final['jogoPorId'][$key]['estadio'] = null;
        }
    }

    // Dados ao vivo
    $aovivo_url    = 'http://esporte.uol.com.br/resultados/ao-vivo/index.htm?time=' . $datetime;
    $json_aovivo_raw = @file_get_contents($aovivo_url);
    $json_aovivo   = $json_aovivo_raw ? json_decode($json_aovivo_raw, true) : [];

    if (is_array($json_aovivo)) {
        foreach ($json_final['jogoPorId'] as $key => $final) {
            foreach ($json_aovivo as $aovivo) {
                if (
                    $aovivo['competicao']['id'] == $idCampeonato &&
                    $aovivo['partida']['id'] == $key &&
                    ($aovivo['periodo']['is-andamento'] === 'true' || ($json_final['jogoPorId'][$key]['placar1'] === null && $aovivo['periodo']['id'] == 8))
                ) {
                    $equipe1    = $aovivo['equipes']['e1']['id'];
                    $equipe2    = $aovivo['equipes']['e2']['id'];
                    $saldoGols1 = $aovivo['equipes']['e1']['saldo-gols'];
                    $saldoGols2 = $aovivo['equipes']['e2']['saldo-gols'];

                    if ($saldoGols1 > $saldoGols2) {
                        $json_final['classificacao'][$equipe1]['pg']['total'] = (int)$json_final['classificacao'][$equipe1]['pg']['total'] + 3;
                        $json_final['classificacao'][$equipe1]['v']['total']  = (int)$json_final['classificacao'][$equipe1]['v']['total'] + 1;
                        $json_final['classificacao'][$equipe2]['d']['total']  = (int)$json_final['classificacao'][$equipe2]['d']['total'] + 1;
                    } elseif ($saldoGols2 > $saldoGols1) {
                        $json_final['classificacao'][$equipe2]['pg']['total'] = (int)$json_final['classificacao'][$equipe2]['pg']['total'] + 3;
                        $json_final['classificacao'][$equipe2]['v']['total']  = (int)$json_final['classificacao'][$equipe2]['v']['total'] + 1;
                        $json_final['classificacao'][$equipe1]['d']['total']  = (int)$json_final['classificacao'][$equipe1]['d']['total'] + 1;
                    } else {
                        $json_final['classificacao'][$equipe1]['pg']['total'] = (int)$json_final['classificacao'][$equipe1]['pg']['total'] + 1;
                        $json_final['classificacao'][$equipe2]['pg']['total'] = (int)$json_final['classificacao'][$equipe2]['pg']['total'] + 1;
                        $json_final['classificacao'][$equipe1]['e']['total']  = (int)$json_final['classificacao'][$equipe1]['e']['total'] + 1;
                        $json_final['classificacao'][$equipe2]['e']['total']  = (int)$json_final['classificacao'][$equipe2]['e']['total'] + 1;
                    }

                    $json_final['classificacao'][$equipe1]['j']['total']  = (int)$json_final['classificacao'][$equipe1]['j']['total'] + 1;
                    $json_final['classificacao'][$equipe2]['j']['total']  = (int)$json_final['classificacao'][$equipe2]['j']['total'] + 1;
                    $json_final['classificacao'][$equipe1]['gp']['total'] = (int)$json_final['classificacao'][$equipe1]['gp']['total'] + $saldoGols1;
                    $json_final['classificacao'][$equipe2]['gp']['total'] = (int)$json_final['classificacao'][$equipe2]['gp']['total'] + $saldoGols2;
                    $json_final['classificacao'][$equipe1]['gc']['total'] = (int)$json_final['classificacao'][$equipe1]['gc']['total'] + $saldoGols2;
                    $json_final['classificacao'][$equipe2]['gc']['total'] = (int)$json_final['classificacao'][$equipe2]['gc']['total'] + $saldoGols1;
                    $json_final['classificacao'][$equipe1]['sg']['total'] = (int)$json_final['classificacao'][$equipe1]['gp']['total'] - (int)$json_final['classificacao'][$equipe1]['gc']['total'];
                    $json_final['classificacao'][$equipe2]['sg']['total'] = (int)$json_final['classificacao'][$equipe2]['gp']['total'] - (int)$json_final['classificacao'][$equipe2]['gc']['total'];
                    $json_final['classificacao'][$equipe1]['ap'] = round(((float)$json_final['classificacao'][$equipe1]['pg']['total'] / ((float)$json_final['classificacao'][$equipe1]['j']['total'] * 3)) * 100);
                    $json_final['classificacao'][$equipe2]['ap'] = round(((float)$json_final['classificacao'][$equipe2]['pg']['total'] / ((float)$json_final['classificacao'][$equipe2]['j']['total'] * 3)) * 100);
                    $json_final['jogoPorId'][$key]['placar1'] = $saldoGols1;
                    $json_final['jogoPorId'][$key]['placar2'] = $saldoGols2;

                    if ($aovivo['periodo']['is-andamento'] === 'true') {
                        $json_final['jogoPorId'][$key]['is-andamento'] = true;
                    }
                }
            }
        }
    }

    usort($json_final['classificacao'], function ($a, $b) {
        $pgA = (int)$a['pg']['total'];
        $pgB = (int)$b['pg']['total'];
        $vA  = (int)$a['v']['total'];
        $vB  = (int)$b['v']['total'];
        $sgA = (int)$a['sg']['total'];
        $sgB = (int)$b['sg']['total'];
        $gpA = (int)$a['gp']['total'];
        $gpB = (int)$b['gp']['total'];

        if ($pgA !== $pgB) return $pgB - $pgA;
        if ($vA !== $vB)   return $vB - $vA;
        if ($sgA !== $sgB) return $sgB - $sgA;
        return $gpB - $gpA;
    });

    echo json_encode($json_final);
    exit;
}, 1);
