<?php
if( !defined('ABSPATH') ){ exit();}
?></div>
<?php
if(get_option('xyz_twap_premium_version_ads')==1){?>
<div id="xyz_twap_wp_premium">


      <div class="xyz_twap_ContentCenter">
     



<?php printf(
 __('%sUpgrade to the Fully Featured XYZ WP Social Media Auto Publish Premium Plugin for %sJust 29 USD%s', 'twitter-auto-publish'),
 '<a href="https://xyzscripts.com/wordpress-plugins/social-media-auto-publish/features" target="_blank">',
 '</a><span style="font-size: 28px; color:#fbff00;">',
 '</span>'

);?>


      <br/><span style="font-size: 14px;color:#fff;"><?php _e('Unlock advanced scheduling, multi-account publishing, hashtag automation, and seamless social media integration!','twitter-auto-publish');?></span>

</div>
	<div class="clear"></div>
	<div class="xyz_twap_ButtonWrap">
    <div class="xyz_twap_BuyNowButton">
        <a target="_blank"  href="https://xyzscripts.com/members/product/purchase/XYZWPSMPPRE">🛒  <?php _e('Buy Now','twitter-auto-publish');?></a>
    </div>
    <div class="xyz_twap_CompareButton">
    <h2>
            <a 	href="<?php echo admin_url('admin.php?page=twitter-auto-publish-about');?>"> <?php _e('Compare Features','twitter-auto-publish'); ?> </a>
        </h2>
    </div>
		</div>
</div>
<?php }?>

<div style="clear: both;">
</div>
<div class="xyz_twap_wrap">
<div class="xyz-twap-footer-contact">
🚀 <span style="font-style: italic;font-size:larger;"><?php _e('We are available for custom development','twitter-auto-publish'); ?></span> — 
    <a href="https://xyzscripts.com/support/" target="_blank">
    <?php _e('Get a quote','twitter-auto-publish'); ?>
    </a>
</div>
</div>
<div class="xyz_twap_wrap">
<div class="xyz_twap_social_media">
<h3 class="xyz_twap_inner_head"><?php _e('Follow Us','twitter-auto-publish'); ?> </h3>
   <a target="_blank" href="http://facebook.com/xyzscripts" class="xyz_twap_home_fbook"></a>
   <a target="_blank" href="http://x.com/xyzscripts" class="xyz_twap_home_twitt"></a>
        <a target="_blank" href="https://www.instagram.com/xyz_scripts/" class="xyz_twap_home_insta"></a>
 <a style="margin-right:0px;" target="_blank" href="https://www.linkedin.com/company/xyzscripts" class="xyz_twap_home_linkedin"></a>
</div>

<div class="xyz_twap_sugession">
<h3 class="xyz_twap_inner_head"> <?php _e('Support','twitter-auto-publish'); ?> </h3>
<p style="width:100%;"><a target="_blank" href="https://xyzscripts.com/support/" > <?php _e('Contact Us','twitter-auto-publish'); ?></a></p>
<p><a target="_blank" href="https://wordpress.org/support/plugin/twitter-auto-publish/reviews/" > <?php _e('Please Rate Us','twitter-auto-publish'); ?>
 </a></p>
<a target="_blank" href="https://wordpress.org/support/plugin/twitter-auto-publish/reviews/" ><span class="xyz_twap_new_star"></span></a>

<p style="width:100%;"><a target="_blank" href="https://wordpress.org/support/plugin/twitter-auto-publish/" > <?php _e('Create a ticket at Wordpress forum','twitter-auto-publish') ?></a></p>
</div>
<div class="xyz_twap_new_subscribe">
<h3 class="xyz_twap_inner_head"> <?php _e('Stay tuned for our updates','twitter-auto-publish') ?> </h3>

<script language="javascript">
function check_email(emailString)
{
    var mailPattern = /^([A-Za-z0-9_\-\.])+\@([A-Za-z0-9_\-\.])+\.([A-Za-z]{2,})$/;
    var matchArray = emailString.match(mailPattern);
    if (emailString.length == 0)
    return false;
       
    if (matchArray == null)    {
    return false;
    }else{
    return true;
    }
}

   
function verify_lists(form)
{
   
    var total=0;
    var checkBox=form['chk[]'];
   
    if(checkBox.length){
   
    for(var i=0;i<checkBox.length;i++){
    checkBox[i].checked?total++:null;
    }
    }else{
       
    checkBox.checked?total++:null;
       
    }
    if(total>0){
    return true;
    }else{
    return false;
    }

}
   
