<?php 
get_header(); 

global $wpdb;

$sql = "SELECT ";
$sql .= "   titulo, ";
$sql .= "   chamada ";
$sql .= "FROM wp_coberturas ";
$sql .= "WHERE status = 1 ";
$sql .= "ORDER BY data_criacao ";

$objCobertura = $wpdb->get_results($sql);

// $sql2 = "SELECT ";
// $sql2 .= "   url_arquivo ";
// $sql2 .= "FROM wp_popups ";
// $sql2 .= "WHERE status = 1 ";
// $sql2 .= "ORDER BY RAND() ";
// $sql2 .= "LIMIT 1";

// $objPopup = $wpdb->get_results($sql2);
$objPopup = array();

?>

<div class="ui basic modal modalOutLimit">
    <i class="close icon" title="Fechar"></i>
    <div class="header">

    </div>
    <div class="image content">
        <div class="image">

        </div>
        <div class="description">
            <img id="outlimit" src="" width="300" height="200">
        </div>
    </div>
</div>

<script>
    
    $.fn.random = function() {
        return this.eq(Math.floor(Math.random() * this.length))
    };
    $(document).ready(function () {

        <?php
        if(count($objPopup) > 0) {
        ?>
            var src = '<?= $objPopup[0]->url_arquivo ?>'+"?a="+Math.random();
            $("#outlimit").attr('src', src);
            setTimeout(function(){
                $('.ui.modal').modal('show');
            }, 90000);
            $('.ui.modal').modal('show');

            setTimeout(function(){
                $("#outlimit").attr('src', src);
                
                setTimeout(function(){
                    $('.ui.basic.modal.modalOutLimit').modal('hide');
                }, 6000);
            }, 7000);
        <?php } ?>
        
        var loadMore = true;
        var running = false;
        
        $(window).scroll(function() {
            if (loadMore && !running && $(window).scrollTop() >= $(".li-home").last().offset().top + $(".li-home").last().outerHeight() - window.innerHeight) {
                
                running = true;
                
                $(".imgLoader").show();

                $.ajax({
                    method: "POST",
                    url: '<?php bloginfo('template_url'); ?>/infiniteScroll.php',
                    data: {
                        post_type: '<?php echo $POST_TYPES_LIST; ?>',
                        showposts: $("#ids").val().split(',').length + 10,
                        post__not_in: $("#ids").val(),
                        orderby: 'post_date',
                        order: 'DESC',
                        editoria: 'false',
                        no_found_rows: 'true',
                        ignore_sticky_posts: 'true'
                    }
                })
                .done(function (data) {
                    $(".imgLoader").hide();

                    var content = data.split(">>>")[0];
                    var new_ids = data.split(">>>")[1];
                    var count = data.split(">>>")[2];
                    
                    $(".li-home").last().after(content);
                    var ids = $("#ids").val() + ',' + new_ids;
                    $("#ids").val(ids);

                    if(count < 10) {
                        loadMore = false;
                    }
                    setTimeout(function() {
                        running = false;
                    }, 1000);

                });
                
            }
        });


        $(".iconAoVivo").hide().repeat().each($).fadeIn($).wait(1000).fadeOut($);
        
        <?php
        if(count($objCobertura) > 1) {
        ?>
            $('#divCoberturaRotatoriaMobile>*').hide().repeat(5000,true).fadeOut(1000).siblings().random().fadeIn(1000).until($);
        <?php } ?>
        
    });
</script>

<main style="padding-top: 65px;">
    
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            
            <div class="grid-base-int">
                
                <?php
                if($objCobertura) {
                ?>

                    <div class="divCoberturaMobile">
                        <div class="divCoberturaAoVivoMobile">
                            <i class="circle icon iconAoVivo"></i>
                            AO VIVO
                        </div>
                        <div id="divCoberturaRotatoriaMobile">
                            <?php
                            foreach ($objCobertura as $obj) {
                            ?>
                                <div class="divCoberturaChamadaMobile">
                                    <a href="/cobertura/?cobertura=<?=$obj->titulo?>" target="_blank"><?=$obj->chamada?></a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php 
                }
                
                obterDestaqueMaiorMobile();
                include get_template_directory() . '/ad-mobile.php';
                ?>
                <div id="divPublicidade">
                    <?php echo adrotate_group(10); ?>
                </div>
                
                <?php //include get_template_directory() . '/ad-mobile.php'; ?>

                <div class="filtros-resultado-busca">
                    <a href="#" class="ativo">ÚLTIMAS NOTÍCIAS</a>
                </div>

                <ul class="resultado-busca">

                    <?php

                    $posts_per_page = 10;
                    
                    $params = array(
                        'post_type' => explode(',', $POST_TYPES_LIST),
                        'post_status' => 'publish',
                        'posts_per_page'	=> $posts_per_page + count($news_ids),
                        'showposts' => $posts_per_page,
                        'no_found_rows' => true,
                        'ignore_sticky_posts' => true
                    );
                    
                    $query = new WP_Query($params);

                    for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                        if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                            $id = $query->posts[$i]->ID;
                            $news_ids[] = $id;
                            $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                            $exclusivo = get_post_meta($id, 'exclusivo', true);
                            
                            $divChamada = "";
                            $spanCategoria = "";
                            if (get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || $exclusivo) {
                                $divChamada = "divChamadaPostTypeMobile";
                            }

                            if (get_post_type($id) == "especial") {
                                $spanCategoria = "spanEspecial";
                            }

                            if (get_post_type($id) == "exclusivo") {
                                $spanCategoria = "spanExclusivo";
                            }

                            if ((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
                                $spanCategoria = "spanLevi";
                            }

                            if (get_post_type($id) == "motor") {
                                $spanCategoria = "categoriaMotor";
                            }

                            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo);
                        }
                    }
                    ?>

                </ul>

                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                </div>

                <?php include get_template_directory() . '/ad-mobile.php'; ?>

            </div>
            
            <div id="divPublicidade">
                <?php echo adrotate_group(10); ?>
            </div>

            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>

<?php get_footer(); ?>