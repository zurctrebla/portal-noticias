<?php

if($_POST  && $page == "addVideo") {
    global $wpdb;
    
    $nome = $_POST['nome'];
    $posicao = $_POST['posicao'];
    $url = $_POST['url'];
    
    $wpdb->insert('wp_video_destaque', array(
        'nome' => $nome,
        'posicao' => $posicao,
        'url' => $url,
        'data_criacao' => current_time('mysql'),
        'status' => 0
    ));
    
    header("Location:" . admin_url('admin.php?page=videos'));
}

function adicionarVideo(){
?>
        
    <h2 class="ui header">Novo Vídeo do Destaque</h2>

    <form class="ui form" action="<?php echo admin_url('admin.php?page=addVideo'); ?>" method="post">
        <div class="six wide field">
            <label for="nome">Nome<span class="ui red color">*</span></label>
            <input type="text" name="nome" placeholder="Nome do vídeo" maxlength="55" required="required">
        </div>
        <div class="six wide field">
            <label for="url">URL<span class="ui red color">*</span></label>
            <input type="text" name="url" placeholder="https://www.youtube.com/embed/x3XgkYetRJo" maxlength="55" required="required">
        </div>
        <div class="six wide field">
            <label for="posicao">Posição<span class="ui red color">*</span></label>
            <select name="posicao" required="required">
                <option value="">--Selecione--</option>
                <option value="1">1</option>
                <option value="2">2</option>
                <option value="3">3</option>
                <option value="4">4</option>
            </select>
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

    <img width="350" height="auto" src="http://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2019/06/01183827/posicoes.png" />
<?php
}
   