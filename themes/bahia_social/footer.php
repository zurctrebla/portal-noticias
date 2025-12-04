        <!-- FOOTER -->
        <footer>

            <div class="top-footer">
                <div class="container"><div class="logo">Bahia.ba</div></div>
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
    </div><!-- FIM PUSHER -->
    
        <!-- FIM FOOTER -->

       <!-- Meta Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '1008215431184124');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id=1008215431184124&ev=PageView&noscript=1"
/></noscript>
<!-- End Meta Pixel Code -->

        <!-- APP FB -->
        <script>
            window.fbAsyncInit = function () {
                FB.init({
                    appId: '826810440751032',
                    xfbml: true,
                    version: 'v2.5'
                });
            };

            (function (d, s, id) {
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
		
		<!--script data-cfasync="false" type="text/javascript" id="clever-core">
			(function (document, window) {
				var a, c = document.createElement("script");

				c.id = "CleverCoreLoader52800";
				c.src = "//scripts.cleverwebserver.com/88032730d15f0623022ce03b6c98d14c.js";

				c.async = !0;
				c.type = "text/javascript";
				c.setAttribute("data-target", window.name);
				c.setAttribute("data-callback", "put-your-callback-macro-here");

				try {
					a = parent.document.getElementsByTagName("script")[0] || document.getElementsByTagName("script")[0];
				} catch (e) {
					a = !1;
				}

				a || (a = document.getElementsByTagName("head")[0] || document.getElementsByTagName("body")[0]);
				a.parentNode.insertBefore(c, a);
			})(document, window);
		</script>
		<div class="clever-core-ads"></div-->

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
		
		
    </body>

</html>
