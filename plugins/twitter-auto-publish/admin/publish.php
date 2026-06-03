<?php 
if( !defined('ABSPATH') ){ exit();}
add_action('save_post', 'xyz_twap_save_metabox_meta');
function xyz_twap_save_metabox_meta($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (isset($_POST['xyz_twap_twpost_permission'])) {
        $data = array(
            'xyz_twap_twpost_permission'       => $_POST['xyz_twap_twpost_permission'],
            'xyz_twap_twpost_image_permission' => $_POST['xyz_twap_twpost_image_permission'] ?? '',
            'xyz_twap_twmessage'       => $_POST['xyz_twap_twmessage'] ?? '',
        );
        update_post_meta($post_id, 'xyz_twap_future_to_publish', $data);
    }
}
///////////////////////////////////////////////////////////////
add_action(  'transition_post_status',  'xyz_link_twap_future_to_publish', 10, 3 );

function xyz_link_twap_future_to_publish($new_status, $old_status, $post){
	
	if (isset($_GET['_locale']) && (empty($_POST) || empty($post)))
		return ;
	if(!isset($GLOBALS['twap_dup_publish']))
		$GLOBALS['twap_dup_publish']=array();
	
	$postid =$post->ID;
	$post_published_date_time=$post_modified_date_time=time();
	if ($post) {
		$post_published_date_time = strtotime(get_the_date('Y-m-d H:i:s', $postid));
		$post_modified_date_time = strtotime(get_the_modified_date('Y-m-d H:i:s', $postid));
	}
	$get_post_meta=get_post_meta($postid,"xyz_twap",true);
	
	$post_twitter_permission=get_option('xyz_twap_twpost_permission');
	if(isset($_POST['xyz_twap_twpost_permission'])){
		$post_twitter_permission=$_POST['xyz_twap_twpost_permission'];
		if ( (isset($_POST['xyz_twap_twpost_permission']) && isset($_POST['xyz_twap_twpost_image_permission'])) )
		{
			$futToPubDataArray=array( 'xyz_twap_twpost_permission'	=>	$_POST['xyz_twap_twpost_permission'],
					'xyz_twap_twpost_image_permission'	=>	$_POST['xyz_twap_twpost_image_permission'],
					'xyz_twap_twmessage'	=>	$_POST['xyz_twap_twmessage']);
			update_post_meta($postid, "xyz_twap_future_to_publish", $futToPubDataArray);
		}
	}
	else
	{
		if ($post_twitter_permission == 1) {
			if($new_status == 'publish')
			{
				if ($get_post_meta == 1 ) {
					if(get_option('xyz_twap_default_selection_edit')==0)
					return;
				}
				else //prevent backend publish
				{
					//post meta not 1, edited post
					if (($post_modified_date_time != $post_published_date_time) && $old_status=='publish' ) 
					{//already plublished ,auto publish on edit is disabled
						if ((get_option('xyz_twap_default_selection_edit') == 0))
							return;
					}
					//post meta not 1, new post ,auto publish on create is disabled
					else
					{
					if ((get_option('xyz_twap_default_selection_create') == 0))
						return;
					}
				}
			}
			else return;
		}
	}
	if($post_twitter_permission == 1)
	{
		if($new_status == 'publish')
		{
			if(!in_array($postid,$GLOBALS['twap_dup_publish'])) {
				$GLOBALS['twap_dup_publish'][]=$postid;
				xyz_twap_link_publish($postid);
			}
		}
		
	}
	 
	
}
////////////////////////////////////////////////////////////////////////////////////
/*$xyz_twap_include_customposttypes=get_option('xyz_twap_include_customposttypes');
$carr=explode(',', $xyz_twap_include_customposttypes);
foreach ($carr  as $cstyps ) {
	add_action('publish_'.$cstyps, 'xyz_twap_link_publish');

}
*/

