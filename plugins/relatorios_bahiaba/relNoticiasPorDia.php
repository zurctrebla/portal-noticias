<?php
function showNoticiasPorDia(){
    global $wpdb;
?>
    <h2 class="ui header">Notícias</h2>

    <form class="ui form formHistoricoDestaques" action="<?=plugins_url('csvNoticiasPorDia.php', __FILE__ )?>" method="GET">
        <div class="two wide field">
            <label>Data Início</label>
            <div class="ui calendar datas">
                <div class="ui input left icon">
                    <i class="calendar icon"></i>
                    <input type="text" id="dataInicio" name="dataInicio" placeholder="Data Início" readonly="readonly">
                </div>
            </div>
        </div>
        <div class="two wide field">
            <label>Data Fim</label>
            <div class="ui calendar datas">
                <div class="ui input left icon">
                    <i class="calendar icon"></i>
                    <input type="text" id="dataFim" name="dataFim" placeholder="Data Fim" readonly="readonly">
                </div>
            </div>
        </div>
        <button class="ui red button" id="btnVoltar">
            <i class="chevron left icon"></i>
            Voltar
        </button>
        <button type="submit" class="ui primary button" id="btnGerarHistoricoDestaques">
            <i class="download icon"></i>
            Download
        </button>
    </form>
<?php
}