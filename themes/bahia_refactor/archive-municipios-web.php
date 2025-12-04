<?php
get_header();
$news_ids = array();
$ipad = strpos(getUserAgent(), "iPad");
?>
<main>
    <div class="container">
        <div class="filtros-resultado-busca <?php if($ipad){echo "main-interno main-internoIpadArchive";}?>" style="margin-bottom: <?php if(!$ipad){echo "2";}?>0px;">
            <a href="/municipios" class="ativo"><?php if ( is_post_type_archive() ) { echo post_type_archive_title();} ?></a>
        </div>

        <!-- DESTAQUES -->
        <div class="destaques <?php if($ipad){echo "main-interno";}?>" style="<?php if($ipad){echo "margin-top: 80px;";}?>">
            <?php
//            $strings = array('1', '2', '3', '4', '5', '6');
//            $random_img = $strings[array_rand($strings)];
            ?>
            <!--<a href="/municipios"><img style="width: 100%; height: auto; margin-top: 20px;" src="<?php //bloginfo('template_url');?>/assets/imgs/municipios/Municipios_0<?//=$random_img?>.png"/></a>-->
            <?php
            if(wp_count_posts(get_query_var('post_type'))->publish <= 5 || $ipad):
                $news_ids = array();
            else :

                $destaques = obterQueryPostsPorPostType(get_query_var('post_type'), 5);
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
                        'no_found_rows' => true,
                        'ignore_sticky_posts' => true
                    );

                    $query = new WP_Query($params);

                    for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                        if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                            $id = $query->posts[$i]->ID;
                            $news_ids[] = $id;
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;
                            $post_type = get_post_type($id);

                            $divChamada = "";
                            if($post_type == "especial" || $post_type == "exclusivo" || $post_type == "entrevista" || $exclusivo) {
                                $divChamada = "divChamadaPostType";
                            }

                            showLinePostWeb($id, $fields, $divChamada, $exclusivo);
                        }
                    }

                    ?>

                </ul>

				<?php include get_template_directory() . '/ad-small.php'; ?>

                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
                </div>

            </div>

            <?php get_sidebar(); ?>

            <input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>

<?php get_footer(); ?>
