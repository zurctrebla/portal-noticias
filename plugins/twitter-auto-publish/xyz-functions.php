<?php
if( !defined('ABSPATH') ){ exit();}
if(!function_exists('xyz_trim_deep'))
{

	function xyz_trim_deep($value) {
		if ( is_array($value) ) {
			$value = array_map('xyz_trim_deep', $value);
		} elseif ( is_object($value) ) {
			$vars = get_object_vars( $value );
			foreach ($vars as $key=>$data) {
				$value->{$key} = xyz_trim_deep( $data );
			}
		} else {
			$value = trim($value);
		}

		return $value;
	}

}

if(!function_exists('esc_textarea'))
{
	function esc_textarea($text)
	{
		$safe_text = htmlspecialchars( $text, ENT_QUOTES );
		return $safe_text;
	}
}

if(!function_exists('xyz_twap_plugin_get_version'))
{
	function xyz_twap_plugin_get_version()
	{
		if ( ! function_exists( 'get_plugins' ) )
			require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
		$plugin_folder = get_plugins( '/' . plugin_basename( dirname( XYZ_TWAP_PLUGIN_FILE ) ) );
		// 		print_r($plugin_folder);
		return $plugin_folder['twitter-auto-publish.php']['Version'];
	}
}

if(!function_exists('xyz_twap_run_upgrade_routines'))
{
function xyz_twap_run_upgrade_routines() {
	global $wpdb;
	if (is_multisite()) {
		$blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
		foreach ($blog_ids as $blog_id) {
			switch_to_blog($blog_id);
			// Run install logic for each site
			twap_install_free();
			// Clear any relevant caches (example: object cache)
			restore_current_blog();
		}
	} else {
		// Single site: just run install and cache clear
		twap_install_free();
	}
}
}

if(!function_exists('xyz_twap_links')){
	function xyz_twap_links($links, $file) {
		$base = plugin_basename(XYZ_TWAP_PLUGIN_FILE);
		if ($file == $base) {

			$links[] = '<a href="http://help.xyzscripts.com/docs/twitter-auto-publish/faq/"  title="FAQ">FAQ</a>';
			$links[] = '<a href="http://help.xyzscripts.com/docs/twitter-auto-publish/"  title="Read Me">README</a>';
			$links[] = '<a href="https://xyzscripts.com/support/" class="xyz_twap_support" title="Support"></a>';
			$links[] = '<a href="http://x.com/xyzscripts" class="xyz_twap_twitt" title="Follow us on twitter"></a>';
			$links[] = '<a href="https://www.facebook.com/xyzscripts" class="xyz_twap_fbook" title="Facebook"></a>';
			$links[] = '<a href="http://www.linkedin.com/company/xyzscripts" class="xyz_twap_linkdin" title="Follow us on linkedIn"></a>';
			$links[] = '<a href="https://www.instagram.com/xyz_scripts/" class="xyz_twap_insta" title="Follow us on Instagram"></a>';
		}
		return $links;
	}
}

if(!function_exists('xyz_twap_string_limit')){
function xyz_twap_string_limit($string, $limit) {

	$space=" ";$appendstr=" ...";
	if (function_exists('mb_strlen') && function_exists('mb_substr') && function_exists('mb_strripos')) {
	if(mb_strlen($string) <= $limit) return $string;
	if(mb_strlen($appendstr) >= $limit) return '';
	$string = mb_substr($string, 0, $limit-mb_strlen($appendstr));
	$rpos = mb_strripos($string, $space);
	if ($rpos===false)
		return $string.$appendstr;
	else
		return mb_substr($string, 0, $rpos).$appendstr;
	}
	else {
		if(strlen($string) <= $limit) return $string;
		if(strlen($appendstr) >= $limit) return '';
		$string = substr($string, 0, $limit-strlen($appendstr));
		$rpos = strripos($string, $space);
		if ($rpos===false)
			return $string.$appendstr;
		else
			return substr($string, 0, $rpos).$appendstr;
	}
  }
}
if(!function_exists('xyz_twap_getimage')){
function xyz_twap_getimage($post_ID,$description_org)
{
	$attachmenturl="";
	$post_thumbnail_id = get_post_thumbnail_id( $post_ID );
	if(!empty($post_thumbnail_id))
		$attachmenturl=wp_get_attachment_url($post_thumbnail_id);
	else {
		preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/is', $description_org, $matches);
		if(isset($matches[1][0]))
			$attachmenturl = $matches[1][0];
		else
		{
		    $matches=array();
			$description_org=apply_filters('the_content', $description_org);
			preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/is', $description_org, $matches);
			if(isset($matches[1][0]))
				$attachmenturl = $matches[1][0];
	        else
	            $attachmenturl=xyz_twap_get_post_gallery_images_with_info($description_org,1);
		}
	}
	return $attachmenturl;
}
}

