<?php

if($_POST  && $page == "addCobertura") {
    global $wpdb;
    
    $titulo = $_POST['titulo'];
    $chamada = $_POST['chamada'];
    $descricao = $_POST['descricao'];
    
    $wpdb->insert('wp_coberturas', array(
        'titulo' => $titulo,
        'chamada' => $chamada,
        'descricao' => $descricao,
        'data_criacao' => current_time('mysql'),
        'status' => 0
    ));
    
    header("Location:" . admin_url('admin.php?page=coberturas'));
}

function adicionarCobertura(){
?>
        
    <h2 class="ui header">Nova Cobertura</h2>

    <form class="ui form" action="<?php echo admin_url('admin.php?page=addCobertura'); ?>" method="post">
        <div class="six wide field">
            <label for="titulo">Título <span class="ui red color">*</span></label>
            <input type="text" name="titulo" placeholder="Título" maxlength="55" required="required">
        </div>
        <div class="six wide field">
            <label for="chamada">Chamada <span class="ui red color">*</span></label>
            <input type="text" name="chamada" placeholder="Chamada" maxlength="55" required="required">
        </div>
        <div class="six wide field">
            <label for="descricao">Descrição</label>
            <textarea type="text" name="descricao" maxlength="500" placeholder="Descrição detalhada da cobertura"></textarea>
        </div>
        <button class="ui red button" id="btnVoltar">
            <i class="chevron left icon"></i>
            Voltar
        </button>
        <button type="submit" class="ui primary button" id="btnSalvar">
            <i class="save icon"></i>
            Salvar
        </button>
    </form>

<?php
}
   