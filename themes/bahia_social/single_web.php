<?php

get_header();
the_post();

// Contabiliza view
$views = get_post_meta(get_the_ID(), 'views', true);
$views = empty($views) ? 0 : $views;
$views = $views + 1;
update_post_meta(get_the_ID(), 'views', $views);

$ipad = strpos(getUserAgent(), "iPad");

$strings = array('https://www.youtube.com/embed/2Pvs0OjhYyw');
$random_str = $strings[array_rand($strings)];

$types = get_post_type();
if (get_post_type() == "rms") {
    $types = '$POST_TYPES_LIST';
}

$urlPost = get_permalink();
?>

<script>
    $(document).ready(function() {

        var loadMore = true;
        var running = false;
        var play = false;

        $(window).scroll(function() {
            if (loadMore && !running && $(window).scrollTop() >= $(".li-home").last().offset().top + $(".li-home").last().outerHeight() - window.innerHeight) {

                running = true;

                $(".imgLoader").show();

                $.ajax({
                        method: "POST",
                        url: '<?php bloginfo('template_url'); ?>/infiniteScroll.php',
                        data: {
                            post_type: '<?= $types ?>',
                            showposts: $("#ids").val().split(',').length + 10,
                            post__not_in: $("#ids").val(),
                            orderby: 'post_date',
                            order: 'DESC',
                            editoria: 'true',
                            no_found_rows: 'true',
                            ignore_sticky_posts: 'true'
                        }
                    })
                    .done(function(data) {
                        $(".imgLoader").hide();

                        var content = data.split(">>>")[0];
                        var new_ids = data.split(">>>")[1];
                        var count = data.split(">>>")[2];

                        $(".li-home").last().after(content);
                        var ids = $("#ids").val() + ',' + new_ids;
                        $("#ids").val(ids);

                        if (count < 10) {
                            loadMore = false;
                        }

                        setTimeout(function() {
                            running = false;
                        }, 1000);

                    });

            }

            if (!play && isScrolledIntoView(".divPublicidade")) {
                play = true;
                $(".divPublicidade iframe").prop("src", "<?= $random_str ?>?autoplay=1&mute=1");
            }
        });
    });

    function isScrolledIntoView(elem) {
        var docViewTop = $(window).scrollTop();
        var docViewBottom = docViewTop + $(window).height();

        var elemTop = $(elem).offset().top - 230;
        var elemBottom = elemTop + $(elem).height();

        return ((elemBottom <= docViewBottom) && (elemTop >= docViewTop));
    }
</script>

<main>
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno <?php if ($ipad) {
                                        echo "main-internoIpad";
                                    } ?>">
            <div class="grid-base-int">
                <div class="filtros-resultado-busca" style="margin-bottom: 20px;">
                    <a href="#" class="ativo"><?php echo post_label(get_the_ID()); ?></a>
                </div>
                <div class="materia-conteudo">

                    <div class="data-publicacao">
                        <?php data_post(get_the_ID()); ?>
                        <?php edit_post_link("Editar post", " | (", ")"); ?>
                    </div>

                    <h1><?php the_title(); ?></h1>
                    <h2><?= get_post_meta(get_the_ID(), 'subtitulo', true); ?></h2>
                    <div class="autor">
						<?php
						if (function_exists('coauthors')) {
							coauthors(" / ", " / ", "");
						} else {
							the_author();
						}
						?>
                    </div>

                    <div id="div-share" style="padding-left: 0px; padding-top: 12px; padding-bottom: 35px;">
                        <a style="float: left;" class="facebook" title="Facebook" onclick="window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent('<?= $urlPost ?>')+'&app_id=1013999078657900', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_facebook_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_facebook_big.png" alt="Facebook" /></a>
                        <a style="float: left; padding-left: 10px;" class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $urlPost ?>')+'&text=<?php resumo(80, get_the_title()); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_twitter_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" /></a>
                    </div>

                    <div class="materia">
                        <div class="conteudo_post">
                            <?php
                            the_content();
                            ?>
                        </div>

                        <?php //VIDEOS
						
						$video = get_post_meta(get_the_ID(), 'video', true);
                        if (get_post_meta(get_the_ID(), 'tipo', true) && !empty($video)) {
                            echo video("https://www.youtube.com/watch?v=" . $video, true);
                        }

                        $idAuthor = get_the_author_meta('ID');
                        $isColunista = get_user_meta($idAuthor, 'colunista', true);
                        if ($isColunista) {
                            obterAssinaturaColunista($idAuthor, get_the_author());
                        }
                        ?>
						
                        <div id="div-share" style="padding-left: 0px; padding-top: 5px; padding-bottom: 40px;">
                            <a style="float: left;" class="facebook" title="Facebook" onclick="window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent('<?= $urlPost ?>')+'&app_id=1013999078657900', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_facebook_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_facebook_big.png" alt="Facebook" /></a>
                            <a style="float: left; padding-left: 10px;" class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $urlPost ?>')+'&text=<?php resumo(80, get_the_title()); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"><img id="img_twitter_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" /></a>
                        </div>


                        <!-- PUBLICIDADE -->

                        <?php // if(get_post_type() == "carnaval2016"): 
                        ?>
                        <!--div style="text-align: left; margin-top: 110px !important; font-size: 10px;" class="divPublicidade">
                                    <div>
                                        PUBLICIDADE
                                    </div>
                                    <iframe width="560" height="315" src="<? //$random_str
                                                                            ?>" frameborder="0" allowfullscreen></iframe>
                                </div-->
                        <?php //endif; 
                        ?>
                        <!-- FIM PUBLICIDADE -->

                    </div>

                    <?php
                    $tags = tags();
                    if ($tags) :
                    ?>
                        <div class="tags">
                            <strong>Temas:</strong>
                            <?= $tags; ?>
                        </div>
                    <?php endif; ?>

                    <?php include get_template_directory() . '/ad-small.php'; ?>


                    <div class="mais-noticias-int">
                        <h4>Mais notícias</h4>

                        <ul class="resultado-busca">

                            <?php
                            $posts_per_page = 10;
                            $news_ids[] = get_the_ID();

                            $params = array(
                                'post_type' => get_post_type(get_the_ID()),
                                'post_status' => 'publish',
                                'posts_per_page'    => $posts_per_page + count($news_ids),
                                'showposts' => $posts_per_page,
                                'no_found_rows' => true,
                                'ignore_sticky_posts' => true
                            );

                            $query = new WP_Query($params);

                            for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                                if (!in_array($query->posts[$i]->ID, $news_ids)) {
                                    $id = $query->posts[$i]->ID;
                                    $news_ids[] = $id;
                                    $img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
                                    $exclusivo = get_post_meta($id, 'exclusivo', true);

                                    $divChamada = "";
                                    if (get_post_type($id) == "exclusivo" || $exclusivo) {
                                        $divChamada = "divChamadaPostType";
                                    }

                                    showLinePostWeb($id, $img, $divChamada, $exclusivo);
                                }
                            }
                            ?>

                        </ul>
                    </div>

                    <?php include get_template_directory() . '/ad-small.php'; ?>

                </div>

                <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                    <img src="<?php bloginfo('template_url'); ?>/assets/imgs/loader.gif">
                </div>

            </div>

            <?php
                get_sidebar();
            ?>

            <input type="hidden" id="ids" value="<?= implode(',', $news_ids) ?>">
            <input type="hidden" id="loadMore" value="true">

        </div>
        <!-- FIM INTERNA -->

    </div>
</main>
<?php get_footer(); ?>