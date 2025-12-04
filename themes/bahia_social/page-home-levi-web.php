<?php
$category = null;
if($_GET) {
    $category = $_GET['category'];
}

$ipad = strpos(getUserAgent(), "iPad");
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
                        post_type: 'politica',
                        showposts: $("#ids").val().split(',').length + 10,
                        author__in: '17, 58',
                        post__not_in: $("#ids").val(),
                        politica_cat: '<?=$category;?>',
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

<main>
    <div class="container">
        
        <div class="filtros-resultado-busca <?php if($ipad){echo "main-interno main-internoIpadArchive";}?>">
            <a href="/colunistas/levi-vasconcelos" class="ativo">BLOG DO LEVI</a>
            <a href="?category=analises" class="categoriasLevi <?php if($category == "analises"){echo "categoriasLeviAtual";}?>" >Análises</a>
            <a href="?category=politica_vatapa" class="categoriasLevi <?php if($category == "politica_vatapa"){echo "categoriasLeviAtual";}?>">Política com Vatapá</a>
            <a href="?category=videos" class="categoriasLevi <?php if($category == "videos"){echo "categoriasLeviAtual";}?>">Vídeos</a>
        </div>


        <div class="main-interno">
            
            <img style="width: 100%; height: auto; margin-top: 20px;" src="<?php bloginfo('template_url');?>/assets/imgs/blogDoLevi.jpg"/>
            
            <?php get_sidebar('levi'); ?>
            
            <div class="grid-base-int">
                
                <?php include get_template_directory() . '/ad-small.php'; ?>
				
                <ul class="resultado-busca">

                    <?php
                    if(!$category) {
                        $args = array(
                            'post_type' => array('politica'),
                            'showposts' => 1,
                            'author__in' => array(17, 58),
                            'politica_cat' => 'analises',
                            'orderby' => 'post_date',
                            'order' => 'DESC',
                            'no_found_rows' => true,
                            'ignore_sticky_posts' => true
                        );

                        $query = new WP_Query($args);

                        for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                            if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                                $id = $query->posts[$i]->ID;
                                $news_ids[] = $id;
                                $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                                $exclusivo = get_post_meta($id, 'exclusivo', true);
                                
                                $divChamada = "";
                                if(get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || get_post_type($id) == "entrevista" || $exclusivo) {
                                    $divChamada = "divChamadaPostType";
                                }
                                
                                showLinePostWeb($id, $img, $divChamada, $exclusivo, true);
                            }
                        }
                    }
                    
                    
                    $args2 = array(
                        'post_type' => array('politica'),
                        'showposts' => 9,
                        'author__in' => array(17, 58),
                        'politica_cat' => $category,
                        'orderby' => 'post_date',
                        'order' => 'DESC',
                        'no_found_rows' => true,
                        'ignore_sticky_posts' => true
                    );

                    $query2 = new WP_Query($args2);

                    for ($i = 0; $i < count($query2->posts) && $i < $posts_per_page; $i++) {
                        if (! in_array($query2->posts[ $i ]->ID, $news_ids)) {
                            $id = $query2->posts[$i]->ID;
                            $news_ids[] = $id;
                            $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                            $exclusivo = get_post_meta($id, 'exclusivo', true);
                            
                            $divChamada = "";
                            if(get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || get_post_type($id) == "entrevista" || $exclusivo) {
                                $divChamada = "divChamadaPostType";
                            }
                            
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
        </div>


    </div>
</main>