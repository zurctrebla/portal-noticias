<?php

if (!class_exists("nxs_addns")) { class nxs_addns {
	var $addns = [];
	//## Static Part
	public static $lst = [
		'ma'=>['s'=>'f', 'v'=>[4,5], 't'=>'Multi Account Addon', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>'Adds an ability to configure more then one account for each social network and some addidional features.'],
		'api'=>['s'=>'f', 'v'=>[4,5], 't'=>'Premium API', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-premium-api/', 'd'=>'Post to Google My Business, Pinterest, Reddit, Flipboard, LinkedIn Company pages and groups'],
		'mu'=>['s'=>'f', 'v'=>[4,5], 't'=>'Multi User Addon', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-multiple-users', 'd'=>'Allows your WordPress users to add their own Social Network Accounts and post there'],
		'gp'=>['s'=>'d', 'v'=>[4], 't'=>'[Legacy] Pinterest API', 'd'=>'ex-"Google+ and Pinterest API".', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>'Depreciated in favor of Premium API'],
		'aci'=>['s'=>'cs', 'v'=>[5], 't'=>'Automatic Content Importer', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'ci'=>['s'=>'cs', 'v'=>[5], 't'=>'Comments Importer', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'vi'=>['s'=>'cs', 'v'=>[5], 't'=>'Video Importer/Exporter', 'u'=>'https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/', 'd'=>''],
		'rp'=>['s'=>'f', 'v'=>[5], 't'=>'Reposter and Scheduler', 'u'=>'https://www.nextscripts.net/addons/reposter/', 'd'=>''],
		'sd'=>['s'=>'f', 'v'=>[4,5], 't'=>'Scheduled and Delayed Posting', 'u'=>'https://www.nextscripts.net/addons/reposter/', 'd'=>''],
		'prx'=>['s'=>'f', 'v'=>[4,5], 't'=>'Proxies', 'u'=>'https://www.nextscripts.net/addons/proxies/', 'd'=>'']
	];
	function gLst(){ return self::$lst; }
	static function chkLst(){
		foreach (self::$lst as $a=>$inf) {
			if (class_exists('nxaddn_'.$a)) self::$lst[$a]['isi'] = true;
		}
	}
	static function getLst($v=5){ $l = [];
		foreach (self::$lst as $a=>$inf) {
			if (in_array($v, $inf['v'])) $l[$a] = $inf; if (class_exists('nxaddn_'.$a)) $l[$a]['isi'] = true;
		}
		return $l;
	}
	static function isActive(){ $isa = false;
		foreach (self::$lst as $a=>$inf) {
			if (class_exists('nxaddn_'.$a)) $isa = true;
		}
		return $isa;
	}

	static function getAddnList(){ $rq = new nxsHttp2; $al = $rq->req('https://www.nextscripts.com/', ['v'=>4,'do'=>'addns']);
		return is_array($al)?$al['body']:json_encode(self::$lst);
	}
	//## Show Pages/Blocks
	static function showAddnsPage(){ self::chkLst(); $lst = self::$lst;
		?><div class="nxscontainer">
		<style type="text/css">
            .nxs-ovrldiv{position: relative; width: 100%; filter: brightness(50%); }
            .nxs-ovrlh2{position: absolute;top: 200px;left: 0;width: 100%; }
            .nxs-ovrlh2 span {
                color: white;
                font: bold 24px/45px Helvetica, Sans-Serif;
                letter-spacing: -1px;
                background: rgb(0, 0, 0); /* fallback color */
                background: rgba(0, 0, 0, 0.7);
                padding: 10px;
            }
		</style>

		<div class="container">
			<div class="row g-2 card-group">
				<?php foreach ($lst as $a=>$inf) if (($inf['s']=='f'||$inf['s']=='a')&&empty($inf['isi'])) { ?>
					<div class="col-6">
						<div class="card" style="padding: 0px;">
							<img class="card-img-top" src="<?php echo NXS_PLURL; ?>img/addn/addn-<?php echo $a; ?>-001.png" />
							<div class="card-body">
								<h5 class="card-title"><?php echo $inf['t']; ?></h5>
								<a class="stretched-link text-decoration-none link-dark" target="_blank" href="<?php echo esc_url($inf['u']); ?>"><p class="card-text"><?php echo $inf['d']; ?></p></a>
                                <div style="margin-top:10px;" class="col border-end  d-flex justify-content-center align-items-center"><a class="btn btn-outline-success" href="<?php echo $inf['u']; ?>" target="_blank">More Info/Get it</a></div>
							</div>
						</div>
					</div>
				<?php } ?>
				<?php foreach ($lst as $a=>$inf) if ($inf['s']=='cs'||!empty($inf['isi'])){ ?>
					<div class="col-6">
						<div class="card" style="padding: 0px;">
							<div class="p-3 border bg-light nxs-ovrldiv"><img class="card-img-top img-fluid" src="<?php echo NXS_PLURL; ?>img/addn/addn-<?php echo esc_attr($a); ?>-001.png" />
								<h2 class="nxs-ovrlh2"><span> <?php echo $inf['s']=='cs'?'Coming soon':'Installed';  ?> </span></h2>
							</div>
							<div class="card-body">
								<h5 class="card-title"><?php echo $inf['t']; ?></h5>
								<a class="stretched-link text-decoration-none link-dark" target="_blank" href="<?php echo esc_url($inf['u']); ?>"><p class="card-text"><?php echo $inf['d']; ?></p></a>

							</div>
						</div>
					</div>
				<?php } ?>
			</div>
		</div>
		</div>
		<?php

	}
	static function showAddnsSideBar(){  self::chkLst(); ?>
		<div class="container" style="padding: 0px;"><div class="row g-2"><div class="col-12">
				<?php
				foreach (nxs_addns::$lst as $a=>$inf) if ($inf['s']=='f') {
					?>
					<div class="card" style="padding: 0px;">
					<img class="card-img-top" src="<?php echo NXS_PLURL; ?>img/addn/addn-<?php echo $a; ?>-001.png" />
					<div class="card-body">
						<h5 class="card-title"><?php echo $inf['t']; ?></h5>
						<a class="stretched-link text-decoration-none link-dark" target="_blank" href="<?php echo esc_url($inf['u']); ?>"><p class="card-text"><?php echo $inf['d']; ?></p></a>
					</div>
					</div><?php
				}
				?></div></div></div><?php

	}
	static function showAddnsOffers(){
		?>        <div class="card border-success mb-3" style="padding: 0px; margin-bottom: 5px; max-width: 18rem;" >
			<div class="card-header"><h5 class="card-title">Limited Time Offer</h5></div>
			<div class="card-body text-success">
				<h6 class="card-title" style="text-align: left;">$49.95 value</h6>
                <p class="card-text">Get <a class="nxsOfrLnk" href="https://www.nextscripts.net/addons/social-networks-autoposter-addon-multiple-accounts/" target="_blank" >Multiple Accounts</a> Addon Plugin for <b style="color: red;">Free</b> with the order of <a class="nxsOfrLnk" href="https://www.nextscripts.net/addons/social-networks-autoposter-addon-premium-api/" target="_blank" >Premium API</a> for WordPress or <a  class="nxsOfrLnk" href="https://www.nextscripts.net/addons/social-networks-autoposter-multiple-users" target="_blank" >Multiple Users</a> Addon</p>
				<a href="https://www.nextscripts.net/offers" target="_blank" class="btn btn-success" id="nxs_snapUPG">Get It</a>
			</div>
		</div> <?php

	}
	static function showAddnsVersions($v=5){
		$c = maybe_unserialize(get_site_option('__plugins_cache_200'));
		//## Installed Addons ifno
		if (!empty($c)) foreach ($c as $cn=>$o){ $clName = 'nxaddn_'.$cn; if (class_exists($clName)) $addns[$cn] = $clName::$inf; } //prr($addns);
		$addnTxt = '';if (!empty($addns)) foreach ($addns as $a) $addnTxt .= $a['n'].' <span style="color:#008000;font-weight: bold;">v.'.$a['v'].'</span><br/>';
		if (!empty($addnTxt)) _e('Active Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addnTxt.'</div>';
	}
	static function showAddnsListBlock($v=5){
		$c = maybe_unserialize(get_site_option('__plugins_cache_200')); $li = maybe_unserialize(get_site_option('__plugins_cache_201'));
		//## Installed Active Addons ifno
		if (!empty($c)) foreach ($c as $cn=>$o){ $clName = 'nxaddn_'.$cn; if (class_exists($clName)) $addns[$cn] = $clName::$inf; } //prr($addns, 'KKK'); prr($c, 'CCC');
		$addnTxt = '';if (!empty($addns)) foreach ($addns as $cn => $a) $addnTxt .= $a['n'].' <span style="color:#008000;font-weight: bold;">v.'.$a['v'].'</span><span style="color:#333;font-weight: bold;">&nbsp;('.$li[$cn]['m'].')</span><br/>';
		//## Installed expired addons ifno
		$addniTxt = ''; if (!empty($li))  foreach ($li as $a) if ($a['s']=='i') $addniTxt .= $a['n'].': <span style="color:orangered;font-weight: bold;">Inactive</span> (<span style="color:#008000;">'.$a['m'].'</span>)<br/>';
		$uid = get_site_option('__nxsuid');
		?>
		<div><a target="_blank" href="https://www.nextscripts.com"><img src="<?php echo NXS_PLURL; ?>img/SNAP_Logo_2014.png"></a></div> <br/>
		<?php wp_nonce_field( 'doLic', 'doLic_wpnonce' ); _e('SNAP Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo NextScripts_SNAP_Version; ?></span><br/>

        <?php if (defined('NextScripts_UPG_SNAP_Version')) { _e('SNAP Upgrade Helper Plugin Version', 'social-networks-auto-poster-facebook-twitter-g'); ?>: <span style="color:#008000;font-weight: bold;"><?php echo NextScripts_UPG_SNAP_Version; echo !empty($uid)?(' [ID:'.$uid.']'):''; ?></span><br/> <?php } ?>

		<?php if (!empty($addnTxt)) _e('Active Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addnTxt.'</div>'; ?>
		<?php if (!empty($addniTxt)) _e('Installed/Inactive Addons:', 'social-networks-auto-poster-facebook-twitter-g'); echo '<div style="padding-left: 15px;">'.$addniTxt.'</div>'; ?>

		<?php global $nxs_apiLInfo; if (isset($nxs_apiLInfo) && !empty($nxs_apiLInfo)) {
			if ($nxs_apiLInfo['1']==$nxs_apiLInfo['2'] || empty($nxs_apiLInfo['2'])) echo $nxs_apiLInfo['1']; else echo "<b>API:</b> (Google+, Pinterest, LinkedIn, Reddit, Flipboard): ".$nxs_apiLInfo['1']."<br/><b>API:</b> (Instragram): ".$nxs_apiLInfo['2']; echo "&nbsp;&nbsp;&nbsp;&nbsp;";
		}
	}
	static function showAddnsActButtons($v=5){ if(defined('NXSAPIVER')||self::isActive()){ $obj = defined('NXSAPIVER')?'API':'Addons'; ?><br/>
		<img id="checkAPI2xLoadingImg" style="display: none;" src='<?php echo NXS_PLURL; ?>img/ajax-loader-sm.gif' /><a href="" id="checkAPI2x">[Check for <?php echo $obj; ?> Update]</a>&nbsp;&nbsp;&nbsp;&nbsp;<a href="" class="showLic">[Change Activation Key]</a> <br/><br/>
	<?php } elseif(defined('NextScripts_UPG_SNAP_Version')) { ?> <br/><span style="color:red;">You have "SNAP Upgrade helper" installed, now please&nbsp;<a href="#" class="showLic">[Enter Activation Key]</a></span><br/><br/> <?php } ?><br/> <?php
	}
}}



?>