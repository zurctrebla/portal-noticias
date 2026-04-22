<?php    
//## NextScripts Medium Connection Class

/* 
1. Options

nName - Nickname of the account [Optional] (Presentation purposes only - No affect on functionality)
postType - A or T - "Attached link" or "Text"

2. Post Info

url
text

*/
$nxs_snapAPINts[] = array('code'=>'MD', 'lcode'=>'md', 'name'=>'Medium');

if (!class_exists("nxs_class_SNAP_MD")) { class nxs_class_SNAP_MD {
    
    var $ntCode = 'MD';
    var $ntLCode = 'md';
    
    function doPost($options, $message){ if (!is_array($options)) return false; $out = array(); // return false;
      foreach ($options as $ii=>$ntOpts) $out[$ii] = $this->doPostToNT($ntOpts, $message);
      return $out;
    }

    function doPostToNT($options, $message){ global $nxs_urlLen; $badOut = array('pgID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>'');  error_reporting(E_ALL); ini_set('display_errors', '1');
      //## Check settings
      if (!is_array($options)) { $badOut['Error'] = 'No Options'; return $badOut; }

      if (!isset($options['accessToken']) || trim($options['accessToken'])=='') { $badOut['Error'] = 'Not Authorized'; return $badOut; }      
      if (empty($options['imgSize'])) $options['imgSize'] = '';
      //## Format Post
      if (!empty($message['pText'])) $msg = $message['pText']; else $msg = nxs_doFormatMsg($options['msgFormat'], $message);
      if (!empty($message['pTitle'])) $msgT = $message['pTitle']; else $msgT = nxs_doFormatMsg($options['msgTFormat'], $message);       
      if ($options['inclTags']=='1') $tags = nsTrnc($message['tags'], 195, ',', ''); else $tags = ''; 
      
      //## Make Post            
     // if (isset($message['imageURL'])) $imgURL = trim(nxs_getImgfrOpt($message['imageURL'], $options['imgSize'])); else $imgURL = '';  $postType = $options['postType'];
      
      $msg = str_replace('&amp;#039;', "'", $msg);  $msg = str_replace('&#039;', "'", $msg);  $msg = str_replace('#039;', "'", $msg);  $msg = str_replace('#039', "'", $msg);
      $msg = str_replace('&amp;#8217;', "'", $msg); $msg = str_replace('&#8217;', "'", $msg); $msg = str_replace('#8217;', "'", $msg); $msg = str_replace('#8217', "'", $msg);
      $msg = str_replace('&amp;#8220;', '"', $msg); $msg = str_replace('&#8220;', '"', $msg); $msg = str_replace('#8220;', '"', $msg); $msg = str_replace('#8220', "'", $msg);
      $msg = str_replace('&amp;#8221;', '"', $msg); $msg = str_replace('&#8221;', '"', $msg); $msg = str_replace('#8221;', '"', $msg); $msg = str_replace('#8221', "'", $msg);
      $msg = str_replace('&amp;#8212;', '-', $msg); $msg = str_replace('&#8212;', '-', $msg); $msg = str_replace('#8212;', '-', $msg); $msg = str_replace('#8212', "-", $msg);

      $argArr = ['ref'=>'https://medium.com',  'aj'=>true, 'extraHeaders'=>['Accept'=> 'application/json, text/javascript, */*; q=0.01','Content-Type'=>'application/json; charset=UTF-8', 'Authorization'=>'Bearer '.$options['accessToken']]];
      $args = nxs_mkRmReqArgs($argArr);  $rq = new WP_Http;
      if (empty($options['appAppUserID'])) {
          $ret = $rq->request('https://api.medium.com/v1/me', $args); /* prr($ret, 'CALL RET'); */ if (is_nxs_error($ret)) return print_r($ret, true);
          if (!empty($ret['body'])) { $ui = json_decode($ret['body'], true); $options['appAppUserID'] = $ui['data']['id']; }
      }

      $data = json_encode( array( 'title'=>$msgT, 'content'=>$msg, 'contentFormat'=>'html', 'canonicalUrl'=>$message['url'], 'tags'=>$message['tagsA'], 'publishStatus'=>'public') );
      $pURL = empty($options['publ'])?'https://api.medium.com/v1/users/'.$options['appAppUserID'].'/posts':'https://api.medium.com/v1/publications/'.$options['publ'].'/posts'; prr($pURL);
      $argArr['flds'] = $data; $args = nxs_mkRmReqArgs($argArr); prr($args);  $ret = $rq->request($pURL, $args); /* prr($ret, 'CALL RET'); */ if (is_nxs_error($ret)) return print_r($ret, true);

      $bd = json_decode($ret['body'], true); if (!is_array($bd) || !is_array($bd['data'])) { $badOut['Error'] = 'ERROR: '.print_r($ret, true); return $badOut; } $bd = $bd['data']; //prr($bd);
      return array('postID'=>$bd['id'], 'isPosted'=>1, 'postURL'=>$bd['url'], 'pDate'=>date('Y-m-d H:i:s')); 
    }  
    
}}
?>