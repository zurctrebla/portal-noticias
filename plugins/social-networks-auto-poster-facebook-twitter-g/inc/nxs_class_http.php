<?php
/*#############################################################################
Project Name: NextScripts Social Networks AutoPoster
Project URL: https://www.nextscripts.com/snap-api/
Description: Automatically posts to all your Social Networks
Author: NextScripts Corp
File Version: 2.0.3 (Dec 26, 2022)
Author URL: https://www.nextscripts.com
Copyright 2012-2023  NextScripts Corp
#############################################################################*/

if (!class_exists('nxsHttp2')) { class nxsHttp2 extends WP_Http {
    var $proxy=[];
    var $postData = false;
	//## Function for simple request with all defaults.
	function req($url,$p=[]){ $argArr = []; if (!empty($p)) $argArr['flds'] = $p; $args = nxs_mkRmReqArgs($argArr); $ret = $this->request($url, $args); return $ret;}
	//## Functions.
    function sendReq($url, $type='GET', $args = array()) { $defaults = array('method' => $type); $r = wp_parse_args( $args, $defaults ); return $this->request($url, $r);}
    function imgUplcurl(&$handle){ curl_setopt($handle, CURLOPT_POSTFIELDS, $this->postData); }
    function request($url, $args = array()){
        //## Add Proxy to the request.
        if (empty($this->proxy)&&!empty($args['proxy'])) $this->proxy = $args['proxy'];
	    if (!empty($this->proxy)&&class_exists('nxaddn_prx')) nxaddn_prx::addPrx($this->proxy);
        //## Post AS Array (for Image/media Upload)
        if ($this->postData===true && !empty($args['body'])) $this->postData = $args['body'];
        if (!empty($this->postData)) { add_action( 'http_api_curl', array($this, 'imgUplcurl') ); unset($args['headers']['Content-Length']);  unset($args['headers']['Content-Type']); }
        $ret = parent::request($url, $args);
        if (!empty($this->postData)) { remove_action( 'http_api_curl', array($this, 'imgUplcurl') ); $this->postData = false; }
        return $ret;
    }
}}
if (!class_exists('nxsHttp')) {  class_alias('nxsHttp2', 'nxsHttp'); }
if (!class_exists('nxs_Http_Cookie')) { class_alias('WP_Http_Cookie', 'nxs_Http_Cookie'); }
if (!class_exists('nxs_Error')) { class_alias('WP_Error', 'nxs_Error'); }
if (!function_exists("is_nxs_error")) { function is_nxs_error($thing) { if ( is_object($thing) && ( is_a($thing, 'nxs_Error') ||  is_a($thing, 'wp_Error') ) ) return true; return false; }}

if (!function_exists("nxs_staticHttpObj")) { function nxs_staticHttpObj() { static $nxs_http; if ( is_null($nxs_http) ) $nxs_http = new nxsHttp2(); return $nxs_http; }}
if (!function_exists("nxs_remote_request")) { function nxs_remote_request($url, $args = array()) { $nxs_http = nxs_staticHttpObj(); return $nxs_http->request($url, $args); }}
if (!function_exists("nxs_remote_get")) { function nxs_remote_get($url, $args = array()) { $nxs_http = nxs_staticHttpObj(); return $nxs_http->sendReq($url, 'GET', $args); }}
if (!function_exists("nxs_remote_post")) { function nxs_remote_post($url, $args = array()) { $nxs_http = nxs_staticHttpObj(); return $nxs_http->sendReq($url,'POST', $args); }}
if (!function_exists("nxs_remote_head")) { function nxs_remote_head($url, $args = array()) { $nxs_http = nxs_staticHttpObj(); return $nxs_http->sendReq($url,'HEAD', $args); }}


