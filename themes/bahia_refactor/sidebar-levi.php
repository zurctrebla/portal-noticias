<aside>
    <div class="box-news-int">

        <div class="grid-base">
            <div class="chamada">
                <?php
                global $news_ids;
                
                $args = array(
                    'post_type' => array('politica'),
                    'showposts' => 1,
                    'author__in' => array(17, 58),
                    'politica_cat' => 'politica_vatapa',
                    'orderby' => 'post_date',
                    'order' => 'DESC',
                    'no_found_rows' => true,
                    'ignore_sticky_posts' => true
                );
                
                $query = new WP_Query($args);

                for ($i = 0; $i < count($query->posts) && $i < 1; $i++) {
                    if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                        $id = $query->posts[$i]->ID;
                        $news_ids[] = $id;
                        
                        // Obtém todos os campos ACF de uma vez
                        $fields = get_post_acf_fields($id);
                        $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
                        $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
                        
                        ?>
                        <div class="" style="float: left">
                            <span class="categoriaMobile spanLevi" style="float: left; background-color: #15559e !important;">POLÍTICA COM VATAPÁ</span>
                            
                            <a href="<?= get_permalink($id); ?>">
                                <?php if(!empty($img)): ?>
									<figure><img src="<?= $img; ?>" <?=getMedidasImagem('news_home');?>></figure>
								<?php endif; ?>
                                <p class="call-chamada"><?= get_the_title($id); ?></p>
                                <?php if ($subtitulo == ""): ?>
                                    <p class="txtSubtitleHome"><?php resumo(170, get_the_excerpt()); ?></p>
                                <?php else: ?>
                                    <p class="txtSubtitleHome"><?php resumo(170, $subtitulo); ?></p>
                                <?php endif; ?>
                            </a>
                        </div>
                        <a href="<?= get_permalink($id); ?>">
                            <span class="categoriaMobile divLerMaisHome">
                                LEIA
                            </span>
                        </a>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        
        <div class="grid-base">
            <div class="chamada">
                <?php
                $args = array(
                    'post_type' => array('politica'),
                    'showposts' => 1,
                    'author__in' => array(17, 58),
                    'politica_cat' => 'videos',
                    'orderby' => 'post_date',
                    'order' => 'DESC',
                    'no_found_rows' => true,
                    'ignore_sticky_posts' => true
                );
                
                $query = new WP_Query($args);

                for ($i = 0; $i < count($query->posts) && $i < 1; $i++) {
                    if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
                        $id = $query->posts[$i]->ID;
                        $news_ids[] = $id;
                        
                        // Obtém todos os campos ACF de uma vez
                        $fields = get_post_acf_fields($id);
                        $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
                        $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
                        
                        ?>
                        <div class="" style="float: left">
                            <span class="categoriaMobile spanLevi" style="float: left; background-color: #15559e !important;">POLÍTICA COM VATAPÁ</span>
                            
                            <a href="<?= get_permalink($id); ?>">
                                <?php if(!empty($img)): ?>
									<figure><img src="<?= $img; ?>" <?=getMedidasImagem('news_home');?>></figure>
								<?php endif; ?>
                                <p class="call-chamada"><?= get_the_title($id); ?></p>
                                <?php if ($subtitulo == ""): ?>
                                    <p class="txtSubtitleHome"><?php resumo(170, get_the_excerpt()); ?></p>
                                <?php else: ?>
                                    <p class="txtSubtitleHome"><?php resumo(170, $subtitulo); ?></p>
                                <?php endif; ?>
                            </a>
                        </div>
                        <a href="<?= get_permalink($id); ?>">
                            <span class="categoriaMobile divLerMaisHome">
                                LEIA
                            </span>
                        </a>
                        <?php
                    }
                }
                ?>
            </div>
        </div>
        

        <div class="grid-base">
            <div class="chamada">
                <div>
                    <?php echo adrotate_group(8); ?>
                </div>
            </div>
        </div>
        
        <div class="titulo"><strong>/</strong> Página Principal</div>

        <?php
        $posts_per_page = 5;
        global $POST_TYPES_LIST;
        $args2 = array(
            'post_type' => explode(',', $POST_TYPES_LIST),
            'showposts' => $posts_per_page,
            'posts_per_page'	=> $posts_per_page + count($news_ids),
            'no_found_rows' => true,
            'ignore_sticky_posts' => true
        );

        $query2 = new WP_Query($args2);

        for ($i = 0; $i < count($query2->posts) && $i < $posts_per_page; $i++) {
			if (! in_array($query2->posts[ $i ]->ID, $news_ids)) {
				$id = $query2->posts[$i]->ID;
				$news_ids[] = $id;
                $subtitulo = get_post_meta($id, 'subtitulo', true);
				$img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
				?>

					<div class="chamada">

                        <a href="<?= get_permalink($id); ?>">
                            <?php if (!isset($first)): ?>
                                <?php if (!empty($img)): ?>
                                    <figure><img src="<?= $img; ?>" <?=getMedidasImagem('news_home');?>></figure>
                                <?php endif;
                            endif; ?>

                            <p class="call-chamada"><?= get_the_title($id); ?></p>
                            <?php if ($subtitulo == ""): ?>
                                    <p><?php resumo(170, get_the_excerpt()); ?></p>
                            <?php else: ?>
                                    <p><?php resumo(170, $subtitulo); ?></p>
                            <?php endif; ?>
                            
                        </a>
					</div>
                    <?php
                    $adulto = get_post_meta($id, 'conteudo_adulto', true);
                    if (!isset($first) && $adulto == false):
                    ?>
                        <?php include get_template_directory() . '/ad-long.php'; ?>
                        
                        <div class="titulo" style="margin-top: 20px;">Últimas Notícias</div>
                    
                    <?php
                    endif;
                    $first = true;
                }
            }
        ?>
        

    </div>
    <div class="grid-base">
        <div class="chamada">
            <div>
                <?php echo adrotate_group(9); ?>
            </div>
        </div>
    </div>

</aside>