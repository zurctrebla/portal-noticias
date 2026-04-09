<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

if($_GET['category'] == "brasileirao") {
    $serie = "A";
} elseif ($_GET['category'] == "serie-b") {
    $serie = "B";
}
?>

<script>
var dados = null;
var rodadaAtual = 1;

(function($){
$(document).ready(function () {
    $.ajax({
        method: "GET",
        url: '/api_brasileirao.php',
        data: {serie: '<?=$serie?>'},
        dataType: 'json'
    }).fail(function(xhr, status, error) {
        $(".bodyTableClassificacao .trLoading").hide();
        $(".divRod .trLoading").hide();
        $('.bodyTableClassificacao').append("<tr><td colspan='12' class='center aligned'>Erro ao carregar classificação.</td></tr>");
        $('.divJogosRodadas').html("<p class='center aligned'>Erro ao carregar rodadas.</p>");
        console.error('Erro API Brasileirão:', status, error, xhr.responseText);
    }).done(function( retorno ) {
        var i;
        dados = retorno;

        if (dados.error) {
            $(".bodyTableClassificacao .trLoading").hide();
            $(".divRod .trLoading").hide();
            $('.bodyTableClassificacao').append("<tr><td colspan='12' class='center aligned'>" + dados.error + "</td></tr>");
            return;
        }
        
        /******* INICIO CLASSIFICAÇÃO ********/
        <?php
        if($serie == "A"):
        ?>
            var libertadores = "1-6";
        <?php
        else:
        ?>
            var libertadores = "1-4";
        <?php
        endif;
        ?>
        //var libertadores = dados['faixasClassificacao']["classifica1"]['faixa'];
        
        var sulMax = 0;
        <?php if($serie == "A") { ?>
            var sulamericana = "7-12";
            //var sulamericana = dados['faixasClassificacao']["classifica2"]['faixa'];
            var rebaixamento = dados['faixasClassificacao']["classifica3"]['faixa'];
            var sulMax = sulamericana.split('-')[1];
        <?php } else { ?>
            var rebaixamento = dados['faixasClassificacao']["classifica3"]['faixa'];
        <?php } ?>
        
        var libMax = libertadores.split('-')[1];
        var rebMin = rebaixamento.split('-')[0];
        
        var arr = dados["classificacao"];
        
        for(i = 0; i < arr.length; i++){
            var idTime = arr[i].id;
            var body, color;
            
            if(i + 1 <=  libMax) {
                color = 'destaqueLibertadores';
            } else if(i + 1 <= sulMax) {
                color = 'destaqueSulAmericana';
            } else if(i + 1 >=  rebMin) {
                color = 'destaqueRebaixamento';
            } else {
                color = '';
            }
            
            var destaqueBaVi = "";
            var destaque = "";
            if(dados['equipes'][idTime]["id"] == 30) {
                destaqueBaVi = "destaqueBaVi";
                destaque = "destaqueBahia";
            }
            if(dados['equipes'][idTime]["id"] == 21) {
                destaqueBaVi = "destaqueBaVi";
                destaque = "destaqueVitoria";
            }
            
            var brasao = '<?php bloginfo('template_url');?>/brasileirao/brasao/'+dados['equipes'][idTime]['nome-slug']+".png";
            
            body = "<tr class='"+destaqueBaVi+" "+destaque+"'>";
            body += "<td class='center aligned "+color+"' title='Posição'>"+(i+1)+"</td>";
            body += "<td class='tdBrasao'><img width='30px' src='"+brasao+"'></td>";
            body += "<td>"+dados['equipes'][idTime]['nome-comum']+"</td>";
            body += "<td class='center aligned' title='Pontos'>"+dados['classificacao'][i].pg.total+"</td>";
            body += "<td class='center aligned' title='Jogos'>"+dados['classificacao'][i].j.total+"</td>";
            body += "<td class='center aligned' title='Vitórias'>"+dados['classificacao'][i].v.total+"</td>";
            body += "<td class='center aligned' title='Empates'>"+dados['classificacao'][i].e.total+"</td>";
            body += "<td class='center aligned' title='Derrotas'>"+dados['classificacao'][i].d.total+"</td>";
            body += "<td class='center aligned' title='Gols Pró'>"+dados['classificacao'][i].gp.total+"</td>";
            body += "<td class='center aligned' title='Gols Contra'>"+dados['classificacao'][i].gc.total+"</td>";
            body += "<td class='center aligned' title='Saldo de Gols'>"+dados['classificacao'][i].sg.total+"</td>";
            body += "<td class='center aligned' title='Aproveitamento'>"+dados['classificacao'][i].ap+"</td>";
            body += "</tr>";
            
            $('.bodyTableClassificacao').append(body);
            $(".bodyTableClassificacao .trLoading").hide();
        }
        /******* FIM CLASSIFICAÇÃO ********/
        
        
        /******* INICIO RODADAS ********/
        var rodadaAtual = dados["rodada"]["atual"];
        
        if(rodadaAtual == null) {
            rodadaAtual = 1;
        }
        
        $(".btnRodadaLeft").click(function(){
            if(rodadaAtual != 1) {
                imprimirRodada(--rodadaAtual);
            }
        });
//        
        $(".btnRodadaRight").click(function(){
            if(rodadaAtual != 38) {
                imprimirRodada(++rodadaAtual);
            }
        });
        
        /******* FIM RODADAS ********/
        
        imprimirRodada(rodadaAtual);
        
        
    });
});

function imprimirRodada(rodada) {
        
    $("#tituloRodada").html(rodada + "ª RODADA");
        
    $('.divJogosRodadas').html("");
    
    if(rodada == 1) {
        $(".iconRodadaLeft").addClass("colorGrey");
        $(".iconRodadaLeft").removeClass("link");
    } else {
        $(".iconRodadaLeft").removeClass("colorGrey");
        $(".iconRodadaLeft").addClass("link");
    }

    if(rodada == 38) {
        $(".iconRodadaRight").addClass("colorGrey");
        $(".iconRodadaRight").removeClass("link");
    } else {
        $(".iconRodadaRight").removeClass("colorGrey");
        $(".iconRodadaRight").addClass("link");
    }

    var jogosRodadaAtual = dados["idJogosPorRodada"][rodada];

    for(i = 0; i < jogosRodadaAtual.length; i++){
        var jogo = dados['jogoPorId'][jogosRodadaAtual[i]];
        var andamento = false;
        
        if(typeof jogo['is-andamento'] !== 'undefined') {
            andamento = true;
        }

        var placar1 = jogo.placar1;
        if(placar1 == null) {
            placar1 = "";
        }

        var placar2 = jogo.placar2;
        if(placar2 == null) {
            placar2 = "";
        }

        var brasaoTime1 = '<?php bloginfo('template_url');?>/brasileirao/brasao/'+dados['equipes'][jogo.time1]['nome-slug']+".png";
        var brasaoTime2 = '<?php bloginfo('template_url');?>/brasileirao/brasao/'+dados['equipes'][jogo.time2]['nome-slug']+".png";

        var jogosRodada;

        jogosRodada = "<div class='divDataJogo'>";
        jogosRodada += getDiaExtenso(jogo.data) + " " + formatarData(jogo.data);
        if(jogo.horario) {
            jogosRodada += " - " + jogo.horario;
        }
        jogosRodada += "</div>";

        var svgX = "<svg width='14' height='14' viewBox='0 0 10 10' fill='none' xmlns='http://www.w3.org/2000/svg'><path d='M5 3.88906L8.88906 0L10 1.11094L6.11094 5L10 8.88906L8.88906 10L5 6.11094L1.11094 10L0 8.88906L3.88906 5L0 1.11094L1.11094 0L5 3.88906Z' fill='#666666'/></svg>";
        var separador = placar1 !== "" ? "<span class='spanPlacar1'>"+placar1+"</span><span style='margin:0 6px;font-size:20px;font-weight:700;color:#333;'>x</span><span class='spanPlacar2'>"+placar2+"</span>" : svgX;

        jogosRodada += "<div style='display:flex;align-items:center;width:100%;'>";

        jogosRodada += "<div style='flex:1;display:flex;align-items:center;justify-content:flex-end;' title='"+dados['equipes'][jogo.time1]['nome-comum']+"'>";
        jogosRodada += "<span style='font-size:14px;font-weight:600;margin-right:10px;'>"+dados['equipes'][jogo.time1]['sigla']+"</span>";
        jogosRodada += "<img width='32' height='32' style='margin-right:10px;' src='"+brasaoTime1+"'>";
        jogosRodada += "</div>";

        jogosRodada += "<div style='flex:0 0 auto;display:flex;align-items:center;justify-content:center;min-width:60px;text-align:center;'>"+separador+"</div>";

        jogosRodada += "<div style='flex:1;display:flex;align-items:center;justify-content:flex-start;' title='"+dados['equipes'][jogo.time2]['nome-comum']+"'>";
        jogosRodada += "<img width='32' height='32' style='margin-left:10px;' src='"+brasaoTime2+"'>";
        jogosRodada += "<span style='font-size:14px;font-weight:600;margin-left:10px;'>"+dados['equipes'][jogo.time2]['sigla']+"</span>";
        jogosRodada += "</div>";

        jogosRodada += "</div>";

        jogosRodada += "<div class='divLocalJogo'>";
        if(andamento) {
            jogosRodada += "<div class='ui mini green label'>EM ANDAMENTO</div>";
        } else if(jogo.estadio == null) {
            // sem info de local
        } else {
            jogosRodada += jogo.estadio.toUpperCase();
        }
        jogosRodada += "</div>";

        jogosRodada += "<div class='ui divider'></div>";

        $('.divJogosRodadas').append(jogosRodada);
        $(".divRod .trLoading").hide();

    }
}

function getDiaExtenso(dataJogo) {
    if(dataJogo != null) {
        var p = dataJogo.split('-');
        var dataJg = new Date(p[0], p[1] - 1, p[2]); // parse como local, sem UTC shift
        var arrayDia = ["DOM", "SEG", "TER", "QUA", "QUI", "SEX", "SÁB"]; // 0=Dom..6=Sab
        return arrayDia[dataJg.getDay()];
    } else {
        return "A DEFINIR DATA";
    }
}

function formatarData(data) {
    if(data != null) {
        var split = data.split('-');
        var novaData = split[2] + "/" +split[1];
        return novaData;
    } else {
        return "";
    }
}
})(jQuery);
</script>


