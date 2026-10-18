<?php
if( !defined('ABSPATH') ){ exit();}
global $current_user;
$auth_varble=0;
require( dirname( __FILE__ ) . '/authorization.php' );
wp_get_current_user();
$imgpath= plugins_url()."/twitter-auto-publish/images/";
$heimg=$imgpath."support.png";


if(!$_POST && isset($_GET['twap_notice']) && $_GET['twap_notice'] == 'hide')
{
	if (! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'],'twap-shw')){
		wp_nonce_ays( 'twap-shw');
		exit;
	}
	update_option('xyz_twap_dnt_shw_notice', "hide");
	?>
<style type='text/css'>
#twap_notice_td
{
display:none !important;
}
</style>
<div class="xyz_twap_system_notice_area_style1" id="xyz_twap_system_notice_area">
<?php esc_html_e('Thanks again for using the plugin. We will never show the message again.','twitter-auto-publish');?>
 &nbsp;&nbsp;&nbsp;<span
		id="xyz_twap_system_notice_area_dismiss"><?php esc_html_e('Dismiss','twitter-auto-publish');?></span>
</div>

<?php
}



$tms1=$tms2=$tms3=$tms4=$tms5=$tms6="";

$terf=0;
if(isset($_POST['twit']))
{
	if (! isset( $_REQUEST['_wpnonce'] )|| ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'xyz_twap_tw_settings_form_nonce' ))
	{
		wp_nonce_ays( 'xyz_twap_tw_settings_form_nonce' );
		exit();
	}
	$tclient_id=$tclient_secret=$tappid=$tappsecret='';
	$xyz_twap_app_sel_mode_old=get_option('xyz_twap_tw_app_sel_mode');
	if(isset($_POST['xyz_twap_app_sel_mode'])) $xyz_twap_app_sel_mode=intval($_POST['xyz_twap_app_sel_mode']);
	if($xyz_twap_app_sel_mode==0){
	$tappid=sanitize_text_field($_POST['xyz_twap_twconsumer_id']);
	$tappsecret=sanitize_text_field($_POST['xyz_twap_twconsumer_secret']);
		$taccess_token=sanitize_text_field($_POST['xyz_twap_current_twappln_token']);
		$taccess_token_secret=sanitize_text_field($_POST['xyz_twap_twaccestok_secret']);
	}
	elseif($xyz_twap_app_sel_mode==2){
		$tclient_id_old=get_option('xyz_twap_client_id');
		$tclient_secret_old=get_option('xyz_twap_client_secret');
		$tclient_id=sanitize_text_field($_POST['xyz_twap_twclientid_oauth2']);
		$tclient_secret=sanitize_text_field($_POST['xyz_twap_twclientsecret_oauth2']);
	}
	$twid=sanitize_text_field($_POST['xyz_twap_tw_id']);
	$tposting_permission=intval($_POST['xyz_twap_twpost_permission']);
	$tposting_image_permission=intval($_POST['xyz_twap_twpost_image_permission']);
	$tmessagetopost=$_POST['xyz_twap_twmessage'];
	$xyz_twap_tw_char_limit=$_POST['xyz_twap_tw_char_limit'];
	$xyz_twap_tw_char_limit=intval($xyz_twap_tw_char_limit);
	if ($xyz_twap_tw_char_limit<140)
		$xyz_twap_tw_char_limit=140; 	
	if($tposting_permission==1){	
		if($xyz_twap_app_sel_mode==2){
			if($tclient_id=="")
			{
				$terf=1;
				$tms1=  __('Please fill Client ID.','twitter-auto-publish'); 
	
			}
			elseif($tclient_secret=="")
			{
				$tms2= __('Please fill Client Secret.','twitter-auto-publish');
				$terf=1;
			}
		}
		elseif($xyz_twap_app_sel_mode==0){
		if($tappid=="")
	{
		$terf=1;
		$tms1=  __('Please fill api key.','twitter-auto-publish'); 

	}
		elseif($tappsecret=="")
	{
	    $tms2= __('Please fill api secret.','twitter-auto-publish');
		$terf=1;
	}

			elseif($taccess_token=="")
	{
	    $tms4=  __('Please fill twitter access token.','twitter-auto-publish');
		$terf=1;
	}
			elseif($taccess_token_secret=="")
	{
	    $tms5=__('Please fill twitter access token secret.','twitter-auto-publish');
		$terf=1;
	}
		}
		if($twid=="" && $terf!=1)
			{
				$tms3= __('Please fill twitter username.','twitter-auto-publish');
				$terf=1;
			}
		if($tmessagetopost=="" && $terf!=1)
	{
	    $tms6= __('Please fill message format for posting.','twitter-auto-publish');
		$terf=1;
	}
	}
	if($terf!=1)
	{	
		if($tmessagetopost=="")
		{
			$tmessagetopost="{POST_TITLE}-{PERMALINK}";
		}
		if($xyz_twap_app_sel_mode==2 && ($tclient_id!=$tclient_id_old || $tclient_secret!=$tclient_secret_old || $xyz_twap_app_sel_mode_old != $xyz_twap_app_sel_mode))
		{
			update_option('xyz_twap_client_id',$tclient_id);
			update_option('xyz_twap_client_secret',$tclient_secret);
				update_option('xyz_twap_tw_af',1);
		}
		if($xyz_twap_app_sel_mode==0)
		{
		update_option('xyz_twap_twconsumer_id',$tappid);
		update_option('xyz_twap_twconsumer_secret',$tappsecret);
		update_option('xyz_twap_current_twappln_token',$taccess_token);
		update_option('xyz_twap_twaccestok_secret',$taccess_token_secret);
		}
		update_option('xyz_twap_tw_id',$twid);
		update_option('xyz_twap_tw_app_sel_mode',$xyz_twap_app_sel_mode);		
		update_option('xyz_twap_twmessage',$tmessagetopost);
		update_option('xyz_twap_twpost_permission',$tposting_permission);
		update_option('xyz_twap_twpost_image_permission',$tposting_image_permission);
		update_option('xyz_twap_tw_char_limit', $xyz_twap_tw_char_limit);
		
	}
}


