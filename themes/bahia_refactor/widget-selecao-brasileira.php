<?php
/**
 * Widget Seleção Brasileira — último e próximo jogo
 * Fonte: api.football-data.org v4 (team id 764 = Brazil)
 * Renderiza HTML pronto para uso no sidebar da home.
 */

if (!function_exists('bahia_selecao_brasileira_dados')) {
    function bahia_selecao_brasileira_dados()
    {
        $cache_key = 'bahia_selecao_brasileira_v2';
        $cached    = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_token = 'f5c2e920e49b4657b44ff0ef77c87350';
        $team_id   = 764; // Brazil

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

        $format = function ($match) {
            if (!$match) return null;
            $dt = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

            $home_id = $match['homeTeam']['id'] ?? null;
            $is_home = ($home_id == 764);
            $adv_tla  = $is_home ? ($match['awayTeam']['tla'] ?? '') : ($match['homeTeam']['tla'] ?? '');
            $adv_name = $is_home
                ? ($match['awayTeam']['shortName'] ?? $match['awayTeam']['name'] ?? '')
                : ($match['homeTeam']['shortName'] ?? $match['homeTeam']['name'] ?? '');
            $info_adv = bahia_selecao_info($adv_tla, $adv_name);
            $info_bra = bahia_selecao_info('BRA', 'Brasil');

            return [
                'data'         => $dt->format('d/m/Y'),
                'horario'      => $dt->format('H:i'),
                'adversario'   => $info_adv['nome'],
                'flag_adv'     => $info_adv['flag'],
                'flag_bra'     => $info_bra['flag'],
                'mando'        => $is_home ? 'casa' : 'fora',
                'placar_bra'   => $is_home ? ($match['score']['fullTime']['home'] ?? null) : ($match['score']['fullTime']['away'] ?? null),
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

$selecao = bahia_selecao_brasileira_dados();
if (!$selecao || (!$selecao['ultimo'] && !$selecao['proximo'])) {
    return;
}
?>
<div class="box-news-int box-selecao-brasileira" style="float: left;">
    <span class="box-categoria">seleção brasileira</span>
    <div class="box-maislidos">
        <div class="cont-tab">
            <?php if (!empty($selecao['ultimo'])): $u = $selecao['ultimo']; ?>
                <div class="selecao-jogo selecao-ultimo">
                    <div class="selecao-rotulo">Último jogo</div>
                    <div class="selecao-linha">
                        <span class="selecao-time">
                            <?php if ($u['flag_bra']): ?>
                                <img src="<?php echo esc_url($u['flag_bra']); ?>" alt="" width="28" height="18">
                            <?php endif; ?>
                            <span class="selecao-nome">Brasil</span>
                        </span>
                        <strong class="selecao-placar">
                            <?php echo ($u['placar_bra'] !== null ? $u['placar_bra'] : '-'); ?>
                            x
                            <?php echo ($u['placar_adv'] !== null ? $u['placar_adv'] : '-'); ?>
                        </strong>
                        <span class="selecao-time">
                            <?php if ($u['flag_adv']): ?>
                                <img src="<?php echo esc_url($u['flag_adv']); ?>" alt="" width="28" height="18">
                            <?php endif; ?>
                            <span class="selecao-nome"><?php echo esc_html($u['adversario']); ?></span>
                        </span>
                    </div>
                    <div class="selecao-meta"><?php echo esc_html($u['data'] . ' • ' . $u['competicao']); ?></div>
                </div>
            <?php endif; ?>

            <?php if (!empty($selecao['proximo'])): $p = $selecao['proximo']; ?>
                <div class="selecao-jogo selecao-proximo">
                    <div class="selecao-rotulo">Próximo jogo</div>
                    <div class="selecao-linha">
                        <span class="selecao-time">
                            <?php if ($p['flag_bra']): ?>
                                <img src="<?php echo esc_url($p['flag_bra']); ?>" alt="" width="28" height="18">
                            <?php endif; ?>
                            <span class="selecao-nome">Brasil</span>
                        </span>
                        <span class="selecao-vs">vs</span>
                        <span class="selecao-time">
                            <?php if ($p['flag_adv']): ?>
                                <img src="<?php echo esc_url($p['flag_adv']); ?>" alt="" width="28" height="18">
                            <?php endif; ?>
                            <span class="selecao-nome"><?php echo esc_html($p['adversario']); ?></span>
                        </span>
                    </div>
                    <div class="selecao-meta"><?php echo esc_html($p['data'] . ' às ' . $p['horario'] . ' • ' . $p['competicao']); ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<style>
    .box-selecao-brasileira .selecao-jogo { padding: 10px 0; border-bottom: 1px solid #eee; }
    .box-selecao-brasileira .selecao-jogo:last-child { border-bottom: 0; }
    .box-selecao-brasileira .selecao-rotulo { font-size: 11px; text-transform: uppercase; color: #15559e; font-weight: 600; margin-bottom: 4px; }
    .box-selecao-brasileira .selecao-linha { display: flex; align-items: center; justify-content: space-between; font-size: 14px; }
    .box-selecao-brasileira .selecao-time { display: flex; flex-direction: column; align-items: center; flex: 1; min-width: 0; text-align: center; }
    .box-selecao-brasileira .selecao-time img { display: block; margin-bottom: 4px; }
    .box-selecao-brasileira .selecao-nome { font-weight: 600; }
    .box-selecao-brasileira .selecao-placar, .box-selecao-brasileira .selecao-vs { font-weight: 700; margin: 0 8px; flex: 0 0 auto; }
    .box-selecao-brasileira .selecao-meta { font-size: 11px; color: #777; margin-top: 4px; }
</style>
