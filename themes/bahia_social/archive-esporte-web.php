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

<main>
    <div class="container">

        <div class="filtros-resultado-busca <?php if($ipad){echo "main-interno main-internoIpadArchive";}?>" style="margin-bottom: 20px;">
            <a href="/esporte" class="ativo" style="color: #15559e">ESPORTE</a>
            <a href="?category=brasileirao" class="categoriasLevi <?php if($category == "brasileirao"){echo "categoriasEntretenimentoAtual";}?>" >BRASILEIRÃO 2024</a>
            <a href="?category=serie-b" class="categoriasLevi <?php if($category == "serie-b"){echo "categoriasEntretenimentoAtual";}?>" >SÉRIE B</a>
            <a href="?category=bahia" class="categoriasLevi <?php if($category == "bahia"){echo "categoriasEntretenimentoAtual";}?>" >E.C. BAHIA</a>
            <a href="?category=vitoria" class="categoriasLevi <?php if($category == "vitoria"){echo "categoriasEntretenimentoAtual";}?>" >E.C. VITÓRIA</a>
        </div>

        <?php
        if($category == "brasileirao" || $category == "serie-b") {
            include("page-brasileirao-web.php");
        } else {
            
        ?>
            <!-- DESTAQUES -->
            <div class="destaques">
                <?php
                
                // Se uma editoria tiver menos que 6 posts mostra uma lista, se não mostra o quadro de destaques.
                if(wp_count_posts(get_query_var('post_type'))->publish >= 6 && !$ipad):
                ?>
                    <?php
                    
                    $destaques = get_option('options_destaques_esporte');
                    if(!$category && $destaques):
                    ?>    
                        <div class="grid-base destg">
                            <?php destaquesEsporte(0, $destaques); ?>
                        </div>
                        <div class="grid-base destp">
                            <?php destaquesEsporte(1, $destaques); ?>
                            <?php destaquesEsporte(2, $destaques); ?>
                        </div>
                        <div class="grid-base destp">
                            <?php destaquesEsporte(3, $destaques); ?>
                            <?php destaquesEsporte(4, $destaques); ?>
                        </div>
                
                    <?php
                    else:
                    
                        $destaques = obterQueryPostsPorPostType(get_query_var('post_type'), 6, 'esporte_cat', $category);

                        if(count($destaques) > 5):
                        ?>
                            <div class="grid-base destg">
                                <?php destaquesArchive(0, $destaques, false, true); ?>
                            </div>
                            <div class="grid-base destp">
                                <?php destaquesArchive(1, $destaques, false, true); ?>
                                <?php destaquesArchive(2, $destaques, false, true); ?>
                            </div>
                            <div class="grid-base destp">
                                <?php destaquesArchive(3, $destaques, false, true); ?>
                                <?php destaquesArchive(4, $destaques, false, true); ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
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
                                if(get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || get_post_type($id) == "entrevista" || $exclusivo) {
                                    $divChamada = "divChamadaPostType";
                                }
                                
                                showLinePostWeb($id, $img, $divChamada, $exclusivo);
                            }
                        }
                        
                        ?>  

                    </ul>

                    <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                        <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                    </div>

					<?php include get_template_directory() . '/ad-small.php'; ?>
                   

                </div>

                <?php get_sidebar('esporte'); ?>

                <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
                <input type="hidden" id="loadMore" value="true">
            </div>
            <!-- FIM INTERNA -->
            <?php } ?>
        </div>
    </main>

<?php get_footer(); ?>