<?php
/**
 * Plugin Name: Bahia.ba - Tabela do Brasileirão na página /esporte/
 * Description: Tabela COMPACTA de classificação do Brasileirão (mesma API
 *              /bahia-api/brasileirao e escudos locais da página /brasileirao-2026/)
 *              + link "TABELA COMPLETA" -> /brasileirao-2026/ (dentro do Newspaper),
 *              na sidebar do archive de Esporte.
 *              O template de categoria do Newspaper (tdb) descarta shortcodes
 *              arbitrários no conteúdo — então injetamos no rodapé (só no archive
 *              esporte) e movemos o bloco para o topo da sidebar (.td-pb-span4).
 *              JS de render é um init global idempotente (varre .bahia-esp-tab),
 *              chamado no load e após o move.
 *              Shortcode [bahia_brasileirao_tabela] também disponível p/ uso direto.
 * Version: 1.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Botão "TABELA COMPLETA" — componente compartilhado.
 *
 * Usado em dois lugares: no pé da tabela de classificação em /esporte/ e abaixo dos
 * boxes de EC Bahia / EC Vitória (bahia-clubes-sidebar.php). Fica aqui, numa função
 * só, para que rótulo, destino e estilo não divirjam entre os dois pontos.
 */
function bahia_esporte_tabela_link_html() {
    return '<a class="bahia-esp-tab-link" href="' . esc_url(home_url('/brasileirao-2026/')) . '">'
         . 'Tabela completa &rsaquo;</a>';
}

/**
 * CSS do botão acima. Emitido uma vez por requisição, venha de onde vier —
 * /esporte/ imprime junto da tabela, a home junto dos boxes dos clubes.
 */
function bahia_esporte_tabela_link_css() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return '<style>'
         . '.bahia-esp-tab-link{display:block;text-align:center;margin-top:2px;padding:11px 12px;'
         . 'background:#13182B;color:#fff !important;font-weight:700;font-size:12px;'
         . 'text-transform:uppercase;letter-spacing:.5px;text-decoration:none}'
         . '.bahia-esp-tab-link:hover{background:#4371B6}'
         . '</style>';
}

/** HTML do bloco (sem script; o init global cuida da renderização). */
function bahia_esporte_tabela_html() {
    $endpoint    = home_url('/bahia-api/brasileirao');
    $brasao_base = content_url('/themes/bahia_refactor/brasileirao/brasao/');
    ob_start(); ?>
<div class="bahia-esp-tab" data-endpoint="<?php echo esc_url($endpoint); ?>" data-serie="A" data-brasao="<?php echo esc_url($brasao_base); ?>">
  <div class="bahia-esp-tab-head">Brasileirão 2026</div>
  <table class="bahia-esp-tab-table">
    <thead>
      <tr>
        <th class="c" title="Posição">#</th>
        <th class="l" colspan="2">Classificação</th>
        <th class="c" title="Pontos">P</th>
        <th class="c" title="Jogos">J</th>
        <th class="c" title="Saldo de Gols">SG</th>
      </tr>
    </thead>
    <tbody class="bahia-esp-tab-corpo">
      <tr class="bahia-esp-tab-load"><td colspan="6">Carregando classificação…</td></tr>
    </tbody>
  </table>
  <?php echo bahia_esporte_tabela_link_html(); ?>
</div>
<?php
    return bahia_esporte_tabela_link_css() . ob_get_clean();
}

/** Shortcode para uso direto em conteúdo comum. */
add_shortcode('bahia_brasileirao_tabela', function ($atts) {
    $atts = shortcode_atts(array('force' => '0'), $atts, 'bahia_brasileirao_tabela');
    if ($atts['force'] !== '1' && !is_post_type_archive('esporte')) {
        return '';
    }
    return bahia_esporte_tabela_html();
});

