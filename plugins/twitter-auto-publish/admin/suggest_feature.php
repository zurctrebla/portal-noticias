<?php
if( !defined('ABSPATH') ){ exit();}
global $wpdb;
$xyz_twap_message='';
if(isset($_GET['msg']))
	$xyz_twap_message = $_GET['msg'];
if($xyz_twap_message == 1){
	?> 
	<div class="xyz_twap_system_notice_area_style1" id="xyz_twap_system_notice_area">
	<?php _e('Thank you for the suggestion.','twitter-auto-publish'); ?> &nbsp;&nbsp;&nbsp;<span
	id="xyz_twap_system_notice_area_dismiss"> <?php _e('Dismiss','twitter-auto-publish'); ?> </span>
	</div>
	<?php
	}
else if($xyz_twap_message == 2){
		?>
		<div class="xyz_twap_system_notice_area_style0" id="xyz_twap_system_notice_area">
		<?php $twap_wp_mail="wp_mail"; $twap_wp_mail_msg=sprintf(__('%s not able to process the request.','twitter-auto-publish'),$twap_wp_mail); echo $twap_wp_mail_msg; ?> &nbsp;&nbsp;&nbsp;<span
		id="xyz_twap_system_notice_area_dismiss"><?php _e('Dismiss','twitter-auto-publish'); ?> </span>
		</div>
		<?php
	}
else if($xyz_twap_message == 3){
	?>
	<div class="xyz_twap_system_notice_area_style0" id="xyz_twap_system_notice_area">
	<?php _e('Please suggest a feature','twitter-auto-publish'); ?>&nbsp;&nbsp;&nbsp;<span
	id="xyz_twap_system_notice_area_dismiss"> <?php _e('Dismiss','twitter-auto-publish'); ?></span>
	</div>
	<?php
}
if (isset($_POST) && isset($_POST['xyz_send_mail']))
{
	if (! isset( $_REQUEST['_wpnonce'] )|| ! wp_verify_nonce( $_REQUEST['_wpnonce'], 'xyz_twap_suggest_feature_form_nonce' ))
	{
		wp_nonce_ays( 'xyz_twap_suggest_feature_form_nonce' );
		exit();
	}
	if (isset($_POST['xyz_twap_suggested_feature']) && $_POST['xyz_twap_suggested_feature']!='')
	{
		$xyz_twap_feature_content=$_POST['xyz_twap_suggested_feature'];
		$xyz_twap_sender_email = get_option('admin_email');
		$entries0 = $wpdb->get_results( $wpdb->prepare( 'SELECT display_name FROM '.$wpdb->base_prefix.'users WHERE user_email=%s',array($xyz_twap_sender_email)));
		foreach( $entries0 as $entry ) {
			$xyz_twap_admin_username=$entry->display_name;
		}
		$xyz_twap_recv_email='support@xyzscripts.com';
		$xyz_twap_mail_subject="WP TWITTER AUTO PUBLISH - FEATURE SUGGESTION";
		$xyz_twap_headers = array('From: '.$xyz_twap_admin_username.' <'. $xyz_twap_sender_email .'>' ,'Content-Type: text/html; charset=UTF-8');
		$wp_mail_processed=wp_mail( $xyz_twap_recv_email, $xyz_twap_mail_subject, $xyz_twap_feature_content, $xyz_twap_headers );
		if ($wp_mail_processed==true){
		wp_safe_redirect( admin_url( 'admin.php?page=twitter-auto-publish-suggest-features&msg=1' ) );
		exit;
		}
		else {
			wp_safe_redirect( admin_url( 'admin.php?page=twitter-auto-publish-suggest-features&msg=2' ) );
			exit;
		}
	}
	else {
		wp_safe_redirect( admin_url( 'admin.php?page=twitter-auto-publish-suggest-features&msg=3' ) );
		exit;
	}
}?>
<form method="post" >
<?php wp_nonce_field( 'xyz_twap_suggest_feature_form_nonce' );?>
<h3><?php _e('Contribute And Get Rewarded','twitter-auto-publish'); ?></h3>
<span style="color: #1A87B9;font-size:13px;padding-left: 10px;" >*<?php _e('Suggest a feature for this plugin and stand a chance to get a free copy of premium version of this plugin.','twitter-auto-publish'); ?> </span>
<table  class="widefat xyz_twap_widefat_table" style="width:98%;padding-top: 10px;">
<tr><td>
<textarea name="xyz_twap_suggested_feature" id="xyz_twap_suggested_feature" style="width:620px;height:250px !important;"></textarea>
</td></tr>
<tr>
<td><input name="xyz_send_mail" class="xyz_twap_submit_new" style="color:#FFFFFF;border-radius:4px; margin-bottom:10px;" type="submit" value="<?php _e('Send Mail To Us','twitter-auto-publish'); ?>" >
</td></tr>
</table>
</form>