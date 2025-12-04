<aside>

	<?php include get_template_directory() . '/ad-long.php'; ?>

    <div class="box-news-int" style="float: left;">
        <a href="#/" class="box-categoria" data-target="cont-box-maislidas">+ mais lidas</a>
        <div class="box-maislidos">
            <div class="cont-tab" id="cont-box-maislidas">
                <?php mais_lidas2(); ?>
            </div>
        </div>
    </div>
	
	<?php include get_template_directory() . '/ad-long.php'; ?>

    <div class="box-news-int" style="float: left;">
        <a href="#/" class="box-categoria" data-target="cont-box-maislidas">mais notícias</a>
        <div class="box-maislidos">
            <div class="cont-tab" id="cont-box-maislidas">
                <?php mais_noticias_editoria(); ?>
            </div>
        </div>
    </div>
	
	<?php include get_template_directory() . '/ad-long.php'; ?>

</aside>