/** CSS + init global (uma vez, no rodapé do front-end). */
add_action('wp_footer', 'bahia_esporte_tabela_assets', 4);
function bahia_esporte_tabela_assets() {
    // Só onde a tabela aparece (archive de esporte). Mantém o resto do site limpo.
    if (is_admin() || !is_post_type_archive('esporte')) {
        return;
    }
    ?>
<style>
.bahia-esp-tab{--lib:#1f7a3d;--sul:#e07a1f;--reb:#d23b3b;font-family:inherit;color:#222;margin:0 0 26px}
.bahia-esp-tab *{box-sizing:border-box}
.bahia-esp-tab-head{background:#13182B;color:#fff;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px}
.bahia-esp-tab-table{width:100%;border-collapse:collapse;font-size:12px;background:#fff}
.bahia-esp-tab-table th{background:#f4f5f7;color:#555;font-weight:600;text-transform:uppercase;font-size:10px;letter-spacing:.3px;padding:7px 5px;border-bottom:1px solid #e6e8ec}
.bahia-esp-tab-table td{padding:7px 5px;border-bottom:1px solid #eef0f3;vertical-align:middle}
.bahia-esp-tab-table .c{text-align:center}.bahia-esp-tab-table .l{text-align:left}
.bahia-esp-tab-pos{font-weight:600}
.bahia-esp-tab-pos.lib{box-shadow:inset 3px 0 0 var(--lib)}
.bahia-esp-tab-pos.sul{box-shadow:inset 3px 0 0 var(--sul)}
.bahia-esp-tab-pos.reb{box-shadow:inset 3px 0 0 var(--reb)}
.bahia-esp-tab-esc{width:18px;height:18px;object-fit:contain;vertical-align:middle;display:inline-block}
.bahia-esp-tab-time{font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:inline-block;max-width:108px}
.bahia-esp-tab-pts{font-weight:700}
.bahia-esp-tab-table tr.bahia td{background:rgba(10,88,202,.10)}
.bahia-esp-tab-table tr.vitoria td{background:rgba(200,16,46,.10)}
/* .bahia-esp-tab-link vive em bahia_esporte_tabela_link_css() — componente
   compartilhado com os boxes dos clubes. Não duplicar aqui. */
.bahia-esp-tab-load{color:#888;text-align:center;font-size:12px}
</style>
<script>
window.bahiaEspTabInit = function(){
  var tabs = document.querySelectorAll('.bahia-esp-tab');
  for (var t=0; t<tabs.length; t++){ (function(root){
    if (root.dataset.done) return; root.dataset.done='1';
    var endpoint=root.dataset.endpoint, serie=root.dataset.serie||'A', brasaoBase=root.dataset.brasao;
    var corpo=root.querySelector('.bahia-esp-tab-corpo');
    var zonas = serie==='A' ? {libMax:6,sulMax:12,rebMin:17} : {libMax:4,sulMax:0,rebMin:17};
    function img(slug,nome){ return '<img class="bahia-esp-tab-esc" alt="'+nome+'" src="'+brasaoBase+encodeURIComponent(slug)+'.png" onerror="this.style.visibility=\'hidden\'">'; }
    function render(d){
      var arr=d.classificacao||[], eq=d.equipes||{}, html='';
      for(var i=0;i<arr.length;i++){
        var c=arr[i], time=eq[c.id]||{}, pos=i+1, zc='';
        if(pos<=zonas.libMax)zc='lib'; else if(zonas.sulMax&&pos<=zonas.sulMax)zc='sul'; else if(pos>=zonas.rebMin)zc='reb';
        var rowc=time.id==30?'bahia':(time.id==21?'vitoria':'');
        html+='<tr class="'+rowc+'"><td class="c bahia-esp-tab-pos '+zc+'">'+pos+'</td>'
          +'<td class="c" style="width:24px">'+img(time['nome-slug']||'',time['nome-comum']||'')+'</td>'
          +'<td class="l"><span class="bahia-esp-tab-time">'+(time['nome-comum']||'')+'</span></td>'
          +'<td class="c bahia-esp-tab-pts">'+c.pg.total+'</td><td class="c">'+c.j.total+'</td><td class="c">'+c.sg.total+'</td></tr>';
      }
      corpo.innerHTML=html||'<tr><td colspan="6" class="bahia-esp-tab-load">Sem dados.</td></tr>';
    }
    fetch(endpoint+'?serie='+encodeURIComponent(serie),{headers:{'Accept':'application/json'}})
      .then(function(r){return r.json();})
      .then(function(d){ if(!d||d.error){corpo.innerHTML='<tr><td colspan="6" class="bahia-esp-tab-load">'+((d&&d.error)||'Erro ao carregar.')+'</td></tr>';return;} render(d); })
      .catch(function(){ corpo.innerHTML='<tr><td colspan="6" class="bahia-esp-tab-load">Erro ao carregar classificação.</td></tr>'; });
  })(tabs[t]); }
};
if(document.readyState!=='loading'){window.bahiaEspTabInit();}else{document.addEventListener('DOMContentLoaded',window.bahiaEspTabInit);}
</script>
    <?php
}

/**
 * Injeta a tabela na sidebar do archive de Esporte (o template tdb descarta
 * shortcodes no conteúdo). Renderiza num holder e move p/ o topo da sidebar.
 */
add_action('wp_footer', 'bahia_esporte_tabela_inject', 6);
function bahia_esporte_tabela_inject() {
    if (is_admin() || !is_post_type_archive('esporte')) {
        return;
    }
    // Boxes de EC Bahia / EC Vitória (mesma fonte e formato dos da home), inseridos
    // logo abaixo da classificação. Sem o botão "TABELA COMPLETA": a tabela acima
    // já traz o mesmo botão, e repetir seria redundante.
    $clubes = function_exists('bahia_clubes_sidebar_boxes_html')
        ? bahia_clubes_sidebar_boxes_html(false)
        : '';
    echo '<div id="bahia-esp-tab-holder" style="display:none">'
       . bahia_esporte_tabela_html()
       . $clubes
       . '</div>';
    ?>
<script>
(function(){
  function place(){
    var holder=document.getElementById('bahia-esp-tab-holder');
    var tab=holder&&holder.querySelector('.bahia-esp-tab');
    if(!tab) return;
    var clubes=holder.querySelector('.bahia-cl-sidebar');
    // IMPORTANTE: escopar em .td-main-content-wrap — há vários .td-pb-span4 na página
    // (inclusive no HEADER), e o genérico pegava o do header (tabela ia p/ o topo,
    // acima do logo). Aqui pegamos a sidebar direita do CONTEÚDO do archive.
    var sidebar=document.querySelector('.td-main-content-wrap .td-pb-span4 .vc_column-inner .wpb_wrapper')
             || document.querySelector('.td-main-content-wrap .td-pb-span4 .wpb_wrapper')
             || document.querySelector('.td-main-content-wrap .td-pb-span4');
    if(sidebar){
      sidebar.insertBefore(tab, sidebar.firstChild);
      tab.style.display='';
      // Cards dos clubes logo abaixo da classificação.
      if(clubes){ tab.parentNode.insertBefore(clubes, tab.nextSibling); clubes.style.display=''; }
    }
    if(window.bahiaEspTabInit) window.bahiaEspTabInit();
  }
  if(document.readyState!=='loading'){place();}else{document.addEventListener('DOMContentLoaded',place);}
})();
</script>
    <?php
}
