<?php 
get_header(); 

$news_ids = array();

$type = array('any');
$cobertura = null;

if(isset($_GET['cobertura']) && $_GET['cobertura'] != "") {
    $cobertura = $_GET['cobertura'];
}

$sql = "SELECT ";
$sql .= "   id, ";
$sql .= "   titulo ";
$sql .= "FROM wp_coberturas ";
$sql .= "WHERE ";
$sql .= "   status = 1 ";
$sql .= "   AND titulo = '{$cobertura}' ";

$objCobertura = $wpdb->get_row($sql);

?>

<script>
    $(document).ready(function () {
        
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
                        post_type: '<?= $POST_TYPES_LIST; ?>',
                        showposts: $("#ids").val().split(',').length + 10,
                        post__not_in: $("#ids").val(),
                        orderby: 'post_date',
                        order: 'DESC',
                        editoria: 'false',
                        no_found_rows: 'true',
                        ignore_sticky_posts: 'true',
                        meta_key: 'cobertura',
                        meta_value: '<?=$objCobertura->id?>'
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
                    <div class="filtros-resultado-busca">
                        <a href="#" class="ativo" style="line-height: 1.4em;"><?=$objCobertura->titulo?></a>
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
                            'ignore_sticky_posts' => true,
                            'meta_key' => 'cobertura',
                            'meta_value' => $objCobertura->id
                        );

                        $query = new WP_Query($params);

                        for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                            if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                                $id = $query->posts[$i]->ID;
                                $news_ids[] = $id;
                                $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                                $exclusivo = get_post_meta($id, 'exclusivo', true);
                                
                                $divChamada = "";
                                if ($exclusivo) {
                                    $divChamada = "divChamadaPostTypeMobile";
                                }

                                showLinePostMobile($id, $img, $divChamada, "", $exclusivo, true);
                            }
                        }
                        
                        ?>  
                    </ul>

                    <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                        <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                    </div>
                <?php
                } else {
                ?>
                    <ul class="resultado-busca">
                        <li><p class="call-chamada">Página não encontrada.</p></li>
                    </ul>
                <?php } ?>

            </div>
            
            <div id="divPublicidade">
                <?php echo adrotate_group(10); ?>
            </div>
            
            <?php get_sidebar(); ?>

            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>

<?php get_footer(); ?>