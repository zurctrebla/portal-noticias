<?php
get_header();

$ipad = strpos(getUserAgent(), "iPad");
$postType = get_query_var('post_type');
$news_ids = array();
?>
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
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;
                            $divChamada = "";

                            showLinePostWeb($id, $fields, $divChamada, $exclusivo, true);
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
