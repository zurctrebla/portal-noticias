<?php

get_header();
the_post();

$post_id = get_the_ID();

// Obtém todos os campos ACF de uma vez
$fields = get_post_acf_fields($post_id);

// Contabiliza view
$views = isset($fields['views']) ? $fields['views'] : 0;
$views = empty($views) ? 0 : $views;
$views = $views + 1;
update_post_meta($post_id, 'views', $views);

$types = $post_type = get_post_type();
if ($post_type == "rms") {
    $types = '$POST_TYPES_LIST';
}
$urlPost = get_permalink();
?>
<main style="padding-top: 65px;">
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="filtros-resultado-busca" style="margin-bottom: 20px;">
                <a href="#" class="ativo"><?php echo post_label($post_type, $post_id); ?></a>
            </div>
            <div class="grid-base-int">
                <div class="materia-conteudo">
                    <div class="data-publicacao">
                        <?php data_post($post_id); ?>
                        <?php edit_post_link("Editar post", " | (", ")"); ?>
                    </div>
                    <h1><?php the_title(); ?></h1>
                    <h2><?= isset($fields['subtitulo']) ? $fields['subtitulo'] : ''; ?></h2>
                    <div class="autor">
                        <?php
                        if (function_exists('coauthors')) {
                            coauthors(" / ", " / ", "");
                        } else {
                            the_author();
                        }
                        ?>
                    </div>
                    <ul class="sharebox">
                        <li>
                            <a class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $urlPost ?>')+'&text=<?php resumo(80, get_the_title()); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');">
                                <img id="img_twitter_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" />
                            </a>
                        </li>
                        <li>
                            <a href="whatsapp://send?text=<?= $urlPost ?>" style="float: left; padding-left: 10px;" class="whatsapp">
                                <img id="img_whatsapp" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_whatsapp_big.png" alt="Whatsapp" />
                            </a>
                        </li>
                    </div>
                    <div class="materia">
                        <div class="conteudo_post">
                            <?php the_content(); ?>
                        </div>
                        <?php //VIDEOS
                        $video = isset($fields['video']) ? $fields['video'] : '';
                        if (isset($fields['tipo']) && !empty($video)) {
                            echo video("https://www.youtube.com/watch?v=" . $video, true);
                        }

                        $idAuthor = get_the_author_meta('ID');
                        $isColunista = get_user_meta($idAuthor, 'colunista', true);
                        if ($isColunista) {
                            obterAssinaturaColunista($idAuthor, get_the_author());
                        }
                        ?>

                        <ul class="sharebox">
                            <li>
                                <a class="twitter" title="Twitter" onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $urlPost ?>')+'&text=<?php resumo(80, get_the_title()); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');">
                                    <img id="img_twitter_<?= get_the_ID() ?>" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_twitter_big.png" alt="Twitter" />
                                </a>
                            </li>
                            <li>
                                <a href="whatsapp://send?text=<?= $urlPost ?>" style="float: left; padding-left: 10px;" class="whatsapp">
                                    <img id="img_whatsapp" style="width: 35px; height: 35px;" src="<?php bloginfo('template_url'); ?>/assets/imgs/img_whatsapp_big.png" alt="Whatsapp" />
                                </a>
                            </li>
                        </div>

                        <!-- PUBLICIDADE -->
                        <!-- FIM PUBLICIDADE -->
                        <div id="divPublicidade">
                            <?php echo adrotate_group(11); ?>
                        </div>
                    </div>
                    <?php
                    $tags = tags();
                    if ($tags):
                    ?>
                        <div class="tags">
                            <strong>Temas:</strong>
                            <?= $tags; ?>
                        </div>
                    <?php endif; ?>

                    <?php include get_template_directory() . '/ad-mobile.php'; ?>

                    <div class="mais-noticias-int">
                        <h4>Mais notícias</h4>
                        <ul class="resultado-busca">
                            <?php
                            $posts_per_page = 10;
                            $news_ids[] = $post_id;
                            $params = array(
                                'post_type' => $post_type,
                                'post_status' => 'publish',
                                'posts_per_page' => $posts_per_page + count($news_ids),
                                'showposts' => $posts_per_page,
                                'no_found_rows' => true,
                                'ignore_sticky_posts' => true
                            );

                            $query = new WP_Query($params);

                            for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
                                if (!in_array($query->posts[$i]->ID, $news_ids)) {
                                    $id = $query->posts[$i]->ID;
                                    $news_ids[] = $id;

                                    // Obtém todos os campos ACF de uma vez
                                    $post_fields = get_post_acf_fields($id);
                                    $img = isset($post_fields['imagem_url']) && is_string($post_fields['imagem_url']) ? $post_fields['imagem_url'] : '';
                                    $exclusivo = isset($post_fields['exclusivo']) ? (bool)$post_fields['exclusivo'] : false;

                                    $divChamada = "";
                                    $spanCategoria = "";
                                    if ($post_type == "exclusivo" || $exclusivo) {
                                        $divChamada = "divChamadaPostTypeMobile";
                                        $spanCategoria = "spanExclusivo";
                                    } else if ($post_type == "especial") {
                                        $spanCategoria = "spanEspecial";
                                    }

                                    showLinePostMobile($id, $post_fields, $divChamada, $spanCategoria, $exclusivo);
                                }
                            }
                            ?>
                        </ul>
                        <div class="pix-wrapper pix-loader imgLoader" style="display: none;">
                            <img src="<?php bloginfo('template_url'); ?>/assets/imgs/loader.gif">
                        </div>
                    </div>
                    <?php include get_template_directory() . '/ad-mobile.php'; ?>
                </div>
            </div>

            <input type="hidden" id="ids" value="<?= implode(',', $news_ids) ?>">
            <input type="hidden" id="loadMore" value="true">
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>
<?php get_footer(); ?>
