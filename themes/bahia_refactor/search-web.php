<?php
get_header();

// Usa get_search_query() do WordPress (padrão)
$searched = get_search_query();

if (empty($searched)) {
?>
    <script type="text/javascript">
        window.location.href = "<?php bloginfo('url'); ?>";
    </script>
<?php
} else {

    $ipad = strpos(getUserAgent(), "iPad");
    $news_ids = array();

    wp_reset_query();

    $params = array(
        'post_type' => explode(',', $POST_TYPES_LIST),
        'post_status' => 'publish',
        's' => $searched,
        'orderby' => 'post_date',
        'order' => 'DESC',
        'no_found_rows' => true,
        'ignore_sticky_posts' => true
    );

    $query = new WP_Query($params);
    $qtdResultados = count($query->posts);
?>
    <main>
        <div class="container">
            <!-- INTERNA -->
            <div class="main-interno <?php if ($ipad) {
                                            echo "main-internoIpad";
                                        } ?>">
                <div class="grid-base-int">

                    <form id="busca" role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                        <input type="search" value="<?= esc_attr($searched); ?>" name="s" placeholder="Buscar..." />
                        <input type="submit" value="" />

                        <div class="feedback-resultado-busca">
                            <?php

                            if ($searched) {
                                if ($qtdResultados <= 1)
                                    echo "Foi encontrado um conteúdo com o termo <strong>“{$searched}”</strong>";
                                else
                                    echo "Foram encontrados alguns conteúdos com o termo <strong>“{$searched}”</strong>";
                            }
                            ?>
                        </div>
                    </form>

                    <div class="filtros-resultado-busca" style="margin-top: 0 !important;">
                        <a href="#" class="ativo">Notícias</a>
                    </div>

                    <ul class="resultado-busca">

                        <?php
                        if ($qtdResultados > 0):
                            $lastDate = null;
                            for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                                if (! in_array($query->posts[$i]->ID, $news_ids)) {
                                    $id = $query->posts[$i]->ID;
                                    $news_ids[] = $id;

                                    // Obtém todos os campos ACF de uma vez
                                    $fields = get_post_acf_fields($id);
                                    $exclusivo = isset($fields['exclusivo']) ? (bool)$fields['exclusivo'] : false;
                                    $post_type = get_post_type($id);

                                    $divChamada = "";
                                    if ($post_type == "especial" || $post_type == "exclusivo" || $post_type == "entrevista" || $exclusivo) {
                                        $divChamada = "divChamadaPostType";
                                    }

                                    $date_post = get_the_date('d \d\e F \d\e Y', $id);

                                    if ($lastDate != $date_post) {
                                        $lastDate = $date_post;
                                        echo "<input type='hidden' class='lastDate' value='" . $lastDate . "'>";
                                        printDateSearch($lastDate);
                                    }

                                    showLinePostWeb($id, $fields, $divChamada, $exclusivo);
                                }
                            }
                        else: ?>

                            <li>
                                <p class="call-chamada">Sua busca não trouxe resultados.</p>
                            </li>

                        <?php endif; ?>

                    </ul>

                    <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                        <img src="<?php bloginfo('template_url'); ?>/assets/imgs/loader.gif">
                    </div>

                </div>

                <input type="hidden" id="ids" value="<?= implode(',', $news_ids) ?>">
                <input type="hidden" id="loadMore" value="true">

                <?php get_sidebar(); ?>
            </div>
            <!-- FIM INTERNA -->
        </div>
    </main>

<?php
}
get_footer();
?>