if(!function_exists('xyz_twap_get_post_gallery_images_with_info'))
{
    function xyz_twap_get_post_gallery_images_with_info($post_content,$single=1) {
        $ids=$images_id=array();
        preg_match('/\[gallery.*ids=.(.*).\]/', $post_content, $ids);
        if (isset($ids[1]))
            $images_id = explode(",", $ids[1]);
            $image_gallery_with_info = array();
            foreach ($images_id as $image_id) {
                $attachment = get_post($image_id);
                $img_src=$attachment->guid;
                if($single==1)
                    return $img_src;
                    else
                        $image_gallery_with_info[]=$img_src;
            }
            return $image_gallery_with_info;
    }
}
/* Local time formating */
if(!function_exists('xyz_twap_local_date_time')){
	function xyz_twap_local_date_time($format,$timestamp){
		return date($format, $timestamp + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ));
	}
}

if (!function_exists("xyz_twap_split_replace"))
{
	function xyz_twap_split_replace($search, $replace, $subject)//case insensitive
	{
		if(!stristr($subject,$search))
		{
			$search_tmp=str_replace("}", "", $search);
			preg_match_all("@(".preg_quote($search_tmp)."\:)(l|w)\-(\d+)}@i",$subject,$matches); // @ is same as /
			if(is_array($matches) && isset($matches[0]))
			{
				foreach ($matches[0] as $k=>$v)
				{
					$limit=$matches[3][$k];
					if(strcasecmp($matches[2][$k],"l")==0)//lines
					{
						$replace_arr = preg_split( "/(\.|;|\!)/", $replace ,0,PREG_SPLIT_DELIM_CAPTURE );
						if(is_array($replace_arr) && count($replace_arr)>0)
						{
							$replace_new=implode(array_slice($replace_arr,0,(2*$limit)));
							$subject=str_replace($matches[0][$k], $replace_new, $subject);
						}
					}
					if(strcasecmp($matches[2][$k],"w")==0)//words
					{
						$replace_arr=explode(" ",$replace);
						if(is_array($replace_arr) && count($replace_arr)>0)
						{
							$replace_new=implode(" ",array_slice($replace_arr,0,$limit));
							$subject=str_replace($matches[0][$k], $replace_new, $subject);
						}
					}
				}
			}
		}
		else
			$subject=str_replace($search, $replace, $subject);
		return $subject;
	}
}

add_filter( 'plugin_row_meta','xyz_twap_links',10,2);

if(!function_exists('xyz_twap_post_to_smap_api'))
{
	function xyz_twap_post_to_smap_api($post_details,$url,$xyzscripts_hash_val='') {
		if (function_exists('curl_init'))
		{
			$post_parameters['post_params'] = serialize($post_details);
			$post_parameters['request_hash'] = md5($post_parameters['post_params'].$xyzscripts_hash_val);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_parameters);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER,(get_option('xyz_twap_peer_verification')=='1') ? true : false);
			$content = curl_exec($ch);
			curl_close($ch);
			if (empty($content))
			{
					$response=array('status'=>0,'tw_api_count'=>0,'msg'=>'Error:unable to connect');
					$content=json_encode($response);
			}
			return $content;
		}
	}
}
if(!function_exists('xyz_twap_custom_cron_interval')){
	function xyz_twap_custom_cron_interval( $schedules ) {
		$schedules['twap_reauth_every_two_hours'] = array(
			'interval' => 2 * 60 * 60, // 2 hours in seconds
			'display'  =>__('Every 2 hours','twitter-auto-publish'),
		);
		return $schedules;
	}
}
?>