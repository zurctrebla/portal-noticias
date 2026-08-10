<?php
/**
 * Widget Clubes da Bahia — último e próximo jogo do EC Bahia e do EC Vitória
 *
 * Fonte: api.football-data.org v4 — a MESMA fonte usada pelo Brasileirão 2026
 * (mu-plugins/api_brasileirao.php, competição BSA) e pelo antigo
 * widget-selecao-brasileira.php. Team IDs: 1777 = EC Bahia, 1782 = EC Vitória.
 *
 * Substitui, no sidebar da home, o widget-selecao-brasileira.php (Seleção Brasileira),
 * mantendo o mesmo padrão visual (box-news-int / box-categoria / box-maislidos).
 *
 * Renderiza HTML pronto para uso no sidebar da home.
 */

if (!function_exists('bahia_clube_jogos_dados')) {
    /**
     * Busca último jogo (FINISHED) e próximo jogo (SCHEDULED) de um clube.
     * Mesmo padrão de consumo/caching do widget da Seleção.
     *
     * @param int    $team_id   ID do time em api.football-data.org
     * @param string $cache_key chave do transient (uma por clube)
     */
    function bahia_clube_jogos_dados($team_id, $cache_key)
    {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_token = 'f5c2e920e49b4657b44ff0ef77c87350';

        $fetch = function ($status) use ($api_token, $team_id) {
            $resp = wp_remote_get(
                "https://api.football-data.org/v4/teams/{$team_id}/matches?status={$status}&limit=1",
                ['headers' => ['X-Auth-Token' => $api_token], 'timeout' => 10]
            );
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
                return null;
            }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            return $body['matches'][0] ?? null;
        };

        $ultimo_raw  = $fetch('FINISHED');
        $proximo_raw = $fetch('SCHEDULED');

        $format = function ($match) use ($team_id) {
            if (!$match) return null;
            $dt = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

            $home_id = $match['homeTeam']['id'] ?? null;
            $is_home = ($home_id == $team_id);

            // Lado do nosso clube
            $clube = $is_home ? ($match['homeTeam'] ?? []) : ($match['awayTeam'] ?? []);
            // Lado do adversário
            $adv   = $is_home ? ($match['awayTeam'] ?? []) : ($match['homeTeam'] ?? []);

            return [
                'data'         => $dt->format('d/m/Y'),
                'horario'      => $dt->format('H:i'),
                'clube_nome'   => $clube['shortName'] ?? $clube['name'] ?? '',
                'clube_crest'  => $clube['crest'] ?? '',
                'adversario'   => $adv['shortName'] ?? $adv['name'] ?? '',
                'adv_crest'    => $adv['crest'] ?? '',
                'mando'        => $is_home ? 'casa' : 'fora',
                'placar_clube' => $is_home ? ($match['score']['fullTime']['home'] ?? null) : ($match['score']['fullTime']['away'] ?? null),
                'placar_adv'   => $is_home ? ($match['score']['fullTime']['away'] ?? null) : ($match['score']['fullTime']['home'] ?? null),
                'competicao'   => $match['competition']['name'] ?? '',
            ];
        };

        $dados = [
            'ultimo'  => $format($ultimo_raw),
            'proximo' => $format($proximo_raw),
        ];

        set_transient($cache_key, $dados, 30 * MINUTE_IN_SECONDS);
        return $dados;
    }
}

