<?php

class td_block_categories_tags extends td_block {

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

                /* @style_general_categories_tags */
                .td_block_categories_tags .td-ct-item {
                  display: block;
                  position: relative;
                  padding: 0 10px 0 12px;
                  line-height: 30px;
                  color: #111;
                  -webkit-transform: translateZ(0);
                  transform: translateZ(0);
                }
                .td_block_categories_tags .td-ct-item:hover {
                  color: var(--td_theme_color, #4db2ec);
                }
                .td_block_categories_tags .td-ct-item:before {
                  content: '';
                  display: block;
                  position: absolute;
                  top: 0;
                  left: 0;
                  width: 100%;
                  height: 100%;
                  z-index: -1;
                }
                .td_block_categories_tags .td-ct-item-name {
                  line-height: 30px;
                }
                .td_block_categories_tags .td-ct-item-no {
                  float: right;
                  line-height: 30px;
                }
                .td_block_categories_tags .td-ct-item-sep {
                  position: relative;
                }
                .td_block_categories_tags .td-ct-item:last-of-type .td-ct-text-sep,
                .td_block_categories_tags .td-ct-item-sep:last-child {
                  display: none;
                }
                .td_block_categories_tags .td-ct-item-sep-svg {
                  display: inline-flex;
                  align-items: center;
                  justify-content: center;
                }
                .td_block_categories_tags .td-ct-item-sep-svg svg {
                  height: auto;
                }
                .td_block_categories_tags .td-ct-load-more {
                    display: inline-block;
                    font-size: 12px;
                    line-height: 1;
                    padding: 8px 10px;
                    border: 1px solid #C9C9C9;
                    text-align: center;
                    vertical-align: middle;
                    margin: 20px 0 0 12px;
                }

                
                /* @display */
				.$unique_block_class .td-ct-item {
					display: @display;
				}
				
				/* @item_space_right */
				.$unique_block_class .td-ct-item {
					margin-right: @item_space_right;
					margin-bottom: 0;
				}
				.$unique_block_class .td-ct-item:last-child {
					margin-right: 0;
				}
				/* @item_space_bottom */
				.$unique_block_class .td-ct-item {
					margin-bottom: @item_space_bottom;
					margin-right: 0;
				}
				.$unique_block_class .td-ct-item:last-child {
					margin-bottom: 0;
				}
				/* @item_horiz_align */
				.$unique_block_class .td-ct-wrap {
					text-align: @item_horiz_align;
				}
				
				
				/* @items_padding */
				.$unique_block_class .td-ct-item {
					padding: @items_padding;
				}
				/* @items_radius */
				.$unique_block_class .td-ct-item:before {
					border-radius: @items_radius;
				}
				/* @items_border */
				.$unique_block_class .td-ct-item:before {
					border-width: @items_border;
					border-style: solid;
				}
				/* @items_skew */
				.$unique_block_class .td-ct-item:before {
					transform: skew(@items_skew);
                    -webkit-transform: skew(@items_skew);
				}
				
                /* @icon_size */
				.$unique_block_class .td-ct-item-sep {
					font-size: @icon_size;
				}
                /* @icon_svg_size */
				.$unique_block_class .td-ct-item-sep svg {
					width: @icon_svg_size;
				}
                /* @icon_space */
				.$unique_block_class .td-ct-item-sep {
					margin: 0  @icon_space;
				}
                /* @icon_align */
				.$unique_block_class .td-ct-item-sep {
					top: @icon_align;
				}
				
				
				
                /* @bg_color */
				.$unique_block_class .td-ct-item:before {
					background-color: @bg_color;
				}
                /* @color */
				.$unique_block_class .td-ct-item-name {
					color: @color;
				}
				/* @posts_color */
				.$unique_block_class .td-ct-item-no {
					color: @posts_color;
				}
                /* @border_color */
				.$unique_block_class .td-ct-item:before {
					border-color: @border_color;
				}
                /* @i_color */
				.$unique_block_class .td-ct-item-sep {
					color: @i_color;
				}
				.$unique_block_class .td-ct-item-sep svg,
				.$unique_block_class .td-ct-item-sep svg * {
					fill: @i_color;
				}
                /* @bg_color_h */
				.$unique_block_class .td-ct-item:hover:before {
					background-color: @bg_color_h;
				}
				/* @color_h */
				.$unique_block_class .td-ct-item:hover .td-ct-item-name {
					color: @color_h;
				}
				/* @posts_color_h */
				.$unique_block_class .td-ct-item:hover .td-ct-item-no {
					color: @posts_color_h;
				}
                /* @border_hover_color */
				.$unique_block_class .td-ct-item:hover:before {
					border-color: @border_hover_color;
				}
				

                /* @f_header */
				.$unique_block_class .td-block-title a,
				.$unique_block_class .td-block-title span {
					@f_header
				}
				/* @f_name */
				.$unique_block_class .td-ct-item-name {
					@f_name
				}
				/* @f_posts */
				.$unique_block_class .td-ct-item-no {
					@f_posts
				}
				/* @f_load_more */
				.$unique_block_class .td-ct-load-more {
					@f_load_more
				}
				/* @load_more_text_color */
				.$unique_block_class .td-ct-load-more {
					color: @load_more_text_color;
				}
				/* @load_more_background_color */
				.$unique_block_class .td-ct-load-more {
					background-color: @load_more_background_color;
				}
				/* @load_more_border_color */
				.$unique_block_class .td-ct-load-more {
					border-color: @load_more_border_color;
				}
				/* @load_more_text_color_h */
				.$unique_block_class .td-ct-load-more:hover {
					color: @load_more_text_color_h;
				}
				/* @load_more_background_color_h */
				.$unique_block_class .td-ct-load-more:hover {
					background-color: @load_more_background_color_h;
				}
				/* @load_more_border_color_h */
				.$unique_block_class .td-ct-load-more:hover {
					border-color: @load_more_border_color_h;
				}
				
			</style>";


        $td_css_res_compiler = new td_css_res_compiler( $raw_css );
        $td_css_res_compiler->load_settings( __CLASS__ . '::cssMedia', $this->get_all_atts() );

        $compiled_css .= $td_css_res_compiler->compile_css();
        return $compiled_css;
    }

