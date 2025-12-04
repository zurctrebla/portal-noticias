<?php
get_header();

$news_ids = array();
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
<style>
    .ui.dropdown .menu {
        left: -72px;
    }
</style>

<main style="padding-top: 65px;">
    <div class="container">

        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">

                <!-- Header com Dropdown de Categorias -->
                <div class="filtros-resultado-busca" style="text-align: center; line-height: 30px; border-bottom: none;">
                    <a href="/esporte" class="ativo" style="color: #15559e; width: 100%; border-right: none;">ESPORTE</a>
                </div>

                <div style="float: left; margin-top: 2%; position: absolute; border-bottom: 2px solid rgba(34,36,38,.15); width: 35%"></div>

                <div class="circular ui icon top pointing dropdown button btnDropdownCategorias" style="left: 43.5%; margin-top: -10px !important; position: absolute; float: left;">
                    <i class="sidebar icon"></i>
                    <div class="menu">
                        <div class="<?php echo $category == 'brasileirao' ? 'active' : ''; ?> item">
                            <a style="color: #545454;" href="?category=brasileirao">BRASILEIRÃO 2025</a>
                        </div>
                        <div class="<?php echo $category == 'serie-b' ? 'active' : ''; ?> item">
                            <a style="color: #545454;" href="?category=serie-b">SÉRIE B</a>
                        </div>
                        <div class="<?php echo $category == 'bahia' ? 'active' : ''; ?> item">
                            <a style="color: #545454;" href="?category=bahia">E.C BAHIA</a>
                        </div>
                        <div class="<?php echo $category == 'vitoria' ? 'active' : ''; ?> item">
                            <a style="color: #545454;" href="?category=vitoria">E.C VITÓRIA</a>
                        </div>
                    </div>
                </div>

                <div style="float: right; margin-top: 2.5%; margin-bottom: 5%; border-bottom: 2px solid rgba(34,36,38,.15); width: 40%"></div>

                <?php
                // Se for categoria de tabela (Brasileirão ou Série B), inclui página especial
                if ($category == 'brasileirao' || $category == 'serie-b') {
                    include("page-brasileirao-mobile.php");
                } else {
                ?>

                    <ul class="resultado-busca" id="posts-container">
                        <?php
                        $posts_per_page = 10;

                        $params = array(
                            'post_type' => $post_type,
                            'post_status' => 'publish',
                            'posts_per_page' => $posts_per_page,
                            'orderby' => 'post_date',
                            'order' => 'DESC',
                            'no_found_rows' => false, // Precisa contar
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
                                $current_post_type = get_post_type($id);

                                $divChamada = "";
                                $spanCategoria = "";

                                if ($exclusivo) {
                                    $divChamada = "divChamadaPostTypeMobile";
                                }

                                if ($current_post_type == "especial") {
                                    $spanCategoria = "spanEspecial";
                                }

                                if ($current_post_type == "exclusivo") {
                                    $spanCategoria = "spanExclusivo";
                                }

                                // Renderiza post mobile (editoria = true)
                                showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo, true);
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

                    <?php get_sidebar(); ?>

                    <!-- Hidden fields -->
                    <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
                    <input type="hidden" id="loadMore" value="true">
                    <script type="text/javascript">
                        var bahiaInfiniteScrollData = {
                            postType: '<?php echo esc_js($post_type); ?>',
                            taxonomy: '<?php echo esc_js($category ? 'esporte_cat' : ''); ?>',
                            termId: <?php echo absint($term_id); ?>,
                            excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                            totalPosts: <?php echo absint($total_posts); ?>, // ADICIONAR
                            postsPerPage: <?php echo absint($posts_per_page); ?>,
                            isMobile: true,
                            category: '<?php echo esc_js($category ? $category : ''); ?>'
                        };
                    </script>
                <?php } ?>
            </div>
        </div>
    </div>
</main>

<?php get_footer(); ?>
