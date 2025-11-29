<?php
get_header();

$ipad = strpos(getUserAgent(), "iPad");
$news_ids = array();
$category = null;
if($_GET) {
    $category = $_GET['category'];
}
?>
<link rel="stylesheet" href="<?php bloginfo('template_url'); ?>/assets/css/brasileirao.min.css" media="all" />

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
                        esporte_cat: '<?= $category ?>',
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
        
    });
</script>

<style>
    .ui.dropdown .menu {
        left: -72px;
    }
</style>

<main style="padding-top: 65px;">
    <div class="container">
        
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
            
            <div class="filtros-resultado-busca" style="text-align: center; line-height: 30px; border-bottom: none;">
                <a href="/esporte" class="ativo" style="color: #15559e; width: 100%; border-right: none;">ESPORTE</a>
            </div>

            <div style="float: left; margin-top: 2%; position: absolute; border-bottom: 2px solid rgba(34,36,38,.15); width: 35%"></div>

             <div class="circular ui icon top pointing dropdown button btnDropdownCategorias" style="left: 43.5%; margin-top: -10px !important; position: absolute; float: left;">
                 <i class="sidebar icon"></i>
                 <div class="menu" >
                     <div class="<?php if($category == "brasileirao"){echo "active";}?> item"><a style="color: #545454;" href="?category=brasileirao">BRASILEIRÃO 2024</a></div>
                     <div class="<?php if($category == "serie-b"){echo "active";}?> item"><a style="color: #545454;" href="?category=serie-b">SÉRIE B</a></div>
                     <div class="<?php if($category == "bahia"){echo "active";}?> item"><a style="color: #545454;" href="?category=bahia">E.C BAHIA</a></div>
                     <div class="<?php if($category == "vitoria"){echo "active";}?> item"><a style="color: #545454;" href="?category=vitoria">E.C VITÓRIA</a></div>
                 </div>
             </div>

             <div style="float: right; margin-top: 2.5%; margin-bottom: 5%; border-bottom: 2px solid rgba(34,36,38,.15); width: 40%"></div> 

            <?php
            if($category == "brasileirao" || $category == "serie-b") {
                include("page-brasileirao-mobile.php");
            } else {

            ?>

                <ul class="resultado-busca">

                    <?php
                    $posts_per_page = 10;
                    
                    $params = array(
                        'post_type' => get_query_var('post_type'),
                        'post_status' => 'publish',
                        'posts_per_page'	=> $posts_per_page + count($news_ids),
                        'showposts' => $posts_per_page,
                        'esporte_cat' => $category,
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
                            if ($exclusivo) {
                                $divChamada = "divChamadaPostTypeMobile";
                            }
                            
                            $spanCategoria = "";
                            if(get_post_type($id) == "especial") {
                                $spanCategoria = "spanEspecial";
                            }

                            if(get_post_type($id) == "exclusivo") {
                                $spanCategoria = "spanExclusivo";
                            }

                            if((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
                                $spanCategoria = "spanLevi";
                            }
                            
                            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, true);
                        }
                    }
                    
                    ?>  

                </ul>

                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                </div>

				<?php include get_template_directory() . '/ad-mobile.php'; ?>

            <?php get_sidebar(); ?>

                <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
                <input type="hidden" id="loadMore" value="true">
            <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>