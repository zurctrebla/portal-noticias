<?php
/**
 * Componentes de Ícones de Redes Sociais
 *
 * Este arquivo contém funções para renderizar ícones SVG padronizados
 * das redes sociais em todo o site.
 */

/**
 * Renderiza o ícone SVG do Facebook
 * SVG padrão usado em todo o site (30x30)
 *
 * @param int $size Tamanho do ícone em pixels (padrão: 30)
 * @return string HTML do SVG
 */
function render_facebook_icon($size = 30) {
    return sprintf(
        '<svg width="%d" height="%d" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M30.337,5.057 C30.337,2.400 27.938,0.000 25.281,0.000 C25.281,0.000 5.056,0.000 5.056,0.000 C2.399,0.000 0.000,2.400 0.000,5.057 C0.000,5.057 0.000,25.281 0.000,25.281 C0.000,27.937 2.399,30.337 5.057,30.337 C5.057,30.337 15.169,30.337 15.169,30.337 C15.169,30.337 15.169,18.876 15.169,18.876 C15.169,18.876 11.461,18.876 11.461,18.876 C11.461,18.876 11.461,13.820 11.461,13.820 C11.461,13.820 15.169,13.820 15.169,13.820 C15.169,13.820 15.169,11.850 15.169,11.850 C15.169,8.453 17.720,5.393 20.857,5.393 C20.857,5.393 24.944,5.393 24.944,5.393 C24.944,5.393 24.944,10.449 24.944,10.449 C24.944,10.449 20.857,10.449 20.857,10.449 C20.409,10.449 19.888,10.992 19.888,11.806 C19.888,11.806 19.888,13.820 19.888,13.820 C19.888,13.820 24.944,13.820 24.944,13.820 C24.944,13.820 24.944,18.876 24.944,18.876 C24.944,18.876 19.888,18.876 19.888,18.876 C19.888,18.876 19.888,30.337 19.888,30.337 C19.888,30.337 25.281,30.337 25.281,30.337 C27.938,30.337 30.337,27.937 30.337,25.281 C30.337,25.281 30.337,5.057 30.337,5.057 Z" fill="#1877F2"/>
        </svg>',
        $size, $size
    );
}

/**
 * Renderiza o ícone SVG do Twitter
 * SVG padrão usado em todo o site (30x30)
 *
 * @param int $size Tamanho do ícone em pixels (padrão: 30)
 * @return string HTML do SVG
 */
function render_twitter_icon($size = 30) {
    return sprintf(
        '<svg width="%d" height="%d" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M28.365,1.635 C27.264,0.534 25.937,-0.017 24.386,-0.017 C24.386,-0.017 5.614,-0.017 5.614,-0.017 C4.063,-0.017 2.737,0.534 1.635,1.635 C0.534,2.736 -0.017,4.063 -0.017,5.614 C-0.017,5.614 -0.017,24.386 -0.017,24.386 C-0.017,25.937 0.534,27.263 1.635,28.365 C2.737,29.466 4.063,30.017 5.614,30.017 C5.614,30.017 24.386,30.017 24.386,30.017 C25.937,30.017 27.264,29.466 28.365,28.365 C29.466,27.263 30.017,25.937 30.017,24.386 C30.017,24.386 30.017,5.614 30.017,5.614 C30.017,4.063 29.466,2.736 28.365,1.635 ZM22.958,11.539 C22.971,11.656 22.978,11.832 22.978,12.067 C22.978,13.162 22.818,14.260 22.499,15.362 C22.180,16.463 21.691,17.519 21.032,18.530 C20.374,19.540 19.589,20.433 18.676,21.208 C17.764,21.984 16.669,22.603 15.391,23.066 C14.114,23.529 12.745,23.760 11.285,23.760 C9.017,23.760 6.919,23.147 4.989,21.922 C5.302,21.961 5.628,21.981 5.967,21.981 C7.857,21.981 9.558,21.394 11.071,20.221 C10.184,20.208 9.392,19.934 8.695,19.400 C7.997,18.865 7.512,18.187 7.238,17.366 C7.577,17.418 7.831,17.444 8.000,17.444 C8.287,17.444 8.620,17.392 8.998,17.288 C8.046,17.105 7.244,16.633 6.593,15.870 C5.941,15.108 5.615,14.237 5.615,13.260 C5.615,13.260 5.615,13.221 5.615,13.221 C6.306,13.546 6.957,13.716 7.570,13.729 C6.384,12.934 5.791,11.793 5.791,10.307 C5.791,9.577 5.980,8.886 6.358,8.234 C7.387,9.499 8.639,10.509 10.112,11.265 C11.585,12.021 13.162,12.445 14.844,12.536 C14.779,12.289 14.746,11.976 14.746,11.598 C14.746,10.463 15.147,9.496 15.949,8.694 C16.750,7.892 17.718,7.491 18.852,7.491 C20.052,7.491 21.049,7.921 21.844,8.782 C22.744,8.612 23.618,8.280 24.465,7.785 C24.139,8.788 23.532,9.551 22.646,10.072 C23.493,9.955 24.282,9.733 25.012,9.407 C24.464,10.242 23.780,10.952 22.958,11.539 Z" fill="#1DA1F2"/>
        </svg>',
        $size, $size
    );
}

