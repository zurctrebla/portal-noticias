<?php
get_header();

$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
$news_ids = array();
?>
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

                            // Obtém todos os campos ACF de uma vez
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

                            $divChamada = "";
                            $spanCategoria = "";

                            showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo, true);
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
