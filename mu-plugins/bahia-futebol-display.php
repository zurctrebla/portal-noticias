<?php
/**
 * Plugin Name: Bahia Futebol Display
 * Description: Shortcode [bahia_brasileirao] que renderiza, no topo, dois boxes de destaque
 *   com o último/próximo jogo do EC Bahia (id 1777) e do EC Vitória (id 1782) — fonte
 *   api.football-data.org v4, /teams/{id}/matches, cache transient 30min — e, abaixo, a tabela
 *   de classificação + jogos por rodada do Brasileirão Série A, consumindo o endpoint
 *   /bahia-api/brasileirao (mu-plugin api_brasileirao.php, mesma fonte football-data.org).
 *   Autossuficiente: CSS próprio (sem Semantic UI) e JS vanilla (fetch). Responsivo (desktop + mobile).
 *
 * Escudos: reutiliza os PNGs já versionados em themes/bahia_refactor/brasileirao/brasao/.
 * Copa do Mundo 2026: desabilitada (item de menu removido); não há shortcode de Copa.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Busca o último jogo (FINISHED) e o próximo jogo (SCHEDULED) de um clube
 * em api.football-data.org v4. MESMA fonte/token do Brasileirão (api_brasileirao.php)
 * e mesmo padrão da branch de produção (themes/bahia_refactor/widget-clubes-ba.php).
 *
 * Team IDs football-data.org: 1777 = EC Bahia, 1782 = EC Vitória.
 * Cache via transient de 30 min (uma chave por clube).
 *
 * @param int    $team_id   ID do time em api.football-data.org
 * @param string $cache_key chave do transient
 * @return array{ultimo: ?array, proximo: ?array}
 */
if (!function_exists('bahia_fut_clube_jogos_dados')) {
    function bahia_fut_clube_jogos_dados($team_id, $cache_key) {
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $api_token = 'f5c2e920e49b4657b44ff0ef77c87350';

        $fetch = function ($status) use ($api_token, $team_id) {
            $resp = wp_remote_get(
                "https://api.football-data.org/v4/teams/{$team_id}/matches?status={$status}&limit=1",
                array('headers' => array('X-Auth-Token' => $api_token), 'timeout' => 10)
            );
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
                return null;
            }
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            return isset($body['matches'][0]) ? $body['matches'][0] : null;
        };

        $ultimo_raw  = $fetch('FINISHED');
        $proximo_raw = $fetch('SCHEDULED');

        $format = function ($match) use ($team_id) {
            if (!$match) return null;
            $dt = new DateTime($match['utcDate'], new DateTimeZone('UTC'));
            $dt->setTimezone(new DateTimeZone('America/Sao_Paulo'));

            $home_id = isset($match['homeTeam']['id']) ? $match['homeTeam']['id'] : null;
            $is_home = ($home_id == $team_id);

            $clube = $is_home ? (isset($match['homeTeam']) ? $match['homeTeam'] : array())
                              : (isset($match['awayTeam']) ? $match['awayTeam'] : array());
            $adv   = $is_home ? (isset($match['awayTeam']) ? $match['awayTeam'] : array())
                              : (isset($match['homeTeam']) ? $match['homeTeam'] : array());

            return array(
                'data'         => $dt->format('d/m/Y'),
                'horario'      => $dt->format('H:i'),
                'clube_nome'   => isset($clube['shortName']) ? $clube['shortName'] : (isset($clube['name']) ? $clube['name'] : ''),
                'adversario'   => isset($adv['shortName']) ? $adv['shortName'] : (isset($adv['name']) ? $adv['name'] : ''),
                'adv_crest'    => isset($adv['crest']) ? $adv['crest'] : '',
                'mando'        => $is_home ? 'casa' : 'fora',
                'placar_clube' => $is_home ? (isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : null)
                                           : (isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : null),
                'placar_adv'   => $is_home ? (isset($match['score']['fullTime']['away']) ? $match['score']['fullTime']['away'] : null)
                                           : (isset($match['score']['fullTime']['home']) ? $match['score']['fullTime']['home'] : null),
                'competicao'   => isset($match['competition']['name']) ? $match['competition']['name'] : '',
            );
        };

        $dados = array(
            'ultimo'  => $format($ultimo_raw),
            'proximo' => $format($proximo_raw),
        );

        set_transient($cache_key, $dados, 30 * MINUTE_IN_SECONDS);
        return $dados;
    }
}

