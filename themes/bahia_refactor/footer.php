<footer>
    <div class="top-footer">
        <div class="container">
            <div class="logo">Bahia.ba</div>
        </div>
    </div>
    <nav>
        <div class="container">
            <ul>
                <li><a href="<?php bloginfo('url'); ?>/ultimas-noticias" data-notificacao="">Últimas Notícias</a></li>
                <li><a href="<?php bloginfo('url'); ?>/politica" data-notificacao="">Política</a></li>
                <li><a href="<?php bloginfo('url'); ?>/salvador" data-notificacao="">Salvador</a></li>
                <li><a href="<?php bloginfo('url'); ?>/bahia" data-notificacao="">Bahia</a></li>
                <li><a href="<?php bloginfo('url'); ?>/entretenimento" class="" data-notificacao="">Entretenimento</a></li>
                <li><a href="<?php bloginfo('url'); ?>/municipios" data-notificacao="">Municípios</a></li>
                <li><a href="<?php bloginfo('url'); ?>/mundo" data-notificacao="">Mundo</a></li>
                <li><a href="<?php bloginfo('url'); ?>/entrevista" data-notificacao="">Entrevistas</a></li>
                <li><a href="<?php bloginfo('url'); ?>/artigo" data-notificacao="">Artigos</a></li>
            </ul>
        </div>
    </nav>
    <div class="low-footer">
        <div class="container">
            <small>Copyright 2016 Bahia.ba</small>
            <ul>
                <li><a href="<?php bloginfo('url'); ?>/anuncie-conosco">Anuncie Conosco</a></li>
            </ul>
        </div>
    </div>
</footer>
</div>
<script>
    !function(f, b, e, v, n, t, s) {
        if (f.fbq) return;
        n = f.fbq = function() {
            n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments)
        };
        if (!f._fbq) f._fbq = n;
        n.push = n;
        n.loaded = !0;
        n.version = '2.0';
        n.queue = [];
        t = b.createElement(e);
        t.async = !0;
        t.src = v;
        s = b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t, s)
    }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1008215431184124');
    fbq('track', 'PageView');
</script>
<noscript>
    <img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=1008215431184124&ev=PageView&noscript=1" />
</noscript>
<!-- End Meta Pixel Code -->
<!-- APP FB -->
<script>
    window.fbAsyncInit = function() {
        FB.init({
            appId: '826810440751032',
            xfbml: true,
            version: 'v2.5'
        });
    };
    (function(d, s, id) {
        var js, fjs = d.getElementsByTagName(s)[0];
        if (d.getElementById(id)) {
            return;
        }
        js = d.createElement(s);
        js.id = id;
        js.src = "//connect.facebook.net/en_US/sdk.js";
        fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));
</script>
<!-- FIM APP FB -->
<?php if (is_home()): ?>
    <div class="truvidPos">
        <script async type="text/javascript" src="https://cnt.trvdp.com/js/1738/8037.js"></script>
    </div>
<?php else: ?>
    <div class="truvidPos">
        <script async type="text/javascript" src="https://cnt.trvdp.com/js/1738/8006.js"></script>
    </div>
<?php endif; ?>
<?php wp_footer(); ?>
<?php echo adrotate_ad(1660); ?>
<div class="clever-core-ads"></div>
</body>
</html>
