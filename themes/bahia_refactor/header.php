<?php
$ipad = strpos(getUserAgent(), "iPad");
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta property="fb:app_id" content="826810440751032" />
    <meta property="fb:admins" content="544534914" />
    <meta name="keywords" content="política, bahia, economia, últimas notícias, jornalismo, informação, notícia, cultura, entretenimento, lazer, opinião, análise, internet, televisão, fotografia, imagem, som, áudio, vídeo, fotos, tecnologia, esporte, eleicoes2024">
    <meta property="og:site_name" content="<?php bloginfo('name'); ?>">
    <?php
    if (is_single()):
        // Obtém todos os campos ACF de uma vez
        $fields = get_post_acf_fields(get_the_ID());
        $img = isset($fields['imagem_url']) && is_string($fields['imagem_url']) ? $fields['imagem_url'] : '';
        $titulo = get_the_title(get_the_ID());
        $subtitulo = isset($fields['subtitulo']) && is_string($fields['subtitulo']) ? $fields['subtitulo'] : '';
        ?>
        <meta property="og:title" content="<?= $titulo ?>" />
        <meta property="og:description" content="<?= $subtitulo ?>">
        <meta property="og:url" content="<?php the_permalink() ?>"/>
        <meta property="og:type" content="article" />
        <meta property="og:image" content="<?= $img; ?>" />
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta name="title" content="<?= $titulo ?>">
        <meta name="description" content="<?= $subtitulo ?>">
        <meta name="twitter:site" content="@pontoBA" />
        <meta name="twitter:creator" content="@pontoBA" />
        <meta name="twitter:url" content="<?php the_permalink() ?>" />
        <meta name="twitter:domain" content="<?php bloginfo('url'); ?>" />
        <meta name="twitter:title" content="<?= $titulo ?>" />
        <meta name="twitter:description" content="<?= $subtitulo ?>" />
        <meta name="twitter:card" content="summary_large_image" />
        <meta name="twitter:image" content="<?= $img; ?>" />
        <title>Bahia.ba | <?= $titulo ?></title>
    <?php else: ?>
            <meta name="title" content="Bahia.ba">
            <meta name="description" content="O Bahia.ba busca cobrir minuciosamente a política do estado da Bahia, a realidade da nossa sociedade, bem como as notícias do mundo, da economia, comportamento, saúde e bem estar, meio ambiente, artes e entretenimento">
            <meta property="og:title" content="Bahia.ba | Seu portal de notícias com 40 anos de credibilidade" />
            <meta property="og:description" content="O Bahia.ba busca cobrir minuciosamente a política do estado da Bahia, a realidade da nossa sociedade, bem como as notícias do mundo, da economia, comportamento, saúde e bem estar, meio ambiente, artes e entretenimento" />
            <meta property="og:url" content="<?php bloginfo('url'); ?>"/>
            <meta property="og:type" content="website" />
            <meta property="og:image" content="https://d1x4bjge7r9nas.cloudfront.net/wp-content/uploads/2023/01/20113045/logo_big.jpg" />
            <meta property="og:image:type" content="image/jpeg">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <title>Bahia.Ba</title>
    <?php endif; ?>
    <link rel="shortcut icon" href="<?php bloginfo('template_url'); ?>/assets/imgs/favicon-ba.png" type="image/png" />
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-JBPJTKCCXY"></script>
    <script data-ad-client="ca-pub-5262455055831929" async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script>
    <!-- Google Analytics -->
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-JBPJTKCCXY');
        (function (i, s, o, g, r, a, m) {
            i['GoogleAnalyticsObject'] = r;
            i[r] = i[r] || function () {
                (i[r].q = i[r].q || []).push(arguments)
            }, i[r].l = 1 * new Date();
            a = s.createElement(o),
                    m = s.getElementsByTagName(o)[0];
            a.async = 1;
            a.src = g;
            m.parentNode.insertBefore(a, m)
        })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');

        ga('create', 'UA-67237036-1', 'auto');
        ga('set', 'anonymizeIp', true);
        ga('send', 'pageview');
    </script>
    <script type="text/javascript">!function(){"use strict";function e(e){var t=!(arguments.length>1&&void 0!==arguments[1])||arguments[1],c=document.createElement("script");c.src=e,t?c.type="module":(c.async=!0,c.type="text/javascript",c.setAttribute("nomodule",""));var n=document.getElementsByTagName("script")[0];n.parentNode.insertBefore(c,n)}!function(t,c){!function(t,c,n){var a,o,r;n.accountId=c,null!==(a=t.marfeel)&&void 0!==a||(t.marfeel={}),null!==(o=(r=t.marfeel).cmd)&&void 0!==o||(r.cmd=[]),t.marfeel.config=n;var i="https://sdk.mrf.io/statics";e("".concat(i,"/marfeel-sdk.js?id=").concat(c),!0),e("".concat(i,"/marfeel-sdk.es5.js?id=").concat(c),!1)}(t,c,arguments.length>2&&void 0!==arguments[2]?arguments[2]:{})}(window,9599,{} /* Config */)}();</script>
    <?php
    $theme_uri = get_template_directory_uri();
    $css_file = $theme_uri . '/dist/css/main.min.css';
    if (file_exists(get_template_directory() . '/dist/css/main.min.css')) {
        $css_version = filemtime(get_template_directory() . '/dist/css/main.min.css');
        ?>
        <link rel="preload" href="<?php echo $css_file; ?>?ver=<?php echo $css_version; ?>" as="style">
        <link rel="stylesheet" href="<?php echo $css_file; ?>?ver=<?php echo $css_version; ?>" media="all">
    <?php
    }
    if (file_exists(get_template_directory() . '/dist/js/theme.min.js')) {
        echo '<link rel="preload" href="' . $theme_uri . '/dist/js/theme.min.js" as="script">' . "\n";
    }
    ?>
    <?php wp_head(); ?>
