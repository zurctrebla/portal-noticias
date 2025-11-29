<aside>
	<div class="box-news-int">
		<div class="titulo"><strong>/</strong> Página Principal</div>

		<div class="titulo"><strong>/</strong> Página Principal</div>

        <?php
        $ids[] = get_the_ID();
        $posts_per_page = 5;
        global $POST_TYPES;
        $args = array(
            'post_type' => $POST_TYPES,
            'showposts' => $posts_per_page,
            'posts_per_page'	=> $posts_per_page + count($ids),
            'no_found_rows' => true,
            'ignore_sticky_posts' => true
        );

        $query = new WP_Query($args);

        for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
			if (! in_array($query->posts[ $i ]->ID, $ids)) {
				$id = $query->posts[$i]->ID;
				$ids[] = $id;
				
                // Obtém todos os campos ACF de uma vez
                $fields = get_post_acf_fields($id);
                $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
                $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
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
                    $first = true;
                }
            }
        ?>
                
	</div>

</aside>