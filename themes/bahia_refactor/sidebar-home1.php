<aside>
	<?php include get_template_directory() . '/ad-long.php'; ?>
	
    <div class="box-news-int">

        <?php
		$sidebarPost2 = get_option('options_sidebar_post_2');
        
        if($sidebarPost2) {
            $id2 = $sidebarPost2[0];
            $news_ids[] = $id2;
            
            // Obtém todos os campos ACF de uma vez
            $fields = get_post_acf_fields($id2);
            $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
            $ftId = isset($fields['imagem']['id']) ? $fields['imagem']['id'] : '';
            $caption = wp_get_attachment_caption($ftId);
            $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
            $post_type = get_post_type($id2);
            ?>

            <div class="chamada">
                <span class="categoriaMobile spanEntrevista" style="float: left; background-color: #15559e !important;">
                    <?php
                    echo post_label($post_type, $id2);
                    ?>
                </span>
                <div class="divChamadaPostTypeMobile" style="float: left">
                    <a href="<?= get_permalink($id2); ?>">

                        <?php if (!empty($img)): ?>
                            <figure><img alt="<?= $caption; ?>" src="<?= $img; ?>" <?=getMedidasImagem('news_home');?>></figure>
                        <?php endif;
                        ?>
                        <p class="call-chamada"><?= get_the_title($id2); ?></p>
                        <?php if ($subtitulo == ""): ?>
                            <p class="txtSubtitleHome"><?php resumo(170, get_the_excerpt()); ?></p>
                        <?php else: ?>
                            <p class="txtSubtitleHome"><?php resumo(170, $subtitulo); ?></p>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="<?php echo $post_type; ?>">
                    <span class="categoriaMobile spanEntrevista divLerTodosEntrevista" style="float: left; background-color: #15559e !important;">
                        LER TODOS
                    </span>
                </a>

            <?php
        }
        ?>

        </div>
    </div>
    
    
    
    <div class="box-news-int">

        <?php
        
        $sidebarPost3 = get_option('options_sidebar_post_3');
        
        if($sidebarPost3) {
            $id3 = $sidebarPost3[0];
            $news_ids[] = $id3;
            $fields = get_post_acf_fields($id3);
            $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
            $ftId = isset($fields['imagem']['id']) ? $fields['imagem']['id'] : '';
			$caption = wp_get_attachment_caption($ftId);
            $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
            $post_type = get_post_type($id3);
            
            $divChamada = "";
            $spanCategoria = "";
            if ($post_type == "especial") {
                $divChamada = "divChamadaPostTypeMobile";
            }

            if ($post_type == "especial") {
                $spanCategoria = "spanEspecial";
                $lerTodos = "divLerTodosEspecial";
            } else {
                $lerTodos = "divLerMaisHome";
            }

            ?>

            <div class="chamada">
                <span class="categoriaMobile <?=$spanCategoria?>" style="float: left;">
                    <?php
                    if($post_type != "entretenimento") {
                        echo obterTextoPostType($post_type);
                    } else {
                        echo obterTituloEntretenimento($id3);
                    }
                    ?>
                </span>
                <div class="<?=$divChamada?>" style="float: left">
                    <a href="<?= get_permalink($id3); ?>">

                        <?php if (!empty($img)): ?>
                            <figure><img alt="<?= $caption; ?>" src="<?= $img; ?>" <?=getMedidasImagem('news_home');?>></figure>
                        <?php endif;
                        ?>
                        <p class="call-chamada"><?= get_the_title($id3); ?></p>
                        <?php if ($subtitulo == ""): ?>
                            <p class="txtSubtitleHome"><?php resumo(170, get_the_excerpt()); ?></p>
                        <?php else: ?>
                            <p class="txtSubtitleHome"><?php resumo(170, $subtitulo); ?></p>
                        <?php endif; ?>
                    </a>
                </div>
                <a href="<?php echo $post_type; ?>">
                    <span class="categoriaMobile <?=$lerTodos?>">
                        LER TODOS
                    </span>
                </a>

            </div>
        </div>
        <?php
        }
        ?>
            
            

            

</aside>