</head>
<body>
    <?php
    if (is_mobile()): ?>
        <div class="divToTop" id="toTopMobile" style="display: none;"><img src="<?php bloginfo('template_url'); ?>/assets/imgs/icon_top.png" alt="Voltar ao topo" title="Voltar ao topo"></div>
        <div class="ui left vertical sidebar menu" id="left-menu">
            <a class="item" href="<?php bloginfo('url'); ?>/ultimas-noticias">
                <p class="txtItemMenu">Últimas Notícias</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/politica-de-privacidade">
                <p class="txtItemMenu">Política de Privacidade</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/politica-de-cookies">
                <p class="txtItemMenu">Política de Cookies</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/carnaval">
                <p class="txtItemMenu">Carnaval</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/politica">
                <p class="txtItemMenu">Política</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/social">
                <p class="txtItemMenu">Ginno Larry</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/investimentos">
                <p class="txtItemMenu">Investimentos</p>
            </a>
            <!-- <a class="item" href="<?//php bloginfo('url'); ?>/eleicoes2024">
                <p class="txtItemMenu">Eleições 2024</p>
            </a> -->
            <a class="item" href="<?php bloginfo('url'); ?>/salvador">
                <p class="txtItemMenu">Salvador</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/bahia">
                <p class="txtItemMenu">Bahia</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/municipios">
                <p class="txtItemMenu">Municípios</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/justica">
                <p class="txtItemMenu">Justiça</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/especial">
                <p class="txtItemMenu">Especial</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/exclusivo">
                <p class="txtItemMenu">Exclusivo</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/esporte">
                <p class="txtItemMenu">Esporte</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/brasil">
                <p class="txtItemMenu">Brasil</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/entretenimento">
                <p class="txtItemMenu">Entretenimento</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/mais_gente">
                <p class="txtItemMenu">Mais Gente</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/entrevista">
                <p class="txtItemMenu">Entrevistas</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/economia">
                <p class="txtItemMenu">Economia</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/mundo">
                <p class="txtItemMenu">Mundo</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/mais_noticias">
                <p class="txtItemMenu">Mais Notícias</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/artigo">
                <p class="txtItemMenu">Artigos</p>
            </a>
            <!-- <a class="item" href="<?//php bloginfo('url'); ?>/contatos">
                <p class="txtItemMenu">Contatos</p>
            </a> -->
        </div>
        <div class="ui top fixed medium menu page grid" id="top-menu">
            <a href="#/" aria-label="icone_mobile" class="mobile-button item removeBorderItemMenu">
                <i class="sidebar blue icon"></i>
            </a>
            <div class="item removeBorderItemMenu">
                <a href="<?php bloginfo('url'); ?>">
                    <img width="130" height="30" alt="logo" style="width: 130px" src="<?php bloginfo('template_url'); ?>/assets/imgs/logo.png">
                </a>
            </div>
                <div class="right item removeBorderItemMenu">
                    <div style="right: 3rem;" class="ui icon top right pointing dropdown button <?php if($ipad){echo "dropdownBtnSearchIpad";}else{echo "dropdownBtnSearchMobile";} ?>">
                        <i class="search icon"></i>
                        <div class="menu divMenu">
                            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                                <div class="ui fluid action input">
                                    <input type="search" id="txtSearch" name="s" placeholder="Buscar..." value="<?php echo get_search_query(); ?>">
                                    <button type="submit" class="ui blue button" id="btnSearch">
                                        <i class="search icon"></i>
                                    </button>
                                </div>
                            </form>
                            <div class="item" style="display: none;"></div>
                        </div>
                    </div>
                </div>
        </div>
        <div class="pusher"><!-- INÍCIO PUSHER -->
    <?php else: ?>
        <div class="divToTop" id="toTop" style="display: none;"><img src="<?php bloginfo('template_url'); ?>/assets/imgs/icon_top.png" alt="Voltar ao topo" title="Voltar ao topo"></div>
        <div class="ui borderless fixed main menu three item menuNovo" style="display: none;">
            <div class="item" style="padding-left: 10% !important;">
                <div class="ui top left pointing dropdown icon blue basic menuHeader" style="color: #4183c4 !important;">
                    Seções
                    <i class="sidebar icon"></i>
                    <div class="menu" style="width: 200px;">
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/ultimas-noticias">
                            <a href="#/">Últimas Notícias</a>
                        </div>
                        <div class="item">
                            <i class="dropdown icon"></i>
                            <div class="url-link" data-url="<?php bloginfo('url'); ?>/">
                                <span class="text" >
                                    <a href="#/">Privacidade</a>
                                </span>
                            </div>
                            <div class="menu ">
                                <div class="item url-link"><a href="#/" class="linkMenu" data-url="<?php bloginfo('url'); ?>/politica-de-privacidade">Política de Privacidade</a></div>
                                <div class="item url-link"><a href="#/" class="linkMenu" data-url="<?php bloginfo('url'); ?>/politica-de-cookies">Política de Cookies</a></div>
                            </div>
                        </div>
                        <div class="item">
                            <!-- <i class="dropdown icon"></i> -->
                            <div class="url-link" data-url="<?php bloginfo('url'); ?>/politica">
                                <span class="text" >
                                    <a href="#/">Política</a>
                                </span>
                            </div>
                            <!-- <div class="menu url-link" data-url="<?//php bloginfo('url'); ?>/eleicoes2024">
                                <div class="item"><a href="#/">Eleições 2024</a></div>
                            </div> -->
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/social">
                            <a href="#/">Ginno Larry</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/investimentos">
                            <a href="#/">Investimentos</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/salvador">
                            <a href="#/">Salvador</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/bahia">
                            <a href="#/">Bahia</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/municipios">
                            <a href="#/">Municípios</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/especial">
                            <a href="#/">Especial</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/exclusivo">
                            <a href="#/">Exclusivo</a>
                        </div>
                        <div class="item">
                            <i class="dropdown icon"></i>
                            <div class="url-link" data-url="<?php bloginfo('url'); ?>/esporte">
                                <span class="text" >
                                    <a href="#/">Esporte</a>
                                </span>
                            </div>
                            <div class="menu url-link" data-url="<?php bloginfo('url'); ?>/esporte?category=brasileirao">
                                <div class="item"><a href="#/">Brasileirão 2025</a></div>
                            </div>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/brasil">
                            <a href="#/">Brasil</a>
                        </div>
                        <div class="item">
                            <i class="dropdown icon"></i>
                            <div class="url-link" data-url="<?php bloginfo('url'); ?>/entretenimento">
                                <span class="text" >
                                    <a href="#/">Entretenimento</a>
                                </span>
                            </div>
                            <div class="menu url-link" data-url="<?php bloginfo('url'); ?>/carnaval">
                                <div class="item"><a href="#/">Carnaval</a></div>
                            </div>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mais_gente">
                            <a href="#/">Mais Gente</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/entrevista">
                            <a href="#/">Entrevistas</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/economia">
                            <a href="#/">Economia</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mundo">
                            <a href="#/">Mundo</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mais_noticias">
                            <a href="#/">Mais Notícias</a>
                        </div>
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/artigo">
                            <a href="#/">Artigos</a>
                        </div>
                        <!-- <div class="item url-link" data-url="<?//php bloginfo('url'); ?>/contatos">
                            <a href="#/">Contatos</a>
                        </div> -->

                    </div>
                </div>
            </div>

            <div class="item">
                <a href="/"><img class="ui small image" width="130" height="30" alt="logo" src="<?php bloginfo('template_url'); ?>/assets/imgs/logo.png"></a>
            </div>

            <div class="item" style="width: 5% !important;">
                <?php
                $facebook = get_option('options_facebook');
                if ($facebook):
                ?>
                    <a title="Facebook" style="display: inline-block; vertical-align: top; margin: 0 0 0 8px; text-decoration: none; opacity: 0.5;"  href="<?= $facebook; ?>" aria-label="Facebook" class="rede-facebook" target="_blank">
                        <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M20 1C21.6569 1 23 2.34315 23 4V20C23 21.6569 21.6569 23 20 23H4C2.34315 23 1 21.6569 1 20V4C1 2.34315 2.34315 1 4 1H20ZM20 3C20.5523 3 21 3.44772 21 4V20C21 20.5523 20.5523 21 20 21H15V13.9999H17.0762C17.5066 13.9999 17.8887 13.7245 18.0249 13.3161L18.4679 11.9871C18.6298 11.5014 18.2683 10.9999 17.7564 10.9999H15V8.99992C15 8.49992 15.5 7.99992 16 7.99992H18C18.5523 7.99992 19 7.5522 19 6.99992V6.31393C19 5.99091 18.7937 5.7013 18.4813 5.61887C17.1705 5.27295 16 5.27295 16 5.27295C13.5 5.27295 12 6.99992 12 8.49992V10.9999H10C9.44772 10.9999 9 11.4476 9 11.9999V12.9999C9 13.5522 9.44771 13.9999 10 13.9999H12V21H4C3.44772 21 3 20.5523 3 20V4C3 3.44772 3.44772 3 4 3H20Z" fill="#0F0F0F"/>
                        </svg>
                    </a>
                <?php
                endif;
                $twitter = get_option('options_twitter');
                if ($twitter):
                ?>
                    <a title="Twitter" style="display: inline-block; vertical-align: top; margin: 0 0 0 8px; text-decoration: none; opacity: 0.5;" href="<?= $twitter; ?>" aria-label="Twitter" class="rede-twitter" target="_blank">
                        <svg fill="#000000" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="xMidYMid" width="24" height="20" viewBox="0 0 31.812 26">
                            <path d="M20.877,2.000 C22.519,2.000 24.382,2.652 25.426,3.738 C26.724,3.486 27.949,3.025 29.050,2.386 C28.625,3.687 27.718,4.779 26.540,5.469 C27.693,5.332 28.797,5.035 29.820,4.590 C29.054,5.707 28.087,6.690 26.971,7.477 C26.981,7.715 26.987,7.955 26.987,8.195 C26.987,15.562 21.445,24.000 10.939,24.000 C7.715,24.000 4.507,23.133 1.982,21.551 C2.428,21.605 2.883,21.631 3.343,21.631 C6.019,21.631 8.482,20.740 10.439,19.242 C7.937,19.199 5.827,17.586 5.103,15.373 C5.450,15.437 5.810,15.473 6.178,15.473 C6.696,15.473 7.203,15.406 7.681,15.277 C5.068,14.768 3.100,12.514 3.100,9.813 C3.100,9.787 3.100,9.764 3.100,9.740 C3.871,10.158 4.750,10.410 5.687,10.440 C4.154,9.437 3.147,7.734 3.147,5.799 C3.147,4.777 3.428,3.818 3.919,2.998 C6.735,6.367 10.945,8.588 15.693,8.822 C15.594,8.414 15.543,7.984 15.543,7.553 C15.543,4.473 17.721,2.000 20.877,2.000 M29.820,4.590 L29.825,4.590 M20.877,-0.000 C17.033,-0.000 14.060,2.753 13.614,6.552 C10.425,5.905 7.524,4.204 5.440,1.711 C5.061,1.257 4.503,0.998 3.919,0.998 C3.867,0.998 3.815,1.000 3.763,1.004 C3.123,1.055 2.547,1.413 2.216,1.966 C1.525,3.122 1.159,4.447 1.159,5.799 C1.159,6.700 1.321,7.579 1.625,8.400 C1.300,8.762 1.113,9.238 1.113,9.740 L1.113,9.813 C1.113,11.772 1.882,13.589 3.160,14.952 C3.087,15.294 3.103,15.655 3.215,15.998 C3.657,17.348 4.459,18.510 5.499,19.396 C4.800,19.552 4.079,19.631 3.343,19.631 C2.954,19.631 2.577,19.609 2.222,19.565 C2.141,19.556 2.061,19.551 1.981,19.551 C1.148,19.551 0.391,20.078 0.108,20.886 C-0.202,21.770 0.140,22.753 0.932,23.249 C3.764,25.023 7.318,26.000 10.939,26.000 C17.778,26.000 22.025,22.843 24.383,20.195 C27.243,16.984 28.907,12.718 28.972,8.455 C29.899,7.682 30.717,6.790 31.410,5.792 C31.661,5.458 31.810,5.041 31.810,4.590 C31.810,3.909 31.473,3.308 30.958,2.946 C31.181,2.176 30.925,1.342 30.303,0.833 C29.940,0.537 29.496,0.386 29.049,0.386 C28.708,0.386 28.365,0.474 28.056,0.654 C27.391,1.040 26.680,1.344 25.931,1.562 C24.555,0.592 22.688,-0.000 20.877,-0.000 L20.877,-0.000 Z"/>
                        </svg>
                    </a>
                <?php
                endif;
                $instagram = get_option('options_instagram');
                if ($instagram):
                ?>
                    <a title="Instagram" style="display: inline-block; vertical-align: top; margin: 0 0 0 8px; text-decoration: none; opacity: 0.5;" href="<?= $instagram; ?>" aria-label="Instagram" class="rede-instagram" target="_blank">
                        <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12 18C15.3137 18 18 15.3137 18 12C18 8.68629 15.3137 6 12 6C8.68629 6 6 8.68629 6 12C6 15.3137 8.68629 18 12 18ZM12 16C14.2091 16 16 14.2091 16 12C16 9.79086 14.2091 8 12 8C9.79086 8 8 9.79086 8 12C8 14.2091 9.79086 16 12 16Z" fill="#0F0F0F"/>
                            <path d="M18 5C17.4477 5 17 5.44772 17 6C17 6.55228 17.4477 7 18 7C18.5523 7 19 6.55228 19 6C19 5.44772 18.5523 5 18 5Z" fill="#0F0F0F"/>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M1.65396 4.27606C1 5.55953 1 7.23969 1 10.6V13.4C1 16.7603 1 18.4405 1.65396 19.7239C2.2292 20.8529 3.14708 21.7708 4.27606 22.346C5.55953 23 7.23969 23 10.6 23H13.4C16.7603 23 18.4405 23 19.7239 22.346C20.8529 21.7708 21.7708 20.8529 22.346 19.7239C23 18.4405 23 16.7603 23 13.4V10.6C23 7.23969 23 5.55953 22.346 4.27606C21.7708 3.14708 20.8529 2.2292 19.7239 1.65396C18.4405 1 16.7603 1 13.4 1H10.6C7.23969 1 5.55953 1 4.27606 1.65396C3.14708 2.2292 2.2292 3.14708 1.65396 4.27606ZM13.4 3H10.6C8.88684 3 7.72225 3.00156 6.82208 3.0751C5.94524 3.14674 5.49684 3.27659 5.18404 3.43597C4.43139 3.81947 3.81947 4.43139 3.43597 5.18404C3.27659 5.49684 3.14674 5.94524 3.0751 6.82208C3.00156 7.72225 3 8.88684 3 10.6V13.4C3 15.1132 3.00156 16.2777 3.0751 17.1779C3.14674 18.0548 3.27659 18.5032 3.43597 18.816C3.81947 19.5686 4.43139 20.1805 5.18404 20.564C5.49684 20.7234 5.94524 20.8533 6.82208 20.9249C7.72225 20.9984 8.88684 21 10.6 21H13.4C15.1132 21 16.2777 20.9984 17.1779 20.9249C18.0548 20.8533 18.5032 20.7234 18.816 20.564C19.5686 20.1805 20.1805 19.5686 20.564 18.816C20.7234 18.5032 20.8533 18.0548 20.9249 17.1779C20.9984 16.2777 21 15.1132 21 13.4V10.6C21 8.88684 20.9984 7.72225 20.9249 6.82208C20.8533 5.94524 20.7234 5.49684 20.564 5.18404C20.1805 4.43139 19.5686 3.81947 18.816 3.43597C18.5032 3.27659 18.0548 3.14674 17.1779 3.0751C16.2777 3.00156 15.1132 3 13.4 3Z" fill="#0F0F0F"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            <div class="item" style="padding-right: 10% !important;">
                <div class="ui icon top right pointing dropdown button buscaNav dropdownBtnSearchIpad">
                    <i class="search icon"></i>
                    <div class="menu">
                        <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>">
                            <div class="ui action input">
                                <input type="search" id="txtSearch2" name="s" placeholder="Buscar..." value="<?php echo get_search_query(); ?>">
                                <button type="submit" class="ui blue button" id="btnSearch2">
                                    <i class="search icon"></i> Buscar
                                </button>
                            </div>
                        </form>
                        <div class="item" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>


        <div id="divHeader" >
            <!-- HEADER -->
            <header>
                <div class="container" >
                    <div id="bar">
                        <?php if(is_home()): ?>
                            <div class="publicidade-leaderboardtop"><?php echo adrotate_group(1); ?></div>
                            <div class="publicidade-minibannertop"><?php echo adrotate_group(2); ?></div>
                        <?php elseif(get_query_var('post_type') == 'municipios') : ?>
                            <div class="publicidade-leaderboardtop"><?php echo adrotate_group(14); ?></div>
                        <?php else : ?>
                            <div class="publicidade-leaderboardtop"><?php echo adrotate_group(12); ?></div>
                            <div class="publicidade-minibannertop"><?php echo adrotate_group(13); ?></div>
                        <?php endif; ?>
                    </div>

                    <a href="#/" class="bt-menu-mobile"><span class="icon"><span></span></span></a>

                    <?php if (is_home()): ?>
                        <h1><a href="/">Bahia.ba</a></h1>
                    <?php else: ?>
                        <h2><a href="<?php bloginfo('url'); ?>">Bahia.ba</a></h2>
                    <?php endif; ?>

                    <!-- <div class="left-header">
                        <div class="redes-topo">
                            <a aria-label="Whatsapp" href="https://wa.me/5571996775577" target="_blank" class="rede-whatsapp"><svg width="30" height="30"><use xlink:href="<?//php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_whatsapp"></use></svg><span>Envie sua mensagem</span><br />71 99677-5577</a>
                                <a href="<?//= $facebook; ?>" aria-label="Facebook" class="rede-facebook" target="_blank"><svg width="30" height="30"><use xlink:href="<?//php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_facebook"></use></svg></a>
                            <?//php endif; ?>

                            <?php
                            // $twitter = get_option('options_twitter');
                            // if ($twitter):
                            ?>
                                <a href="<?//= $twitter; ?>" aria-label="Twitter" class="rede-twitter" target="_blank"><svg width="30" height="30"><use xlink:href="<?//php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_twitter"></use></svg></a>
                            <?//php endif; ?>

                            <?php
                            // $instagram = get_option('options_instagram');
                            // if ($instagram):
                            ?>
                                <a href="<?//= $instagram; ?>" aria-label="Instagram" class="rede-instagram" target="_blank"><svg width="30" height="30"><use xlink:href="<?//php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_instagram"></use></svg></a>
                            <?//php endif; ?>

                        </div>
                        <a href="<?//php bloginfo('url'); ?>/ultimas-noticias" class="bt-ultimas-noticias"><svg width="20" height="20"><use xlink:href="<?//php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_relogio"></use></svg><span> ÚLTIMAS NOTÍCIAS</span></a>
                    </div> -->
                    <nav class="nav-row">
                        <ul>
                            <li style="display: block; color: #706f73; text-decoration: none; padding: 0 10px; border-right: solid 1px #98cbff">
                                <a href="#/" aria-label="menu">
                                    <div class="ui top left pointing dropdown icon blue basic menuHeader" style="margin-top: -7px !important;">

                                        Seções
                                        <i class="sidebar icon"></i>

                                        <div class="menu" style="width: 200px;">
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/ultimas-noticias">
                                                <a href="#/">Últimas Notícias</a>
                                            </div>
                                            <div class="item">
                                                <i class="dropdown icon"></i>
                                                <div class="url-link" data-url="<?php bloginfo('url'); ?>/">
                                                    <span class="text" >
                                                        <a href="#/">Privacidade</a>
                                                    </span>
                                                </div>
                                                <div class="menu ">
                                                    <div class="item url-link"><a href="#/" class="linkMenu" data-url="<?php bloginfo('url'); ?>/politica-de-privacidade">Política de Privacidade</a></div>
                                                    <div class="item url-link"><a href="#/" class="linkMenu" data-url="<?php bloginfo('url'); ?>/politica-de-cookies">Política de Cookies</a></div>
                                                </div>
                                            </div>
                                            <div class="item">
                                                <!-- <i class="dropdown icon"></i> -->
                                                <div class="url-link" data-url="<?php bloginfo('url'); ?>/politica">
                                                    <span class="text" >
                                                        <a href="#/">Política</a>
                                                    </span>
                                                </div>
                                                <!-- <div class="menu url-link" data-url="<?//php bloginfo('url'); ?>/eleicoes2024">
                                                    <div class="item"><a href="#/">Eleições 2024</a></div>
                                                </div> -->
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/social">
                                                <a href="#/">Ginno Larry</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/investimentos">
                                                <a href="#/">Investimentos</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/salvador">
                                                <a href="#/">Salvador</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/bahia">
                                                <a href="#/">Bahia</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/municipios">
                                                <a href="#/">Municípios</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/especial">
                                                <a href="#/">Especial</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/exclusivo">
                                                <a href="#/">Exclusivo</a>
                                            </div>
                                            <div class="item">
                                                <i class="dropdown icon"></i>
                                                <div class="url-link" data-url="<?php bloginfo('url'); ?>/esporte">
                                                    <span class="text" >
                                                        <a href="#/">Esporte</a>
                                                    </span>
                                                </div>
                                                <div class="menu url-link" data-url="<?php bloginfo('url'); ?>/esporte?category=brasileirao">
                                                    <div class="item"><a href="#/">Brasileirão 2025</a></div>
                                                </div>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/brasil">
                                                <a href="#/">Brasil</a>
                                            </div>
                                            <div class="item">
                                                <i class="dropdown icon"></i>
                                                <div class="url-link" data-url="<?php bloginfo('url'); ?>/entretenimento">
                                                    <span class="text" >
                                                        <a href="#/">Entretenimento</a>
                                                    </span>
                                                </div>
                                                <div class="menu url-link" data-url="<?php bloginfo('url'); ?>/carnaval">
                                                    <div class="item"><a href="#/">Carnaval</a></div>
                                                </div>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mais_gente">
                                                <a href="#/">Mais Gente</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/entrevista">
                                                <a href="#/">Entrevistas</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/economia">
                                                <a href="#/">Economia</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mundo">
                                                <a href="#/">Mundo</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/mais_noticias">
                                                <a href="#/">Mais Notícias</a>
                                            </div>
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/artigo">
                                                <a href="#/">Artigos</a>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            </li>
                            <li><a href="<?php bloginfo('url'); ?>/politica" data-notificacao="">Política</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/social" data-notificacao="">Ginno Larry</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/investimentos" data-notificacao="">Investimentos</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/esporte" data-notificacao="">Esporte</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/entretenimento" data-notificacao="">Entretenimento</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/salvador" data-notificacao="">Salvador</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/bahia" data-notificacao="">Bahia</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/justica" data-notificacao="">Justiça</a></li>
                        </ul>
                    </nav>
                    <nav class="nav-drop">
                        <ul class="menu-principal">
                            <li><a href="<?php bloginfo('url'); ?>/ultimas-noticias" data-notificacao="">Últimas Notícias</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/carnaval" data-notificacao="">Carnaval</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/politica" data-notificacao="">Política</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/social" data-notificacao="">Ginno Larry</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/investimentos" data-notificacao="">Investimentos</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/salvador" data-notificacao="">Salvador</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/bahia" data-notificacao="">Bahia</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/municipios" data-notificacao="">Municípios</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/brasil" data-notificacao="">Brasil</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/esporte" data-notificacao="">Esporte</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/entretenimento" data-notificacao="">Entretenimento</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/economia" data-notificacao="">Economia</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/mundo" data-notificacao="">Mundo</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/entrevista" data-notificacao="">Entrevistas</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/artigo" data-notificacao="">Artigos</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/especial" data-notificacao="">Especial</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/exclusivo" data-notificacao="">Exclusivo</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/mais_noticias" data-notificacao="">Mais Notícias</a></li>
                        </ul>
                        <a href="<?php bloginfo('url'); ?>/anuncie-conosco" class="bt-anuncie-conosco">Anuncie conosco</a>
                    </nav>
                </div>
            </header>
            <!-- FIM HEADER -->
        </div>
    <?php endif; ?>
