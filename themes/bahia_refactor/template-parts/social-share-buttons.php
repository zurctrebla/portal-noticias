<?php
/**
 * Template Part: Botões de Compartilhamento em Redes Sociais
 *
 * Exibe botões para compartilhar o conteúdo atual nas redes sociais.
 * Inclui: Facebook, Twitter, WhatsApp e Instagram
 *
 * @param string $classes Classes CSS adicionais para o container
 */

// Carrega as funções de ícones
require_once get_template_directory() . '/template-parts/social-icons.php';

// Obtém a URL do post atual
$urlPost = isset($urlPost) ? $urlPost : get_permalink();
$post_title = get_the_title();

// Obtém as configurações de redes sociais
$whatsapp = get_option('options_whatsapp');
$facebook = get_option('options_facebook');
$instagram = get_option('options_instagram');

// Classes CSS para o container
$container_classes = isset($classes) ? $classes : 'sharebox';
$icon_size = isset($icon_size) ? $icon_size : 35;
?>

<ul class="<?php echo esc_attr($container_classes); ?>" style="display: flex; flex-direction: row; align-items: center; gap: 10px; list-style: none; padding: 0; margin: 0;">
    <!-- Twitter -->
    <li style="display: inline-block; margin: 0; padding: 0;">
        <a class="social-share-btn social-twitter"
           title="Compartilhar no Twitter"
           onclick="window.open('https://twitter.com/intent/tweet?url='+encodeURIComponent('<?= $urlPost ?>')+'&text=<?php resumo(80, $post_title); ?>', '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"
           style="display: inline-block; cursor: pointer; line-height: 0;">
            <?php echo render_twitter_icon($icon_size); ?>
        </a>
    </li>

    <!-- Facebook -->
    <li style="display: inline-block; margin: 0; padding: 0;">
        <a class="social-share-btn social-facebook"
           title="Compartilhar no Facebook"
           onclick="window.open('https://www.facebook.com/sharer/sharer.php?u='+encodeURIComponent('<?= $urlPost ?>'), '', 'toolbar=no, location=no, status=no, menubar=no, scrollbars=no, resizable=no, top=200, left='+Math.round((screen.width / 2) - (550 / 2))+', width=550, height=450');"
           style="display: inline-block; cursor: pointer; line-height: 0;">
            <?php echo render_facebook_icon($icon_size); ?>
        </a>
    </li>

    <!-- WhatsApp -->
    <?php if ($whatsapp): ?>
    <li style="display: inline-block; margin: 0; padding: 0;">
        <a href="https://wa.me/<?= $whatsapp ?>?text=<?= urlencode('Olá! Vi esta notícia: ') . $urlPost ?>"
           target="_blank"
           class="social-share-btn social-whatsapp"
           title="Compartilhar no WhatsApp"
           style="display: inline-block; cursor: pointer; line-height: 0;">
            <?php echo render_whatsapp_icon($icon_size); ?>
        </a>
    </li>
    <?php endif; ?>

    <!-- Instagram -->
    <?php if ($instagram): ?>
    <li style="display: inline-block; margin: 0; padding: 0;">
        <a href="<?= $instagram ?>"
           target="_blank"
           class="social-share-btn social-instagram"
           title="Visite nosso Instagram"
           style="display: inline-block; cursor: pointer; line-height: 0;">
            <?php echo render_instagram_icon($icon_size, 'instagram-gradient-share-' . uniqid()); ?>
        </a>
    </li>
    <?php endif; ?>
</ul>
