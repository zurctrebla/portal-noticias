<aside>
	<?php include get_template_directory() . '/ad-long.php'; ?>
	
    <div class="box-news-int">

        <?php
		$sidebarPost2 = get_option('options_sidebar_post_2');
        
        if($sidebarPost2) {
            $id2 = $sidebarPost2[0];
            $news_ids[] = $id2;
            $ftId = get_post_meta($id2, 'imagem', true);
			$img = as3cf_get_attachment_url($ftId);
			$caption = wp_get_attachment_caption($ftId);
            $subtitulo = get_post_meta($id2, 'subtitulo', true);
            ?>

            <div class="chamada">
                <span class="categoriaMobile spanEntrevista" style="float: left; background-color: #15559e !important;">
                    <?php
                    echo post_label($id2);
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
                <a href="<?php echo get_post_type($id2); ?>">
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
            $ftId = get_post_meta($id3, 'imagem', true);
			$img = as3cf_get_attachment_url($ftId);
			$caption = wp_get_attachment_caption($ftId);
            $subtitulo = get_post_meta($id3, 'subtitulo', true);
            
            $divChamada = "";
            $spanCategoria = "";
            if (get_post_type($id) == "especial") {
                $divChamada = "divChamadaPostTypeMobile";
            }

            if (get_post_type($id) == "especial") {
                $spanCategoria = "spanEspecial";
                $lerTodos = "divLerTodosEspecial";
            } else {
                $lerTodos = "divLerMaisHome";
            }

            ?>

            <div class="chamada">
                <span class="categoriaMobile <?=$spanCategoria?>" style="float: left;">
                    <?php
                    if(get_post_type($id3) != "entretenimento") {
                        echo obterTextoPostType($id3, get_post_field('post_author', $id3));
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
                <a href="<?php echo get_post_type($id3); ?>">
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