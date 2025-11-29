<?php
get_header();
$news_ids = array();
?>
<main>
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
                <div class="filtros-resultado-busca">
                    <a href="#" class="ativo"><?php the_title(); ?></a>
                </div>
                <ul class="resultado-busca" id="posts-container">
                    <?php
                    $posts_per_page = 10;
                    $params = array(
                        'post_type' => explode(',', $POST_TYPES_LIST),
                        'post_status' => 'publish',
                        'posts_per_page' => $posts_per_page,
                        'orderby' => 'post_date',
                        'order' => 'DESC',
                        'no_found_rows' => false, // Precisa contar para paginação
                        'ignore_sticky_posts' => true,
                        'update_post_meta_cache' => true,
                        'update_post_term_cache' => true,
                    );

                    $query = new WP_Query($params);

                    // Pré-carrega campos ACF
                    if ($query->have_posts()) {
                        $post_ids = wp_list_pluck($query->posts, 'ID');
                        preload_post_acf_fields($post_ids);
                    }

                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            $id = get_the_ID();

                            if (in_array($id, $news_ids)) {
                                continue;
                            }

                            $news_ids[] = $id;

                            // Obtém todos os campos ACF de uma vez
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

                            $divChamada = "";
                            $post_type = get_post_type($id);
                            if (in_array($post_type, array('especial', 'exclusivo', 'entrevista')) || $exclusivo) {
                                $divChamada = "divChamadaPostType";
                            }

                            // editoria = false para mostrar o nome da editoria
                            showLinePostWeb($id, $fields, $divChamada, $exclusivo, false);
                        }
                    } else {
                        echo '<li><p class="no-posts">Nenhuma notícia encontrada.</p></li>';
                    }

                    $max_pages = $query->max_num_pages;
                    $total_posts = $query->found_posts;

                    wp_reset_postdata();
                    ?>
                </ul>
                <!-- Loader -->
                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url'); ?>/assets/imgs/loader.gif" alt="Carregando...">
                </div>
                <!-- Mensagem de fim -->
                <div class="no-more-posts" style="display: none;">
                    <p>Você chegou ao final das últimas notícias.</p>
                </div>
            </div>
            <?php get_sidebar(); ?>
            <!-- Hidden fields para compatibilidade -->
            <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
        <!-- Dados para JavaScript do Infinite Scroll -->
        <script type="text/javascript">
            var bahiaInfiniteScrollData = {
                postType: '<?php echo esc_js($POST_TYPES_LIST); ?>',
                taxonomy: '',
                termId: 0,
                excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                totalPosts: <?php echo absint($total_posts); ?>, // ADICIONAR
                postsPerPage: <?php echo absint($posts_per_page); ?>,
                isMobile: false,
                isMultiPostType: true
            };
        </script>
    </div>
</main>
<?php get_footer(); ?>
