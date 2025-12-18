<?php get_header(); the_post(); ?>
<main style="padding-top: 65px;">
    <div class="container">
        <!-- INTERNA -->
        <div class="main-interno">
            <div class="grid-base-int">
                <div class="materia-conteudo">
                    <div class="quem-somos-header">
                        <h1 class="quem-somos-title"><?php the_title(); ?></h1>
                    </div>
                    <div class="materia quem-somos-content">
                        <?php the_content(); ?>
                    </div>
                </div>
            </div>

            <div id="divPublicidade">
                <?php echo adrotate_group(10); ?>
            </div>

            <?php get_sidebar(); ?>
        </div>
        <!-- FIM INTERNA -->
    </div>
</main>
<?php get_footer(); ?>
