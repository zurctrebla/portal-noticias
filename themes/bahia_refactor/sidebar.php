<aside>
    <div class="box-news-int">

        <?php
        if (isset($author) && ($author == 17 || $author == 58)):
            $dados = (isset($_GET['author_name'])) ? get_user_by('slug', $author_name) : get_userdata(intval($author));
        ?>
            <div class="infoLevi">
                <div class="nome"><?php the_author(); ?></div>
                <div class="thumb"><?php foto_perfil($author, 195); ?></div>
                <div class="call-colunista"><?php echo wpautop($dados->description); ?></div>
            </div>
        <?php endif; ?>

        <div class="grid-base">
            <div class="chamada">
                <div>
                    <?php echo adrotate_group(8); ?>
					
                </div>
            </div>
        </div>
        			
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