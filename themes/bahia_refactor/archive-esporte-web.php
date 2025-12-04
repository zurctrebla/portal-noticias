<?php
get_header();

$news_ids = array();
$ipad = strpos(getUserAgent(), "iPad");
$post_type = get_query_var('post_type');

// Obtém categoria da URL
$category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : null;
$term_id = 0;

// Se houver categoria, obtém o term_id
if ($category) {
    $term = get_term_by('slug', $category, 'esporte_cat');
    if ($term) {
        $term_id = $term->term_id;
    }
}
?>
<link rel="stylesheet" href="<?php bloginfo('template_url'); ?>/assets/css/brasileirao.min.css" media="all" />
<main>
    <div class="container">
        <!-- Menu de Categorias -->
        <div class="filtros-resultado-busca <?php if($ipad) echo 'main-interno main-internoIpadArchive'; ?>" style="margin-bottom: 20px;">
            <a href="/esporte" class="<?php echo !$category ? 'ativo categoriasEntretenimentoAtual' : ''; ?>" style="color: #15559e">ESPORTE</a>
            <a href="?category=brasileirao" class="categoriasLevi <?php echo $category == 'brasileirao' ? 'categoriasEntretenimentoAtual' : ''; ?>">BRASILEIRÃO 2025</a>
            <a href="?category=serie-b" class="categoriasLevi <?php echo $category == 'serie-b' ? 'categoriasEntretenimentoAtual' : ''; ?>">SÉRIE B</a>
            <a href="?category=bahia" class="categoriasLevi <?php echo $category == 'bahia' ? 'categoriasEntretenimentoAtual' : ''; ?>">E.C. BAHIA</a>
            <a href="?category=vitoria" class="categoriasLevi <?php echo $category == 'vitoria' ? 'categoriasEntretenimentoAtual' : ''; ?>">E.C. VITÓRIA</a>
        </div>

        <?php
        // Se for categoria de tabela (Brasileirão ou Série B), inclui página especial
        if ($category == 'brasileirao' || $category == 'serie-b') {
            include("page-brasileirao-web.php");
        } else {
        ?>
            <!-- DESTAQUES -->
            <div class="destaques">
                <?php
                // Só mostra destaques se tiver pelo menos 6 posts e não for iPad
                if (wp_count_posts($post_type)->publish >= 6 && !$ipad):

                    // Se não houver categoria, usa destaques customizados do ACF
                    if (!$category):
                        $destaques = get_option('options_destaques_esporte');

                        if ($destaques && is_array($destaques)):
                            // Adiciona IDs dos destaques para excluir depois
                            foreach ($destaques as $destaque_id) {
                                $news_ids[] = $destaque_id;
                            }
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
                        endif;

                    // Se houver categoria, busca destaques da categoria
                    else:
                        $destaques = obterQueryPostsPorPostType($post_type, 6, 'esporte_cat', $category);

                        if (is_array($destaques) && count($destaques) > 5):
                            // Adiciona IDs dos destaques
                            foreach ($destaques as $destaque) {
                                if (isset($destaque->ID)) {
                                    $news_ids[] = $destaque->ID;
                                }
                            }
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
                        <?php
                        endif;
                    endif;

                endif;
                ?>
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

                        // Adiciona filtro de categoria se existir
                        if ($category && $term_id > 0) {
                            $params['tax_query'] = array(
                                array(
                                    'taxonomy' => 'esporte_cat',
                                    'field' => 'term_id',
                                    'terms' => $term_id,
                                )
                            );
                        }

                        // Exclui destaques já exibidos
                        if (!empty($news_ids)) {
                            $params['post__not_in'] = $news_ids;
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

                                // Evita duplicatas
                                if (in_array($id, $news_ids)) {
                                    continue;
                                }

                                $news_ids[] = $id;
                                $fields = get_post_acf_fields($id);
                                $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;
                                $current_post_type = get_post_type($id);

                                $divChamada = "";
                                if (in_array($current_post_type, array('especial', 'exclusivo', 'entrevista')) || $exclusivo) {
                                    $divChamada = "divChamadaPostType";
                                }

                                // Renderiza post (editoria = true)
                                showLinePostWeb($id, $fields, $divChamada, $exclusivo, true);
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

                    <?php include get_template_directory() . '/ad-small.php'; ?>
                </div>

                <?php get_sidebar('esporte'); ?>

                <!-- Hidden fields para compatibilidade -->
                <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
                <input type="hidden" id="loadMore" value="true">
            </div>
            <!-- FIM INTERNA -->

            <script type="text/javascript">
                var bahiaInfiniteScrollData = {
                    postType: '<?php echo esc_js($post_type); ?>',
                    taxonomy: '<?php echo esc_js($category ? 'esporte_cat' : ''); ?>',
                    termId: <?php echo absint($term_id); ?>,
                    excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                    totalPosts: <?php echo absint($total_posts); ?>, // ADICIONAR
                    postsPerPage: <?php echo absint($posts_per_page); ?>,
                    isMobile: false,
                    category: '<?php echo esc_js($category ? $category : ''); ?>'
                };
            </script>
        <?php } ?>
    </div>
</main>

<?php get_footer(); ?>