<div class="ui grid">
    <div class="eleven wide column">
        
        <table class="ui selectable striped table tableClassificacao">
            <thead>
                <tr>
                    <th class='tdPosicao center aligned' title="Posição">#</th>
                    <th class='tdClassificao' colspan="2">CLASSIFICAÇÃO</th>
                    <th class='tdPontos center aligned' title="Pontos">P</th>
                    <th class='tdJogos center aligned' title="Jogos">J</th>
                    <th class='tdVitorias center aligned' title="Vitórias">V</th>
                    <th class='tdEmpates center aligned' title="Empates">E</th>
                    <th class='tdDerrotas center aligned' title="Derrotas">D</th>
                    <th class='tdGolsPro center aligned' title="Gols Pró">GP</th>
                    <th class='tdGolsContra center aligned' title="Gols Contra">GC</th>
                    <th class='tdSaldoGols center aligned' title="Saldo de Gols">SG</th>
                    <th class='tdAproveitamento center aligned' title="Aproveitamento">%</th>
                </tr>
            </thead>
            <tbody class="bodyTableClassificacao">
                <tr class="trLoading"><td colspan="12"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></td></tr>
            </tbody>
            <tfoot class="footTableClassificacao">
                <tr style="border-bottom: 0px;">
                    <th colspan="12" class="top aligned">
                        <label style="padding-left: 10px; padding-right: 30px"><a class="ui blue empty circular label" ></a> <?php if($serie == "A") { echo "Copa Libertadores"; } elseif ($serie == "B") {echo "Acesso à Série A";}?></label>
                        <?php if($serie == "A") { ?>
                            <label style="padding-right: 30px"><a class="ui orange empty circular label"></a> Copa Sul-americana</label>
                        <?php } ?>
                        <a class="ui red empty circular label"></a> Rebaixamento
                    </th>
                </tr>
                <tr>
                    <th colspan="12" class="top aligned">
                        <label style="padding-left: 10px; font-weight: 500;">P</label><label style="padding-right: 30px"> - Pontos</label>
                        <label style="font-weight: 500;">J</label><label style="padding-right: 30px;"> - Jogos</label>
                        <label style="font-weight: 500;">V</label><label style="padding-right: 30px"> - Vitórias</label>
                        <label style="font-weight: 500;">E</label><label style="padding-right: 30px"> - Empates</label>
                        <label style="font-weight: 500;">D</label><label style="padding-right: 30px"> - Derrotas</label>
                        <label style="font-weight: 500;">GP</label><label style="padding-right: 30px"> - Gols Pró</label>
                        <label style="font-weight: 500;">GC</label><label style="padding-right: 30px"> - Gols Contra</label> <br><br>
                        <label style="padding-left: 10px; font-weight: 500;">SG</label><label style="padding-right: 30px"> - Saldo de Gols</label>
                        <label style="font-weight: 500;">%</label><label style="padding-right: 30px"> - Aproveitamento</label>
                    </th>
                </tr>
            </tfoot>
        </table>
        
        
    </div>
    <div class="five wide column divRod">
        <table class="ui table tableNumRodada">
            <thead>
                <tr>
                    <th class='left aligned btnRodadaLeft'><i class="chevron link left icon iconRodadaLeft"></i></th>
                    <th class='center aligned' id="tituloRodada">1ª RODADA</th>
                    <th class='right aligned btnRodadaRight'><i class="chevron link right icon iconRodadaRight"></i></th>
                </tr>
            </thead>
        </table>
        
        
        <div class="trLoading"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></div>
        <div class="divJogosRodadas"></div>
        
</div>


