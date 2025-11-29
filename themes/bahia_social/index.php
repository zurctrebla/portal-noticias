<?php
if ((is_mobile() AND is_home())) {
	include("page-ultimas-noticias-mobile.php");
} else {
	get_header();
	// variavel de controle das news exibidas
	$news_ids = array();
	 
	global $wpdb;

	$sql = "SELECT ";
	$sql .= "   titulo, ";
	$sql .= "   chamada ";
	$sql .= "FROM wp_coberturas ";
	$sql .= "WHERE status = 1 ";
	$sql .= "ORDER BY data_criacao ";

	$objCobertura = $wpdb->get_results($sql);

	// $sql2 = "SELECT ";
	// $sql2 .= "   url_arquivo ";
	// $sql2 .= "FROM wp_popups ";
	// $sql2 .= "WHERE status = 1 ";
	// $sql2 .= "ORDER BY RAND() ";
	// $sql2 .= "LIMIT 1";

	// $objPopup = $wpdb->get_results($sql2);
	$objPopup = array();

	?>

	<div class="ui basic modal modalOutLimit">
		<i class="close icon" title="Fechar"></i>
		<div class="header">
		</div>
		<div class="image content">
			<img class="image" id="outlimit" src="" width="800" height="600">
		</div>
	</div>

	<script>
		
		$.fn.random = function() {
			return this.eq(Math.floor(Math.random() * this.length))
		};
		$(document).ready(function () {
			
			<?php
			if(count($objPopup) > 0) {
			?>
				var src = '<?= $objPopup[0]->url_arquivo ?>'+"?a="+Math.random();

				// a variavel src é uma imagem que será exibida em um modal
				// pegue o tamanho da imagem e coloque no width e height da imagem, o id é outlimit
				
				var img = new Image();
				img.onload = function() {
					$("#outlimit").attr('width', this.width);
					$("#outlimit").attr('height', this.height);
					$("#outlimit").css({
						'display': 'block',
						'margin-left': 'auto',
						'margin-right': 'auto'
					});
				// $('.ui.basic.modal.modalOutLimit .close.icon').css({
				// 	'position': 'absolute',
				// 	'top': $("#outlimit").position().top + 'px',
				// 	'right': ($(window).width() - $("#outlimit").position().right - $("#outlimit").width() - 20) + 'px'
				// });
			};
			//o icone de fechar não está acompanhando a imagem, faça com o que o icone fique proximo da imagem independente do tamanho da imagem
				img.src = src;
				

				$("#outlimit").attr('src', src);
				setTimeout(function(){
					$('.ui.modal').modal('show');
				}, 90000);
				$('.ui.modal').modal('show');

				setTimeout(function(){
					$("#outlimit").attr('src', src);
					
					// setTimeout(function(){
					// 	$('.ui.basic.modal.modalOutLimit').modal('hide');
					// }, 6000);
				}, 7000);
			<?php } ?>

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
							orderby: 'post_date',
							order: 'DESC',
							editoria: 'false',
							no_found_rows: 'true',
							ignore_sticky_posts: 'true'
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
						setTimeout(function() {
							running = false;
						}, 1000);

					});
					
				}
			});
			
			$(".iconAoVivo").hide().repeat().each($).fadeIn($).wait(1000).fadeOut($);
			
			<?php
			if(count($objCobertura) > 1) {
			?>
				$('#divCoberturaRotatoria>*').hide().repeat(5000,true).fadeOut(1000).siblings().random().fadeIn(1000).until($);
			<?php } ?>
			
		});
	</script>

	<main>

		<div class="container">
			
			
			<!-- DESTAQUES -->
			<div class="destaques">

				<?php
				$mod = 1;
				include("inc_destaques.php");
				?>

			</div>
			<!-- FIM DESTAQUES -->
			
			
			<div class="main-interno">
				
				<?php get_sidebar('home1'); ?>
				
				<div class="grid-base-int">
					
					<?php include get_template_directory() . '/ad-small.php'; ?>

					<ul class="resultado-busca">
						
						<?php
						if($objCobertura) {
						?>

							<div class="divCobertura">
								<div class="divCoberturaAoVivo">
									<i class="circle icon iconAoVivo"></i>
									AO VIVO
								</div>
								<div id="divCoberturaRotatoria">
									<?php
									foreach ($objCobertura as $obj) {
									?>
										<div class="divCoberturaChamada">
											<a href="/cobertura/?cobertura=<?=$obj->titulo?>" target="_blank"><?=$obj->chamada?></a>
										</div>
									<?php } ?>
								</div>
							</div>

						<?php 
						}
						$posts_per_page = 10;
						
						$params = array(
							'post_type' => explode(',', $POST_TYPES_LIST),
							'post_status' => 'publish',
							'posts_per_page'	=> $posts_per_page + count($news_ids),
							'showposts' => $posts_per_page,
							'no_found_rows' => true,
							'ignore_sticky_posts' => true
						);
						
						$query = new WP_Query($params);

						for ($i = 0; $i < count($query->posts) && $i < $posts_per_page; $i++) {
							if (! in_array($query->posts[ $i ]->ID, $news_ids)) {
								$id = $query->posts[$i]->ID;
								$news_ids[] = $id;
								$img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
								$exclusivo = get_post_meta($id, 'exclusivo', true);
								
								$divChamada = "";
								if(get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || get_post_type($id) == "entrevista" || $exclusivo) {
									$divChamada = "divChamadaPostType";
								}
								
								showLinePostWeb($id, $img, $divChamada, $exclusivo);
							}
						}
						
						?>  
					</ul>

					<div class="pix-wrapper pix-loader imgLoader" style="display: none;">
						<img src="<?php bloginfo('template_url');?>/assets/imgs/loader.gif">
					</div>

				</div>

				<?php get_sidebar('home2'); ?>
				
			</div>
			
			
			<!-- PUBLICIDADE INNER CONTENT -->
			<!-- <div class="row-publicidade">
				<div class="publicidade-leaderboard"><//?php echo adrotate_group(3); ?></div>
				<div class="publicidade-minibanner"><//?php echo adrotate_group(4); ?></div>
			</div> -->
			<!-- FIM PUBLICIDADE INNER CONTENT -->
			

			<input type="hidden" id="ids" value="<?=implode(',', $news_ids)?>">
			<input type="hidden" id="loadMore" value="true">

		</div>
	</main>

	<?php get_footer(); ?>

<?php } ?>