<?php
$page = "";
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

if ($page == "coberturas" || $page == "listCobertura" || $page == "addCobertura" || $page == "editCobertura") {
    
    require_once( plugin_dir_path(__FILE__) . 'listCobertura.php');
    require_once( plugin_dir_path(__FILE__) . 'addCobertura.php');
    require_once( plugin_dir_path(__FILE__) . 'editCobertura.php');

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
               $(location).attr('href', '<?=admin_url('admin.php?page=coberturas')?>');
               return false;
            });
            
            $(".btnExcluir").click(function(){
                if(confirm("Confirma a exclusão desta Cobertura?")) {
                   var id = $(this).attr('data-id');
                   $(location).attr('href', '<?=plugins_url('actions.php?action=excluir&id=', __FILE__ )?>'+id);
                   return false;
                }
            });
            
            $(".btnStatus").click(function(){
                var status = $(this).attr('data-status');
                var msg;
                
                if(status == 0) {
                    msg = "Deseja ativar esta Cobertura?";
                } else {
                    msg = "Deseja desativar esta Cobertura?";
                }
                
                if(confirm(msg)) {
                   var id = $(this).attr('data-id');
                   $(location).attr('href', '<?=plugins_url('actions.php?action=status&id=', __FILE__ )?>'+id+'&status='+status);
                   return false;
                }
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