function xyz_twap_link_publish($post_ID) {
	$_POST_CPY=$_POST;
	$_POST=stripslashes_deep($_POST);
	$post_twitter_image_permission=0;$messagetopost='';
	$get_post_meta_future_data=get_post_meta($post_ID,"xyz_twap_future_to_publish",true);
	$get_post_meta=get_post_meta($post_ID,"xyz_twap",true);
	$post_twitter_permission=get_option('xyz_twap_twpost_permission');

	if(!empty($get_post_meta_future_data) && ((get_option('xyz_twap_default_selection_edit')==2 && $get_post_meta==1) || (get_option('xyz_twap_default_selection_create')==2 && $get_post_meta!=1 )))///select values from post meta
	{
		$post_twitter_permission=$get_post_meta_future_data['xyz_twap_twpost_permission'];
		$post_twitter_image_permission=$get_post_meta_future_data['xyz_twap_twpost_image_permission'];
		$messagetopost=$get_post_meta_future_data['xyz_twap_twmessage'];
	}
	if(isset($_POST['xyz_twap_twpost_permission']))
	$post_twitter_permission=$_POST['xyz_twap_twpost_permission'];
	if ($post_twitter_permission != 1) {
		$_POST=$_POST_CPY;
		return ;
	} else if(( (isset($_POST['_inline_edit'])) || (isset($_REQUEST['bulk_edit'])) )  && (get_option('xyz_twap_default_selection_edit') == 0 && $get_post_meta==1) ) {
		$_POST=$_POST_CPY;
		return;
	}
	global $current_user;
	wp_get_current_user();
	//$af=get_option('xyz_twap_af');	
	
/////////////twitter//////////
$tappid=$tappsecret=$taccess_token=$taccess_token_secret=$tauthToken='';
$tw_af=1;
	$xyz_twap_tw_app_sel_mode=get_option('xyz_twap_tw_app_sel_mode');
	if($xyz_twap_tw_app_sel_mode==0){
	$tappid=get_option('xyz_twap_twconsumer_id');
	$tappsecret=get_option('xyz_twap_twconsumer_secret');
		$taccess_token=get_option('xyz_twap_current_twappln_token');
		$taccess_token_secret=get_option('xyz_twap_twaccestok_secret');
		
	}
	elseif($xyz_twap_tw_app_sel_mode==2){
		$tauthToken = get_option('xyz_twap_tw_token');
		$tw_af = get_option('xyz_twap_tw_af');
	}
	$twid=get_option('xyz_twap_tw_id');
	
	if ($messagetopost=='')
	$messagetopost=get_option('xyz_twap_twmessage');
	if(isset($_POST['xyz_twap_twmessage']))
		$messagetopost=$_POST['xyz_twap_twmessage'];

	if ($post_twitter_image_permission==0)
	$post_twitter_image_permission=get_option('xyz_twap_twpost_image_permission');
	if(isset($_POST['xyz_twap_twpost_image_permission']))
		$post_twitter_image_permission=$_POST['xyz_twap_twpost_image_permission'];
	global $wpdb;
	
	$reg_exUrl = "/(http|https)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/";
	$display_name =$user_nicename = '';
	$postpp= get_post($post_ID);
	$author_id = $postpp->post_author;
	$user = get_userdata($author_id);
	if ($user) {
	$user_displayname = $user->display_name;
	$user_nicename = $user->user_nicename;
	}
	
	if ($postpp->post_status == 'publish')
	{
		$posttype=$postpp->post_type;
		if ($posttype=="page")
		{
			$xyz_twap_include_pages=get_option('xyz_twap_include_pages');	
			if($xyz_twap_include_pages==0)
			{
				$_POST=$_POST_CPY;return;
			}
		}
			
		else if($posttype=="post")
		{
			$xyz_twap_include_posts=get_option('xyz_twap_include_posts');
			if($xyz_twap_include_posts==0)
			{
				$_POST=$_POST_CPY;return;
			}
			
			$xyz_twap_include_categories=get_option('xyz_twap_include_categories');
			if($xyz_twap_include_categories!="All")
			{
				$carr1=explode(',', $xyz_twap_include_categories);
				$defaults = array('fields' => 'ids');
				$carr2=wp_get_post_categories( $post_ID, $defaults );
				$retflag=1;
				foreach ($carr2 as $key=>$catg_ids)
				{
					if(in_array($catg_ids, $carr1))
						$retflag=0;
				}
					
					
				if($retflag==1)
				{$_POST=$_POST_CPY;return;}
			}
		}
		else
		{
			
			$xyz_twap_include_customposttypes=get_option('xyz_twap_include_customposttypes');
			if($xyz_twap_include_customposttypes!='')
			{
				
			$carr=explode(',', $xyz_twap_include_customposttypes);
			
				if(!in_array($posttype, $carr))
				{
					$_POST=$_POST_CPY;return;
				}	
			
			}
			else 
			{
			$_POST=$_POST_CPY;return;	
			}
			
		}

		$get_post_meta=get_post_meta($post_ID,"xyz_twap",true);
		if (get_post_status($post_ID) === 'publish' && ! $get_post_meta)
			add_post_meta($post_ID, "xyz_twap", "1");
		include_once ABSPATH.'wp-admin/includes/plugin.php';
		$pluginName = 'bitly/bitly.php';
		
		if (is_plugin_active($pluginName)) {
			remove_all_filters('post_link');
		}
		$link = get_permalink($postpp->ID);

		
		$xyz_twap_apply_filters=get_option('xyz_twap_apply_filters');
		$ar2=explode(",",$xyz_twap_apply_filters);
		$con_flag=$exc_flag=$tit_flag=0;
		if(isset($ar2))
		{
			if(in_array(1, $ar2)) $con_flag=1;
			if(in_array(2, $ar2)) $exc_flag=1;
			if(in_array(3, $ar2)) $tit_flag=1;
		}
		$content = $postpp->post_content;
		if($con_flag==1)
			$content = apply_filters('the_content', $content);
		
		$content = html_entity_decode($content, ENT_QUOTES, get_bloginfo('charset'));
		$excerpt = $postpp->post_excerpt;
		if($exc_flag==1)
			$excerpt = apply_filters('the_excerpt', $excerpt);
		
		$excerpt = html_entity_decode($excerpt, ENT_QUOTES, get_bloginfo('charset'));
		$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $content);
		$content=  preg_replace("/\\[caption.*?\\].*?\\[.caption\\]/is","", $content);
		$content = preg_replace('/\[.+?\]/', '', $content);
		$excerpt = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $excerpt);
		
		if($excerpt=="")
		{
			if($content!="")
			{
				$content1=$content;
				$content1=strip_tags($content1);
				$content1=strip_shortcodes($content1);
				
				$excerpt=implode(' ', array_slice(explode(' ', $content1), 0, 50));
			}
		}
		else
		{
			$excerpt=strip_tags($excerpt);
			$excerpt=strip_shortcodes($excerpt);
		}
		$description = $content;
		
		$description_org=$description;
		$attachmenturl=xyz_twap_getimage($post_ID, $postpp->post_content);
		if(!empty($attachmenturl))
			$image_found=1;
		else
			$image_found=0;
		
		$name = $postpp->post_title;
		$caption = html_entity_decode(get_bloginfo('title'), ENT_QUOTES, get_bloginfo('charset'));
		
		if($tit_flag==1)
			$name = apply_filters('the_title', $name,$post_ID);
		
		$name = html_entity_decode($name, ENT_QUOTES, get_bloginfo('charset'));
		$name=strip_tags($name);
		$name=strip_shortcodes($name);
		
		$description=strip_tags($description);		
		$description=strip_shortcodes($description);
		$description=str_replace("&nbsp;","",$description);
		$excerpt=str_replace("&nbsp;","", $excerpt);
	
		if(( ($xyz_twap_tw_app_sel_mode==0 && $taccess_token!="" && $taccess_token_secret!="" && $tappid!="" && $tappsecret!="") || 
		 ($xyz_twap_tw_app_sel_mode==2 && $tw_af!=1 && $tauthToken!='')) && $post_twitter_permission==1)
		{
			
			////image up start///
			$api_exceed_err=0;$remaining_tw_api_count=0;$tw_api_count=0;
			$img_status="";
			if($post_twitter_image_permission==1)
			{
			    update_post_meta($post_ID, "xyz_twap_insert_twitter_card", "1");
				
				$img=array();
				if(!empty($attachmenturl))
					$img = wp_remote_get($attachmenturl,array('sslverify'=> (get_option('xyz_twap_peer_verification')=='1') ? true : false));
					
				if(is_array($img) && ! is_wp_error( $img ) )
				{
					if (isset($img['body'])&& trim($img['body'])!='')
					{
						$image_found = 1;
							if (($img['headers']['content-length']) && trim($img['headers']['content-length'])!='')
						{						$img_size_bytes=$img['headers']['content-length'];
								$img_size=$img['headers']['content-length']/(1024*1024);
								if($img_size>3){
									$image_found=0;$img_status="Image skipped(greater than 3MB)";
									$image_found = 0;
								}
							}
							
						$img = $img['body'];
						///////////////////////Create temp folder ß
						$wp_twap_img_targetfolder = realpath(dirname(__FILE__) . '/../../../')."/uploads/xyz_twap_temp_images";
						if (file_exists($wp_twap_img_targetfolder)==false)
						{
							if (mkdir($wp_twap_img_targetfolder, 0777, true))
							{
								chmod($wp_twap_img_targetfolder,0777);
							}
						}
						////////////upload image to temporary folder and get path
						$xyz_twap_ext = pathinfo($attachmenturl, PATHINFO_EXTENSION);
						$xyz_twap_filename=pathinfo($attachmenturl, PATHINFO_FILENAME);
						$xyz_twap_image_files=$wp_twap_img_targetfolder."/".$xyz_twap_filename.".".$xyz_twap_ext;
					  file_put_contents($xyz_twap_image_files, $img);
					
						////////////////////////////
					}
					else
						$image_found = 0;
				}
				else
					$image_found = 0;
					
			}
			///Twitter upload image end/////
			$messagetopost=str_replace("&nbsp;","",$messagetopost);
			
			$substring="";$islink=0;$issubstr=0;
		
			$substring=str_replace('{POST_TITLE}', $name, $messagetopost);
			$substring=str_replace('{BLOG_TITLE}', $caption,$substring);
			$substring=str_replace('{PERMALINK}', ' '.$link.' ', $substring);
			$substring=str_replace('{POST_EXCERPT}', $excerpt, $substring);
			$substring=str_replace('{POST_CONTENT}', $description, $substring);
			$substring=str_replace('{USER_NICENAME}', $user_nicename, $substring);
			$substring=str_replace('{USER_DISPLAY_NAME}', $user_displayname, $substring);
			$publish_time=get_the_time(get_option('date_format'),$post_ID );
			$substring=str_replace('{POST_PUBLISH_DATE}', $publish_time, $substring);
			$substring=str_replace('{POST_ID}', $post_ID, $substring);
			preg_match_all($reg_exUrl,$substring,$matches); // @ is same as /
			
			if(is_array($matches) && isset($matches[0]))
			{
				$matches=$matches[0];
				$final_str='';
				$len=0;
				$tw_max_len=get_option('xyz_twap_tw_char_limit');
				if (function_exists('mb_strlen') && function_exists('mb_substr') && function_exists('mb_strpos')) {
				foreach ($matches as $key=>$val)
				{
					$url_max_len=23;//23 for https and 22 for http
					$messagepart=mb_substr($substring, 0, mb_strpos($substring, $val));
			
					if(mb_strlen($messagepart)>($tw_max_len-$len))
					{
						$final_str.=mb_substr($messagepart,0,$tw_max_len-$len-3)."...";
						$len+=($tw_max_len-$len);
						break;
					}
					else
					{
						$final_str.=$messagepart;
						$len+=mb_strlen($messagepart);
					}
			
					$cur_url_len=mb_strlen($val);
					if(mb_strlen($val)>$url_max_len)
						$cur_url_len=$url_max_len;
			
					$substring=mb_substr($substring, mb_strpos($substring, $val)+strlen($val));
					if($cur_url_len>($tw_max_len-$len))
					{
						$final_str.="...";
						$len+=3;
						break;
					}
					else
					{
						$final_str.=$val;
						$len+=$cur_url_len;
					}
				}
			
				if(mb_strlen($substring)>0 && $tw_max_len>$len)
				{
			
					if(mb_strlen($substring)>($tw_max_len-$len))
					{
						$final_str.=mb_substr($substring,0,$tw_max_len-$len-3)."...";
					}
					else
					{
						$final_str.=$substring;
					}
				}
				}
				else {
					foreach ($matches as $key=>$val)
					{
							
						$url_max_len=23;
							$messagepart=substr($substring, 0, strpos($substring, $val));
								
							if(strlen($messagepart)>($tw_max_len-$len))
							{
								$final_str.=substr($messagepart,0,$tw_max_len-$len-3)."...";
								$len+=($tw_max_len-$len);
								break;
							}
							else
							{
								$final_str.=$messagepart;
								$len+=strlen($messagepart);
							}
								
							$cur_url_len=strlen($val);
							if(strlen($val)>$url_max_len)
								$cur_url_len=$url_max_len;
									
								$substring=substr($substring, strpos($substring, $val)+strlen($val));
								if($cur_url_len>($tw_max_len-$len))
								{
									$final_str.="...";
									$len+=3;
									break;
								}
								else
								{
									$final_str.=$val;
									$len+=$cur_url_len;
								}
									
					}
						
					if(strlen($substring)>0 && $tw_max_len>$len)
					{
							
						if(strlen($substring)>($tw_max_len-$len))
						{
							$final_str.=substr($substring,0,$tw_max_len-$len-3)."...";
						}
						else
						{
							$final_str.=$substring;
						}
					}
				}
				
			
				$substring=$final_str;
			}
 		 /* if (strlen($substring)>$tw_max_len)
                	$substring=substr($substring, 0, $tw_max_len-3)."...";*/
			$tw_publish_status='';
 				 if($xyz_twap_tw_app_sel_mode==0)
					{
						$twobj = new Abraham\TwitterOAuth\TwitterOAuth(
										 $tappid,
										 $tappsecret,
										 $taccess_token,
										 $taccess_token_secret,
								 );
								 $twobj->userId = explode('-', $taccess_token)[0];
								 $twobj->setApiVersion('2');
			}
			elseif($xyz_twap_tw_app_sel_mode==2)
			{
				require_once (dirname(__FILE__) . '/../api/twitter.php');
				// Re-authenticate if 2 hours have passed since the last authorization
				$reauth_err=0;
				$current_time=time();
				$last_auth_time = get_option('xyz_twap_last_auth_time'); 
				$auth_timer = (2 * 60 * 60) - (2 * 60);
				 if ((time() - $last_auth_time) >= $auth_timer)
				 {
					$response=xyz_twap_twitter_auth2_reauth();
					if(isset($response['status']) && $response['status']=='error'){
						$reauth_err=1;
						$tw_publish_status_insert=serialize("<span style=\"color:red\">".$response['code'].':'.$response['message'].".</span>");
					}
					$tauthToken = get_option('xyz_twap_tw_token');
					}
			}
			
			if($image_found==1 && $post_twitter_image_permission==1)
			{
				if($xyz_twap_tw_app_sel_mode==0){
				$twobj->setTimeouts( 10, 60 );
				$twobj->setApiVersion( '1.1' );
				$response = $twobj->upload( 'media/upload', array( 'media' => $xyz_twap_image_files ) );
				if ( ! isset( $response->media_id ) ) {
					$media_upload_id = 0;
				} else {
					$media_upload_id = $response->media_id;
				}

				if ( $media_upload_id ) {
				$twobj->setTimeouts( 10, 30 );
				$twobj->setApiVersion( '2' );
				$resultfrtw = $twobj->post(
					'tweets',
					array('text' =>$substring,'media'=>array(
							'media_ids' => [ (string) $media_upload_id ],
						) ),
					true
				);
			if ( isset( $resultfrtw->data ) && ! is_wp_error( $resultfrtw->data ) ) {
					// Tweet posted successfully
						$tw_publish_status="<span style=\"color:green\">statuses/update : Success.</span>";
				} else if( is_wp_error( $resultfrtw->data )) {
				$error_string = $resultfrtw->data->get_error_message();
				$tw_publish_status="<span style=\"color:red\">".$error_string.".</span>";
				}
				else
					{
					if(!empty($resultfrtw->detail))
						$tw_publish_status="<span style=\"color:red\">".$resultfrtw->status.":".$resultfrtw->detail.".</span>";
						else
						$tw_publish_status="<span style=\"color:red\">Not Available</span>";
					}
				if($img_status!="")
					$tw_publish_status.="<span style=\"color:red\">".$img_status.".</span>";
					
				}
				else
				{
					$tw_publish_status="<span style=\"color:red\">statuses/update : ".serialize($response)."</span>";
				}
				if (is_file($xyz_twap_image_files) === true)
       				 {
         			    unlink($xyz_twap_image_files);
				}
			}
				elseif($xyz_twap_tw_app_sel_mode==2 && $reauth_err!=1){
					$response=xyz_twap_upload_media($tauthToken,$attachmenturl,$img_size_bytes);
					if($response['status']=='error'){
						$tw_publish_status="<span style=\"color:red\">".$response['code'].':'.$response['message'].".</span>";
					}
					elseif (isset($response['status']) && $response['status'] === 'success' && !empty($response['data'])) 
					{
						$mediaId=$response['data']['id'];
						$response=xyz_twap_create_post($tauthToken,$mediaId,$substring);
						if($response['status']=='error'){
							$tw_publish_status="<span style=\"color:red\">".$response['code'].':'.$response['message'].".</span>";
						}
						else{
							// $tweet_id=$response['data']['id'];
							$tw_publish_status="<span style=\"color:green\">statuses/update : Success.</span>";
						}
						if($img_status!="")						$tw_publish_status.="<span style=\"color:red\">".$img_status.".</span>";
					}
				}
			}
			else
			{
			    if($xyz_twap_tw_app_sel_mode==0)
			    {
        		//	$resultfrtw = $twobj->request('POST', $twobj->url('1.1/statuses/update'), array('text' =>$substring));
							$twobj->setTimeouts( 10, 30 );
							$twobj->setApiVersion( '2' );
							$resultfrtw = $twobj->post(
								'tweets',
								array('text' =>$substring),
								true
							);

						if ( isset( $resultfrtw->data ) && ! is_wp_error( $resultfrtw->data ) ) {
								// Tweet posted successfully
    					$tw_publish_status="<span style=\"color:green\">statuses/update : Success.</span>";
							} else if( is_wp_error( $resultfrtw )) {
							    // Handle error case
										$error_string = $resultfrtw->get_error_message();
										$tw_publish_status="<span style=\"color:red\">".$error_string.".</span>";
    				}
							else
							{
								if(!empty($resultfrtw->detail))
									$tw_publish_status="<span style=\"color:red\">".$resultfrtw->status.":".$resultfrtw->detail.".</span>";
    					else
									$tw_publish_status="<span style=\"color:red\">Not Available</span>";
    				}
			     }
				elseif($xyz_twap_tw_app_sel_mode==2  && $reauth_err!=1){
					$response=xyz_twap_post_to_twitter($tauthToken,$substring);
				if($response['status']=='error'){
					$tw_publish_status="<span style=\"color:red\">".$response['code'].':'.$response['message'].".</span>";
				}
				else{
					$tweet_id=$response['data']['id'];
					$tw_publish_status="<span style=\"color:green\">statuses/update : Success.</span>";
				}
			     }
			}
			$tweet_id_string='';
			if ($xyz_twap_tw_app_sel_mode==0) 
			{
if(isset($resultfrtw->data))

    			$resp = $resultfrtw->data;
    		if (isset($resp->id) && !empty($resp->id)){
    				$tweet_link="https://x.com/".$twid."/status/".$resp->id;
    				$tweet_id_string="<br/><span style=\"color:#21759B;text-decoration:underline;\"><a target=\"_blank\" href=".$tweet_link.">View Tweet</a></span>";
    					
    			}    			
    			$tw_publish_status_insert=serialize($tw_publish_status.$tweet_id_string);
    			}
			elseif ($xyz_twap_tw_app_sel_mode==2  && $reauth_err!=1)
			{
				if(isset($response['data']))
					$resp = $response['data'];
				if (isset($resp['id']) && !empty($resp['id'])){
					$tweet_link="https://x.com/".$twid."/status/".$resp['id'];
					$tweet_id_string="<br/><span style=\"color:#21759B;text-decoration:underline;\"><a target=\"_blank\" href=".$tweet_link.">View Tweet</a></span>";
				}
    			$tw_publish_status_insert=serialize($tw_publish_status.$tweet_id_string);
	       	}
	       	if($xyz_twap_tw_app_sel_mode==1){
       	    
       	        $tw_publish_status_insert= serialize("<span style=\"color:red\"> SMAPSolutions has discontinued its Twitter service </span>");//1;       	        
   	            }
			$time=time();
			$post_tw_options=array(
					'postid'	=>	$post_ID,
					'acc_type'	=>	"Twitter",
					'publishtime'	=>	$time,
					'status'	=>	$tw_publish_status_insert
			);
			$update_opt_array=array();
			
			$arr_retrive=(get_option('xyz_twap_post_logs'));
			
			$update_opt_array[0]=isset($arr_retrive[0]) ? $arr_retrive[0] : '';
			$update_opt_array[1]=isset($arr_retrive[1]) ? $arr_retrive[1] : '';
			$update_opt_array[2]=isset($arr_retrive[2]) ? $arr_retrive[2] : '';
			$update_opt_array[3]=isset($arr_retrive[3]) ? $arr_retrive[3] : '';
			$update_opt_array[4]=isset($arr_retrive[4]) ? $arr_retrive[4] : '';
			$update_opt_array[5]=isset($arr_retrive[5]) ? $arr_retrive[5] : '';
			$update_opt_array[6]=isset($arr_retrive[6]) ? $arr_retrive[6] : '';
			$update_opt_array[7]=isset($arr_retrive[7]) ? $arr_retrive[7] : '';
			$update_opt_array[8]=isset($arr_retrive[8]) ? $arr_retrive[8] : '';
			$update_opt_array[9]=isset($arr_retrive[9]) ? $arr_retrive[9] : '';
			
			array_shift($update_opt_array);
			array_push($update_opt_array,$post_tw_options);
			update_option('xyz_twap_post_logs', $update_opt_array);
		}
	}
	
	$_POST=$_POST_CPY;

}
