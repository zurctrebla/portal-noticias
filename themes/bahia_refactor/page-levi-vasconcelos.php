<?php get_header();

		$ids[] = get_the_ID();
		$args = array(
		            'post_type' => array('post'),
		            'showposts' => -1,
		            'post__not_in' => $ids,
		            );

		$posts = query_posts($args);

 ?>
		
		<main>
			<div class="container">
				
				<!-- DESTAQUES -->
				<div class="destaques">
					<div class="grid-base destg">
						<?php destaques(0,$posts); ?>
					</div>
					<div class="grid-base destp">
						<?php destaques(1,$posts); ?>
						<?php destaques(2,$posts); ?>
					</div>
					<div class="grid-base destp">
						<?php destaques(3,$posts); ?>
						<?php destaques(4,$posts); ?>
					</div>
				</div>
				<!-- FIM DESTAQUES -->

				<div class="col2t">

					<!-- NEWS -->
					<div class="news">
						<?php news_home($news_ids,'post',2); ?>
					</div>
					<!-- FIM NEWS -->


					<!-- VEREOUVIR -->
					<?php ver_e_ouvir(); ?>
					<!-- FIM VEREOUVIR -->


					<!-- NEWS -->
					<div class="news">
						<?php news_home($news_ids,'post',2); ?>
					</div>
					<!-- FIM NEWS -->

				</div>

				<?php get_sidebar(); ?>

			</div>
		</main>

<?php get_footer(); ?>