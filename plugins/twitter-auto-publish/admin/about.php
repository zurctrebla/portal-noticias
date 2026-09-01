<?php
if( !defined('ABSPATH') ){ exit();}
?>
<h1 style="visibility: visible;">WP Twitter Auto Publish (V <?php echo xyz_twap_plugin_get_version(); ?>)</h1>

<div style="width: 99%">
<p style="text-align: justify">
<?php $wp_twap="WP Twitter Auto Publish";
$twap_pub_msg=sprintf( __('%s automatically publishes posts from your blog to your Twitter pages. It allows you to filter posts based on post-types and categories. %s is developed and maintained by','twitter-auto-publish'),$wp_twap,$wp_twap);
      echo $twap_pub_msg; ?> <a href="http://xyzscripts.com">XYZScripts</a>.</p>



<p style="text-align: justify">
	<?php $twap_smap_url="https://xyzscripts.com/wordpress-plugins/social-media-auto-publish/features";
	$twap_smap_plugin = "XYZ Social Media Auto Publish";
	$twap_feature_msg=sprintf( __('If you would like to have more features , please try <a href="%s" target="_blank">%s</a> which is a premium version of this plugin. We have included a quick comparison of the free and premium plugins for your reference.','twitter-auto-publish'),$twap_smap_url,$twap_smap_plugin); 
	echo $twap_feature_msg; ?>
</p>
 </div>
 <table class="xyz_twap_premium_comparison" cellspacing=0 style="width: 99%;">
	<tr style="background-color: #EDEDED">
		<td><h2><?php _e('Feature group','twitter-auto-publish');?></h2></td>
		<td><h2><?php _e('Feature','twitter-auto-publish');?></h2></td>
		<td><h2><?php _e('Free','twitter-auto-publish');?></h2>
		</td>
		<td><h2><?php _e('Premium','twitter-auto-publish');?></h2></td>
		<td><h2>  <?php $twap_smap="SMAP";
		                $twap_premium_msg=sprintf( __('%s Premium','twitter-auto-publish'),$twap_smap);
		                echo $twap_premium_msg; ?>+</h2></td>
	</tr>
	<!-- Supported Media  -->
	<tr>
		<td rowspan="8"><h4><?php _e('Supported Media','twitter-auto-publish');?></h4></td>
		<td> <?php _e('Facebook','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td> <?php _e('Twitter','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('LinkedIn','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Instagram','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Tumblr','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Pinterest','twitter-auto-publish'); ?> <span style="color: #FF8000;font-size: 14px;font-weight: bold;">*</span></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Telegram','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Threads','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<!-- Posting Options  -->
	<tr>
		<td rowspan="18"><h4><?php _e('Posting Options','twitter-auto-publish');?></h4></td>
		<td><?php _e('Publish to facebook pages','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

		<tr>

		<td><?php _e('Publish to twitter profile','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish to linkedin profile/company pages','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td> <?php _e('Publish to instagram Business accounts','twitter-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

		<tr>
		<td><?php _e('Publish to tumblr profile','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish to pinterest boards','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish to telegram channels and groups','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Publish to threads profile','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Option to add twitter image description for visually impaired people','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to republish existing posts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish to multiple social media accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Seperate message formats for publishing to multiple social media accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Save auto publish settings of individual posts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Hash Tags support for Facebook, Twitter, Linkedin, Instagram, Tumblr, Threads, Pinterest and Telegram','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to use post tags as hash tags','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Option to use post categories as hash tags','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Enable/Disable SSL peer verification','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
<tr>
		<td> <?php _e('Carousel post support for Instagram and Telegram','social-media-auto-publish'); ?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<!-- Image Options  -->

	<tr>
	<td rowspan="5"><h4><?php _e('Image Options','twitter-auto-publish');?></h4></td>
		<td><?php _e('Publish images along with post content','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>


	<tr>
		<td><?php _e('Separate default image url for publishing to multiple social media accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

		<tr>
		<td><?php _e('Option to specify preference from featured image, post content, post meta and open graph tags','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish multiple images to facebook, tumblr, linkedin, twitter, threads and telegram along with post content	','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to specify multiphoto preference from post content and post meta','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<!-- Video Options  -->

	<tr>
	<td rowspan="4"><h4><?php _e('Video/Audio Options','twitter-auto-publish');?></h4></td>
		<td><?php _e('Publish video to facebook, tumblr,Linkedin, Instagram, twitter, threads and telegram along with post content','twitter-auto-publish');?> </td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to specify preference from post content, post meta and open graph tags','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Publish audio to tumblr along with post content','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Option to specify audio preference from  post content, post meta and open graph tags','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<!-- Filter Options  -->

	<tr>
	<td rowspan="9"><h4><?php _e('Filter Options','twitter-auto-publish');?></h4></td>
		<td><?php _e('Filter posts to publish based on categories','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Filter posts to publish based on custom post types','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Filter posts to publish based on sticky posts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Configuration to enable/disable page publishing','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Category filter for individual accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Custom post type filter for individual accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Enable/Disable page publishing for individual accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Override auto publish scheduling for individual accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Override auto publish based on sticky posts for individual accounts','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<!-- Scheduling  -->

	<tr>
	<td rowspan="4"><h4><?php _e('Scheduling','twitter-auto-publish');?></h4></td>
		<td><?php _e('Instantaneous post publishing','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Scheduled post publishing using cron','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Status summary of auto publish tasks by mail','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Configurable auto publishing time interval','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>




	<!-- Publishing History  -->
	<tr>
		<td rowspan="4"><h4><?php _e('Publishing History','twitter-auto-publish');?></h4></td>
		<td><?php _e('View auto publish history','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('View auto publish error logs','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to republish post','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<tr>
		<td><?php _e('Option to reschedule publishing','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<!-- Installation and Support -->
	<tr>
		<td rowspan="2"><h4><?php _e('Installation and Support','twitter-auto-publish');?></h4></td>
		<td> <?php _e('Free Installation','twitter-auto-publish');?> </td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Privilege customer support','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>

	<!-- Addons and Support -->
	<tr>
		<td rowspan="3"><h4><?php _e('Addon Features','twitter-auto-publish');?></h4></td>
		<td><?php _e('Advanced Autopublish Scheduler','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		</tr>
		<tr>
		<td><?php _e('URL-Shortener','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td><?php _e('Privilege Management','twitter-auto-publish');?></td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/cross.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
		<td><img src="<?php echo plugins_url("images/tick.png",XYZ_TWAP_PLUGIN_FILE);?>">
		</td>
	</tr>
	<tr>
		<td rowspan="2"><h4><?php _e('Other','twitter-auto-publish');?></h4></td>
		<td><?php _e('Price','twitter-auto-publish');?></td>
		<td><?php _e('FREE','twitter-auto-publish');?></td>
		<td><?php _e('Starts from 29 USD','twitter-auto-publish');?></td>
		<td><?php _e('Starts from 59 USD','twitter-auto-publish');?></td>
	</tr>
	<tr>
		<td><?php _e('Purchase','twitter-auto-publish');?></td>
		<td></td>
		<td style="padding: 2px" colspan='2' ><a target="_blank"href="https://xyzscripts.com/wordpress-plugins/social-media-auto-publish/purchase"  class="xyz_twap_buy_button"><?php _e('Buy Now','twitter-auto-publish');?></a>
		</td>
	</tr>

</table>
<br/>
<div style="clear: both;"></div>
<span style="color: #FF8000;font-size: 14px;font-weight: bold;"> * </span> <?php _e('Pinterest is added on experimental basis.','twitter-auto-publish');?>
