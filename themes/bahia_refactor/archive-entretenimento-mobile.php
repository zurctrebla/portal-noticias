<?php
get_header();

$news_ids = array();
$post_type = get_query_var('post_type');

// Obtém categoria da URL
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null;
$term_id = 0;

// Se houver categoria, obtém o term_id
if ($category) {
    $term = get_term_by('slug', $category, 'entretenimento_cat');
    if ($term) {
        $term_id = $term->term_id;
    }
}
?>
<main style="padding-top: 65px;">
    <div class="container">
        <!-- Menu de Categorias Mobile -->
        <div class="filtros-resultado-busca" style="margin-bottom: 20px; overflow-x: auto; white-space: nowrap;">
            <a href="/entretenimento" class="<?php echo !$category ? 'ativo categoriasEntretenimentoAtual' : ''; ?>" style="color: #15559e; display: inline-block;">ENTRETENIMENTO</a>
            <a href="?category=quem_indica" class="categoriasLevi <?php echo $category == 'quem_indica' ? 'categoriasEntretenimentoAtual' : ''; ?>" style="display: inline-block;">QUEM INDICA</a>
            <a href="?category=comportamento" class="categoriasLevi <?php echo $category == 'comportamento' ? 'categoriasEntretenimentoAtual' : ''; ?>" style="display: inline-block;">COMPORTAMENTO</a>
            <a href="?category=lugar_legal" class="categoriasLevi <?php echo $category == 'lugar_legal' ? 'categoriasEntretenimentoAtual' : ''; ?>" style="display: inline-block;">LUGAR LEGAL</a>
            <a href="?category=gente_boa" class="categoriasLevi <?php echo $category == 'gente_boa' ? 'categoriasEntretenimentoAtual' : ''; ?>" style="display: inline-block;">GENTE BOA</a>
        </div>

        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
                <ul class="resultado-busca" id="posts-container">
                    <?php
                    $posts_per_page = 10;
                    $params = array(
                        'post_type' => $post_type,
                        'post_status' => 'publish',
                        'posts_per_page' => $posts_per_page,
                        'orderby' => 'post_date',
                        'order' => 'DESC',
                        'no_found_rows' => false,
                        'ignore_sticky_posts' => true,
                        'update_post_meta_cache' => true,
                        'update_post_term_cache' => true,
                    );

                    // Adiciona filtro de categoria se existir
                    if ($category && $term_id > 0) {
                        $params['tax_query'] = array(
                            array(
                                'taxonomy' => 'entretenimento_cat',
                                'field' => 'term_id',
                                'terms' => $term_id,
                            )
                        );
                    }

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
                            $fields = get_post_acf_fields($id);
                            $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

                            // Renderiza post mobile (editoria = true)
                            showLinePostMobile($id, $fields, "", "", $exclusivo, true);
                        }
                    } else {
                        echo '<li><p class="no-posts">Nenhuma notícia encontrada nesta categoria.</p></li>';
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
                    <p>Você chegou ao final das notícias desta categoria.</p>
                </div>

                <?php include get_template_directory() . '/ad-mobile.php'; ?>
            </div>

            <div id="divPublicidade">
                <?php echo adrotate_group(10); ?>
            </div>

            <?php get_sidebar(); ?>

            <!-- Hidden fields -->
            <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
        <script type="text/javascript">
            var bahiaInfiniteScrollData = {
                postType: '<?php echo esc_js($post_type); ?>',
                taxonomy: '<?php echo esc_js($category ? 'entretenimento_cat' : ''); ?>',
                termId: <?php echo absint($term_id); ?>,
                excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                totalPosts: <?php echo absint($total_posts); ?>, // ADICIONAR
                postsPerPage: <?php echo absint($posts_per_page); ?>,
                isMobile: true,
                category: '<?php echo esc_js($category ? $category : ''); ?>'
            };
        </script>
    </div>
</main>
<?php get_footer(); ?>
