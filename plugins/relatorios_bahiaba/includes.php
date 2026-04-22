<?php
require_once( plugin_dir_path(__FILE__) . 'relHistoricoDestaques.php');
require_once( plugin_dir_path(__FILE__) . 'relNoticiasPorDia.php');

$page = "";
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}
if ($page == "relatorios" || $page == "relHistoricoDestaques" || $page == "relNoticiasPorDia") {
?>
    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_url'); ?>/semantic-ui/dist/semantic.min.css">
    <link href="<?=plugins_url( 'calendar/calendar.min.css', __FILE__ )?>" rel="stylesheet" type="text/css" />

    <script src="<?php bloginfo('template_url'); ?>/semantic-ui/dist/jquery-2.2.3.min.js"></script>
    <script src="<?php bloginfo('template_url'); ?>/semantic-ui/dist/semantic.min.js"></script>
    <script src="<?=plugins_url( 'calendar/calendar.min.js', __FILE__ )?>"></script>

    <style>
        body {
            background: #F1F1F1; 
        }
        .divRelatorios {
            margin-top: 20px;
        }
        .wp-menu-name {
            font-family: "Open Sans",sans-serif;
            font-size: 14px;
        }
    </style>
    <script>
        $(document).ready(function () {

            $("#btnVoltar").click(function(){
               $(location).attr('href', '<?=admin_url('admin.php?page=relatorios')?>');
               return false;
            });

            $('.ui.cmbPosicaoDestaques').dropdown({
                message: {
                    noResults: 'Nenhum resultado'
                }
            });

            $('.datas').calendar({
              type: 'date',
              today: true, 
              closable: true, 
              text: {
                days: ['D', 'S', 'T', 'Q', 'Q', 'S', 'S'],
                months: ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'],
                monthsShort: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'],
                today: 'Hoje'
              },
              formatter: {
                date: function (date, settings) {
                  if (!date) return '';
                  var day = date.getDate();
                  if(day < 10) {
                      day = "0"+day;
                  }
                  var month = date.getMonth() + 1;
                  if(month < 10) {
                      month = "0"+month;
                  }
                  var year = date.getFullYear();
                  return day + '/' + month + '/' + year;
                }
              }
            });

        });
    </script>
<?php
}