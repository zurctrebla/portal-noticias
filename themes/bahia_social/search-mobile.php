<?php
get_header();

if(!isset($_POST["b"])) {
?>
    <script type="text/javascript">
        window.location.href = "<?php bloginfo('url'); ?>";
    </script>
<?php
} else {

	$searched = stripslashes($_POST['b']);
	$ipad = strpos(getUserAgent(), "iPad");
	$news_ids = array();

	if (isset($searched)) {
		wp_reset_query();

		$params = array(
			'post_type' => explode(',', $POST_TYPES_LIST),
			'post_status' => 'publish',
			's' => $searched,
			'orderby' => 'post_date',
			'order' => 'DESC',
			'no_found_rows' => true,
			'ignore_sticky_posts' => true
		);
	}

	$query = new WP_Query($params);
	$qtdResultados = count($query->posts);
?>

<script>
		$(document).ready(function () {
			
			var loadMore = true;
			var running = false;
			
			$(window).scroll(function() {
				if (loadMore && !running && $(window).scrollTop() >= $(".li-home").last().offset().top + $(".li-home").last().outerHeight() - window.innerHeight) {
					
					running = true;
					
					$(".imgLoader").show();

					$.ajax({
						method: "POST",
						url: '<?php bloginfo('template_url'); ?>/infiniteScroll.php',
						data: {
							post_type: '<?php echo $POST_TYPES_LIST; ?>',
							showposts: $("#ids").val().split(',').length + 10,
							post__not_in: $("#ids").val(),
							s: '<?= $searched ?>',
							orderby: 'post_date',
							order: 'DESC',
							editoria: 'false',
							s: '<?=$searched?>',
							lastDate: $(".lastDate").last().val(),
                        	no_found_rows: 'true'
						}
					})
					.done(function (data) {
						$(".imgLoader").hide();

						var content = data.split(">>>")[0];
						var new_ids = data.split(">>>")[1];
						var count = data.split(">>>")[2];

						$(".li-home").last().after(content);
						var ids = $("#ids").val() + ',' + new_ids;
						$("#ids").val(ids);

						if(count < 10) {
							loadMore = false;
						}
						
						running = false;

					});
					
				}
			});
			
			
		});
	</script>

	<main style="padding-top: 65px;">
		<div class="container">
			<!-- INTERNA -->
			<div class="main-interno">
				<div class="grid-base-int">

					<form id="busca" method="post" action="/?s=">
						<input type="text" value="<?= $searched; ?>" name="b" />
						<input type="submit" value="" />

						<div class="feedback-resultado-busca">
							<?php
							if($searched){
								if ($qtdResultados <= 1)
									echo "Foi encontrado um conteúdo com o termo <strong>“{$searched}”</strong>";
								else
									echo "Foram encontrados alguns conteúdos com o termo <strong>“{$searched}”</strong>";
							}
							?>
						</div>
					</form>

					<div class="filtros-resultado-busca" style="margin-top: 0 !important;">
						<a href="#" class="ativo">Notícias</a>
					</div>

					<ul class="resultado-busca">


						<?php
							if ($qtdResultados > 0):
								$lastDate = null;
								for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
									if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
										$id = $query->posts[$i]->ID;
										$news_ids[] = $id;
										$img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
										$exclusivo = get_post_meta($id, 'exclusivo', true);
										
										$divChamada = "";
										if (get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || $exclusivo) {
											$divChamada = "divChamadaPostTypeMobile";
										}
										
										$spanCategoria = "";
										if(get_post_type($id) == "especial") {
											$spanCategoria = "spanEspecial";
										}

										if(get_post_type($id) == "exclusivo") {
											$spanCategoria = "spanExclusivo";
										}

										if((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
											$spanCategoria = "spanLevi";
										}
										
										if (get_post_type($id) == "motor") {
											$spanCategoria = "categoriaMotor";
										}
										
										if($lastDate != get_the_date('d \d\e F \d\e Y', get_the_ID())){
											$lastDate = get_the_date('d \d\e F \d\e Y', get_the_ID());
											echo "<input type='hidden' class='lastDate' value='".$lastDate."'>";
											printDateSearch($lastDate);
										}
										
										showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo);
									}
								}
							else: ?>

								<li><p class="call-chamada">Sua busca não trouxe resultados.</p></li>

							<?php endif; ?>

					</ul>
					
					<div class="pix-wrapper pix-loader imgLoader" style="display: none;">
						<img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
					</div>

				</div>
				
				<?php get_sidebar(); ?>

				<input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
				<input type="hidden" id="loadMore" value="true">
			</div>
			<!-- FIM INTERNA -->
		</div>
	</main>

<?php
}
get_footer();
?>