//## Headers and Remote Options Array
if (!function_exists("nxs_makeHeaders")) { function nxs_makeHeaders($ref='', $org='', $type='GET', $aj=false){  $hdrsArr = array();
    $hdrsArr['Cache-Control']='max-age=0'; $hdrsArr['Referer']=$ref;
    $hdrsArr['User-Agent']='Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36';
    if($type=='JSON') $hdrsArr['Content-Type']='application/json;charset=UTF-8'; elseif($type=='POST') $hdrsArr['Content-Type']='application/x-www-form-urlencoded';
    elseif($type=='JS') $hdrsArr['Content-Type']='application/javascript; charset=UTF-8'; elseif($type=='PUT') $hdrsArr['Content-Type']='application/octet-stream';
    if($aj===true) $hdrsArr['X-Requested-With']='XMLHttpRequest';  if ($org!='') $hdrsArr['Origin']=$org;
    if ($type=='GET') $hdrsArr['Accept']='text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'; else $hdrsArr['Accept']='*/*';
    if (function_exists('gzdeflate')) $hdrsArr['Accept-Encoding']='deflate,sdch';  $hdrsArr['Accept-Language']='en-US,en;q=0.8'; return $hdrsArr;
}}
if (!function_exists("nxs_getNXSHeaders")) {  function nxs_getNXSHeaders($ref='', $post=false){ return nxs_makeHeaders($ref, '', $post?'POST':'GET'); }} //## Compatibility function...
//## AdvSet
if (!function_exists("nxs_mkRemOptsArr")) {function nxs_mkRemOptsArr($hdrsArr, $ck='', $flds='', $p='', $rdr=0, $timt=45, $sslverify = false){ $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36';
    if (empty($hdrsArr)) $hdrsArr = nxs_makeHeaders('http://'.$_SERVER['HTTP_HOST']); $a = array('headers' => $hdrsArr, 'httpversion' => '1.1', 'timeout' => $timt, 'redirection' => $rdr, 'sslverify'=>$sslverify, 'user-agent'=>$ua);
    if (!empty($flds)) $a['body'] = $flds; if (!empty($p)) $a['proxy'] = $p;  if (!empty($ck)) $a['cookies'] = $ck; return $a;
}}
//##########################################################
//## AdvSet & Headers (V5)
//##########################################################
//## Headers and Remote Options Array (WP_Http Version)
if (!function_exists("nxs_makeHdrs")) { function nxs_makeHdrs($args=[], $hdrs=[]){  $hdrsArr = array(); $args = array_merge(['ref'=>'', 'org'=>'', 'type'=>'GET', 'aj'=>false], $args);
    $hdrsArr['Cache-Control']='max-age=0'; $hdrsArr['Referer']=$args['ref'];
    $hdrsArr['User-Agent']= empty($args['ua'])?'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36':$args['ua'];
    if($args['type']=='JSON') $hdrsArr['Content-Type']='application/json;charset=UTF-8'; elseif($args['type']=='POST') $hdrsArr['Content-Type']='application/x-www-form-urlencoded';
    elseif($args['type']=='JS') $hdrsArr['Content-Type']='application/javascript; charset=UTF-8'; elseif($args['type']=='PUT') $hdrsArr['Content-Type']='application/octet-stream';
    if($args['aj']===true) $hdrsArr['X-Requested-With']='XMLHttpRequest';  if ($args['org']!='') $hdrsArr['Origin']=$args['org'];
    if ($args['type']=='GET') $hdrsArr['Accept']='text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'; else $hdrsArr['Accept']='*/*';
    if (function_exists('gzdeflate')) $hdrsArr['Accept-Encoding']='deflate,sdch';  $hdrsArr['Accept-Language']='en-US,en;q=0.8'; $hdrsArr = array_merge($hdrsArr, $hdrs); return $hdrsArr;
}}
//## AdvSet
if (!function_exists("nxs_mkRmReqArgs")) {function nxs_mkRmReqArgs($args=[]){ if (empty($args)) $args = [];
    $ua = !empty($args['mblUA'])?'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4':'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/90.0.4430.93 Safari/537.36';
    $def = array('method'=>'GET', 'ref'=>'http://'.$_SERVER['HTTP_HOST'], 'org'=>'', 'aj'=>false, 'hdrsArr'=>'', 'ck'=>'', 'flds'=>'', 'proxy'=>'', 'rdr'=>0, 'limit'=>45, 'sslverify' => false, 'ua'=>$ua, 'extraHeaders'=>[]);  $args = array_merge($def, $args);
    if (!empty($args['flds'])) $args['method']='POST'; if (empty($args['hdrsArr'])) $args['hdrsArr'] = nxs_makeHdrs(['ref'=>$args['ref'], 'org'=>$args['org'], 'type'=>$args['method'], 'aj'=>$args['aj'], 'ua'=>$args['ua']], $args['extraHeaders']);
    $a = array('method'=>$args['method'], 'headers' => $args['hdrsArr'], 'httpversion' => '1.1', 'timeout' => $args['limit'], 'redirection' =>  $args['rdr'], 'sslverify'=> $args['sslverify'], 'user-agent'=>$args['ua']);
    if (!empty($args['flds'])) $a['body'] = $args['flds']; if (!empty($args['proxy'])) $a['proxy'] = $args['proxy'];  if (!empty($args['ck'])) $a['cookies'] = $args['ck']; return $a;
}}
if (!function_exists("nxs_getRawResp")) {function nxs_getRawResp($resp){ return htmlspecialchars( print_r($resp['http_response']->get_response_object()->raw, true)); }}
?>