if(isset($_POST['twit']) && $terf==0)
{
	?>

<div class="xyz_twap_system_notice_area_style1" id="xyz_twap_system_notice_area">
	<?php esc_html_e('Settings updated successfully','twitter-auto-publish');?>. &nbsp;&nbsp;&nbsp;<span
		id="xyz_twap_system_notice_area_dismiss"><?php esc_html_e('Dismiss','twitter-auto-publish');?></span>
</div>
<?php }
if(isset($_POST['twit']) && $terf==1)
{
	?>
<div class="xyz_twap_system_notice_area_style0" id="xyz_twap_system_notice_area">
	<?php 
	if(isset($_POST['twit']))
	{
		echo esc_html($tms1);echo esc_html($tms2);echo esc_html($tms3);echo esc_html($tms4);echo esc_html($tms5);echo esc_html($tms6);
	}
	?>
	&nbsp;&nbsp;&nbsp;<span id="xyz_twap_system_notice_area_dismiss"><?php esc_html_e('Dismiss','twitter-auto-publish');?></span>
</div>
<?php } 

if(isset($_GET['msg']) && $_GET['msg']==2){
	?>
<div class="xyz_twap_system_notice_area_style1" id="xyz_twap_system_notice_area">
<?php esc_html_e('Account has been authenticated successfully','twitter-auto-publish');?>.&nbsp;&nbsp;&nbsp;<span
id="xyz_twap_system_notice_area_dismiss"><?php esc_html_e('Dismiss','twitter-auto-publish');?></span>
</div>
<?php 	
}
if(isset($_GET['error_msg']) && $_GET['error_msg']!=''){
	$error_msg = urldecode($_GET['error_msg']);
	?>
<div class="xyz_twap_system_notice_area_style0" id="xyz_twap_system_notice_area">
<?php echo esc_html($error_msg);?>.&nbsp;&nbsp;&nbsp;<span
id="xyz_twap_system_notice_area_dismiss"><?php esc_html_e('Dismiss', 'twitter-auto-publish');?></span>
</div>
<?php 	
}
?>
<script type="text/javascript">
function detdisplay_twap(id)
{
	document.getElementById(id).style.display='';
}
function dethide_twap(id)
{
	document.getElementById(id).style.display='none';
}


</script>

<div style="width: 100%">

<div class="xyz_twap_tab">
  <button class="xyz_twap_tablinks" onclick="xyz_twap_open_tab(event, 'xyz_twap_twitter_settings')" id="xyz_twap_default_tab_settings"><?php esc_html_e('Twitter / X Settings','twitter-auto-publish');?></button>
   <button class="xyz_twap_tablinks" onclick="xyz_twap_open_tab(event, 'xyz_twap_basic_settings')" id="xyz_twap_basic_tab_settings"><?php esc_html_e('General Settings','twitter-auto-publish');?></button>
