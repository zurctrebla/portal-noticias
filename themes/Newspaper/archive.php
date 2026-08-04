<?php get_header();

    $td_archive_title = '';
    if (is_day()) {
        $td_archive_title .= __('Daily Archives:', 'newspaper' ) . ' ' . get_the_date();
    } elseif (is_month()) {
        $td_archive_title .= __('Monthly Archives:', 'newspaper') . ' ' . get_the_date('F Y');
    } elseif (is_year()) {
        $td_archive_title .= __('Yearly Archives:', 'newspaper') . ' ' . get_the_date('Y');
    } elseif (is_post_type_archive()) {
        // Editorias do bahia.ba são CPTs (has_archive): /politica, /municipios, ...
        // O core do WP não é chamado aqui, então sem este ramo todo archive de CPT
        // caía no genérico "Arquivos". post_type_archive_title() devolve o
        // labels->name do CPT (ex.: "Municípios", "Justiça") definido em
        // mu-plugins/bahia-editorias-cpt.php.
        $td_archive_title .= post_type_archive_title('', false);
    } else {
        $td_archive_title .= __('Archives', 'newspaper');
    }

?>

    <div class="td-main-content-wrap td-container-wrap" role="main">
        <div class="td-container">
            <div class="td-crumb-container">
                <?php echo tagdiv_page_generator::get_breadcrumbs(array(
                    'template' => 'archive',
                )); ?>
            </div>

            <div class="td-pb-row">
                <div class="td-pb-span8 td-main-content">
                    <div class="td-ss-main-content">
                        <div class="td-page-header">
                            <h1 class="entry-title td-page-title">
                                <span><?php echo esc_html( $td_archive_title ) ?></span>
                            </h1>
                        </div>

                        <?php
                            get_template_part('loop-archive');
                        ?>
                    </div>
                </div>

                <div class="td-pb-span4 td-main-sidebar">
                    <div class="td-ss-main-sidebar">
                        <?php dynamic_sidebar( 'td-default' ) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php get_footer(); ?>