/**
 * Nome curto da competição para a linha de meta dos cards.
 *
 * A API devolve "Campeonato Brasileiro Série A", que estoura a largura do card e
 * quebra em duas linhas — em 390px chega a empurrar o layout. "Brasileirão Série A"
 * é o termo corrente e cabe em uma linha nas duas larguras.
 *
 * Aplicado no RENDER, e não na normalização, para valer também para o que já está
 * no transient de 30 min.
 */
if (!function_exists('bahia_fut_competicao_curta')) {
    function bahia_fut_competicao_curta($nome) {
        if (!is_string($nome) || $nome === '') {
            return '';
        }
        $mapa = array(
            'Campeonato Brasileiro Série A' => 'Brasileirão Série A',
            'Campeonato Brasileiro Série B' => 'Brasileirão Série B',
            'Campeonato Brasileiro'         => 'Brasileirão',
        );
        if (isset($mapa[$nome])) {
            return $mapa[$nome];
        }
        // A API às vezes varia o nome ("Brasileiro Serie A", sem acento etc.).
        return preg_replace(
            '/^Campeonato\s+Brasileiro\b/iu',
            'Brasileirão',
            $nome
        );
    }
}

/**
 * Renderiza um box de destaque (último + próximo jogo) para um clube,
 * no padrão visual próprio do widget do Brasileirão (Newspaper-neutral).
 *
 * @param string $rotulo      Rótulo do cabeçalho (ex: "EC Bahia").
 * @param string $classe      Classe de cor do clube ("bahia" | "vitoria").
 * @param array  $dados       Retorno de bahia_fut_clube_jogos_dados().
 * @param string $clube_crest URL do escudo LOCAL do clube (brasao/*.png).
 */
if (!function_exists('bahia_fut_render_box_clube')) {
    function bahia_fut_render_box_clube($rotulo, $classe, $dados, $clube_crest) {
        if (!$dados || (empty($dados['ultimo']) && empty($dados['proximo']))) {
            return;
        }
        $esc = function ($url) {
            return $url ? '<img class="bahia-cl-esc" src="' . esc_url($url) . '" alt="" onerror="this.style.visibility=\'hidden\'">' : '';
        };
        // Mando de campo: o mandante ocupa sempre o lado esquerdo da linha (.casa).
        // Quando o clube joga fora, clube e adversario — e os placares — trocam de lado.
        // O clube segue identificavel pelo cabecalho colorido do box e pelo escudo local.
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
        }; ?>
        <div class="bahia-cl-box <?php echo esc_attr($classe); ?>">
          <div class="bahia-cl-head"><?php echo esc_html($rotulo); ?></div>
          <div class="bahia-cl-body">
            <?php if (!empty($dados['ultimo'])) : $u = $dados['ultimo']; $lu = $lados($u); ?>
              <div class="bahia-cl-jogo">
                <div class="bahia-cl-rot">Último jogo</div>
                <div class="bahia-cl-row">
                  <div class="bahia-cl-lado casa">
                    <span class="bahia-cl-nome"><?php echo esc_html($lu['esq_nome']); ?></span>
                    <?php echo $esc($lu['esq_crest']); ?>
                  </div>
                  <div class="bahia-cl-mid">
                    <?php echo ($lu['esq_placar'] !== null ? intval($lu['esq_placar']) : '-'); ?><span class="bahia-cl-x">x</span><?php echo ($lu['dir_placar'] !== null ? intval($lu['dir_placar']) : '-'); ?>
                  </div>
                  <div class="bahia-cl-lado fora">
                    <?php echo $esc($lu['dir_crest']); ?>
                    <span class="bahia-cl-nome"><?php echo esc_html($lu['dir_nome']); ?></span>
                  </div>
                </div>
                <div class="bahia-cl-meta"><?php echo esc_html($u['data'] . ' · ' . bahia_fut_competicao_curta($u['competicao'])); ?></div>
              </div>
            <?php endif; ?>
            <?php if (!empty($dados['proximo'])) : $p = $dados['proximo']; $lp = $lados($p); ?>
              <div class="bahia-cl-jogo">
                <div class="bahia-cl-rot">Próximo jogo</div>
                <div class="bahia-cl-row">
                  <div class="bahia-cl-lado casa">
                    <span class="bahia-cl-nome"><?php echo esc_html($lp['esq_nome']); ?></span>
                    <?php echo $esc($lp['esq_crest']); ?>
                  </div>
                  <div class="bahia-cl-mid"><span class="bahia-cl-x">×</span></div>
                  <div class="bahia-cl-lado fora">
                    <?php echo $esc($lp['dir_crest']); ?>
                    <span class="bahia-cl-nome"><?php echo esc_html($lp['dir_nome']); ?></span>
                  </div>
                </div>
                <div class="bahia-cl-meta"><?php echo esc_html($p['data'] . ' às ' . $p['horario'] . ' · ' . bahia_fut_competicao_curta($p['competicao'])); ?></div>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }
}

