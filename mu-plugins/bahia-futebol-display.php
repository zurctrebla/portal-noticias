<?php
/**
 * Plugin Name: Bahia Futebol Display
 * Description: Shortcode [bahia_brasileirao] que renderiza a tabela de classificação + jogos
 *   por rodada do Brasileirão Série A, consumindo o endpoint /bahia-api/brasileirao
 *   (mu-plugin api_brasileirao.php, fonte football-data.org). Autossuficiente: CSS próprio
 *   (sem Semantic UI) e JS vanilla (fetch). Responsivo (desktop + mobile).
 *
 * Escudos: reutiliza os PNGs já versionados em themes/bahia_refactor/brasileirao/brasao/.
 * Copa do Mundo 2026: desabilitada (item de menu removido); não há shortcode de Copa.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('bahia_brasileirao', 'bahia_brasileirao_shortcode');
function bahia_brasileirao_shortcode($atts) {
    $atts = shortcode_atts(array('serie' => 'A'), $atts, 'bahia_brasileirao');
    $serie = strtoupper($atts['serie']) === 'B' ? 'B' : 'A';
    $endpoint = home_url('/bahia-api/brasileirao');
    $brasao_base = content_url('/themes/bahia_refactor/brasileirao/brasao/');

    ob_start(); ?>
<div class="bahia-bra" data-endpoint="<?php echo esc_url($endpoint); ?>" data-serie="<?php echo esc_attr($serie); ?>" data-brasao="<?php echo esc_url($brasao_base); ?>">
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