    static function cssMedia( $res_ctx ) {

        $res_ctx->load_settings_raw( 'style_general_categories_tags', 1 );

        // inline list elements
        $display = $res_ctx->get_shortcode_att('inline');
        if( $display == 'yes' ) {
            $res_ctx->load_settings_raw( 'display', 'inline-block' );
        } else {
            $res_ctx->load_settings_raw( 'display', 'block' );
        }


        // list item space
        $item_space = $res_ctx->get_shortcode_att('item_space');
        if( $display == 'yes' ) {
            $res_ctx->load_settings_raw( 'item_space_right', $item_space );
            if( $item_space != '' && is_numeric( $item_space ) ) {
                $res_ctx->load_settings_raw( 'item_space_right', $item_space  . 'px' );
            }
        } else {
            $res_ctx->load_settings_raw( 'item_space_bottom', $item_space );
            if( $item_space != '' && is_numeric( $item_space ) ) {
                $res_ctx->load_settings_raw( 'item_space_bottom', $item_space . 'px' );
            }
        }
        // items padding
        $items_padding = $res_ctx->get_shortcode_att('items_padding');
        $res_ctx->load_settings_raw( 'items_padding', $items_padding );
        if ( is_numeric( $items_padding ) ) {
            $res_ctx->load_settings_raw( 'items_padding', $items_padding . 'px' );
        }
        // border size
        $items_border = $res_ctx->get_shortcode_att('items_border');
        $res_ctx->load_settings_raw( 'items_border', $items_border );
        if( $items_border != '' && is_numeric($items_border) ) {
            $res_ctx->load_settings_raw( 'items_border', $res_ctx->get_shortcode_att('items_border') . 'px' );
        }
        // border radius
        $items_radius = $res_ctx->get_shortcode_att('items_radius');
        if ( $items_radius != 0 || !empty($items_radius) ) {
            $res_ctx->load_settings_raw( 'items_radius', $items_radius . 'px' );
        }
        // items skew
        $items_skew = $res_ctx->get_shortcode_att('items_skew');
        if ( $items_skew != 0 || !empty($items_skew) ) {
            $res_ctx->load_settings_raw( 'items_skew', $items_skew . 'deg' );
        }

        // icon_size
        $icon = $res_ctx->get_icon_att('tdicon');
        $icon_size = $res_ctx->get_shortcode_att('icon_size');
        if ( $icon_size != 0 || !empty($icon_size) ) {
            if( base64_encode( base64_decode( $icon ) ) == $icon ) {
                $res_ctx->load_settings_raw( 'icon_svg_size', $icon_size . 'px' );
            } else {
                $res_ctx->load_settings_raw( 'icon_size', $icon_size . 'px' );
            }
        }
        // icon_space
        $icon_space = $res_ctx->get_shortcode_att('icon_space');
        if ( $icon_space != 0 || !empty($icon_space) ) {
            $res_ctx->load_settings_raw( 'icon_space', $icon_space . 'px' );
        }
        // icon_align
        $icon_align = $res_ctx->get_shortcode_att('icon_align');
        if ( $icon_align != 0 || !empty($icon_align) ) {
            $res_ctx->load_settings_raw( 'icon_align', $icon_align . 'px' );
        }

        // menu list horizontal align
        $item_horiz_align = $res_ctx->get_shortcode_att('item_horiz_align');
        if( $item_horiz_align == 'content-horiz-center' ) {
            $res_ctx->load_settings_raw( 'item_horiz_align', 'center' );
        }
        if( $item_horiz_align == 'content-horiz-right' ) {
            $res_ctx->load_settings_raw( 'item_horiz_align', 'right' );
        }

        // colors
        $res_ctx->load_settings_raw( 'bg_color', $res_ctx->get_shortcode_att('bg_color') );
        $res_ctx->load_settings_raw( 'color', $res_ctx->get_shortcode_att('color') );
        $res_ctx->load_settings_raw( 'posts_color', $res_ctx->get_shortcode_att('posts_color') );
        $res_ctx->load_settings_raw( 'border_color', $res_ctx->get_shortcode_att('border_color') );
        $res_ctx->load_settings_raw( 'i_color', $res_ctx->get_shortcode_att('i_color') );
        $res_ctx->load_settings_raw( 'load_more_text_color', $res_ctx->get_shortcode_att('load_more_text_color') );
        $res_ctx->load_settings_raw( 'load_more_background_color', $res_ctx->get_shortcode_att('load_more_background_color') );
        $res_ctx->load_settings_raw( 'load_more_border_color', $res_ctx->get_shortcode_att('load_more_border_color') );
        $res_ctx->load_settings_raw( 'bg_color_h', $res_ctx->get_shortcode_att('bg_color_h') );
        $res_ctx->load_settings_raw( 'color_h', $res_ctx->get_shortcode_att('color_h') );
        $res_ctx->load_settings_raw( 'posts_color_h', $res_ctx->get_shortcode_att('posts_color_h') );
        $res_ctx->load_settings_raw( 'border_hover_color', $res_ctx->get_shortcode_att('border_hover_color') );
        $res_ctx->load_settings_raw( 'load_more_text_color_h', $res_ctx->get_shortcode_att('load_more_text_color_h') );
        $res_ctx->load_settings_raw( 'load_more_background_color_h', $res_ctx->get_shortcode_att('load_more_background_color_h') );
        $res_ctx->load_settings_raw( 'load_more_border_color_h', $res_ctx->get_shortcode_att('load_more_border_color_h') );

        /*-- FONTS -- */
        $res_ctx->load_font_settings( 'f_header' );
        $res_ctx->load_font_settings( 'f_name' );
        $res_ctx->load_font_settings( 'f_posts' );
        $res_ctx->load_font_settings( 'f_load_more' );

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

        $id_filter = $this->get_att( 'id_filter' );
        $show_count = $this->get_att( 'post_count' );
        $show_inline = $this->get_att( 'inline' );

        // load more
        $load_more_enabled = ( $this->get_att('load_more') === 'yes' );
        $load_more_text = $this->get_att('load_more_text');
        if ( $load_more_text === '' ) {
            $load_more_text = 'Load more';
        }

        // order ny args
        $order_by = $this->get_att( 'order_by' );
        $order_by_arg = 'name';
        $order_arg = 'ASC';
        if( $order_by == 'count' ) {
            $order_by_arg = 'count';
            $order_arg = 'DESC';
        }


        extract(shortcode_atts(
            array(
                'limit' => '6', // show only 6 categories by default
                'custom_title' => '',
                'custom_url' => '',
                'hide_title' => '',
                'header_color' => ''
            ), $atts));

        $limit = (int)$limit;
        if ( $limit <= 0 ) {
            $limit = 6;
        }

        $item_args = array(
            'hide_empty' => false,
            'number' => $limit,
            'offset' => 0,
            'orderby' => $order_by_arg,
            'order' => $order_arg,
            'exclude' => '',
            'include' => $id_filter
        );


        $items = array();
        $block_error = '';

        $type = $this->get_att( 'type' );
        switch( $type ) {
            case '':
                $item_args['exclude'] = get_cat_ID(TD_FEATURED_CAT);

                // exclude categories from the demo
                if (TD_DEPLOY_MODE == 'demo' or TD_DEPLOY_MODE == 'dev') {
                    $item_args['exclude'] = '153, 154, 155, 156, 157, 158, 159, 90, 91, 92, 93 , 94, 95, 96, 97, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 82, 83, 84, 85, 86, 87, 88, 89, 98, ' . get_cat_ID(TD_FEATURED_CAT);
                }

                $items = get_categories($item_args);

                break;

            case 'tags':
                $items = get_tags($item_args);

                break;

            case 'custom_tax':
                $custom_tax = $this->get_att('custom_tax');

                if( $custom_tax == '' ) {
                    $block_error = td_util::get_block_error(
                        'Categories/tags list',
                        'Please select a <strong>custom taxonomy</strong>.');
                } else if( !taxonomy_exists($custom_tax) ) {
                    $block_error = td_util::get_block_error(
                        'Categories/tags list',
                        'The <strong>custom taxonomy</strong> that you have selected does not exist.');
                } else {
                    $custom_tax_terms = get_terms(
                        array_merge(
                            array(
                                'taxonomy' => $custom_tax
                            ),
                            $item_args
                        )
                    );

                    if( !is_wp_error( $custom_tax_terms ) ) {
                        $items = $custom_tax_terms;
                    }
                }

                break;
        }

        // text separator
        $text_sep_html = '';
        $text_sep = $this->get_att( 'sep_text' );
        if( $text_sep != '' ) {
            $text_sep_html = '<span class="td-ct-text-sep">' . $text_sep . '</span>';
        }

        // icon separator
        $tdicon_html = '';
        $tdicon = $this->get_icon_att( 'tdicon' );
        $tdicon_data = '';
        if( td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax() ) {
            $tdicon_data = 'data-td-svg-icon="' . $this->get_att('tdicon') . '"';
        }
        if( $tdicon != '' ) {
            if( base64_encode( base64_decode( $tdicon ) ) == $tdicon ) {
                $tdicon_html = '<span class="td-ct-item-sep td-ct-item-sep-svg" ' . $tdicon_data . '>' . base64_decode( $tdicon ) . '</span>';
            } else {
                $tdicon_html = '<i class="' . $tdicon . ' td-ct-item-sep"></i>';
            }
        }


        $buffy .= '<div class="' . $this->get_block_classes() . '" ' . $this->get_block_html_atts() . '>';

        //get the block css
        $buffy .= $this->get_block_css();

        // get the js for this block
        $buffy .= $this->get_block_js();

        // load more js
        if( $load_more_enabled && !( td_util::tdc_is_live_editor_iframe() || td_util::tdc_is_live_editor_ajax() ) ) {

            td_resources_load::render_script(
                TDC_SCRIPTS_URL . '/tdCategoriesTagsLoadMore.js' . TDC_SCRIPTS_VER,
                'tdCategoriesTagsLoadMore-js',
                '',
                'footer'
            );

            ob_start();
            ?>
            <script>
                /* global jQuery:{} */
                jQuery().ready(function () {

                    let uid = '<?php echo $this->block_uid ?>',
                        $blockObj = jQuery('.<?php echo $this->block_uid ?>');

                    if (!$blockObj.length) {
                        return;
                    }

                    let tdCtItem = new tdCategoriesTagsLoadMore.item();
                    // block uid
                    tdCtItem.uid = uid;
                    // block object
                    tdCtItem.blockObj = $blockObj;

                    tdCategoriesTagsLoadMore.addItem(tdCtItem);

                });
            </script>
            <?php
            td_js_buffer::add_to_footer( "\n" . td_util::remove_script_tag( ob_get_clean() ) );
        }

        // block title wrap
        $buffy .= '<div class="td-block-title-wrap">';
        $buffy .= $this->get_block_title();
        $buffy .= $this->get_pull_down_filter(); //get the sub category filter for this block
        $buffy .= '</div>';

        if( !empty( $block_error ) ) {
            $buffy .= $block_error;
        } else if( !empty($items) ) {

            // if load more enabled, check if there are more terms
            $has_more_initial = false;
            if ( $load_more_enabled ) {
                $peek_args = $item_args;
                $peek_args['number'] = 1;
                $peek_args['offset'] = $limit;

                $peek = array();
                switch( $type ) {
                    case '':
                        $peek = get_categories($peek_args);
                        break;

                    case 'tags':
                        $peek = get_tags($peek_args);
                        break;

                    case 'custom_tax':
                        $custom_tax = $this->get_att('custom_tax');
                        $peek = get_terms(
                            array_merge(
                                array('taxonomy' => $custom_tax),
                                $peek_args
                            )
                        );
                        break;
                }

                if( !is_wp_error($peek) && !empty($peek) ) {
                    $has_more_initial = true;
                }
            }

            if ( $load_more_enabled ) {
                $nonce = wp_create_nonce('td_ct_load_more');

                $buffy .= '<div class="td-ct-wrap"'
                    . ' data-td-ct-nonce="' . esc_attr($nonce) . '"'
                    . ' data-td-ct-limit="' . esc_attr($limit) . '"'
                    . ' data-td-ct-offset="' . esc_attr($limit) . '"'
                    . ' data-td-ct-orderby="' . esc_attr($order_by_arg) . '"'
                    . ' data-td-ct-order="' . esc_attr($order_arg) . '"'
                    . ' data-td-ct-include="' . esc_attr($id_filter) . '"'
                    . ' data-td-ct-exclude="' . esc_attr($item_args['exclude']) . '"'
                    . ' data-td-ct-type="' . esc_attr($type) . '"'
                    . ' data-td-ct-custom-tax="' . esc_attr($this->get_att('custom_tax')) . '"'
                    . ' data-td-ct-show-count="' . esc_attr($show_count) . '"'
                    . ' data-td-ct-show-inline="' . esc_attr($show_inline) . '"'
                    . ' data-td-ct-sep-text="' . esc_attr($text_sep) . '"'
                    . ' data-td-ct-tdicon="' . esc_attr($this->get_att('tdicon')) . '"'
                    . '>';
            } else {
                $buffy .= '<div class="td-ct-wrap">';
            }

            foreach ($items as $item) {
                if (strtolower($item->name) != 'uncategorized') {
                    $buffy .= '<a href="' . get_category_link($item->term_id) . '" class="td-ct-item">';
                    $buffy .= '<span class="td-ct-item-name">' . $item->name . '</span>';

                    if( $show_count == 'yes' ) {
                        if( $show_inline == 'yes' ) {
                            $buffy .= '&nbsp;<span class="td-ct-item-no">(' . $item->count . ')</span>';
                        } else {
                            $buffy .= '<span class="td-ct-item-no">' . $item->count . '</span>';
                        }
                    }
                    $buffy .= $text_sep_html . '</a>' . $tdicon_html;
                }
            }
            $buffy .= '</div>';

            // load more button
            if ( $load_more_enabled && $has_more_initial ) {
                $buffy .= '<button class="td-ct-load-more" type="button">' . esc_html($load_more_text) . '</button>';
            }
        }

        $buffy .= '</div>';
        return $buffy;
    }

    function inner($posts, $td_column_number = '') {

    }
}
