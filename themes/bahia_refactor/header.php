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
// Carrega as funções de ícones
require_once get_template_directory() . '/template-parts/social-icons.php';
?>
    <?php
    if (is_mobile()): ?>
        <div class="divToTop" id="toTopMobile" style="display: none;"><img src="<?php bloginfo('template_url'); ?>/assets/imgs/icon_top.png" alt="Voltar ao topo" title="Voltar ao topo"></div>
        <div class="ui left vertical sidebar menu" id="left-menu">
            <a class="item" href="<?php bloginfo('url'); ?>/ultimas-noticias">
                <p class="txtItemMenu">Últimas Notícias</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/politica">
                <p class="txtItemMenu">Política</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/esporte">
                <p class="txtItemMenu">Esporte</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/entretenimento">
                <p class="txtItemMenu">Entretenimento</p>
            </a>
            <a class="item" href="<?php bloginfo('url'); ?>/quem-somos">
                <p class="txtItemMenu">Quem Somos</p>
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
            <a class="item" href="<?php bloginfo('url'); ?>/brasil">
                <p class="txtItemMenu">Brasil</p>
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
                    <form method="post" action="/?s=" style="position: absolute;" id="searchFormMobile">
                        <div style="right: 3rem;" class="ui icon top right pointing dropdown button <?php if($ipad){echo "dropdownBtnSearchIpad";}else{echo "dropdownBtnSearchMobile";} ?>">
                            <i class="search icon"></i>
                            <div class="menu divMenu">
                                    <div class="ui fluid action input">
                                            <input type="text" id="txtSearch" name="b" data-url="<?php bloginfo('url'); ?>" placeholder="Conteúdo">
                                            <button type="button" class="ui blue button" id="btnSearch">Buscar</button>
                                    </div>
                                <div class="item" style="display: none;"></div>
                            </div>
                        </div>
                    </form>
                </div>
        </div>

        <!-- Banners mobile - fora do header fixo -->
        <div id="bar">
            <div class="container">
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
                        <div class="item url-link" data-url="<?php bloginfo('url'); ?>/quem-somos">
                            <a href="#/">Quem Somos</a>
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
                                <div class="item"><a href="#/">Brasileirão 2026</a></div>
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
                render_social_follow_links(30, array(
                    'wrapper_class' => '',
                    'link_style' => 'display: inline-block; vertical-align: top; margin: 0 0 0 8px; text-decoration: none;'
                ));
                ?>
            </div>

            <div class="item" style="padding-right: 10% !important;">
                <form method="post" action="/?s=" id="searchFormDesktop">
                    <div class="ui icon top right pointing dropdown button buscaNav dropdownBtnSearchIpad">
                        <i class="search icon"></i>
                        <div class="menu">
                            <div class="ui action input">
                                <input type="text" id="txtSearch2" name="b" data-url="<?php bloginfo('url'); ?>" placeholder="Conteúdo">
                                <button type="button" class="ui blue button" id="btnSearch2">Buscar</button>
                            </div>
                            <div class="item" style="display: none;"></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Banners mobile - fora do header fixo -->
        <div id="bar">
            <div class="container">
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
        </div>

        <div id="divHeader" >
            <!-- HEADER -->
            <header>
                <div class="container" >
                    <a href="#/" class="bt-menu-mobile"><span class="icon"><span></span></span></a>

                    <?php if (is_home()): ?>
                        <h1><a href="/">Bahia.ba</a></h1>
                    <?php else: ?>
                        <h2><a href="<?php bloginfo('url'); ?>">Bahia.ba</a></h2>
                    <?php endif; ?>

                    <div class="left-header">
                        <div class="redes-topo">
                            <?php render_social_follow_links(30); ?>
                        </div>
                        <!-- <a href="<?php bloginfo('url'); ?>/ultimas-noticias" class="bt-ultimas-noticias">
                            <svg width="20" height="20"><use xlink:href="<?php bloginfo('template_url'); ?>/assets/svgs/dest.svg#ico_relogio"></use></svg>
                            <span> ÚLTIMAS NOTÍCIAS</span>
                        </a> -->
                    </div>
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
                                            <div class="item">
                                                <i class="dropdown icon"></i>
                                                <div class="url-link" data-url="<?php bloginfo('url'); ?>/esporte">
                                                    <span class="text" >
                                                        <a href="#/">Esporte</a>
                                                    </span>
                                                </div>
                                                <div class="menu url-link" data-url="<?php bloginfo('url'); ?>/esporte?category=brasileirao">
                                                    <div class="item"><a href="#/">Brasileirão 2026</a></div>
                                                </div>
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
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/quem-somos">
                                                <a href="#/">Quem Somos</a>
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
                                            <div class="item url-link" data-url="<?php bloginfo('url'); ?>/brasil">
                                                <a href="#/">Brasil</a>
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
                            <li><a href="<?php bloginfo('url'); ?>/esporte" data-notificacao="">Esporte</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/entretenimento" data-notificacao="">Entretenimento</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/social" data-notificacao="">Ginno Larry</a></li>
                            <li><a href="<?php bloginfo('url'); ?>" data-notificacao="">Investimentos</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/salvador" data-notificacao="">Salvador</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/bahia" data-notificacao="">Bahia</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/justica" data-notificacao="">Justiça</a></li>
                        </ul>
                    </nav>
                    <nav class="nav-drop">
                        <ul class="menu-principal">
                            <li><a href="<?php bloginfo('url'); ?>/ultimas-noticias" data-notificacao="">Últimas Notícias</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/quem-somos" data-notificacao="">Quem Somos</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/carnaval" data-notificacao="">Carnaval</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/politica" data-notificacao="">Política</a></li>
                            <li><a href="<?php bloginfo('url'); ?>/social" data-notificacao="">Ginno Larry</a></li>
                            <li><a href="<?php bloginfo('url'); ?>" data-notificacao="">Investimentos</a></li>
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
