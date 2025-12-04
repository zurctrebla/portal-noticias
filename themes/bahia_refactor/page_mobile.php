<?php get_header(); the_post(); ?>
		<main style="padding-top: 85px;">
			<div class="container">

				<!-- INTERNA -->
				<div class="main-interno">
					<div class="grid-base-int">
						<div class="materia-conteudo">

							<div class="data-publicacao">Publicado em <?php echo get_the_date('d/m/Y \à\s H\hi'); ?>.</div>
							<h1 style="font-weight: 600;"><?php the_title();?></h1>

							<div class="materia">
								<?php the_content(); ?>
							</div>
						</div>
					</div>

					<?php get_sidebar(); ?>

				</div>
				<!-- FIM INTERNA -->

			</div>
		</main>
<?php get_footer(); ?>