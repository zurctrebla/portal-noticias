<?php
if (is_mobile() and is_home()) {
    include 'page-ultimas-noticias-mobile.php';
} else {
    get_header();
    $news_ids = array();
    global $wpdb;
    $sql = 'SELECT titulo, chamada FROM wp_coberturas WHERE status = 1 ORDER BY data_criacao';
    $objCobertura = $wpdb->get_results($sql);
?>
    <main>
        <div class="container">
            <div class="destaques">
                <?php
                $mod = 1;
                include("inc_destaques.php");
                ?>
            </div>
            <!-- FIM DESTAQUES -->
            <div class="main-interno">
                <?php get_sidebar('home1'); ?>
                <div class="grid-base-int">
                    <?php include get_template_directory() . '/ad-small.php'; ?>
                    <ul class="resultado-busca" id="posts-container">
                        <?php if ($objCobertura) { ?>
                            <div class="divCobertura">
                                <div class="divCoberturaAoVivo">
                                    <i class="circle icon iconAoVivo"></i>
                                    AO VIVO
                                </div>
                                <div id="divCoberturaRotatoria">
                                    <?php foreach ($objCobertura as $obj) { ?>
                                        <div class="divCoberturaChamada">
                                            <a href="/cobertura/?cobertura=<?= $obj->titulo ?>" target="_blank"><?= $obj->chamada ?></a>
                                        </div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                        <?php
                        $postPerPage = 15;
                        $query = new WP_Query(
                            [
                                'post_type'           => explode(',', $POST_TYPES_LIST),
                                'post_status'         => 'publish',
                                'posts_per_page'      => $postPerPage + count($news_ids),
                                'showposts'           => $postPerPage,
                                'no_found_rows'       => true,
                                'ignore_sticky_posts' => true
                            ]
                        );

                        for ($i = 0; $i < count($query->posts) && $i < $postPerPage; $i++) {
                            if (!in_array($query->posts[$i]->ID, $news_ids)) {
                                $id = $query->posts[$i]->ID;
                                $news_ids[] = $id;

                                // Obtém todos os campos ACF de uma vez
                                $fields = get_post_acf_fields($id);
                                $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;

                                $divChamada = "";
                                $post_type = get_post_type($id);
                                if ($post_type == "especial" || $post_type == "exclusivo" || $post_type == "entrevista" || $exclusivo) {
                                    $divChamada = "divChamadaPostType";
                                }

                                showLinePostWeb($id, $fields, $divChamada, $exclusivo);
                            }
                        }
                        ?>
                    </ul>
                    <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                        <img src="<?php bloginfo('template_url'); ?>/assets/imgs/loader.gif">
                    </div>

                    <!-- Botão Ver Mais -->
                    <div class="load-more-wrapper">
                        <button id="load-more-btn" class="ui blue large button">
                            <i class="angle down icon"></i>
                            Ver Mais Notícias
                        </button>
                    </div>

                    <!-- Mensagem fim de posts -->
                    <div class="no-more-posts-message" style="display: none;">
                        <p class="text-center">
                            <i class="check circle outline icon"></i>
                            Você visualizou todas as notícias disponíveis.
                        </p>
                    </div>
                </div>
                <?php get_sidebar('home2'); ?>
            </div>
            <input type="hidden" id="ids" value="<?= implode(',', $news_ids) ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
    </main>
<?php
get_footer();
}
?>
