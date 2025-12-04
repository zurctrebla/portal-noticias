<?php
get_header();

$news_ids = array();
$post_type = get_query_var('post_type');
?>
<main>
    <div class="container">
        <?php if ($post_type == "mais_gente2"): ?>
            <img style="display: block; margin-left: auto; margin-right: auto"
                 src="<?php bloginfo('template_url'); ?>/assets/imgs/tipoassim.png"
                 alt="Tipo Assim">
        <?php else: ?>
            <div class="filtros-resultado-busca">
                <a href="#" class="ativo">
                    <?php
                    if (is_post_type_archive()) {
                        echo post_type_archive_title('', false);
                    }
                    ?>
                </a>
            </div>
        <?php endif; ?>

        <!-- DESTAQUES -->
        <div class="destaques">
            <?php
            $noDestaques = array('brasil', 'economia', 'mundo', 'mais_noticias');

            // Se uma editoria tiver menos que 6 posts mostra uma lista, se não mostra o quadro de destaques.
            if (in_array($post_type, $noDestaques) || wp_count_posts($post_type)->publish <= 5):
                // Não mostra os destaques
            else:
                $destaques = obterQueryPostsPorPostType($post_type, 5);

                // Adiciona IDs dos destaques para excluir depois
                if (is_array($destaques)) {
                    foreach ($destaques as $destaque) {
                        if (isset($destaque->ID)) {
                            $news_ids[] = $destaque->ID;
                        }
                    }
                }
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
                <ul class="resultado-busca" id="posts-container">
                    <?php
                    $posts_per_page = 10;
                    $params = array(
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'posts_per_page' => $posts_per_page,
                        'orderby' => 'post_date',
                        'order' => 'DESC',
                        'no_found_rows' => false, // Precisa contar para paginação
                        'ignore_sticky_posts' => true,
                        'update_post_meta_cache' => true,
                        'update_post_term_cache' => true,
                    );

                    // Exclui destaques já exibidos
                    if (!empty($news_ids)) {
                        $params['post__not_in'] = $news_ids;
                    }

                    $query = new WP_Query($params);

                    // Pré-carrega campos ACF para melhor performance
                    if ($query->have_posts()) {
                        $post_ids = wp_list_pluck($query->posts, 'ID');
                        preload_post_acf_fields($post_ids);
                    }

                    if ($query->have_posts()) {
                        while ($query->have_posts()) {
                            $query->the_post();
                            $id = get_the_ID();

                            // Evita duplicatas
                            if (in_array($id, $news_ids)) {
                                continue;
                            }

                            $news_ids[] = $id;
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

                            // Renderiza post (editoria = true, sem divChamada pois não é especial/exclusivo por padrão)
                            showLinePostWeb($id, $fields, "", $exclusivo, true);
                        }
                    } else {
                        echo '<li><p class="no-posts">Nenhuma notícia encontrada nesta editoria.</p></li>';
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
                    <p>Você chegou ao final das notícias desta editoria.</p>
                </div>
                <?php include get_template_directory() . '/ad-small.php'; ?>
            </div>
            <?php get_sidebar(); ?>
            <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
        <script type="text/javascript">
            var bahiaInfiniteScrollData = {
                postType: '<?php echo esc_js($post_type); ?>',
                taxonomy: '',
                termId: 0,
                excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                currentPage: 1,
                maxPages: <?php echo absint($max_pages); ?>,
                totalPosts: <?php echo absint($total_posts); ?>,
                postsPerPage: <?php echo absint($posts_per_page); ?>,
                isMobile: false
            };
        </script>
    </div>
</main>

<?php get_footer(); ?>
