<?php
get_header();

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$news_ids = array();
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
                        post_type: '<?= get_query_var('post_type'); ?>',
                        showposts: $("#ids").val().split(',').length + 10,
                        post__not_in: $("#ids").val(),
                        orderby: 'post_date',
                        order: 'DESC',
                        editoria: 'true',
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
        
    });
</script>


<main style="padding-top: 65px;">
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
                
                
                <?php

                if(get_query_var('post_type') == "mais_gente2"):
                ?>
                    <div style="margin-top: 15px;">
                        <img style="display: block; margin-left: auto; margin-right: auto; width: 55%;" src="<?php bloginfo('template_url');?>/assets/imgs/tipoassim.png">
                    </div>
                <?php
                else:
                ?>
                    <div class="filtros-resultado-busca">
                        <a href="#" class="ativo"><?php if ( is_post_type_archive() ) { echo post_type_archive_title();} ?></a>
                    </div>
                <?php
                endif;
                ?>


                <ul class="resultado-busca">

                    <?php
                    $posts_per_page = 10;
                    
                    $params = array(
                        'post_type' => get_query_var('post_type'),
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
                            
                            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, true);
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

            <?php get_sidebar(); ?>

            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>

<?php get_footer(); ?>