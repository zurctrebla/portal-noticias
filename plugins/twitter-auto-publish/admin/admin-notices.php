<?php
if( !defined('ABSPATH') ){ exit();}
function wp_twap_admin_notice()
{
	add_thickbox();
	$sharelink_text_array_tw = array
						(
						"I Use WP Twitter Auto Publish  wordpress plugin from @xyzscripts and you should too",
						"WP Twitter Auto Publish  wordpress Plugin from @xyzscripts is awesome",
						"Thanks @xyzscripts for developing such a wonderful Twitter auto publishing wordpress plugin",
						"I was looking for a Twitter publishing plugin like this. Thanks @xyzscripts",
						"Its very easy to use WP Twitter Auto Publish  wordpress Plugin from @xyzscripts",
						"I installed WP Twitter Auto Publish from @xyzscripts, it works flawlessly",
						"The WP Twitter Auto Publish wordpress plugin that i use works terrific", 
						"I am using WP Twittter Auto Publish wordpress plugin from @xyzscripts and I like it",
						"The WP Twitter Auto Publish plugin from @xyzscripts is simple and works fine",
						"I've been using this Twitter plugin for a while now and it is really good",
						"WP Twitter Auto Publish wordpress plugin is a fantastic plugin",
						"WP Twitter Auto Publish wordpress plugin is easy to use and works great. Thank you!",
						"Good and flexible WP Twitter Auto publish plugin especially for beginners",
						"The best Twittter auto publish wordpress plugin I have used ! THANKS @xyzscripts",
						);
$sharelink_text_tw = array_rand($sharelink_text_array_tw, 1);
$sharelink_text_tw = $sharelink_text_array_tw[$sharelink_text_tw];
$xyz_twap_link = admin_url('admin.php?page=twitter-auto-publish-settings&twap_blink=en');
$xyz_twap_link = wp_nonce_url($xyz_twap_link,'twap-blk');
$xyz_twap_notice = admin_url('admin.php?page=twitter-auto-publish-settings&twap_notice=hide');
$xyz_twap_notice = wp_nonce_url($xyz_twap_notice,'twap-shw');
	echo '
	<script type="text/javascript">
			function xyz_twap_shareon_tckbox(){
			tb_show("Share on","#TB_inline?width=500&amp;height=75&amp;inlineId=show_share_icons_tw&class=thickbox");
		}
	</script>
	<div id="twap_notice_td" class="error" style="color: #666666;margin-left: 2px; padding: 5px;line-height:16px;">' ?>
	<p><?php
	   $twap_url="https://wordpress.org/plugins/twitter-auto-publish/";
	   $twap_xyz_url="https://xyzscripts.com/";
	   $twap_wp="WP Twitter Auto Publish";
	   $twap_xyz_com="xyzscripts.com";
	   $twap_thanks_msg=sprintf( __('Thank you for using <a href="%s" target="_blank"> %s </a> plugin from <a href="%s" target="_blank"> %s </a>. Would you consider supporting us with the continued development of the plugin using any of the below methods?','twitter-auto-publish'),$twap_url,$twap_wp,$twap_xyz_url,$twap_xyz_com); 
	   echo $twap_thanks_msg; ?></p>
	   
	<p>
	<a href="https://wordpress.org/support/plugin/twitter-auto-publish/reviews" class="button xyz_twap_rate_btn" target="_blank"> <?php _e('Rate it 5★\'s on wordpress','twitter-auto-publish'); ?></a>
	<?php if(get_option('xyz_credit_link')=="0") ?>
	    <a href="<?php echo $xyz_twap_link; ?>"class="button xyz_twap_backlink_btn xyz_blink"> <?php _e('Enable Backlink','twitter-auto-publish'); ?> </a>
	    
	<a class="button xyz_twap_share_btn" onclick=xyz_twap_shareon_tckbox();> <?php _e('Share on','twitter-auto-publish'); ?> </a>
		<a href="https://xyzscripts.com/donate/5" class="button xyz_twap_donate_btn" target="_blank"> <?php _e('Donate','twitter-auto-publish'); ?> </a>
	
	<a href="<?php echo $xyz_twap_notice; ?>" class="button xyz_twap_show_btn"> <?php _e('Don\'t Show This Again','twitter-auto-publish'); ?></a>
	</p>
	
	<div id="show_share_icons_tw" style="display: none;">
	<a class="button" style="background-color:#3b5998;color:white;margin-right:4px;margin-left:100px;margin-top: 25px;" href="http://www.facebook.com/sharer/sharer.php?u=https://xyzscripts.com/wordpress-plugins/twitter-auto-publish/" target="_blank"> <?php _e('Facebook','twitter-auto-publish'); ?> </a>
	<a class="button" style="background-color:#00aced;color:white;margin-right:4px;margin-left:20px;margin-top: 25px;" href="http://x.com/share?url=https://xyzscripts.com/wordpress-plugins/twitter-auto-publish/&text='.$sharelink_text_tw.'" target="_blank"> <?php _e('Twitter','twitter-auto-publish'); ?> </a>
	<a class="button" style="background-color:#007bb6;color:white;margin-right:4px;margin-left:20px;margin-top: 25px;" href="http://www.linkedin.com/shareArticle?mini=true&url=https://xyzscripts.com/wordpress-plugins/twitter-auto-publish/" target="_blank"> <?php _e('LinkedIn','twitter-auto-publish'); ?> </a>
	</div>
	<?php echo '</div>';
	
}
$twap_installed_date = get_option('twap_installed_date');
if ($twap_installed_date=="") 
{
	$twap_installed_date = time();
}
if($twap_installed_date < ( time() - (30*24*60*60) ))
{
	if (get_option('xyz_twap_dnt_shw_notice') != "hide")
	{
		add_action('admin_notices', 'wp_twap_admin_notice');
	}
}
