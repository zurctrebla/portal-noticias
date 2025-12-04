<?php
function listarCoberturas() {
?>
    
    <h2 class="ui header">Coberturas do Bahia.ba</h2>
    
    <a class="ui primary button" href="<?php echo admin_url('admin.php?page=addCobertura'); ?>">
        <i class="plus left icon"></i>
        Adicionar
    </a>

    <table class="ui striped table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Chamada</th>
                <th>Status</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php
            global $wpdb;
            
            $sql = "SELECT ";
            $sql .= "   id, ";
            $sql .= "   titulo, ";
            $sql .= "   chamada, ";
            $sql .= "   descricao, ";
            $sql .= "   data_criacao, ";
            $sql .= "   status ";
            $sql .= "FROM wp_coberturas ";
            $sql .= "WHERE status IN (0,1) ";
            $sql .= "ORDER BY data_criacao ";

            $objCobertura = $wpdb->get_results($sql);
            
            if($objCobertura) {
            
                foreach ($objCobertura as $obj) {

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
                        <td><?=$obj->titulo?></td>
                        <td><?=$obj->chamada?></td>
                        <td style="color: <?=$tdStatus?>; font-weight: 600;"><?=$txtStatus?></td>
                        <td>
                            <button class="ui <?=$colorBtn?> button btnStatus" data-id="<?=$obj->id?>" data-status="<?=$obj->status?>">
                                <i class="<?=$iconBtn?> left icon"></i>
                                <?=$txtBtn?>
                            </button>
                            <a class="ui primary button" href="<?php echo admin_url('admin.php?page=editCobertura&id='.$obj->id); ?>">
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
                        <td colspan="4">Nenhum registro encontrado!</td>
                    </tr>  
            <?php
            }
            ?>
        </tbody>
    </table>
    
<?php
}
   