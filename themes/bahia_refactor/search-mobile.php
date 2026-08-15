<?php
get_header();

// O termo pode chegar por dois caminhos:
//   POST 'b' -> o formulario desta pagina e o do cabecalho;
//   GET  's' -> o botao "Buscar" do site, cujo JS navega por GET em vez de
//               enviar o formulario (assets/js/base.js:260), alem de links
//               compartilhados e dos links de tag das materias.
// Ate 15/08/2026 so o POST era aceito, entao o botao do site caia no ramo de
// baixo e redirecionava para a home: a busca simplesmente nao devolvia nada.
// Pior, o WordPress ja tinha rodado a consulta cara do ?s= antes de o template
// descartar tudo — era custo de banco sem nenhum resultado entregue.
$bahia_termo = null;
if (isset($_POST['b'])) {
    $bahia_termo = stripslashes($_POST['b']);
} elseif (isset($_GET['s'])) {
    $bahia_termo = stripslashes($_GET['s']);
}
$bahia_termo = is_string($bahia_termo) ? trim($bahia_termo) : '';

if ($bahia_termo === '') {
?>
    <script type="text/javascript">
        window.location.href = "<?php bloginfo('url'); ?>";
    </script>
<?php
} else {

    $searched = $bahia_termo;
    $ipad = strpos(getUserAgent(), "iPad");
    $news_ids = array();

    if (isset($searched)) {
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
    }

    $query = new WP_Query($params);
    $qtdResultados = count($query->posts);
?>
    <main style="padding-top: 65px;">
        <div class="container">
            <!-- INTERNA -->
            <div class="main-interno">
                <div class="grid-base-int">

                    <form id="busca" method="post" action="/?s=">
                        <input type="text" value="<?= esc_attr($searched); ?>" name="b" />
                        <input type="submit" value="" />

                        <div class="feedback-resultado-busca">
                            <?php
                            if ($searched) {
                                if ($qtdResultados <= 1)
                                    echo "Foi encontrado um conteúdo com o termo <strong>“" . esc_html($searched) . "”</strong>";
                                else
                                    echo "Foram encontrados alguns conteúdos com o termo <strong>“" . esc_html($searched) . "”</strong>";
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
                                    if ($post_type == "especial" || $post_type == "exclusivo" || $exclusivo) {
                                        $divChamada = "divChamadaPostTypeMobile";
                                    }

                                    $spanCategoria = "";
                                    if ($post_type == "especial") {
                                        $spanCategoria = "spanEspecial";
                                    }

                                    if ($post_type == "exclusivo") {
                                        $spanCategoria = "spanExclusivo";
                                    }

                                    if ($post_type == "motor") {
                                        $spanCategoria = "categoriaMotor";
                                    }

                                    $date_post = get_the_date('d \d\e F \d\e Y', $id);

                                    if ($lastDate != $date_post) {
                                        $lastDate = $date_post;
                                        echo "<input type='hidden' class='lastDate' value='" . $lastDate . "'>";
                                        printDateSearch($lastDate);
                                    }

                                    showLinePostMobile($id, $fields, $divChamada, $spanCategoria, $exclusivo);
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

                <?php get_sidebar(); ?>

                <input type="hidden" id="ids" value="<?= implode(',', $news_ids) ?>">
                <input type="hidden" id="loadMore" value="true">
            </div>
            <!-- FIM INTERNA -->
        </div>
    </main>
<?php
}
get_footer();
?>
