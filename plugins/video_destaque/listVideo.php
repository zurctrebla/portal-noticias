<?php
function listarVideos() {
?>
    
    <h2 class="ui header">Vídeos do Destaque</h2>
    
    <a class="ui primary button" href="<?php echo admin_url('admin.php?page=addVideo'); ?>">
        <i class="plus left icon"></i>
        Adicionar
    </a>

    <table class="ui striped table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>URL</th>
                <th>Posição</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            
            $sql = "SELECT ";
            $sql .= "   id, ";
            $sql .= "   nome, ";
            $sql .= "   url, ";
            $sql .= "   posicao, ";
            $sql .= "   data_criacao, ";
            $sql .= "   status ";
            $sql .= "FROM wp_video_destaque ";
            $sql .= "WHERE status IN (0,1) ";
            $sql .= "ORDER BY posicao, data_criacao ";

            $objVideo = $wpdb->get_results($sql);
            
            if($objVideo) {
            
                foreach ($objVideo as $obj) {

                    if($obj->status == 0) {
                        $txtStatus = "Desativado";
                        $tdStatus = "#db2828";
                        $colorBtn = "positive";
                        $iconBtn = "unhide";
                        $txtBtn = "Ativar";
                    } else {
                        $txtStatus = "Ativado";
                        $tdStatus = "#21ba45";
                        $colorBtn = "red";
                        $iconBtn = "ban";
                        $txtBtn = "Desativar";
                    }
                
            ?>
                    <tr>
                        <td><?=$obj->nome?></td>
                        <td><?=$obj->url?></td>
                        <td><?=$obj->posicao?></td>
                        <td style="color: <?=$tdStatus?>; font-weight: 600;"><?=$txtStatus?></td>
                        <td>
                            <button class="ui <?=$colorBtn?> button btnStatus" data-id="<?=$obj->id?>" data-status="<?=$obj->status?>">
                                <i class="<?=$iconBtn?> left icon"></i>
                                <?=$txtBtn?>
                            </button>
                            <a class="ui primary button" href="<?php echo admin_url('admin.php?page=editVideo&id='.$obj->id); ?>">
                                <i class="edit left icon"></i>
                                Editar
                            </a>
                            <a class="ui orange button btnExcluir" data-id="<?=$obj->id?>">
                                <i class="trash left icon"></i>
                                Excluir
                            </a>
                        </td>
                    </tr>
            <?php 
            
                }
            } else {
            ?>
                    <tr>
                        <td colspan="5">Nenhum registro encontrado!</td>
                    </tr>  
            <?php
            }
            ?>
        </tbody>
    </table>
    
<?php
}
   