</div>
<div id="xyz_twap_twitter_settings" class="xyz_twap_tabcontent">
<table class="widefat" style="width: 99%;background-color: #FFFBCC" id='xyz_twap_app_creation_note'>
<tr>
<?php
/////////traditional note///////////?>
<td id="bottomBorderNone" style="border: 1px solid #FCC328;">
	<div class="xyz_twap_twitter_traditional_settings">
	<b><?php esc_html_e('Note:','twitter-auto-publish');?></b> <?php _e('You have to create a Twitter application before filling in following fields.','twitter-auto-publish');?> 	
	<?php	
	printf(
		__('%sClick here%s to create a new application.', 'twitter-auto-publish'),
		'<b><a href="https://developer.x.com/en/portal/projects-and-apps" target="_blank">',
		'</a></b>'
	);
		$twap_path1="Settings > User authentication settings > Edit";
		 $twap_navigate=sprintf(__('In the twitter application, navigate to <b>%s</b>.','twitter-auto-publish'),$twap_path1);	
		 echo $twap_navigate; 
		 $twap_read_write="Read and Write"; 
		 $twap_select=sprintf(__('Set <b>App permissions</b> to <b>%s</b>.','twitter-auto-publish'),$twap_read_write);
		 echo $twap_select;	
		 $twap_website_url= sprintf(
			__('Specify <b>Website URL</b> as %s', 'twitter-auto-publish'),
			"<span style='color: red;'>" . (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . "</span>"
		);
		 echo $twap_website_url;?>
		 <?php $twap_token_path="Keys and Tokens"; $twap_token_create="API Key and Secret, Access Token and Secret"; 
		 $oauth_credentials="OAuth 2.0 Client ID and Client Secret"; $twap_acess_token_create=sprintf(__('After updation , navigate to <b>%s</b> where you will get <b>%s</b>.','twitter-auto-publish'),$twap_token_path,$twap_token_create); echo $twap_acess_token_create; ?>
		 <?php  $twap_create_twapp="http://help.xyzscripts.com/docs/social-media-auto-publish/faq/how-can-i-create-twitter-application/"; $twap_inst_link=sprintf(__('For detailed step by step instructions <b><a href="%s" target="_blank"> Click here','twitter-auto-publish'),$twap_create_twapp); echo $twap_inst_link; ?></a></b>
	</div>
<?php ////////////////////oauth2 note////////////////////////?>
	<div class="xyz_twap_twitter_oauth2_settings">
		<b><?php esc_html_e('Note:','twitter-auto-publish');?></b> <?php esc_html_e('You have to create a Twitter application before filling in following fields.','twitter-auto-publish');?> 	
		<?php printf(
    __('%s Click here %s to create a new application.', 'twitter-auto-publish'),
    '<b><a href="https://developer.x.com/en/portal/projects-and-apps" target="_blank">',
    '</a></b>'
);?>
		 <br><?php $twap_path1="Settings > User authentication settings > Edit";
		 $twap_navigate=sprintf(__('In the twitter application, navigate to <b>%s</b>.','twitter-auto-publish'),$twap_path1);	
		 echo $twap_navigate; ?><br><?php
		 $twap_read_write="Read and Write"; 
		 $twap_apptyp="Web App, Automated App or Bot"; 
		 $twap_select=sprintf(__('Set <b>App permissions</b> to <b>%s</b>, and <b>Type of App</b> to <b>%s</b> .<br>Under <b>App info</b> section set <b>Redirect URI</b> to: %s .','twitter-auto-publish'),$twap_read_write,$twap_apptyp,"<span style='color: red;'>" .
        admin_url('admin.php?page=twitter-auto-publish-settings') . 
    "</span>"); 
		 echo $twap_select;?><br><?php		   
	  $twap_website_url= sprintf(
		__('Specify <b>Website URL</b> as %s', 'twitter-auto-publish'),
		"<span style='color: red;'>" . (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . "</span>"
	);
	 echo $twap_website_url;?>
		 <?php $twap_token_path="Keys and Tokens"; $twap_token_create="Client ID,Client Secret"; 
		 $oauth_credentials="OAuth 2.0 Client ID and Client Secret"; $twap_acess_token_create=sprintf(__('After updation , navigate to <b>%s</b> where you will get <b>%s</b><br>under <b>%s</b>.','twitter-auto-publish'),$twap_token_path,$twap_token_create,$oauth_credentials); echo $twap_acess_token_create; 
		  $twap_create_twapp="http://help.xyzscripts.com/docs/social-media-auto-publish/faq/how-can-i-create-twitter-application/"; $twap_inst_link=sprintf(__('For detailed step by step instructions <b><a href="%s" target="_blank"> Click here','twitter-auto-publish'),$twap_create_twapp); echo $twap_inst_link; ?></a></b>

	</div>
</td>
</tr>
</table>
<?php
$xyz_twap_app_sel_mode=get_option('xyz_twap_tw_app_sel_mode');
$tw_af=get_option('xyz_twap_tw_af');
$tclient_id=get_option('xyz_twap_client_id');
$tclient_secret=get_option('xyz_twap_client_secret');
/////////////////////oauth2 authorization button//////////////
if($xyz_twap_app_sel_mode==2)
	{
	if($tw_af==1 && $tclient_id!="" && $tclient_secret!="")
	{
		?>
			<span style="color: red;" id="auth_message" > <?php esc_html_e('Application needs authorisation','social-media-auto-publish'); ?> </span> <br>
	<form method="post">
	<?php wp_nonce_field( 'xyz_twap_tw_auth_form_nonce' );?>

		<input type="submit" class="xyz_twap_submit_new" name="tw_auth"
			value="<?php esc_html_e('Authorize','social-media-auto-publish'); ?>" /><br><br>
	</form>
	<?php }
	else if($tw_af==0 && $tclient_id!="" && $tclient_secret!="")
	{
		?>
	<form method="post">
	<?php wp_nonce_field( 'xyz_twap_tw_auth_form_nonce' );?>
	<input type="submit" class="xyz_twap_submit_new" name="tw_auth"
	value="<?php esc_html_e('Reauthorize','social-media-auto-publish'); ?>" title="Reauthorize the account" /><br><br>
	</form>
	<?php }
	}
?>
	<form method="post">
	<?php wp_nonce_field( 'xyz_twap_tw_settings_form_nonce' );?>
		<input type="hidden" value="config">

			<div style="font-weight: bold;padding: 3px;"><?php esc_html_e('All fields given below are mandatory','twitter-auto-publish');?></div> 
		
			<table class="widefat xyz_twap_widefat_table" style="width: 99%">
						<tr valign="top">
					<td><?php esc_html_e('Enable auto publish posts to my twitter account','twitter-auto-publish');?>
					</td>
			<td  class="xyz_twap_switch_field">
				<label id="xyz_twap_twpost_permission_yes"><input type="radio" name="xyz_twap_twpost_permission" value="1" <?php  if(get_option('xyz_twap_twpost_permission')==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
				<label id="xyz_twap_twpost_permission_no"><input type="radio" name="xyz_twap_twpost_permission" value="0" <?php  if(get_option('xyz_twap_twpost_permission')==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
			</td>
				</tr>
				<tr valign="top">
			<td width="50%"><?php esc_html_e('Application Selection','twitter-auto-publish');?>
			</td>
				<td>
				<input type="radio" name="xyz_twap_app_sel_mode" id="xyz_twap_app_sel_mode_reviewd" value="0" <?php if($xyz_twap_app_sel_mode==0) echo 'checked';?>>
				<span style="color: #a7a7a7;font-weight: bold;"> <?php esc_html_e('Own App- Traditional (Deprecating soon. Use OAuth 2.0) ','twitter-auto-publish'); ?><br/>
				<br>
				<input type="radio" name="xyz_twap_app_sel_mode" id="xyz_twap_app_sel_mode_reviewd" value="2" <?php if($xyz_twap_app_sel_mode==2) echo 'checked';?>>
				<span style="color: #a7a7a7;font-weight: bold;"> <?php esc_html_e('Own App (OAuth2.0 :Recommended)','twitter-auto-publish'); ?></span>


				</td>
			</tr>
		
<!-- ////////////Tradional credentials////////// -->
			<tr valign="top" class="xyz_twap_twitter_traditional_settings ">
					<td width="50%"><?php esc_html_e('API key','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_twconsumer_id"
						name="xyz_twap_twconsumer_id" type="text"
						value="<?php if($tms1=="") {echo esc_attr(get_option('xyz_twap_twconsumer_id'));}?>" />					
					</td>
				</tr>
				<tr valign="top" class="xyz_twap_twitter_traditional_settings">
					<td> <?php esc_html_e('API secret','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_twconsumer_secret"
						name="xyz_twap_twconsumer_secret" type="text"
						value="<?php if($tms2=="") { echo esc_attr(get_option('xyz_twap_twconsumer_secret')); }?>" />
					</td>
				</tr>

<!-- //////////////oauth2////////////////// -->
				<tr valign="top" class="xyz_twap_twitter_oauth2_settings ">
					<td width="50%"><?php esc_html_e('Client ID','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_twclientid_oauth2"
						name="xyz_twap_twclientid_oauth2" type="text"
						value="<?php if($tms1=="") {echo esc_attr($tclient_id);}?>" />					
					</td>
				</tr>
				<tr valign="top" class="xyz_twap_twitter_oauth2_settings">
					<td> <?php esc_html_e('Client Secret ','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_twclientsecret_oauth2"
						name="xyz_twap_twclientsecret_oauth2" type="text"
						value="<?php if($tms2=="") { echo esc_attr($tclient_secret); }?>" />
					</td>
				</tr>
				<!-- ///////////////////// -->
				<tr valign="top" class="xyz_twap_twitter_common_settings" id="tw_username">
					<td><?php esc_html_e('Twitter username','twitter-auto-publish');?>
					</td>
					<td><input id="xyz_twap_tw_id" class="al2tw_text"
						name="xyz_twap_tw_id" type="text"
						value="<?php if($tms3=="") {echo esc_attr(get_option('xyz_twap_tw_id'));}?>" />
					</td>
				</tr>
				<tr valign="top" class="xyz_twap_twitter_traditional_settings">
					<td> <?php esc_html_e('Access token','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_current_twappln_token" class="al2tw_text"
						name="xyz_twap_current_twappln_token" type="text"
						value="<?php if($tms4=="") {echo esc_attr(get_option('xyz_twap_current_twappln_token'));}?>" />
					</td>
				</tr>
				<tr valign="top" class="xyz_twap_twitter_traditional_settings">
					<td> <?php esc_html_e('Access token secret','twitter-auto-publish'); ?>
					</td>
					<td><input id="xyz_twap_twaccestok_secret" class="al2tw_text"
						name="xyz_twap_twaccestok_secret" type="text"
						value="<?php if($tms5=="") {echo esc_attr(get_option('xyz_twap_twaccestok_secret'));}?>" />
					</td>
				</tr>
				<tr valign="top">
					<td><?php esc_html_e('Message format for posting','twitter-auto-publish');?> <img src="<?php echo $heimg?>"
						onmouseover="detdisplay_twap('xyz_tw')" onmouseout="dethide_twap('xyz_tw')" style="width:13px;height:auto;">
						<div id="xyz_tw" class="xyz_twap_informationdiv"
							style="display: none; font-weight: normal;">
							{POST_TITLE} - <?php esc_html_e('Insert the title of your post.','twitter-auto-publish'); ?><br/>
							{PERMALINK} - <?php esc_html_e('Insert the URL where your post is displayed.','twitter-auto-publish'); ?><br/>
							{POST_EXCERPT} - <?php esc_html_e('Insert the excerpt of your post.','twitter-auto-publish'); ?><br/>
							{POST_CONTENT} - <?php esc_html_e('Insert the description of your post.','twitter-auto-publish'); ?><br/>
							{BLOG_TITLE} - <?php esc_html_e('Insert the name of your blog.','twitter-auto-publish'); ?><br/>
							{USER_NICENAME} - <?php esc_html_e('Insert the nicename of the author.','twitter-auto-publish'); ?><br/>
							{POST_ID} - <?php esc_html_e('Insert the ID of your post.','twitter-auto-publish'); ?><br/>
							{POST_PUBLISH_DATE} - <?php esc_html_e('Insert the publish date of your post.','twitter-auto-publish'); ?><br/>
							{USER_DISPLAY_NAME} - <?php esc_html_e('Insert the display name of the author.','twitter-auto-publish'); ?>
						</div></td>
	<td>
	<select name="xyz_twap_info" id="xyz_twap_info" onchange="xyz_twap_info_insert(this)">
		<option value ="0" selected="selected"> --<?php esc_html_e('Select','twitter-auto-publish'); ?>-- </option>
		<option value ="1">{POST_TITLE} </option>
		<option value ="2">{PERMALINK} </option>
		<option value ="3">{POST_EXCERPT} </option>
		<option value ="4">{POST_CONTENT} </option>
		<option value ="5">{BLOG_TITLE} </option>
		<option value ="6">{USER_NICENAME} </option>
		<option value ="7">{POST_ID} </option>
		<option value ="8">{POST_PUBLISH_DATE} </option>
		<option value ="9">{USER_DISPLAY_NAME} </option>
		</select> </td></tr><tr><td>&nbsp;</td><td>
		<textarea id="xyz_twap_twmessage"  name="xyz_twap_twmessage"><?php if($tms6=="") {
								echo esc_textarea(get_option('xyz_twap_twmessage'));}?></textarea>
	</td></tr>
						
				
				<tr valign="top">
					<td><?php esc_html_e('Attach image to twitter post','twitter-auto-publish');?>
					</td>
					<td  class="xyz_twap_switch_field">
						<label id="xyz_twap_twpost_image_permission_yes"><input type="radio" name="xyz_twap_twpost_image_permission" value="1" <?php  if(get_option('xyz_twap_twpost_image_permission')==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
						<label id="xyz_twap_twpost_image_permission_no"><input type="radio" name="xyz_twap_twpost_image_permission" value="0" <?php  if(get_option('xyz_twap_twpost_image_permission')==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
					</td>
				</tr>
				
				<tr valign="top">
					<td><?php esc_html_e('Twitter character limit','twitter-auto-publish');?>  <img src="<?php echo $heimg?>"
							onmouseover="detdisplay_twap('xyz_twap_tw_char_limit')" onmouseout="dethide_twap('xyz_twap_tw_char_limit')" style="width:13px;height:auto;">
							<div id="xyz_twap_tw_char_limit" class="xyz_twap_informationdiv" style="display: none;">
							<?php esc_html_e('The character limit of tweets is 280.','twitter-auto-publish'); ?><br/> 
							<?php esc_html_e('Use 140 for languages like Chinese, Japanese and Korean <br/> which won'."'".'t get the 280 character limit.','twitter-auto-publish'); ?><br />
							</div></td>
				<td>
					<input id="xyz_twap_tw_char_limit"  name="xyz_twap_tw_char_limit" type="text" value="<?php echo esc_attr(get_option('xyz_twap_tw_char_limit'));?>" style="width: 155px">
				</td></tr>
				
				<tr>
			<td   id="bottomBorderNone"></td>
					<td   id="bottomBorderNone"><div style="height: 50px;">
							<input type="submit" class="xyz_twap_submit_new"
								style=" margin-top: 10px; "
								name="twit" value=<?php esc_html_e('Save','twitter-auto-publish');?> /></div>
					</td>
				</tr>
			</table>

	</form>
</div>
<?php 

	if(isset($_POST['bsettngs']))
	{
		if (! isset( $_REQUEST['_wpnonce'] )|| ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'xyz_smap_tw_basic_settings_form_nonce' ))
		{
			wp_nonce_ays( 'xyz_smap_tw_basic_settings_form_nonce' );
			exit();
		}

		$xyz_twap_include_pages=intval($_POST['xyz_twap_include_pages']);
		$xyz_twap_include_posts=intval($_POST['xyz_twap_include_posts']);
		$xyz_smap_free_enforce_twitter_cards=intval($_POST['xyz_smap_free_enforce_twitter_cards']);
		$twap_category_ids='';
		if($_POST['xyz_twap_cat_all']=="All")
			$twap_category_ids=$_POST['xyz_twap_cat_all'];//redio btn name
		else
		{
		    if(!empty($_POST['xyz_twap_catlist']))
		    {
			$twap_category_ids=$_POST['xyz_twap_catlist'];//dropdown
			$twap_category_ids=implode(',', $twap_category_ids);
		    }
		}
		$xyz_customtypes="";
		
        if(isset($_POST['post_types']))
		$xyz_customtypes=$_POST['post_types'];

        $xyz_twap_peer_verification=intval($_POST['xyz_twap_peer_verification']);
        $xyz_twap_premium_version_ads=intval($_POST['xyz_twap_premium_version_ads']);
        $xyz_twap_default_selection_edit=intval($_POST['xyz_twap_default_selection_edit']);
        $xyz_twap_default_selection_create=intval($_POST['xyz_twap_default_selection_create']);
        
        //$xyz_twap_future_to_publish=$_POST['xyz_twap_future_to_publish'];
		$twap_customtype_ids="";

		$xyz_twap_applyfilters="";
		if(isset($_POST['xyz_twap_applyfilters']))
			$xyz_twap_applyfilters=$_POST['xyz_twap_applyfilters'];
		
		
		
		if($xyz_customtypes!="")
		{
			for($i=0;$i<count($xyz_customtypes);$i++)
			{
				$twap_customtype_ids.=$xyz_customtypes[$i].",";
			}

		}
		$twap_customtype_ids=rtrim($twap_customtype_ids,',');

		$xyz_twap_applyfilters_val="";
		if($xyz_twap_applyfilters!="")
		{
			for($i=0;$i<count($xyz_twap_applyfilters);$i++)
			{
			$xyz_twap_applyfilters_val.=$xyz_twap_applyfilters[$i].",";
		}
		}
		$xyz_twap_applyfilters_val=rtrim($xyz_twap_applyfilters_val,',');
		
		update_option('xyz_twap_apply_filters',$xyz_twap_applyfilters_val);
		update_option('xyz_twap_include_pages',$xyz_twap_include_pages);
		update_option('xyz_twap_include_posts',$xyz_twap_include_posts);
		if($xyz_twap_include_posts==0)
			update_option('xyz_twap_include_categories',"All");
		else
			update_option('xyz_twap_include_categories',$twap_category_ids);
		update_option('xyz_twap_include_customposttypes',$twap_customtype_ids);
		update_option('xyz_twap_peer_verification',$xyz_twap_peer_verification);
		update_option('xyz_twap_premium_version_ads',$xyz_twap_premium_version_ads);
		update_option('xyz_twap_default_selection_edit',$xyz_twap_default_selection_edit);
		update_option('xyz_twap_default_selection_create',$xyz_twap_default_selection_create);
		update_option('xyz_smap_free_enforce_twitter_cards',$xyz_smap_free_enforce_twitter_cards);
		//update_option('xyz_twap_future_to_publish',$xyz_twap_future_to_publish);
	}
	//$xyz_twap_future_to_publish=get_option('xyz_twap_future_to_publish');
	$xyz_credit_link=get_option('xyz_credit_link');
	$xyz_twap_include_pages=get_option('xyz_twap_include_pages');
	$xyz_twap_include_posts=get_option('xyz_twap_include_posts');
	$xyz_twap_include_categories=get_option('xyz_twap_include_categories');
	/*if ($xyz_twap_include_categories!='All')
	$xyz_twap_include_categories=explode(',', $xyz_twap_include_categories);*/
	$xyz_twap_include_customposttypes=get_option('xyz_twap_include_customposttypes');
	$xyz_twap_apply_filters=get_option('xyz_twap_apply_filters');
	$xyz_twap_peer_verification=esc_html(get_option('xyz_twap_peer_verification'));
	$xyz_twap_premium_version_ads=esc_html(get_option('xyz_twap_premium_version_ads'));
	$xyz_twap_default_selection_edit=esc_html(get_option('xyz_twap_default_selection_edit'));
	$xyz_twap_default_selection_create=esc_html(get_option('xyz_twap_default_selection_create'));
	$xyz_smap_free_enforce_twitter_cards=get_option('xyz_smap_free_enforce_twitter_cards');
	?>
	
		<div id="xyz_twap_basic_settings" class="xyz_twap_tabcontent">
		<form method="post">
<?php wp_nonce_field( 'xyz_smap_tw_basic_settings_form_nonce' );?>
			<table class="widefat xyz_twap_widefat_table" style="width: 99%">
			<tr><td><h2> <?php esc_html_e('Basic Settings','twitter-auto-publish'); ?></h2></td></tr>
				<tr valign="top">

					<td  colspan="1" width="50%"> <?php esc_html_e('Publish wordpress `pages` to twitter','twitter-auto-publish'); ?>
					</td>
			<td  class="xyz_twap_switch_field">
				<label id="xyz_twap_include_pages_yes"><input type="radio" name="xyz_twap_include_pages" value="1" <?php  if($xyz_twap_include_pages==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
				<label id="xyz_twap_include_pages_no"><input type="radio" name="xyz_twap_include_pages" value="0" <?php  if($xyz_twap_include_pages==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
			</td>
				</tr>
				
				<tr valign="top">

					<td  colspan="1"><?php esc_html_e('Publish wordpress `posts` to twitter','twitter-auto-publish'); ?>
					</td>
			<td  class="xyz_twap_switch_field">
				<label id="xyz_twap_include_posts_yes"><input type="radio" name="xyz_twap_include_posts" value="1" <?php  if($xyz_twap_include_posts==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
				<label id="xyz_twap_include_posts_no"><input type="radio" name="xyz_twap_include_posts" value="0" <?php  if($xyz_twap_include_posts==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
			</td>
				</tr>
				<?php 
				$xyz_twap_hide_custompost_settings='';
					$args=array(
							'public'   => true,
							'_builtin' => false
					);
					$output = 'names'; // names or objects, note names is the default
					$operator = 'and'; // 'and' or 'or'
					$post_types=get_post_types($args,$output,$operator);

					$ar1=explode(",",$xyz_twap_include_customposttypes);
					$cnt=count($post_types);
					if($cnt==0)
					$xyz_twap_hide_custompost_settings = 'style="display: none;"';//echo 'NA';
					?>
				<tr valign="top" <?php echo $xyz_twap_hide_custompost_settings;?>>

					<td  colspan="1"> <?php esc_html_e('Select wordpress custom post types for auto publish','twitter-auto-publish'); ?></td>
					<td><?php 
					foreach ($post_types  as $post_type ) {
					
						echo '<input type="checkbox" name="post_types[]" value="'.$post_type.'" ';
						if(in_array($post_type, $ar1))
						{
							echo 'checked="checked"/>';
						}
						else
							echo '/>';
					
							echo $post_type.'<br/>';
					
					}
					?>
					</td>
				</tr>
				
				
				<tr><td><h2> <?php esc_html_e('Advanced Settings','twitter-auto-publish'); ?></h2></td></tr>
				
				<tr valign="top" id="selPostCat">

					<td  colspan="1"> <?php esc_html_e('Select post categories for auto publish','twitter-auto-publish'); ?>
					</td>
					<td class="xyz_twap_switch_field">
	                <input type="hidden" value="<?php echo esc_html($xyz_twap_include_categories);?>" name="xyz_twap_sel_cat" 
			id="xyz_twap_sel_cat"> 
					<label id="xyz_twap_include_categories_no">
					<input type="radio"	name="xyz_twap_cat_all" id="xyz_twap_cat_all" value="All" onchange="rd_cat_chn(1,-1)" <?php if($xyz_twap_include_categories=="All") echo "checked"?>> <?php esc_html_e('All','twitter-auto-publish'); ?><font style="padding-left: 10px;"></font></label>
					<label id="xyz_twap_include_categories_yes">
					<input type="radio"	name="xyz_twap_cat_all" id="xyz_twap_cat_all" value=""	onchange="rd_cat_chn(1,1)" <?php if($xyz_twap_include_categories!="All") echo "checked"?>> <?php esc_html_e('Specific','twitter-auto-publish'); ?></label>
					<br /> <br /> <div class="xyz_twap_scroll_checkbox"  id="cat_dropdown_span">
					<?php 
					$args = array(
							'show_option_all'    => '',
							'show_option_none'   => '',
							'orderby'            => 'name',
							'order'              => 'ASC',
							'show_last_update'   => 0,
							'show_count'         => 0,
							'hide_empty'         => 0,
							'child_of'           => 0,
							'exclude'            => '',
							'echo'               => 0,
							'selected'           => '1 3',
							'hierarchical'       => 1,
							'id'                 => 'xyz_twap_catlist',
							'class'              => 'postform',
							'depth'              => 0,
							'tab_index'          => 0,
							'taxonomy'           => 'category');

					if(count(get_categories($args))>0)
					{
					    $xyz_twap_include_categories=explode(',', $xyz_twap_include_categories);
						$twap_categories=get_categories($args);
						foreach ($twap_categories as $twap_cat)
						{
							$cat_id[]=$twap_cat->cat_ID;
							$cat_name[]=$twap_cat->cat_name;
							?>
							<input type="checkbox" name="xyz_twap_catlist[]"  value="<?php  echo $twap_cat->cat_ID;?>" <?php if(is_array($xyz_twap_include_categories)) if(in_array($twap_cat->cat_ID, $xyz_twap_include_categories)) echo "checked" ?>/><?php echo $twap_cat->cat_name; ?>
							<br/><?php }
					}
					else
					esc_html_e('NIL','twitter-auto-publish');
					?><br /> <br /> </div>
				</td>
				</tr>
				<tr valign="top">
	    <td> <?php esc_html_e('Add twitter cards while posting to twitter','twitter-auto-publish'); ?> <img src="<?php echo $heimg?>"
							onmouseover="detdisplay_twap('xyz_smap_free_enforce_card')" onmouseout="dethide_twap('xyz_smap_free_enforce_card')" style="width:13px;height:auto;">
							<div id="xyz_smap_free_enforce_card" class="xyz_twap_informationdiv" style="display: none;">* <?php esc_html_e('By crawling twitter card specific meta tags, twitter can generate a summarised preview of the tweeted link.','twitter-auto-publish'); ?><br/> * <?php esc_html_e('To generate tweet preview of post,set <b>Attach media to twitter post</b> as <b>No</b>','twitter-auto-publish'); ?> </div></td>
		<td  class="xyz_twap_switch_field">
			<label id="xyz_smap_free_enforce_twitter_cards_yes"><input type="radio" name="xyz_smap_free_enforce_twitter_cards" value="1" <?php  if($xyz_smap_free_enforce_twitter_cards==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
			<label id="xyz_smap_free_enforce_twitter_cards_no"><input type="radio" name="xyz_smap_free_enforce_twitter_cards" value="0" <?php  if($xyz_smap_free_enforce_twitter_cards==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
		</td>
	 </tr>
				<tr valign="top">

					<td scope="row" colspan="1" width="50%"> <?php esc_html_e('Auto publish on creating posts/pages/custom post types','twitter-auto-publish'); ?>
					</td>
				<td>
					<input type="radio" name="xyz_twap_default_selection_create" value="1" <?php  if($xyz_twap_default_selection_create==1) echo 'checked';?>/> <?php esc_html_e('Enabled','twitter-auto-publish'); ?>
					<br/><input type="radio" name="xyz_twap_default_selection_create" value="0" <?php  if($xyz_twap_default_selection_create==0) echo 'checked';?>/> <?php esc_html_e('Disabled','twitter-auto-publish'); ?>
					<br/><input type="radio" name="xyz_twap_default_selection_create" value="2" <?php  if($xyz_twap_default_selection_create==2) echo 'checked';?>/> <?php esc_html_e('Use metabox settings','twitter-auto-publish'); ?>
				</td>
				</tr>
				<tr valign="top">

					<td scope="row" colspan="1" width="50%"> <?php esc_html_e('Auto publish on editing posts/pages/custom post types','twitter-auto-publish'); ?>
					</td>
				<td>
					<input type="radio" name="xyz_twap_default_selection_edit" value="1" <?php  if($xyz_twap_default_selection_edit==1) echo 'checked';?>/> <?php esc_html_e('Enabled','twitter-auto-publish'); ?>
					<br/><input type="radio" name="xyz_twap_default_selection_edit" value="0" <?php  if($xyz_twap_default_selection_edit==0) echo 'checked';?>/> <?php esc_html_e('Disabled','twitter-auto-publish'); ?>
					<br/><input type="radio" name="xyz_twap_default_selection_edit" value="2" <?php  if($xyz_twap_default_selection_edit==2) echo 'checked';?>/> <?php esc_html_e('Use metabox settings','twitter-auto-publish'); ?>
				</td>
				</tr>
				<tr valign="top">
				
				<td scope="row" colspan="1" width="50%"> <?php esc_html_e('Enable SSL peer verification in remote requests','twitter-auto-publish'); ?> </td>
				<td  class="xyz_twap_switch_field">
					<label id="xyz_twap_peer_verification_yes"><input type="radio" name="xyz_twap_peer_verification" value="1" <?php  if($xyz_twap_peer_verification==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
					<label id="xyz_twap_peer_verification_no"><input type="radio" name="xyz_twap_peer_verification" value="0" <?php  if($xyz_twap_peer_verification==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
				</td>
				</tr>
				
				<tr valign="top">
					<td scope="row" colspan="1">  <?php esc_html_e('Apply filters during publishing','twitter-auto-publish'); ?> </td>
					<td>
					<?php 
					$ar2=explode(",",$xyz_twap_apply_filters);
					for ($i=0;$i<3;$i++ ) {
						$filVal=$i+1;
						
						if($filVal==1)
							$filName='the_content';
						else if($filVal==2)
							$filName='the_excerpt';
						else if($filVal==3)
							$filName='the_title';
						else $filName='';
						
						echo '<input type="checkbox" name="xyz_twap_applyfilters[]"  value="'.$filVal.'" ';
						if(in_array($filVal, $ar2))
						{
							echo 'checked="checked"/>';
						}
						else
							echo '/>';
					
						echo '<label>'.$filName.'</label><br/>';
					
					}
					
					?>
					</td>
				</tr>
<tr><td><h2> <?php esc_html_e('Other Settings','twitter-auto-publish'); ?></h2></td></tr>

				<tr valign="top">

					<td  colspan="1">  <?php esc_html_e('Enable credit link to author','twitter-auto-publish'); ?>
					</td>
					<td  class="xyz_twap_switch_field">
						<label id="xyz_credit_link_yes"><input type="radio" name="xyz_credit_link" value="twap" <?php  if($xyz_credit_link=='twap') echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
						<label id="xyz_credit_link_no"><input type="radio" name="xyz_credit_link" value="<?php echo $xyz_credit_link!='twap'?$xyz_credit_link:0;?>" <?php  if($xyz_credit_link!='twap') echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
					</td>
				</tr>

				
				

				<tr valign="top">

					<td  colspan="1"> <?php esc_html_e('Enable premium version ads','twitter-auto-publish'); ?>
					</td>
					<td  class="xyz_twap_switch_field">
						<label id="xyz_twap_premium_version_ads_yes"><input type="radio" name="xyz_twap_premium_version_ads" value="1" <?php  if($xyz_twap_premium_version_ads==1) echo 'checked';?>/> <?php esc_html_e('Yes','twitter-auto-publish'); ?> </label>
						<label id="xyz_twap_premium_version_ads_no"><input type="radio" name="xyz_twap_premium_version_ads" value="0" <?php  if($xyz_twap_premium_version_ads==0) echo 'checked';?>/> <?php esc_html_e('No','twitter-auto-publish'); ?> </label>
					</td>
				</tr>

				
				<tr>

					<td id="bottomBorderNone">
							

					</td>

					
<td id="bottomBorderNone"><div style="height: 50px;">
<input type="submit" class="xyz_twap_submit_new" style="margin-top: 10px;"	value="<?php esc_html_e('Update Settings','twitter-auto-publish'); ?>"  name="bsettngs" /></div></td>
				</tr>


			</table>
		</form>
		</div>
		
</div>	
	
<?php if (is_array($xyz_twap_include_categories))
$xyz_twap_include_categories1=implode(',', $xyz_twap_include_categories);
else 
	$xyz_twap_include_categories1=$xyz_twap_include_categories;
	?>
	<script type="text/javascript">
	//drpdisplay();
var catval='<?php echo esc_html($xyz_twap_include_categories1); ?>';
var custtypeval='<?php echo esc_html($xyz_twap_include_customposttypes); ?>';
var get_opt_cats='<?php echo esc_html(get_option('xyz_twap_include_posts'));?>';
jQuery(document).ready(function() {
	<?php  if(isset($_POST['bsettngs'])) {?>
					document.getElementById("xyz_twap_basic_tab_settings").click();	
					<?php }
					else {?>
					document.getElementById("xyz_twap_default_tab_settings").click();
					<?php }?>

	
	  if(catval=="All")
		  jQuery("#cat_dropdown_span").hide();
	  else
		  jQuery("#cat_dropdown_span").show();

	  if(get_opt_cats==0)
		  jQuery('#selPostCat').hide();
	  else
		  jQuery('#selPostCat').show();
   var xyz_credit_link=jQuery("input[name='xyz_credit_link']:checked").val();
   if(xyz_credit_link=='twap')
	   xyz_credit_link=1;
   else
	   xyz_credit_link=0;
   XyzTwapToggleRadio(xyz_credit_link,'xyz_credit_link');
   
   var xyz_twap_cat_all=jQuery("input[name='xyz_twap_cat_all']:checked").val();
   if (xyz_twap_cat_all == 'All') 
	   xyz_twap_cat_all=0;
   else 
	   xyz_twap_cat_all=1;
   XyzTwapToggleRadio(xyz_twap_cat_all,'xyz_twap_include_categories'); 
  

   var toggle_element_ids=['xyz_twap_twpost_image_permission','xyz_smap_free_enforce_twitter_cards','xyz_twap_twpost_permission','xyz_twap_include_pages','xyz_twap_include_posts','xyz_twap_peer_verification','xyz_twap_premium_version_ads'];

   jQuery.each(toggle_element_ids, function( index, value ) {
		   checkedval= jQuery("input[name='"+value+"']:checked").val();
		   XyzTwapToggleRadio(checkedval,value); 
   	});
   var xyz_twap_app_sel_mode=jQuery("input[name='xyz_twap_app_sel_mode']:checked").val();
   if(xyz_twap_app_sel_mode ==1){
		jQuery('.xyz_twap_twitter_traditional_settings').hide();
		jQuery('.xyz_twap_twitter_oauth2_settings').hide();
		jQuery('#xyz_twap_app_creation_note').hide();
		jQuery('.xyz_twap_twitter_auth').show();
   }
   else if(xyz_twap_app_sel_mode ==0){
	   	jQuery('.xyz_twap_twitter_traditional_settings').show();
		jQuery('.xyz_twap_twitter_oauth2_settings').hide();
	   	jQuery('#xyz_twap_app_creation_note').show();
	   	jQuery('.xyz_twap_twitter_auth').hide();
	   		}
	else{
		jQuery('.xyz_twap_twitter_oauth2_settings').show();
		jQuery('.xyz_twap_twitter_traditional_settings').hide();
	}		
   jQuery("input[name='xyz_twap_app_sel_mode']").click(function(){
	   var xyz_twap_app_sel_mode=jQuery("input[name='xyz_twap_app_sel_mode']:checked").val();
	   if(xyz_twap_app_sel_mode ==1){
		    jQuery('#xyz_twap_app_creation_note').hide();
	jQuery('.xyz_twap_twitter_traditional_settings').hide();
		jQuery('.xyz_twap_twitter_oauth2_settings').hide();
			jQuery('.xyz_twap_twitter_auth').show();
			}
		   else if(xyz_twap_app_sel_mode ==0){
			jQuery('.xyz_twap_twitter_traditional_settings').show();
			jQuery('.xyz_twap_twitter_oauth2_settings').hide();
			jQuery('#xyz_twap_app_creation_note').show(); 
		   	jQuery('.xyz_twap_twitter_auth').hide();
		   	}
			else{
				jQuery('.xyz_twap_twitter_oauth2_settings').show();
				jQuery('.xyz_twap_twitter_traditional_settings').hide();
		   	}
	   });

	}); 
	
function setcat(obj)
{
var sel_str="";
for(k=0;k<obj.options.length;k++)
{
if(obj.options[k].selected)
sel_str+=obj.options[k].value+",";
}


var l = sel_str.length; 
var lastChar = sel_str.substring(l-1, l); 
if (lastChar == ",") { 
	sel_str = sel_str.substring(0, l-1);
}

document.getElementById('xyz_twap_sel_cat').value=sel_str;

}

//var d1='<?php // echo esc_html($xyz_twap_include_categories1);?>';
// splitText = d1.split(",");
// jQuery.each(splitText, function(k,v) {
// jQuery("#xyz_twap_catlist").children("option[value="+v+"]").attr("selected","selected");
// });

function rd_cat_chn(val,act)
{
	if(val==1)
	{
		if(act==-1)
		  jQuery("#cat_dropdown_span").hide();
		else
		  jQuery("#cat_dropdown_span").show();
	}
	
}

function xyz_twap_info_insert(inf){
	
    var e = document.getElementById("xyz_twap_info");
    var ins_opt = e.options[e.selectedIndex].text;
    if(ins_opt=="0")
    	ins_opt="";
    var str=jQuery("textarea#xyz_twap_twmessage").val()+ins_opt;
    jQuery("textarea#xyz_twap_twmessage").val(str);
    jQuery('#xyz_twap_info :eq(0)').prop('selected', true);
    jQuery("textarea#xyz_twap_twmessage").focus();

}
function xyz_twap_show_postCategory(val)
{
	if(val==0)
		jQuery('#selPostCat').hide();
	else
		jQuery('#selPostCat').show();
}
var toggle_element_ids=['xyz_twap_twpost_image_permission','xyz_smap_free_enforce_twitter_cards','xyz_twap_twpost_permission','xyz_twap_include_pages','xyz_twap_include_posts','xyz_twap_peer_verification','xyz_credit_link','xyz_twap_premium_version_ads','xyz_twap_include_categories'];

jQuery.each(toggle_element_ids, function( index, value ) {
	jQuery("#"+value+"_no").click(function(){
		XyzTwapToggleRadio(0,value);
		if(value=='xyz_twap_include_posts')
			xyz_twap_show_postCategory(0);
	});
	jQuery("#"+value+"_yes").click(function(){
		XyzTwapToggleRadio(1,value);
		if(value=='xyz_twap_include_posts')
			xyz_twap_show_postCategory(1);
	});
	});
function xyz_twap_open_tab(evt, xyz_twap_form_div_id) {
    var i, xyz_twap_tabcontent, xyz_twap_tablinks;
    tabcontent = document.getElementsByClassName("xyz_twap_tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("xyz_twap_tablinks");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(xyz_twap_form_div_id).style.display = "block";
    evt.currentTarget.className += " active";
}

</script>