function verify_fields()
{

    if(check_email(document.email_subscription.email.value) == false){
    alert(xyz_script_twap_var.alert1); 
    document.email_subscription.email.select();
    return false;
    }else if(verify_lists(document.email_subscription)==false){
    alert(xyz_script_twap_var.alert2);
    }
    else{
    document.email_subscription.submit();
    }

}
</script>
<?php global $current_user; wp_get_current_user(); ?>
<form action="https://xyzscripts.com/newsletter/index.php?page=list/subscribe" method="post" name="email_subscription" id="email_subscription" target="_blank">
<input type="hidden" name="fieldNameIds" value="1,">
<input type="hidden" name="redirActive" value="https://xyzscripts.com/subscription/pending/XYZWPSMPPRE">
<input type="hidden" name="redirPending" value="https://xyzscripts.com/subscription/active/XYZWPSMPPRE">
<input type="hidden" name="mode" value="1">
<input class="xyz_twap_name"  type="text" placeholder="<?php _e('Name','twitter-auto-publish'); ?>" name="field1" value="<?php
if ($current_user->user_firstname != "" || $current_user->user_lastname != "") 
{
    echo esc_html($current_user->user_firstname . " " . $current_user->user_lastname);
} 
else if (strcasecmp($current_user->display_name, 'admin')!=0 && strcasecmp($current_user->display_name , "administrator")!=0 ) 
{
    echo esc_html($current_user->display_name);
} 
else if (strcasecmp($current_user->user_login ,"admin")!=0 && strcasecmp($current_user->user_login , "administrator")!=0 ) 
{
    echo esc_html($current_user->user_login);
}
?>"  >

<input class="xyz_twap_email" name="email"
type="text" placeholder="<?php _e('Email','twitter-auto-publish'); ?>" value="<?php echo esc_html($current_user->user_email); ?>" />

<input id="xyz_twap_submit_twap" class="xyz_twap_submit_btn" type="submit" value="<?php _e('Subscribe','twitter-auto-publish');?>" name="Submit"  onclick="javascript: if(!verify_fields()) return false; " />

<input type="hidden" name="listName" value="7,1,"/>
</form>
</div>   

</div>

<div style="clear: both;"></div>
<div style="width: 100%">

<div class="xyz_twap_our_plugins_new">
<p class="xyz_twap_plugin_head"> <?php _e('Our Plugins','twitter-auto-publish'); ?> : </p>

    <a target="_blank"  href="https://wordpress.org/plugins/social-media-auto-publish/"><span>1</span>Social Media Auto Publish</a>
    <a target="_blank"  href="https://wordpress.org/plugins/facebook-auto-publish/"><span>2</span>WP2Social Auto Publish</a>
    <a target="_blank"  href="https://wordpress.org/plugins/twitter-auto-publish/"><span>3</span>WP Twitter Auto Publish</a>
    <a target="_blank"  href="https://wordpress.org/plugins/linkedin-auto-publish/"><span>4</span>WP to LinkedIn Auto Publish</a>
    <a target="_blank"  href="https://wordpress.org/plugins/insert-html-snippet/"><span>5</span>Insert HTML Snippet</a>
    <a target="_blank"  href="https://wordpress.org/plugins/insert-php-code-snippet/"><span>6</span>Insert PHP Code Snippet</a>
    <a target="_blank"  href="https://wordpress.org/plugins/contact-form-manager/"><span>7</span>Contact Form Manager</a>
    <a target="_blank"  href="https://wordpress.org/plugins/newsletter-manager/"><span>8</span>Newsletter Manager</a>
    <a target="_blank"  href="https://wordpress.org/plugins/lightbox-pop/"><span>9</span>Lightbox Pop</a>
    <a target="_blank"  href="https://wordpress.org/plugins/full-screen-popup/"><span>10</span>Full Screen Popup</a>
    <a target="_blank"  href="https://wordpress.org/plugins/popup-dialog-box/"><span>11</span>Popup Dialog Box</a>
    <a target="_blank"  href="https://wordpress.org/plugins/quick-bar/"><span>12</span>Quick Bar</a>
    <a target="_blank"  href="https://wordpress.org/plugins/quick-box-popup/"><span>13</span>Quick Box Popup</a>
    <a target="_blank"  href="https://wordpress.org/plugins/custom-field-manager/"><span>14</span> Custom Field Manager</a>
    <a target="_blank"  href="https://wordpress.org/plugins/wp-filter-posts/"><span>15</span>  WP Filter Posts</a>
    <a target="_blank"  href="https://wordpress.org/plugins/wp-gallery-manager/"><span>16</span>  WP Gallery Manager</a>
</div>
</div>
<div class="xyz_twap_poweredBy">  
<?php _e('Powered by','twitter-auto-publish');?> <a href="http://xyzscripts.com" target="_blank">XYZScripts</a>
</div>
<div style="clear: both;"></div>
<p style="clear: both;"></p>