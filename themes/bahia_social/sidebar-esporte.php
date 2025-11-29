<script>
var dados = null;
var rodadaAtual = 1;

$(document).ready(function () {
    $.ajax({
        method: "GET",
        url: '/api_brasileirao.php',
        data: {serie: 'A'},
        dataType: 'json'
    }).done(function( retorno ) {
        var i;
        dados = retorno;
        
        /******* INICIO CLASSIFICAÇÃO ********/
        var arr = dados["classificacao"];
        for(i = 0; i < arr.length; i++){
            var idTime = arr[i].id;
            if(i <= 2 || dados['equipes'][idTime]["id"] == 30 || dados['equipes'][idTime]["id"] == 21) {
                var body, color;
                color = '';

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
                body += "<td class='center aligned' title='Saldo de Gols'>"+dados['classificacao'][i].sg.total+"</td>";
                body += "</tr>";
                
                $('.bodyTableClassificacao').append(body);
                $(".bodyTableClassificacao .trLoading").hide();
                
            }
            
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
        
        if(jogo.time1 == 30 || jogo.time1 == 21 || jogo.time2 == 30 || jogo.time2 == 21) {

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

            jogosRodada += "<div class='divTime1' title='"+dados['equipes'][jogo.time1]['nome-comum']+"'>";
            jogosRodada += "<div class='divNomeTime1'>";
            jogosRodada += dados['equipes'][jogo.time1]['sigla'];
            jogosRodada += "</div>";

            jogosRodada += "<div class='divBrasaoTime1'>";
            jogosRodada += "<img width='30px' src='"+brasaoTime1+"'>";
            jogosRodada += "</div>";
            jogosRodada += "</div>";

            jogosRodada += "<div class='divPlacar'>";
            jogosRodada += "<span class='spanPlacar1'>"+placar1+"</span>";
            jogosRodada += "<span class='spanVersus'>-</span>";
            jogosRodada += "<span class='spanPlacar2'>"+placar2+"</span>";
            jogosRodada += "</div>";

            jogosRodada += "<div class='divTime2' title='"+dados['equipes'][jogo.time2]['nome-comum']+"'>";
            jogosRodada += "<div class='divNomeTime2'>";
            jogosRodada += "<img width='30px' src='"+brasaoTime2+"'>";
            jogosRodada += "</div>";
            jogosRodada += "<div class='divBrasaoTime2'>";
            jogosRodada += dados['equipes'][jogo.time2]['sigla'];
            jogosRodada += "</div>";

            jogosRodada += "</div>";

            jogosRodada += "<div style='clear: both;'></div>";

            jogosRodada += "<div class='divLocalJogo'>";
            if(andamento) {
                jogosRodada += "<div class='ui mini green label'>EM ANDAMENTO</div>";
            } else if(jogo.estadio == null) {
                jogosRodada += "A DEFINIR LOCAL";
            } else {
                jogosRodada += jogo.estadio.toUpperCase();
            }
            jogosRodada += "</div>";

            jogosRodada += "<div class='ui divider'></div>";

            $('.divJogosRodadas').append(jogosRodada);
            $(".divRod .trLoading").hide();
        }

    }
}


function getDiaExtenso(dataJogo) {
    if(dataJogo != null) {
        var dataJg = new Date(dataJogo);
        var arrayDia = new Array(7);

        arrayDia[0] = "SEG";
        arrayDia[1] = "TER";
        arrayDia[2] = "QUA";
        arrayDia[3] = "QUI";
        arrayDia[4] = "SEX";
        arrayDia[5] = "SÁB";
        arrayDia[6] = "DOM";

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
</script>

<aside>
    <div class="box-news-int">

        <label class="tituloBrasileirao2017"><a href="?category=brasileirao">BRASILEIRÃO 2024</a></label>
        <div class="ui divider" style="margin-top: 4px;"></div>
        <table class="ui striped table tableClassificacao">
            <thead>
                <tr>
                    <th class='tdPosicao center aligned' title="Posição">#</th>
                    <th class='tdClassificao' colspan="2">CLASSIFICAÇÃO</th>
                    <th class='tdPontos center aligned' title="Pontos">P</th>
                    <th class='tdJogos center aligned' title="Jogos">J</th>
                    <th class='tdVitorias center aligned' title="Vitórias">V</th>
                    <th class='tdSaldoGols center aligned' title="Saldo de Gols">SG</th>
                </tr>
            </thead>
            <tbody class="bodyTableClassificacao">
                <tr class="trLoading"><td colspan="12"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></td></tr>
            </tbody>
            <tfoot class="footTableClassificacao">
                <tr>
                    <th colspan="7" class="left aligned">
                        <label class="tituloTabelaCompleta"><a href="?category=brasileirao"><i class="list layout icon"></i> TABELA COMPLETA</a></label>
                    </th>
                </tr>
            </tfoot>
        </table>

    </div>
    
    <div class="box-news-int divRod">
        
        <table class="ui table tableNumRodada">
            <thead>
                <tr>
                    <th class='left aligned btnRodadaLeft'><i class="chevron left icon iconRodadaLeft"></i></th>
                    <th class='center aligned' id="tituloRodada">1ª RODADA</th>
                    <th class='right aligned btnRodadaRight'><i class="chevron right icon iconRodadaRight"></i></th>
                </tr>
            </thead>
        </table>
        
        <div class="trLoading"><img class="imgLoading" src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif"></div>
        <div class="divJogosRodadas"></div>
        
    </div>

</aside>