if (!function_exists('bahia_render_box_clube')) {
    /**
     * Renderiza um box (categoria) com o último e o próximo jogo do clube,
     * no mesmo padrão visual do antigo widget da Seleção.
     *
     * @param string $rotulo       Rótulo exibido no cabeçalho do box (ex: "EC Bahia").
     * @param array  $dados        Dados retornados por bahia_clube_jogos_dados().
     * @param string $clube_crest  URL do escudo LOCAL do nosso clube (brasileirao/brasao),
     *                             usado no lugar do crest da API para o time da casa.
     */
    function bahia_render_box_clube($rotulo, $dados, $clube_crest = '')
    {
        if (!$dados || (empty($dados['ultimo']) && empty($dados['proximo']))) {
            return;
        }
        // Mando de campo: o mandante ocupa sempre o lado esquerdo da linha.
        // Quando o clube joga fora, clube e adversário — e os placares — trocam de lado.
        // O clube segue identificável pelo rótulo do box e pelo escudo local.
        $lados = function ($j) use ($clube_crest) {
            $fora = (isset($j['mando']) && $j['mando'] === 'fora');
            return array(
                'esq_nome'   => $fora ? $j['adversario']   : $j['clube_nome'],
                'esq_crest'  => $fora ? $j['adv_crest']    : $clube_crest,
                'esq_placar' => $fora ? $j['placar_adv']   : $j['placar_clube'],
                'dir_nome'   => $fora ? $j['clube_nome']   : $j['adversario'],
                'dir_crest'  => $fora ? $clube_crest       : $j['adv_crest'],
                'dir_placar' => $fora ? $j['placar_clube'] : $j['placar_adv'],
            );
        };
        ?>
        <div class="box-news-int box-clube-jogos" style="float: left;">
            <span class="box-categoria"><?php echo esc_html($rotulo); ?></span>
            <div class="box-maislidos">
                <div class="cont-tab">
                    <?php if (!empty($dados['ultimo'])): $u = $dados['ultimo']; $lu = $lados($u); ?>
                        <div class="clube-jogo clube-ultimo">
                            <div class="clube-rotulo">Último jogo</div>
                            <div class="clube-linha">
                                <span class="clube-time">
                                    <?php if ($lu['esq_crest']): ?>
                                        <img src="<?php echo esc_url($lu['esq_crest']); ?>" alt="" width="26" height="26">
                                    <?php endif; ?>
                                    <span class="clube-nome"><?php echo esc_html($lu['esq_nome']); ?></span>
                                </span>
                                <strong class="clube-placar">
                                    <?php echo ($lu['esq_placar'] !== null ? intval($lu['esq_placar']) : '-'); ?>
                                    x
                                    <?php echo ($lu['dir_placar'] !== null ? intval($lu['dir_placar']) : '-'); ?>
                                </strong>
                                <span class="clube-time">
                                    <?php if ($lu['dir_crest']): ?>
                                        <img src="<?php echo esc_url($lu['dir_crest']); ?>" alt="" width="26" height="26">
                                    <?php endif; ?>
                                    <span class="clube-nome"><?php echo esc_html($lu['dir_nome']); ?></span>
                                </span>
                            </div>
                            <div class="clube-meta"><?php echo esc_html($u['data'] . ' • ' . $u['competicao']); ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($dados['proximo'])): $p = $dados['proximo']; $lp = $lados($p); ?>
                        <div class="clube-jogo clube-proximo">
                            <div class="clube-rotulo">Próximo jogo</div>
                            <div class="clube-linha">
                                <span class="clube-time">
                                    <?php if ($lp['esq_crest']): ?>
                                        <img src="<?php echo esc_url($lp['esq_crest']); ?>" alt="" width="26" height="26">
                                    <?php endif; ?>
                                    <span class="clube-nome"><?php echo esc_html($lp['esq_nome']); ?></span>
                                </span>
                                <span class="clube-vs">vs</span>
                                <span class="clube-time">
                                    <?php if ($lp['dir_crest']): ?>
                                        <img src="<?php echo esc_url($lp['dir_crest']); ?>" alt="" width="26" height="26">
                                    <?php endif; ?>
                                    <span class="clube-nome"><?php echo esc_html($lp['dir_nome']); ?></span>
                                </span>
                            </div>
                            <div class="clube-meta"><?php echo esc_html($p['data'] . ' às ' . $p['horario'] . ' • ' . $p['competicao']); ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

$bahia_dados   = bahia_clube_jogos_dados(1777, 'bahia_clube_ecbahia_v1');
$vitoria_dados = bahia_clube_jogos_dados(1782, 'bahia_clube_ecvitoria_v1');

// Se nenhum dos clubes tem dado algum, não imprime nada (evita CSS órfão).
if ((!$bahia_dados || (empty($bahia_dados['ultimo']) && empty($bahia_dados['proximo'])))
    && (!$vitoria_dados || (empty($vitoria_dados['ultimo']) && empty($vitoria_dados['proximo'])))) {
    return;
}
?>
<style>
    .box-clube-jogos .clube-jogo { padding: 10px 0; border-bottom: 1px solid #eee; }
    .box-clube-jogos .clube-jogo:last-child { border-bottom: 0; }
    .box-clube-jogos .clube-rotulo { font-size: 11px; text-transform: uppercase; color: #15559e; font-weight: 600; margin-bottom: 4px; }
    .box-clube-jogos .clube-linha { display: flex; align-items: center; justify-content: space-between; font-size: 14px; }
    .box-clube-jogos .clube-time { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 0; text-align: center; }
    .box-clube-jogos .clube-time img { display: block; margin-bottom: 4px; object-fit: contain; }
    .box-clube-jogos .clube-nome { font-weight: 600; }
    .box-clube-jogos .clube-placar, .box-clube-jogos .clube-vs { font-weight: 700; margin: 0 8px; flex: 0 0 auto; }
    .box-clube-jogos .clube-meta { font-size: 11px; color: #777; margin-top: 4px; }
</style>
<?php
$brasao_base = get_template_directory_uri() . '/brasileirao/brasao/';
bahia_render_box_clube('EC Bahia', $bahia_dados, $brasao_base . 'bahia.png');
bahia_render_box_clube('EC Vitória', $vitoria_dados, $brasao_base . 'vitoria.png');
