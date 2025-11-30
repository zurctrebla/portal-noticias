<?php
get_header();

global $wpdb;

// Busca coberturas ao vivo
$sql = "SELECT titulo, chamada FROM wp_coberturas WHERE status = 1 ORDER BY data_criacao";
$objCobertura = $wpdb->get_results($sql);

// Popup (desativado por padrão)
$objPopup = [];
$news_ids = [];
?>
<main style="padding-top: 65px;">
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
                <?php if($objCobertura) : ?>
                    <div class="divCoberturaMobile">
                        <div class="divCoberturaAoVivoMobile">
                            <i class="circle icon iconAoVivo"></i>
                            AO VIVO
                        </div>
                        <div id="divCoberturaRotatoriaMobile">
                            <?php foreach ($objCobertura as $obj) : ?>
                                <div class="divCoberturaChamadaMobile">
                                    <a href="/cobertura/?cobertura=<?php echo urlencode($obj->titulo); ?>" target="_blank">
                                        <?php echo esc_html($obj->chamada); ?>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                endif;
                obterDestaqueMaiorMobile();
                include get_template_directory() . '/ad-mobile.php';
                ?>
                <div id="divPublicidade">
                    <?php echo adrotate_group(10); ?>
                </div>
                <div class="filtros-resultado-busca">
                    <a href="#" class="ativo">ÚLTIMAS NOTÍCIAS</a>
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
                        'no_found_rows' => false, // Precisa contar
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
                            $spanCategoria = "";

                            $post_type = get_post_type($id);

                            if (in_array($post_type, array('especial', 'exclusivo')) || $exclusivo) {
                                $divChamada = "divChamadaPostTypeMobile";
                            }

                            if ($post_type == "especial") {
                                $spanCategoria = "spanEspecial";
                            } elseif ($post_type == "exclusivo") {
                                $spanCategoria = "spanExclusivo";
                            } elseif ($post_type == "motor") {
                                $spanCategoria = "categoriaMotor";
                            }

                            // editoria = false para mostrar o nome da editoria
                            showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo, false);
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

                <!-- Botão Ver Mais -->
                <div class="load-more-wrapper">
                    <button id="load-more-btn" class="ui blue large button">
                        <i class="angle down icon"></i>
                        Ver Mais Notícias
                    </button>
                </div>

                <!-- Mensagem de fim -->
                <div class="no-more-posts-message" style="display: none;">
                    <p class="text-center">
                        <i class="check circle outline icon"></i>
                        Você visualizou todas as notícias disponíveis.
                    </p>
                </div>
                <?php include get_template_directory() . '/ad-mobile.php'; ?>
            </div>
            <div id="divPublicidade">
                <?php echo adrotate_group(10); ?>
            </div>
            <!-- Hidden fields -->
            <input type="hidden" id="ids" value="<?php echo esc_attr(implode(',', $news_ids)); ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
        <!-- Dados para JavaScript -->
        <script type="text/javascript">
            var bahiaInfiniteScrollData = {
                postType: '<?php echo esc_js($POST_TYPES_LIST); ?>',
                taxonomy: '',
                termId: 0,
                excludeIds: [<?php echo implode(',', array_map('absint', $news_ids)); ?>],
                totalPosts: <?php echo absint($total_posts); ?>, // ADICIONAR
                postsPerPage: <?php echo absint($posts_per_page); ?>,
                isMobile: true,
                isMultiPostType: true
            };
        </script>
    </div>
</main>
<?php get_footer(); ?>
