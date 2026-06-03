<?php
if( !defined('ABSPATH') ){ exit();}
add_action('admin_menu', 'xyz_twap_menu');

function xyz_twap_add_admin_scripts()
{
	wp_enqueue_script('jquery');
    $current_version = xyz_twap_plugin_get_version();
	wp_register_script( 'xyz_notice_script_twap', plugins_url('twitter-auto-publish/js/notice.js'),array(),$current_version );
	wp_enqueue_script( 'xyz_notice_script_twap' );
	$twap_smapsolution_var="SMAPSolutions";
	$twap_xyzscripts_var="xyzscripts";
	wp_localize_script('xyz_notice_script_twap','xyz_script_twap_var',array(
	    'alert1' => __('Please check whether the email is correct.','twitter-auto-publish'),
	    'alert2' => __('Select atleast one list.','twitter-auto-publish'),
	    'alert3' => __('You do not have sufficient permissions','twitter-auto-publish'),
	    'html1' => sprintf(__('Account details successfully deleted from %s','twitter-auto-publish'),$twap_smapsolution_var),
	    'html2' => sprintf(__('In-active Twitter account successfully deleted from %s','twitter-auto-publish'),$twap_smapsolution_var),
	    'html3' => sprintf(__('Please connect your %s member account','twitter-auto-publish'),$twap_xyzscripts_var),
	    'html4' => __('Thank you for enabling backlink !','twitter-auto-publish')
	));
	wp_register_style('xyz_twap_style', plugins_url('twitter-auto-publish/css/style.css'),array(),$current_version);
	wp_enqueue_style('xyz_twap_style');
}

add_action("admin_enqueue_scripts","xyz_twap_add_admin_scripts");

function xyz_twap_menu()
{
	add_menu_page('Twitter Auto Publish - Manage settings', 'WP Twitter Auto Publish', 'manage_options', 'twitter-auto-publish-settings', 'xyz_twap_settings',plugin_dir_url( XYZ_TWAP_PLUGIN_FILE ) . 'images/twap.png');
	$page=add_submenu_page('twitter-auto-publish-settings', 'Twitter Auto Publish - Manage settings', __('Settings','twitter-auto-publish'), 'manage_options', 'twitter-auto-publish-settings' ,'xyz_twap_settings'); // 8 for admin
	add_submenu_page('twitter-auto-publish-settings', 'Twitter Auto Publish - Logs', __('Logs','twitter-auto-publish'), 'manage_options', 'twitter-auto-publish-log' ,'xyz_twap_logs');
	add_submenu_page('twitter-auto-publish-settings', 'Twitter Auto Publish - About', __('About','twitter-auto-publish'), 'manage_options', 'twitter-auto-publish-about' ,'xyz_twap_about'); // 8 for admin
	add_submenu_page('twitter-auto-publish-settings','Twitter Auto Publish - Suggest Feature', __('Suggest a Feature','twitter-auto-publish'), 'manage_options', 'twitter-auto-publish-suggest-features' ,'xyz_twap_suggest_feature');
}


function xyz_twap_settings()
{
	$_POST = stripslashes_deep($_POST);
	$_GET = stripslashes_deep($_GET);	
	$_POST = xyz_trim_deep($_POST);
	$_GET = xyz_trim_deep($_GET);
	
	require( dirname( __FILE__ ) . '/header.php' );
	require( dirname( __FILE__ ) . '/settings.php' );
	require( dirname( __FILE__ ) . '/footer.php' );
}

function xyz_twap_about()
{
	require( dirname( __FILE__ ) . '/header.php' );
	require( dirname( __FILE__ ) . '/about.php' );
	require( dirname( __FILE__ ) . '/footer.php' );
}