/**
 * Renderiza o ícone SVG do Instagram
 * SVG padrão usado em todo o site (30x30)
 *
 * @param int $size Tamanho do ícone em pixels (padrão: 30)
 * @param string $gradient_id ID único para o gradiente (necessário quando há múltiplos no mesmo HTML)
 * @return string HTML do SVG
 */
function render_instagram_icon($size = 30, $gradient_id = 'instagram-gradient') {
    return sprintf(
        '<svg width="%d" height="%d" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <linearGradient id="%s" x1="0%%" y1="100%%" x2="100%%" y2="0%%">
                    <stop offset="0%%" style="stop-color:#FCAF45;stop-opacity:1" />
                    <stop offset="25%%" style="stop-color:#FD1D1D;stop-opacity:1" />
                    <stop offset="50%%" style="stop-color:#E1306C;stop-opacity:1" />
                    <stop offset="75%%" style="stop-color:#C13584;stop-opacity:1" />
                    <stop offset="100%%" style="stop-color:#833AB4;stop-opacity:1" />
                </linearGradient>
            </defs>
            <path d="M27.000,-0.000 C27.000,-0.000 3.000,-0.000 3.000,-0.000 C1.350,-0.000 -0.000,1.350 -0.000,3.000 C-0.000,3.000 -0.000,27.000 -0.000,27.000 C-0.000,28.650 1.350,30.000 3.000,30.000 C3.000,30.000 27.000,30.000 27.000,30.000 C28.650,30.000 30.000,28.650 30.000,27.000 C30.000,27.000 30.000,3.000 30.000,3.000 C30.000,1.350 28.650,-0.000 27.000,-0.000 ZM15.000,9.000 C18.300,9.000 21.000,11.700 21.000,15.000 C21.000,18.300 18.300,21.000 15.000,21.000 C11.700,21.000 9.000,18.300 9.000,15.000 C9.000,11.700 11.700,9.000 15.000,9.000 ZM3.750,27.000 C3.300,27.000 3.000,26.700 3.000,26.250 C3.000,26.250 3.000,13.500 3.000,13.500 C3.000,13.500 6.150,13.500 6.150,13.500 C6.000,13.950 6.000,14.550 6.000,15.000 C6.000,19.950 10.050,24.000 15.000,24.000 C19.950,24.000 24.000,19.950 24.000,15.000 C24.000,14.550 24.000,13.950 23.850,13.500 C23.850,13.500 27.000,13.500 27.000,13.500 C27.000,13.500 27.000,26.250 27.000,26.250 C27.000,26.700 26.700,27.000 26.250,27.000 C26.250,27.000 3.750,27.000 3.750,27.000 ZM27.000,6.750 C27.000,7.200 26.700,7.500 26.250,7.500 C26.250,7.500 23.250,7.500 23.250,7.500 C22.800,7.500 22.500,7.200 22.500,6.750 C22.500,6.750 22.500,3.750 22.500,3.750 C22.500,3.300 22.800,3.000 23.250,3.000 C23.250,3.000 26.250,3.000 26.250,3.000 C26.700,3.000 27.000,3.300 27.000,3.750 C27.000,3.750 27.000,6.750 27.000,6.750 Z" fill="url(#%s)" fill-rule="evenodd"/>
        </svg>',
        $size, $size, $gradient_id, $gradient_id
    );
}

/**
 * Renderiza o ícone SVG do WhatsApp
 * SVG padrão usado em todo o site (30x30)
 *
 * @param int $size Tamanho do ícone em pixels (padrão: 30)
 * @return string HTML do SVG
 */
