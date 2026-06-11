<?php require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php'); ?>

<style>
    .copaGrupos { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 30px; }
    .copaGrupo { border: 1px solid #e0e0e0; border-radius: 4px; padding: 10px; }
    .copaGrupo h3 { margin: 0 0 10px; font-size: 16px; color: #15559e; }
    .copaGrupo table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .copaGrupo th, .copaGrupo td { padding: 4px 6px; border-bottom: 1px solid #f0f0f0; text-align: center; }
    .copaGrupo th { background: #f7f7f7; font-weight: 600; }
    .copaGrupo td.timeNome { text-align: left; }
    .copaGrupo img.crest { width: 18px; height: 18px; vertical-align: middle; margin-right: 6px; }
    .copaJogos { margin-top: 20px; }
    .copaJogo { display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
    .copaJogo .jogoData { flex: 0 0 110px; font-size: 12px; color: #666; }
    .copaJogo .jogoTime { flex: 1; display: flex; align-items: center; }
    .copaJogo .jogoTime.right { justify-content: flex-end; }
    .copaJogo .jogoTime img { width: 24px; height: 24px; margin: 0 6px; }
    .copaJogo .jogoPlacar { flex: 0 0 70px; text-align: center; font-weight: 700; }
    .copaJogo .jogoFase { flex: 0 0 100px; text-align: right; font-size: 11px; color: #999; text-transform: uppercase; }
    .copaTitulo { margin: 0 0 15px; color: #15559e; }
</style>

<div class="ui grid">
    <div class="sixteen wide column">
        <h2 class="copaTitulo">Copa do Mundo 2026 — Grupos</h2>
        <div class="copaGrupos" id="copaGrupos">
            <div class="trLoading"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></div>
        </div>

        <h2 class="copaTitulo">Jogos</h2>
        <div class="copaJogos" id="copaJogos">
            <div class="trLoading"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></div>
        </div>
    </div>
</div>

<script>
(function($){
    $(document).ready(function () {
        $.ajax({
            method: "GET",
            url: '/api_copa_mundo.php',
            dataType: 'json'
        }).fail(function(xhr, status, error) {
            $('#copaGrupos').html("<p class='center aligned'>Erro ao carregar grupos.</p>");
            $('#copaJogos').html("<p class='center aligned'>Erro ao carregar jogos.</p>");
            console.error('Erro API Copa do Mundo:', status, error, xhr.responseText);
        }).done(function (dados) {
            if (!dados || dados.error) {
                $('#copaGrupos').html("<p class='center aligned'>" + (dados && dados.error ? dados.error : 'Sem dados disponíveis.') + "</p>");
                $('#copaJogos').html("");
                return;
            }

            // Grupos
            var htmlGrupos = '';
            (dados.grupos || []).forEach(function (g) {
                htmlGrupos += "<div class='copaGrupo'>";
                htmlGrupos += "<h3>" + g.nome + "</h3>";
                htmlGrupos += "<table><thead><tr><th>#</th><th style='text-align:left'>Seleção</th><th>P</th><th>J</th><th>SG</th></tr></thead><tbody>";
                (g.classificacao || []).forEach(function (t, i) {
                    var crest = t.crest ? "<img class='crest' src='" + t.crest + "' alt=''>" : "";
                    htmlGrupos += "<tr>";
                    htmlGrupos += "<td>" + (i + 1) + "</td>";
                    htmlGrupos += "<td class='timeNome'>" + crest + t.nome + "</td>";
                    htmlGrupos += "<td>" + t.pg + "</td>";
                    htmlGrupos += "<td>" + t.j + "</td>";
                    htmlGrupos += "<td>" + t.sg + "</td>";
                    htmlGrupos += "</tr>";
                });
                htmlGrupos += "</tbody></table></div>";
            });
            $('#copaGrupos').html(htmlGrupos || "<p>Grupos ainda não definidos.</p>");

            // Jogos
            var htmlJogos = '';
            (dados.jogos || []).forEach(function (j) {
                var placar = (j.placar1 !== null && j.placar2 !== null)
                    ? j.placar1 + " x " + j.placar2
                    : "vs";
                var dataFmt = formatarDataCopa(j.data) + (j.horario ? " - " + j.horario : "");
                var fase = j.grupo ? j.grupo : (j.fase || '').replace(/_/g, ' ');
                var crest1 = j.crest1 ? "<img src='" + j.crest1 + "' alt=''>" : "";
                var crest2 = j.crest2 ? "<img src='" + j.crest2 + "' alt=''>" : "";

                htmlJogos += "<div class='copaJogo'>";
                htmlJogos += "<div class='jogoData'>" + dataFmt + "</div>";
                htmlJogos += "<div class='jogoTime right'><span>" + j.time1 + "</span>" + crest1 + "</div>";
                htmlJogos += "<div class='jogoPlacar'>" + placar + "</div>";
                htmlJogos += "<div class='jogoTime'>" + crest2 + "<span>" + j.time2 + "</span></div>";
                htmlJogos += "<div class='jogoFase'>" + fase + "</div>";
                htmlJogos += "</div>";
            });
            $('#copaJogos').html(htmlJogos || "<p>Sem jogos disponíveis.</p>");
        });
    });

    function formatarDataCopa(data) {
        if (!data) return "";
        var p = data.split('-');
        return p[2] + "/" + p[1] + "/" + p[0];
    }
})(jQuery);
</script>
