<?php
$page = "";
if(isset($_GET['page'])) {
    $page = $_GET['page'];
}

if ($page == "videos" || $page == "listVideo" || $page == "addVideo" || $page == "editVideo") {
    
    require_once( plugin_dir_path(__FILE__) . 'listVideo.php');
    require_once( plugin_dir_path(__FILE__) . 'addVideo.php');
    require_once( plugin_dir_path(__FILE__) . 'editVideo.php');

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
               $(location).attr('href', '<?=admin_url('admin.php?page=videos')?>');
               return false;
            });
            
            $(".btnExcluir").click(function(){
                if(confirm("Confirma a exclusão deste Vídeo?")) {
                   var id = $(this).attr('data-id');
                   $(location).attr('href', '<?=plugins_url('actions.php?action=excluir&id=', __FILE__ )?>'+id);
                   return false;
                }
            });
            
            $(".btnStatus").click(function(){
                var status = $(this).attr('data-status');
                var msg;
                
                if(status == 0) {
                    msg = "Deseja ativar este Vídeo?";
                } else {
                    msg = "Deseja desativar este Vídeo?";
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
        });
    </script>
<?php
}