function render_whatsapp_icon($size = 30) {
    return sprintf(
        '<svg width="%d" height="%d" viewBox="0 0 30 30" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M30.000,14.778 C30.000,22.939 23.333,29.556 15.107,29.556 C12.496,29.556 10.043,28.888 7.908,27.717 C7.908,27.717 -0.337,30.337 -0.337,30.337 C-0.337,30.337 2.351,22.408 2.351,22.408 C0.995,20.181 0.214,17.570 0.214,14.778 C0.214,6.616 6.882,0.000 15.107,0.000 C23.333,0.000 30.000,6.616 30.000,14.778 ZM15.107,2.353 C8.202,2.353 2.586,7.927 2.586,14.778 C2.586,17.497 3.472,20.014 4.971,22.062 C4.971,22.062 3.407,26.676 3.407,26.676 C3.407,26.676 8.219,25.147 8.219,25.147 C10.196,26.445 12.564,27.202 15.107,27.202 C22.011,27.202 27.628,21.629 27.628,14.778 C27.628,7.927 22.012,2.353 15.107,2.353 ZM22.628,18.181 C22.536,18.030 22.293,17.940 21.928,17.759 C21.563,17.577 19.767,16.701 19.433,16.580 C19.099,16.460 18.855,16.399 18.611,16.761 C18.368,17.124 17.669,17.940 17.455,18.181 C17.242,18.423 17.030,18.454 16.664,18.272 C16.300,18.091 15.123,17.708 13.729,16.475 C12.644,15.515 11.911,14.329 11.698,13.966 C11.485,13.604 11.676,13.408 11.858,13.228 C12.023,13.065 12.224,12.805 12.406,12.594 C12.589,12.382 12.650,12.231 12.771,11.989 C12.893,11.747 12.832,11.536 12.740,11.354 C12.649,11.173 11.918,9.391 11.614,8.666 C11.310,7.941 11.007,8.062 10.793,8.062 C10.581,8.062 10.337,8.031 10.093,8.031 C9.850,8.031 9.454,8.122 9.120,8.484 C8.785,8.847 7.843,9.723 7.843,11.505 C7.843,13.288 9.150,15.010 9.333,15.251 C9.515,15.493 11.858,19.269 15.569,20.720 C19.281,22.170 19.281,21.686 19.950,21.626 C20.619,21.565 22.110,20.750 22.415,19.904 C22.719,19.057 22.719,18.332 22.628,18.181 Z" fill="#25D366" fill-rule="evenodd"/>
        </svg>',
        $size, $size
    );
}

/**
 * Renderiza links para as redes sociais (botões de "seguir")
 *
 * @param int $size Tamanho dos ícones em pixels (padrão: 30)
 * @param array $options Opções de customização
 * @return void (imprime HTML diretamente)
 */
function render_social_follow_links($size = 30, $options = []) {
    $whatsapp = get_option('options_whatsapp');
    $facebook = get_option('options_facebook');
    $twitter = get_option('options_twitter');
    $instagram = get_option('options_instagram');

    $wrapper_class = isset($options['wrapper_class']) ? $options['wrapper_class'] : 'social-links';
    $link_style = isset($options['link_style']) ? $options['link_style'] : 'display: inline-block; margin: 0 8px; text-decoration: none; transition: opacity 0.3s;';

    // Só usa wrapper se wrapper_class não estiver vazio
    $has_wrapper = !empty($wrapper_class);

    if ($has_wrapper) {
        echo '<div class="' . esc_attr($wrapper_class) . '">';
    }

    // WhatsApp
    if ($whatsapp) {
        echo sprintf(
            '<a href="https://wa.me/%s?text=%s" target="_blank" class="social-link social-whatsapp rede-whatsapp" aria-label="WhatsApp" title="WhatsApp" style="%s">%s</a>',
            esc_attr($whatsapp),
            urlencode('Olá, vim do site Bahia.ba'),
            $link_style,
            render_whatsapp_icon($size)
        );
    }

    // Facebook
    if ($facebook) {
        echo sprintf(
            '<a href="%s" target="_blank" class="social-link social-facebook rede-facebook" aria-label="Facebook" title="Facebook" style="%s">%s</a>',
            esc_url($facebook),
            $link_style,
            render_facebook_icon($size)
        );
    }

    // Twitter
    if ($twitter) {
        echo sprintf(
            '<a href="%s" target="_blank" class="social-link social-twitter rede-twitter" aria-label="Twitter" title="Twitter" style="%s">%s</a>',
            esc_url($twitter),
            $link_style,
            render_twitter_icon($size)
        );
    }

    // Instagram
    if ($instagram) {
        echo sprintf(
            '<a href="%s" target="_blank" class="social-link social-instagram rede-instagram" aria-label="Instagram" title="Instagram" style="%s">%s</a>',
            esc_url($instagram),
            $link_style,
            render_instagram_icon($size, 'instagram-gradient-' . uniqid())
        );
    }

    if ($has_wrapper) {
        echo '</div>';
    }
}
