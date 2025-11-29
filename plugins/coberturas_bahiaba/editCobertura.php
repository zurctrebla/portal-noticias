<?php
if($_POST && $page == "editCobertura") {
    global $wpdb;
    
    $id = $_GET['id'];
    $titulo = $_POST['titulo'];
    $chamada = $_POST['chamada'];
    $descricao = $_POST['descricao'];
    
    $wpdb->update('wp_coberturas',
        array('titulo' => $titulo, 'chamada' => $chamada, 'descricao' => $descricao),
        array('id' => $id)
    );
    
    header("Location:" . admin_url('admin.php?page=coberturas'));
}

function editarCobertura(){
    global $wpdb;
            
    $id = $_GET['id'];
    
    $sql = "SELECT ";
    $sql .= "   id, ";
    $sql .= "   titulo, ";
    $sql .= "   chamada, ";
    $sql .= "   descricao ";
    $sql .= "FROM wp_coberturas ";
    $sql .= "WHERE status IN (0,1) AND id = {$id} ";

    $cobertura = $wpdb->get_row($sql);
?>

    <h2 class="ui header">Atualizar Cobertura</h2>

    <form class="ui form" action="<?php echo admin_url('admin.php?page=editCobertura&id='.$id); ?>" method="post">
        <div class="six wide field">
            <label for="titulo">Título <span class="ui red color">*</span></label>
            <input type="text" name="titulo" placeholder="Título" maxlength="55" value="<?=$cobertura->titulo?>" required="required">
        </div>
        <div class="six wide field">
            <label for="chamada">Chamada <span class="ui red color">*</span></label>
            <input type="text" name="chamada" placeholder="Chamada" maxlength="55" value="<?=$cobertura->chamada?>" required="required">
        </div>
        <div class="six wide field">
            <label for="descricao">Descrição</label>
            <textarea type="text" name="descricao" maxlength="500" placeholder="Descrição detalhada da cobertura"><?=$cobertura->descricao?></textarea>
        </div>
        <button class="ui red button" id="btnVoltar">
            <i class="chevron left icon"></i>
            Voltar
        </button>
        <button type="submit" class="ui primary button" id="btnSalvar">
            <i class="save icon"></i>
            Atualizar
        </button>
    </form>

<?php
}