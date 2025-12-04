<?php
get_header();
$news_ids = array();
?>
<main style="padding-top: 65px;">
    <div class="container">

        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">

                <!-- DESTAQUES -->
<!--                <div class="destaques">
                    <?php
//                    $strings = array('1', '2', '3', '4', '5', '6');
//                    $random_img = $strings[array_rand($strings)];
                    ?>
                    <a href="/municipios"><img style="width: 100%; height: auto; margin-top: 20px;" src="<?php //bloginfo('template_url');?>/assets/imgs/municipios/Municipios_0<?//=$random_img?>.png"/></a>
                </div>-->
                <!-- FIM DESTAQUES -->

            <div class="filtros-resultado-busca">
                <a href="/municipios" class="ativo">MUNICÍPIOS</a>
            </div>

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
                            if ($exclusivo) {
                                $divChamada = "divChamadaPostTypeMobile";
                            }

                            $spanCategoria = "";
                            if($post_type == "especial") {
                                $spanCategoria = "spanEspecial";
                            }

                            if($post_type == "exclusivo") {
                                $spanCategoria = "spanExclusivo";
                            }

                            showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo);
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

            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
