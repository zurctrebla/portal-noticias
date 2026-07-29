<?php

class td_block_popular_categories extends td_block {

    public function get_custom_css() {
        // $unique_block_class - the unique class that is on the block. use this to target the specific instance via css
        $in_composer = td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax();
        $in_element = td_global::get_in_element();
        $unique_block_class_prefix = '';
        if( $in_element || $in_composer ) {
            $unique_block_class_prefix = 'tdc-row .';

            if( $in_element && $in_composer ) {
                $unique_block_class_prefix = 'tdc-row-composer .';
            }
        }
        $unique_block_class = $unique_block_class_prefix . $this->block_uid;

        $compiled_css = '';

        $raw_css =
            "<style>

                /* @style_general_popular_categories */
                .td_block_popular_categories {
                  padding-bottom: 0;
                }

                /* @category_color */
				.$unique_block_class .td-cat-name {
					color: @category_color;
				}
				/* @category_posts_color */
				.$unique_block_class .td-cat-no {
					color: @category_posts_color;
				}
				/* @category_color_h */
				.$unique_block_class li:hover .td-cat-name {
					color: @category_color_h;
				}
				/* @category_posts_color_h */
				.$unique_block_class li:hover .td-cat-no {
					color: @category_posts_color_h;
				}
				

                /* @f_header */
				.$unique_block_class .td-block-title a,
				.$unique_block_class .td-block-title span {
					@f_header
				}
				/* @f_cat */
				.$unique_block_class li {
					@f_cat
				}
				/* @f_posts */
				.$unique_block_class .td-cat-no {
					@f_posts
				}
				
			</style>";


        $td_css_res_compiler = new td_css_res_compiler( $raw_css );
        $td_css_res_compiler->load_settings( __CLASS__ . '::cssMedia', $this->get_all_atts() );

        $compiled_css .= $td_css_res_compiler->compile_css();
        return $compiled_css;
    }

    static function cssMedia( $res_ctx ) {

        $res_ctx->load_settings_raw( 'style_general_popular_categories', 1 );

        // category name color
        $res_ctx->load_settings_raw( 'category_color', $res_ctx->get_shortcode_att('category_color') );

        // category posts count color
        $res_ctx->load_settings_raw( 'category_posts_color', $res_ctx->get_shortcode_att('category_posts_color') );

        // category name hover color
        $res_ctx->load_settings_raw( 'category_color_h', $res_ctx->get_shortcode_att('category_color_h') );

        // category posts count hover color
        $res_ctx->load_settings_raw( 'category_posts_color_h', $res_ctx->get_shortcode_att('category_posts_color_h') );



        /*-- FONTS -- */
        $res_ctx->load_font_settings( 'f_header' );
        $res_ctx->load_font_settings( 'f_cat' );
        $res_ctx->load_font_settings( 'f_posts' );

    }


	/**
	 * Disable loop block features. This block does not use a loop and it dosn't need to run a query.
	 */
	function __construct() {
		parent::disable_loop_block_features();
	}

    function render($atts, $content = null){
        parent::render($atts);

        $buffy = '';

        extract(shortcode_atts(
            array(
                'limit'            => '6',
                'custom_title'     => '',
                'custom_url'       => '',
                'hide_title'       => '',
                'header_color'     => '',
                'popular_taxonomy' => 'category', // NEW: taxonomy to show terms from
            ), $atts
        ));

        // Validate taxonomy (fallback to category)
        $taxonomy = !empty($popular_taxonomy) ? sanitize_key($popular_taxonomy) : 'category';
        if ( ! taxonomy_exists($taxonomy) ) {
            $taxonomy = 'category';
        }

        // Build args for terms
        $term_args = array(
            'taxonomy'   => $taxonomy,
            'orderby'    => 'count',
            'order'      => 'DESC',
            'number'     => (int) $limit,
            'hide_empty' => false,
        );

        // Keep old featured-category exclusion ONLY for category taxonomy
        if ($taxonomy === 'category') {
            $term_args['exclude'] = array( (int) get_cat_ID(TD_FEATURED_CAT) );

            // exclude categories from the demo
            if (TD_DEPLOY_MODE == 'demo' or TD_DEPLOY_MODE == 'dev') {
                $term_args['exclude'] = array_map('intval', array_filter(array_map('trim', explode(',', '153,154,155,156,157,158,159,90,91,92,93,94,95,96,97,99,100,101,102,103,104,105,106,107,108,109,110,111,112,113,114,82,83,84,85,86,87,88,89,98'))));
                $term_args['exclude'][] = (int) get_cat_ID(TD_FEATURED_CAT);
            }
        }

        // Get terms (works for categories and custom taxonomies)
        $terms = get_terms($term_args);
        if (is_wp_error($terms)) {
            $terms = array();
        }

        $buffy .= '<div class="' . $this->get_block_classes(array('widget', 'widget_categories')) . '" ' . $this->get_block_html_atts() . '>';

        $buffy .= $this->get_block_css();

        // block title wrap
        $buffy .= '<div class="td-block-title-wrap">';
        $buffy .= $this->get_block_title();
        $buffy .= $this->get_pull_down_filter();
        $buffy .= '</div>';

        if (!empty($terms)) {
            $buffy .= '<ul class="td-pb-padding-side">';

            foreach ($terms as $term) {

                // Keep your old "uncategorized" skip for categories (optional)
                if ($taxonomy === 'category') {
                    if (strtolower($term->name) === 'uncategorized' || $term->slug === 'uncategorized') {
                        continue;
                    }
                }

                $link = get_term_link($term, $taxonomy);
                if (is_wp_error($link)) {
                    continue;
                }

                $buffy .= '<li><a href="' . esc_url($link) . '">'
                    . '<span class="td-cat-name">' . esc_html($term->name) . '</span>'
                    . '<span class="td-cat-no">' . (int) $term->count . '</span>'
                    . '</a></li>';
            }

            $buffy .= '</ul>';
        }

        $buffy .= '</div>';

        return $buffy;
    }


    function inner($posts, $td_column_number = '') {

    }
}