add_shortcode('bahia_brasileirao', 'bahia_brasileirao_shortcode');
function bahia_brasileirao_shortcode($atts) {
    $atts = shortcode_atts(array('serie' => 'A'), $atts, 'bahia_brasileirao');
    $serie = strtoupper($atts['serie']) === 'B' ? 'B' : 'A';
    $endpoint = home_url('/bahia-api/brasileirao');
    $brasao_base = content_url('/themes/bahia_refactor/brasileirao/brasao/');

    // Destaques: último/próximo jogo do EC Bahia e do EC Vitória (football-data.org v4).
    $bahia_jogos   = bahia_fut_clube_jogos_dados(1777, 'bahia_fut_ecbahia_v1');
    $vitoria_jogos = bahia_fut_clube_jogos_dados(1782, 'bahia_fut_ecvitoria_v1');

    ob_start(); ?>
<div class="bahia-bra" data-endpoint="<?php echo esc_url($endpoint); ?>" data-serie="<?php echo esc_attr($serie); ?>" data-brasao="<?php echo esc_url($brasao_base); ?>">
  <div class="bahia-bra-clubes">
    <?php
    bahia_fut_render_box_clube('EC Bahia', 'bahia', $bahia_jogos, $brasao_base . 'bahia.png');
    bahia_fut_render_box_clube('EC Vitória', 'vitoria', $vitoria_jogos, $brasao_base . 'vitoria.png');
    ?>
  </div>
  <div class="bahia-bra-grid">
    <div class="bahia-bra-tabela-wrap">
      <table class="bahia-bra-tabela">
        <thead>
          <tr>
            <th class="c" title="Posição">#</th>
            <th class="l" colspan="2">CLASSIFICAÇÃO</th>
            <th class="c" title="Pontos">P</th>
            <th class="c" title="Jogos">J</th>
            <th class="c" title="Vitórias">V</th>
            <th class="c" title="Empates">E</th>
            <th class="c" title="Derrotas">D</th>
            <th class="c hide-sm" title="Gols Pró">GP</th>
            <th class="c hide-sm" title="Gols Contra">GC</th>
            <th class="c" title="Saldo de Gols">SG</th>
            <th class="c hide-sm" title="Aproveitamento">%</th>
          </tr>
        </thead>
        <tbody class="bahia-bra-corpo">
          <tr class="bahia-bra-load"><td colspan="12">Carregando classificação…</td></tr>
        </tbody>
      </table>
      <div class="bahia-bra-legenda">
        <span><i class="dot lib"></i> <?php echo $serie === 'A' ? 'Libertadores' : 'Acesso à Série A'; ?></span>
        <?php if ($serie === 'A') : ?><span><i class="dot sul"></i> Sul-americana</span><?php endif; ?>
        <span><i class="dot reb"></i> Rebaixamento</span>
      </div>
    </div>
    <div class="bahia-bra-jogos-wrap">
      <div class="bahia-bra-rodada-nav">
        <button type="button" class="bahia-bra-prev" aria-label="Rodada anterior">‹</button>
        <span class="bahia-bra-rodada-tit">Rodada</span>
        <button type="button" class="bahia-bra-next" aria-label="Próxima rodada">›</button>
      </div>
      <div class="bahia-bra-jogos"><p class="bahia-bra-load">Carregando jogos…</p></div>
    </div>
  </div>
  <div class="bahia-bra-updated"></div>
</div>

<style>
.bahia-bra{--lib:#1f7a3d;--sul:#e07a1f;--reb:#d23b3b;--bahia:#0a58ca;--vitoria:#c8102e;font-family:inherit;color:#222;margin:0 0 30px}
.bahia-bra *{box-sizing:border-box}
.bahia-bra-clubes{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.bahia-cl-box{flex:1 1 300px;min-width:280px;border:1px solid #e5e5e5;border-radius:4px;overflow:hidden;background:#fff}
.bahia-cl-head{color:#fff;font-weight:700;font-size:14px;letter-spacing:.03em;text-transform:uppercase;padding:9px 14px}
.bahia-cl-box.bahia .bahia-cl-head{background:var(--bahia)}
.bahia-cl-box.vitoria .bahia-cl-head{background:var(--vitoria)}
.bahia-cl-body{padding:4px 14px}
.bahia-cl-jogo{padding:12px 0;border-bottom:1px solid #eee}
.bahia-cl-jogo:last-child{border-bottom:0}
.bahia-cl-rot{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#888;font-weight:700;margin-bottom:8px}
.bahia-cl-row{display:flex;align-items:center}
.bahia-cl-lado{flex:1;display:flex;align-items:center;gap:8px;min-width:0}
.bahia-cl-lado.casa{justify-content:flex-end;text-align:right}
.bahia-cl-lado.fora{justify-content:flex-start;text-align:left}
.bahia-cl-nome{font-weight:600;font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bahia-cl-esc{width:30px;height:30px;object-fit:contain;flex:0 0 auto}
.bahia-cl-mid{flex:0 0 auto;min-width:60px;text-align:center;font-weight:700;font-size:18px}
.bahia-cl-x{color:#bbb;font-weight:400;margin:0 5px}
.bahia-cl-meta{font-size:11px;color:#999;margin-top:8px;text-align:center}
@media(max-width:767px){
  .bahia-cl-nome{font-size:13px}
  .bahia-cl-esc{width:26px;height:26px}
}
.bahia-bra-grid{display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap}
.bahia-bra-tabela-wrap{flex:1 1 560px;min-width:0}
.bahia-bra-jogos-wrap{flex:1 1 300px;min-width:280px}
.bahia-bra-tabela{width:100%;border-collapse:collapse;font-size:13px}
.bahia-bra-tabela th,.bahia-bra-tabela td{padding:8px 6px;border-bottom:1px solid #eee}
.bahia-bra-tabela thead th{background:#111;color:#fff;font-weight:600;font-size:11px;letter-spacing:.03em;text-transform:uppercase;border-bottom:0}
.bahia-bra-tabela .c{text-align:center}
.bahia-bra-tabela .l{text-align:left}
.bahia-bra-tabela tbody tr:hover{background:#f7f7f7}
.bahia-bra-pos{font-weight:700;position:relative}
.bahia-bra-pos.lib{box-shadow:inset 3px 0 0 var(--lib)}
.bahia-bra-pos.sul{box-shadow:inset 3px 0 0 var(--sul)}
.bahia-bra-pos.reb{box-shadow:inset 3px 0 0 var(--reb)}
.bahia-bra-esc{width:22px;height:22px;object-fit:contain;vertical-align:middle;display:inline-block}
.bahia-bra-time{font-weight:600;white-space:nowrap}
.bahia-bra-tabela tr.bahia .bahia-bra-time{color:var(--bahia)}
.bahia-bra-tabela tr.vitoria .bahia-bra-time{color:var(--vitoria)}
.bahia-bra-tabela tr.bahia,.bahia-bra-tabela tr.vitoria{background:#fafcff}
.bahia-bra-pts{font-weight:700}
.bahia-bra-legenda{display:flex;flex-wrap:wrap;gap:14px;margin-top:12px;font-size:12px;color:#555}
.bahia-bra-legenda .dot{display:inline-block;width:10px;height:10px;border-radius:50%;margin-right:5px;vertical-align:middle}
.bahia-bra-legenda .dot.lib{background:var(--lib)}.bahia-bra-legenda .dot.sul{background:var(--sul)}.bahia-bra-legenda .dot.reb{background:var(--reb)}
.bahia-bra-rodada-nav{display:flex;align-items:center;justify-content:space-between;background:#111;color:#fff;border-radius:4px;padding:6px 10px;margin-bottom:12px}
.bahia-bra-rodada-tit{font-weight:700;font-size:14px}
.bahia-bra-rodada-nav button{background:none;border:0;color:#fff;font-size:22px;line-height:1;cursor:pointer;padding:0 10px}
.bahia-bra-rodada-nav button:disabled{opacity:.3;cursor:default}
.bahia-bra-jogo{padding:10px 0;border-bottom:1px solid #eee}
.bahia-bra-jogo-data{font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.03em;margin-bottom:6px}
.bahia-bra-jogo-row{display:flex;align-items:center}
.bahia-bra-jogo-lado{flex:1;display:flex;align-items:center;gap:8px}
.bahia-bra-jogo-lado.casa{justify-content:flex-end}
.bahia-bra-jogo-lado.fora{justify-content:flex-start}
.bahia-bra-jogo-sigla{font-size:13px;font-weight:700}
.bahia-bra-jogo-mid{flex:0 0 auto;min-width:64px;text-align:center;font-weight:700;font-size:16px}
.bahia-bra-jogo-x{color:#999;font-weight:400;margin:0 4px}
.bahia-bra-jogo-local{font-size:11px;color:#888;text-transform:uppercase;margin-top:5px;text-align:center}
.bahia-bra-jogo-vivo{display:inline-block;background:var(--lib);color:#fff;font-size:10px;font-weight:700;padding:2px 6px;border-radius:3px}
.bahia-bra-load{color:#999;text-align:center;padding:16px}
.bahia-bra-updated{margin-top:10px;font-size:11px;color:#aaa;text-align:right}
@media(max-width:767px){
  .bahia-bra-tabela .hide-sm{display:none}
  .bahia-bra-tabela th,.bahia-bra-tabela td{padding:7px 4px;font-size:12px}
  .bahia-bra-esc{width:18px;height:18px}
}
</style>

<script>
(function(){
  var root=document.currentScript.closest('.bahia-bra')||document.querySelector('.bahia-bra');
  if(!root||root.dataset.init)return; root.dataset.init='1';
  var endpoint=root.dataset.endpoint, serie=root.dataset.serie, brasaoBase=root.dataset.brasao;
  var dados=null, rodadaAtual=1;
  var corpo=root.querySelector('.bahia-bra-corpo');
  var jogosBox=root.querySelector('.bahia-bra-jogos');
  var titRod=root.querySelector('.bahia-bra-rodada-tit');
  var btnPrev=root.querySelector('.bahia-bra-prev'), btnNext=root.querySelector('.bahia-bra-next');
  var DIAS=['DOM','SEG','TER','QUA','QUI','SEX','SÁB'];

  var zonas = serie==='A' ? {libMax:6, sulMax:12, rebMin:17} : {libMax:4, sulMax:0, rebMin:17};

  function escUrl(slug){ return brasaoBase + encodeURIComponent(slug) + '.png'; }
  function img(slug,nome){ return '<img class="bahia-bra-esc" alt="'+nome+'" src="'+escUrl(slug)+'" onerror="this.style.visibility=\'hidden\'">'; }

  function renderTabela(){
    var arr=dados.classificacao||[], eq=dados.equipes||{}, html='';
    for(var i=0;i<arr.length;i++){
      var c=arr[i], time=eq[c.id]||{}, pos=i+1, zc='';
      if(pos<=zonas.libMax) zc='lib'; else if(zonas.sulMax&&pos<=zonas.sulMax) zc='sul'; else if(pos>=zonas.rebMin) zc='reb';
      var rowc = time.id==30?'bahia':(time.id==21?'vitoria':'');
      html+='<tr class="'+rowc+'">'
        +'<td class="c bahia-bra-pos '+zc+'">'+pos+'</td>'
        +'<td class="c" style="width:26px">'+img(time['nome-slug']||'', time['nome-comum']||'')+'</td>'
        +'<td class="l bahia-bra-time">'+(time['nome-comum']||'')+'</td>'
        +'<td class="c bahia-bra-pts">'+c.pg.total+'</td>'
        +'<td class="c">'+c.j.total+'</td>'
        +'<td class="c">'+c.v.total+'</td>'
        +'<td class="c">'+c.e.total+'</td>'
        +'<td class="c">'+c.d.total+'</td>'
        +'<td class="c hide-sm">'+c.gp.total+'</td>'
        +'<td class="c hide-sm">'+c.gc.total+'</td>'
        +'<td class="c">'+c.sg.total+'</td>'
        +'<td class="c hide-sm">'+c.ap+'</td>'
        +'</tr>';
    }
    corpo.innerHTML=html||'<tr><td colspan="12" class="bahia-bra-load">Sem dados.</td></tr>';
  }

  function fmtData(d){ if(!d)return''; var p=d.split('-'); return p[2]+'/'+p[1]; }
  function diaSemana(d){ if(!d)return'A DEFINIR'; var p=d.split('-'); return DIAS[new Date(p[0],p[1]-1,p[2]).getDay()]; }

  function renderRodada(r){
    rodadaAtual=r;
    titRod.textContent=r+'ª RODADA';
    btnPrev.disabled = r<=1; btnNext.disabled = r>=38;
    var ids=(dados.idJogosPorRodada||{})[r]||[], eq=dados.equipes||{}, html='';
    if(!ids.length){ jogosBox.innerHTML='<p class="bahia-bra-load">Sem jogos nesta rodada.</p>'; return; }
    for(var i=0;i<ids.length;i++){
      var j=dados.jogoPorId[ids[i]], t1=eq[j.time1]||{}, t2=eq[j.time2]||{};
      var p1=j.placar1==null?'':j.placar1, p2=j.placar2==null?'':j.placar2;
      var mid = p1!==''||p2!=='' ? p1+'<span class="bahia-bra-jogo-x">x</span>'+p2 : '<span class="bahia-bra-jogo-x">×</span>';
      html+='<div class="bahia-bra-jogo">'
        +'<div class="bahia-bra-jogo-data">'+diaSemana(j.data)+' '+fmtData(j.data)+(j.horario?' · '+j.horario:'')+'</div>'
        +'<div class="bahia-bra-jogo-row">'
          +'<div class="bahia-bra-jogo-lado casa" title="'+(t1['nome-comum']||'')+'"><span class="bahia-bra-jogo-sigla">'+(t1.sigla||'')+'</span>'+img(t1['nome-slug']||'',t1['nome-comum']||'')+'</div>'
          +'<div class="bahia-bra-jogo-mid">'+mid+'</div>'
          +'<div class="bahia-bra-jogo-lado fora" title="'+(t2['nome-comum']||'')+'">'+img(t2['nome-slug']||'',t2['nome-comum']||'')+'<span class="bahia-bra-jogo-sigla">'+(t2.sigla||'')+'</span></div>'
        +'</div>'
        +'<div class="bahia-bra-jogo-local">'+(j['is-andamento']?'<span class="bahia-bra-jogo-vivo">EM ANDAMENTO</span>':(j.estadio?String(j.estadio).toUpperCase():''))+'</div>'
        +'</div>';
    }
    jogosBox.innerHTML=html;
  }

  btnPrev.addEventListener('click',function(){ if(rodadaAtual>1) renderRodada(rodadaAtual-1); });
  btnNext.addEventListener('click',function(){ if(rodadaAtual<38) renderRodada(rodadaAtual+1); });

  fetch(endpoint+'?serie='+encodeURIComponent(serie),{headers:{'Accept':'application/json'}})
    .then(function(r){return r.json();})
    .then(function(d){
      if(!d||d.error){ corpo.innerHTML='<tr><td colspan="12" class="bahia-bra-load">'+((d&&d.error)||'Erro ao carregar.')+'</td></tr>'; jogosBox.innerHTML='<p class="bahia-bra-load">Indisponível.</p>'; return; }
      dados=d; renderTabela();
      var ra=(d.rodada&&d.rodada.atual)||1; renderRodada(ra);
      var up=root.querySelector('.bahia-bra-updated'); if(d.atualizacao) up.textContent='Atualizado em '+d.atualizacao;
    })
    .catch(function(){ corpo.innerHTML='<tr><td colspan="12" class="bahia-bra-load">Erro ao carregar classificação.</td></tr>'; jogosBox.innerHTML='<p class="bahia-bra-load">Erro ao carregar jogos.</p>'; });
})();
</script>
<?php
    return ob_get_clean();
}