function xyz_twap_suggest_feature()
{
	require( dirname( __FILE__ ) . '/header.php' );
	require( dirname( __FILE__ ) . '/suggest_feature.php' );
	require( dirname( __FILE__ ) . '/footer.php' );
}
function xyz_twap_logs()
{
	$_POST = stripslashes_deep($_POST);
	$_GET = stripslashes_deep($_GET);
	$_POST = xyz_trim_deep($_POST);
	$_GET = xyz_trim_deep($_GET);

	require( dirname( __FILE__ ) . '/header.php' );
	require( dirname( __FILE__ ) . '/logs.php' );
	require( dirname( __FILE__ ) . '/footer.php' );
}
add_action('wp_head', 'xyz_smap_insert_twitter_card');
function xyz_smap_insert_twitter_card(){
    global $post;
    $xyz_twap_tw_enforce_twitter_cards=get_option('xyz_twap_tw_enforce_twitter_cards');
    if (empty($post))
        $post=get_post();
        if (!empty($post) && ( $xyz_twap_tw_enforce_twitter_cards==1 ) )
        {
            $postid= $post->ID;
            $name='';$excerpt='';$attachmenturl='';
            if(isset($postid) && $postid>0 && isset($_SERVER["HTTP_USER_AGENT"]))
            { 
                $get_post_meta_insert_twitter_card=0;
                $get_post_meta_future_data=get_post_meta($postid,"xyz_twap_future_to_publish",true);
                
                $get_post_meta_insert_twitter_card=get_post_meta($postid,"xyz_twap_insert_twitter_card",true);
                if (!empty($get_post_meta_future_data) && ( $xyz_twap_tw_enforce_twitter_cards==1 ) && (strpos($_SERVER["HTTP_USER_AGENT"], "Twitterbot") !== false))
                { 
                    $xyz_smap_apply_filters=get_option('xyz_smap_apply_filters');
                        $ar2=explode(",",$xyz_smap_apply_filters);
                        $excerpt = $post->post_excerpt;
                        if(in_array(2, $ar2))
                            $excerpt = apply_filters('the_excerpt', $excerpt);
                            $excerpt = html_entity_decode($excerpt, ENT_QUOTES, get_bloginfo('charset'));
                            $excerpt = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $excerpt);
                            if($excerpt=="")
                            {
                                $content = $post->post_content;
                                if(in_array(1, $ar2))
                                    $content = apply_filters('the_content', $content);
                                    if($content!="")
                                    {
                                        $content1=$content;
                                        $content1=strip_tags($content1);
                                        $content1=strip_shortcodes($content1);
                                        $content1 = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content1);
                                        $content1=  preg_replace("/\\[caption.*?\\].*?\\[.caption\\]/is", "", $content1);
                                        $content1 = preg_replace('/\[.+?\]/', '', $content1);
                                        $excerpt=implode(' ', array_slice(explode(' ', $content1), 0, 50));
                                    }
                            }
                            else
                            {
                                $excerpt=strip_tags($excerpt);
                                $excerpt=strip_shortcodes($excerpt);
                            } //print_r($excerpt);die;
                            $excerpt=str_replace("&nbsp;","",$excerpt);
                            //print_r($excerpt);die;
                            $name = $post->post_title;//print_r($name);die;
                            if(in_array(3, $ar2))
                                $name = apply_filters('the_title', $name,$postid);
                                $name = html_entity_decode($name, ENT_QUOTES, get_bloginfo('charset'));//print_r($name);die;
                                $name=strip_tags($name);
                                $name=strip_shortcodes($name);
                                $attachmenturl=xyz_twap_getimage($postid, $post->post_content);
                                if(is_array($attachmenturl) && isset($attachmenturl['image_url']) && $attachmenturl['image_url']!='')
                                    $attachmenturl=$attachmenturl['image_url'];//print_r($name);die;**/
                }
                if (($get_post_meta_insert_twitter_card==1) && strpos($_SERVER["HTTP_USER_AGENT"], "Twitterbot") !== false && ($xyz_twap_tw_enforce_twitter_cards==1))
                    {
                        echo '<meta name="twitter:card" content="summary_large_image" />';
                        if(!empty( $name ))
                            echo '<meta name="twitter:title" content="'.$name.'" />';
                            if (!empty($excerpt))
                                echo '<meta name="twitter:description" content="'.$excerpt.'" />';
                                    if(!empty($attachmenturl))
                                        echo '<meta name="twitter:image" content="'.$attachmenturl.'" />';
                    }
                }
            }
        }
        add_filter( 'cron_schedules', 'xyz_twap_custom_cron_interval' );

        if ( ! wp_next_scheduled( 'xyz_twap_tw_auto_reauth' ) ) {
            wp_schedule_event( time(), 'twap_reauth_every_two_hours', 'xyz_twap_tw_auto_reauth' );
        }
        if(get_option('xyz_twap_tw_app_sel_mode')==2){
            require_once (dirname(__FILE__) . '/../api/twitter.php');
            add_action( 'xyz_twap_tw_auto_reauth', 'xyz_twap_twitter_auth2_reauth' );
        }
