<?php
get_header();

$ipad = strpos(getUserAgent(), "iPad");
$postType = get_query_var('post_type');
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
                        post_type: 'any',
                        showposts: $("#ids").val().split(',').length + 10,
                        post__not_in: $("#ids").val(),
                        orderby: 'post_date',
                        order: 'DESC',
                        meta_key: 'exclusivo',
                        meta_value: '1',
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

<main>
    <div class="container">
        <div class="filtros-resultado-busca <?php if($ipad){echo "main-interno main-internoIpadArchive";}?>" style="margin-bottom: <?php if(!$ipad){echo "2";}?>0px;">
            <a href="#" class="ativo"><?php if ( is_post_type_archive() ) { echo post_type_archive_title();} ?></a>
        </div>
        
        <!-- DESTAQUES -->
        <div class="destaques <?php if($ipad){echo "main-interno";}?>">
            <?php
            $noDestaques = array('brasil', 'economia', 'mundo', 'justica');
            
            // Se uma editoria tiver menos que 6 posts mostra uma lista, se não mostra o quadro de destaques.
            if(in_array($postType, $noDestaques) || $ipad || (wp_count_posts($postType)->publish <= 5 && $postType != 'exclusivo')):
                // Não mostra os destaques
                if($postType == "justica"):
                ?>
                    <img style="width: 100%; height: auto; margin-top: 20px;" src="<?php bloginfo('template_url');?>/assets/imgs/topoJustica.jpg"/>
                <?php
                endif;
            else :
            
                $destaques = obterQueryPostsPorPostType($postType, 5);
                ?>

                <div class="grid-base destg">
                    <?php destaquesArchive(0, $destaques); ?>
                </div>
                <div class="grid-base destp">
                    <?php destaquesArchive(1, $destaques); ?>
                    <?php destaquesArchive(2, $destaques); ?>
                </div>
                <div class="grid-base destp">
                    <?php destaquesArchive(3, $destaques); ?>
                    <?php destaquesArchive(4, $destaques); ?>
                </div>
            <?php endif; ?>
        </div>
        <!-- FIM DESTAQUES -->
        
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">

                <?php include get_template_directory() . '/ad-small.php'; ?>

                <ul class="resultado-busca">

                    <?php
                    $posts_per_page = 10;
                    $params = array(
                        'post_status' => 'publish',
                        'post_type' => explode(',', $POST_TYPES_LIST),
                        'meta_key' => 'exclusivo',
                        'meta_value' => 1,
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
                            $exclusivo = false;
                            $divChamada = "";

                            showLinePostWeb($id, $img, $divChamada, $exclusivo, true);
                        }
                    }
                    ?>

                </ul>
                
                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                </div>

            </div>
            
            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">

            <?php get_sidebar(); ?>
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>

<?php get_footer(); ?>