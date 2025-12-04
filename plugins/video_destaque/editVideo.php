<?php
if($_POST && $page == "editVideo") {
    global $wpdb;
    
    $id = $_GET['id'];
    $url = $_POST['url'];
    $posicao = $_POST['posicao'];
    $nome = $_POST['nome'];
    
    $wpdb->update('wp_video_destaque',
        array('url' => $url, 'posicao' => $posicao, 'nome' => $nome),
        array('id' => $id)
    );
    
    header("Location:" . admin_url('admin.php?page=videos'));
}

function editarVideo(){
    global $wpdb;
            
    $id = $_GET['id'];
    
    $sql = "SELECT ";
    $sql .= "   id, ";
    $sql .= "   url, ";
    $sql .= "   posicao, ";
    $sql .= "   nome ";
    $sql .= "FROM wp_video_destaque ";
    $sql .= "WHERE status IN (0,1) AND id = {$id} ";

    $video = $wpdb->get_row($sql);
    
?>

    <h2 class="ui header">Atualizar Vídeo do Destaque</h2>

    <form class="ui form" action="<?php echo admin_url('admin.php?page=editVideo&id='.$id); ?>" method="post">
        <div class="six wide field">
            <label for="nome">Nome<span class="ui red color">*</span></label>
            <input type="text" name="nome" placeholder="Nome do vídeo" maxlength="55" value="<?=$video->nome?>" required="required">
        </div>
        <div class="six wide field">
            <label for="url">URL<span class="ui red color">*</span></label>
            <input type="text" name="url" placeholder="https://www.youtube.com/embed/x3XgkYetRJo" maxlength="55" value="<?=$video->url?>" required="required">
        </div>
        <div class="six wide field">
            <label for="posicao">Posição<span class="ui red color">*</span></label>
            <select name="posicao" required="required">
                <option value="">--Selecione--</option>
                <option value="1" <?php if($video->posicao == "1"){echo "selected";} ?>>1</option>
                <option value="2" <?php if($video->posicao == "2"){echo "selected";} ?>>2</option>
                <option value="3" <?php if($video->posicao == "3"){echo "selected";} ?>>3</option>
                <option value="4" <?php if($video->posicao == "4"){echo "selected";} ?>>4</option>
            </select>
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

    <img width="350" height="auto" src="http://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2019/06/01183827/posicoes.png" />
<?php
}