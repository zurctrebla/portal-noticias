<?php
//## NextScripts FriendFeed Connection Class
$nxs_snapAPINts[] = array('code'=>'WP', 'lcode'=>'wp', 'name'=>'WP Based Blog');

add_filter( 'register_post_type_args', 'my_post_type_args', 10, 2 );

function my_post_type_args( $args, $post_type ) {
    if ( 'cp_recipe' === $post_type ) {
        $args['show_in_rest'] = true;
        $args['rest_base']             = 'recipes';
        $args['rest_controller_class'] = 'WP_REST_Posts_Controller';
    } return $args;
}

if (!class_exists("nxs_class_SNAP_WP")) { class nxs_class_SNAP_WP {

    var $ntCode = 'WP';
    var $ntLCode = 'wp';

    function doPost($options, $message){ if (!is_array($options)) return false; $out = array();
        foreach ($options as $ii=>$ntOpts) $out[$ii] = $this->doPostToNT($ntOpts, $message);
        return $out;
    }

    function _getNounce($url, $ck){ $rq = new nxsHttp; $nc = ''; $url = str_ireplace('/wp-login.php','/wp-admin/',$url);
        $argArr = ['ck'=>$ck]; $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($url, $args);
        if (stripos($ret['body'], 'var wpApiSettings')!==false) { $wpn = CutFromTo($ret['body'],'var wpApiSettings','}'); $nc = CutFromTo($wpn,'"nonce":"','"'); }
        return $nc;
    }
    function _auth($url,$u,$p,$ck=[]){ $rq = new nxsHttp; $lgnCk = [];
        //## Check is Cookies are still ok and get Nonce if yes.
        if (!empty($ck)) { $wpn = $this->_getNounce($url, $ck); if (!empty($wpn)) return ['ck'=>$lgnCk, 'wpn'=>$wpn]; }
        //## If not, login and get Nonce
        $post = ['log'=>$u, "pwd"=>$p,"wp-submit"=>'Log+In', 'redirect_to'=>'']; $argArr = ['flds'=>$post]; $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($url, $args);
        foreach ($ret['cookies'] as $c) if (stripos($c->name, 'wordpress_logged_in_')!==false || stripos($c->name, 'wordpress_sec_')!==false)  $lgnCk[] = $c;
        if (!empty($lgnCk)) { $wpn = $this->_getNounce($url, $lgnCk); if (!empty($wpn)) return ['ck'=>$lgnCk, 'wpn'=>$wpn]; else return 'NO Nonce'; } else return 'NO Auth';
    }

    function _doMakeExactCopy($pid, $opts){ if (empty($opts['ck'])) $opts['ck'] = [];//$pid = 102;
        $aurl = str_ireplace('/xmlrpc.php','/wp-login.php',$opts['url']); $auth = $this->_auth($aurl, $opts['uName'], $opts['uPass'], $opts['ck']); $badOut = [];
        if (!empty($auth) && is_array($auth)) {  $rq = new nxsHttp; $selfURL = get_site_url() . '/wp-json/wp/v2/posts/' . $pid;
            //## Post Type
            $pt = get_post_type($pid); if ($pt=='post') $ptPath = 'posts'; elseif ($pt=='page') $ptPath = 'pages';  else $ptPath = $pt;  $url = str_ireplace('/xmlrpc.php','/wp-json/wp/v2/'.$ptPath.'/',$opts['url']);
            //## Get Post
            $k = new WP_REST_Posts_Controller($pt); $req = new WP_REST_Request(); $request = new WP_REST_Request('GET', '/wp/v2/posts/' . $pid);
            // $flds = ['date','date_gmt','guid','id','link','modified','modified_gmt','slug','status','type','password','permalink_template','generated_slug','title','content','author','featured_media','comment_status','ping_status','template','password','permalink_template','generated_slug','excerpt','format','meta','sticky','categories','tags'];
            // $request->set_query_params( ['id'=>$pid, '_fields'=>$flds] );
            // if ($pt!='post') { if (empty($request['_fields'])) $request['_fields'] = []; $request['_fields'] = array_merge($request['_fields'], $flds); }
            $request->set_query_params(['id' => $pid, 'context'=>'edit']);
            $res = $k->get_item($request);// prr($res); die();
            if (empty($opts['keepDate'])) { $res->data['date'] = current_time( 'mysql' ); $res->data['date_gmt'] = current_time( 'mysql', 1 ); }
            $res->data['meta'] = get_post_meta($pid);
            //    $res->data['categories'] = wp_get_post_categories($pid);
            //    $res->data['tags'] = wp_get_post_tags($pid);
            $taxonomies = get_object_taxonomies(array('post_type' => $pt)); foreach ($taxonomies as $taxonomy) $res->data[$taxonomy] = wp_get_post_terms($pid, $taxonomy);
            //foreach( $taxonomies as $taxonomy ) { $terms = get_terms( $taxonomy ); foreach( $terms as $term ) echo $term->name; }
            //####################################
            //## Upload Post
            //####################################
            //## 1. Get and Push Featured Image
            if (!empty($res->data['featured_media'])) {
                //## 1.1 Get image
                $imgURL = wp_get_attachment_url($res->data['featured_media']); $args = nxs_mkRmReqArgs(); $ret = $rq->request($imgURL, $args); $imgSrc = $ret['body'];
                //## 1.2 Upload Media
                $argArr = ['flds' => $imgSrc, 'ck' => $auth['ck'], 'hdrsArr' => ['X-WP-Nonce' => $auth['wpn'], 'Content-Disposition' => 'attachment; filename=zzz.jpg', 'Content-Type' => 'application/binary', 'Accept' => 'application/json']];
                $urlM = str_ireplace('/xmlrpc.php', '/wp-json/wp/v2/media', $opts['url']); $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($urlM, $args);
                if (!empty($ret['body'])){
                    $resJ = json_decode($ret['body'], true);
                    $res->data['featured_media'] = $resJ['id'];
                }
            }
            //## 2. Push Tags and Cats
            //## 2.1 Tags
            if (!empty($res->data['tags'])) foreach ($res->data['tags'] as $tg){ $tgN = get_tag($tg); $tgN = $tgN->name;
                $argArr = ['flds' => ["name"=>$tgN], 'ck' => $auth['ck'], 'hdrsArr' => ['X-WP-Nonce' => $auth['wpn'], 'Accept' => 'application/json']];
                $urlM = str_ireplace('/xmlrpc.php', '/wp-json/wp/v2/tags', $opts['url']); $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($urlM, $args); // prr($ret); prr($args); //die();
                if (!empty($ret['body'])){ $resJ = json_decode($ret['body'], true); $tgs[] = !empty($resJ['id'])?$resJ['id']:$resJ['data']['term_id']; }
            } $res->data['tags'] = $tgs;
            //## 2.1 Cats
            if (!empty($res->data['categories'])) foreach ($res->data['categories'] as $tg){ $tgN = get_cat_name($tg);
                $argArr = ['flds' => ["name"=>$tgN], 'ck' => $auth['ck'], 'hdrsArr' => ['X-WP-Nonce' => $auth['wpn'], 'Accept' => 'application/json']];
                $urlM = str_ireplace('/xmlrpc.php', '/wp-json/wp/v2/categories', $opts['url']); $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($urlM, $args);
                if (!empty($ret['body'])){ $resJ = json_decode($ret['body'], true); $cts[] = !empty($resJ['id'])?$resJ['id']:$resJ['data']['term_id']; }
            } $res->data['categories'] = $cts;
            //## 3. Insert Post itself.
            $post = $res->data; unset($post['id']); //prr($res, 'RES');
            $argArr = ['flds' => $post, 'ck'=>$auth['ck'], 'hdrsArr' => ['X-WP-Nonce'=>$auth['wpn']]];
            $args = nxs_mkRmReqArgs($argArr); $ret = $rq->request($url, $args);
            $resP = json_decode($ret['body'], true);
            if (empty($resP)||empty($resP['id'])) { $badOut['Error'] .= '-=ERROR=- '.print_r($ret, true); return $badOut; }
            //prr($url, 'PT-URRL'); prr($args, 'PP-ARGS'); prr($ret, 'PP-RET'); prr($opts);
            return array('postID'=>$resP['id'], 'isPosted'=>1, 'postURL'=>$resP['link'], 'pDate'=>date('Y-m-d H:i:s'));
        }
    }

    function doPostToNT($options, $message){ $badOut = array('pgID'=>'', 'isPosted'=>0, 'pDate'=>date('Y-m-d H:i:s'), 'Error'=>''); //prr($options, 'OPTS'); prr($message, 'MSSGX');

        //## Check settings
        if (!is_array($options)) { $badOut['Error'] = 'No Options'; return $badOut; }
        if (!isset($options['uName']) || trim($options['uPass'])=='') { $badOut['Error'] = 'Not Configured'; return $badOut; }
        $pass = (substr($options['uPass'], 0, 5)=='n5g9a'||substr($options['uPass'], 0, 5)=='g9c1a'||substr($options['uPass'], 0, 5)=='b4d7s')?nsx_doDecode(substr($options['uPass'], 5)):$options['uPass'];
        if (empty($options['imgSize'])) $options['imgSize'] = '';
        //## Check is Exact Copy
        if (!empty($options['exactCopy'])) return $this->_doMakeExactCopy($message['pid'], ['url'=>$options['wpURL'], 'uPass'=>$pass, 'uName'=>$options['uName'], 'keepDate'=>$options['keepDate']]);
        //## Format
        if (empty($message['orID'])) $message['orID']=''; if (empty($message['tags'])) $message['tags']=''; if (empty($message['cats'])) $message['cats']='';
        if (!empty($message['pText'])) $msg = $message['pText']; else $msg = nxs_doFormatMsg($options['msgFormat'], $message);
        if (!empty($message['pTitle'])) $msgT = $message['pTitle']; else $msgT = nxs_doFormatMsg($options['msgTFormat'], $message);
        if (isset($message['imageURL'])) $imgURL = trim(nxs_getImgfrOpt($message['imageURL'], $options['imgSize'])); else $imgURL = '';
        if (empty($options['pt'])) $options['pt'] = 'post';
        $link = urlencode($message['url']); $ext = substr($msg, 0, 1000);
        //## Fix missing xmlrpc.php
        if (substr($options['wpURL'], -1)=='/') $options['wpURL'] = substr($options['wpURL'], 0, -1); if (substr($options['wpURL'], -10)!='xmlrpc.php') $options['wpURL'] .= "/xmlrpc.php";
        //## Post
        require_once ('apis/xmlrpc-client.php'); $nxsToWPclient = new NXS_XMLRPC_Client($options['wpURL']); $nxsToWPclient->debug = false;
        if ($imgURL!=='' && stripos($imgURL, 'http')!==false) {
            // $handle = fopen($imgURL, "rb"); $filedata = ''; while (!feof($handle)) {$filedata .= fread($handle, 8192);} fclose($handle);
            $filedata = nxs_remote_get($imgURL); if (! is_nxs_error($filedata) ) $filedata = $filedata['body'];
            $data = array('name'  => 'image-'.$message['orID'].'.jpg', 'type'  => 'image/jpg', 'bits'  => new NXS_XMLRPC_Base64($filedata), true);
            $status = $nxsToWPclient->query('metaWeblog.newMediaObject', $message['orID'], $options['uName'], $pass, $data);  $imgResp = $nxsToWPclient->getResponse();  $gid = $imgResp['id'];
        } else $gid = '';

        $params = array(0, $options['uName'], $pass, array('software_version')); // prr($params);
        if (!$nxsToWPclient->query('wp.getOptions', $params)) { $ret = 'Something went wrong - '.$nxsToWPclient->getErrorCode().' : '.$nxsToWPclient->getErrorMessage();} else $ret = 'OK';
        $rwpOpt = $nxsToWPclient->getResponse(); if (!empty($rwpOpt['software_version'])) { $rwpOpt = $rwpOpt['software_version']['value']; $rwpOpt = floatval($rwpOpt); } else $rwpOpt = 0; prr($rwpOpt);prr($nxsToWPclient);
        //## MAIN Post
        if ($rwpOpt==0) {
            $errMsg = $nxsToWPclient->getErrorMessage(); if ($errMsg!='') $ret = $errMsg; else  $ret = 'XMLRPC is not found or not active. WP admin - Settings - Writing - Enable XML-RPC';
        } else if ($rwpOpt<3.0)  $ret = 'XMLRPC is too OLD - '.$rwpOpt.' You need at least 3.0'; else {

            if ($rwpOpt>3.3){
                $nxsToWPContent = array('title'=>$msgT, 'description'=>$msg, 'post_status'=>'draft', 'mt_excerpt'=>$ext, 'mt_allow_comments'=>1, 'mt_allow_pings'=>1, 'post_type'=>$options['pt'], 'mt_keywords'=>$message['tags'], 'categories'=>$message['catsA'], 'custom_fields' =>  '');
                $params = array(0, $options['uName'], $pass, $nxsToWPContent, true); prr($params);
                if (!$nxsToWPclient->query('metaWeblog.newPost', $params)) { $ret = 'Something went wrong - #1.1'.$nxsToWPclient->getErrorCode().' : '.$nxsToWPclient->getErrorMessage();} else $ret = 'OK';

                prr($nxsToWPclient, 'OBJ');

                $pid = $nxsToWPclient->getResponse();  prr($pid);

                if ($gid!='') {
                    $nxsToWPContent = array('post_thumbnail'=>$gid);  $params = array(0, $options['uName'], $pass, $pid, $nxsToWPContent, true);
                    if (!$nxsToWPclient->query('wp.editPost', $params)) { $ret = 'Something went wrong #(image upload) - '.$nxsToWPclient->getErrorCode().' : '.$nxsToWPclient->getErrorMessage();} else $ret = 'OK';
                }
                $nxsToWPContent = array('post_status'=>'publish');  $params = array(0, $options['uName'], $pass, $pid, $nxsToWPContent, true);
                if (!$nxsToWPclient->query('wp.editPost', $params)) { $ret = 'Something went wrong #1.2 - (PID: '.$pid.')'.$nxsToWPclient->getErrorCode().' : '.$nxsToWPclient->getErrorMessage();} else $ret = 'OK';
            } else {
                $nxsToWPContent = array('title'=>$msgT, 'description'=>$msg, 'post_status'=>'publish', 'mt_allow_comments'=>1, 'mt_allow_pings'=>1, 'post_type'=>$options['pt'], 'mt_keywords'=>$message['tags'], 'categories'=>$message['catsA'], 'custom_fields' => '');
                $params = array(0, $options['uName'], $pass, $nxsToWPContent, true);
                if (!$nxsToWPclient->query('metaWeblog.newPost', $params)) { $ret = 'Something went wrong - '.$nxsToWPclient->getErrorCode().' : '.$nxsToWPclient->getErrorMessage();} else $ret = 'OK';
                $pid = $nxsToWPclient->getResponse();
            }
        }
        if ($ret!='OK') $badOut['Error'] .= '-=ERROR=- '.print_r($ret, true); else {
            $wpURL = str_ireplace('/xmlrpc.php','',$options['wpURL']); if(substr($wpURL, -1)=='/') $wpURL=substr($wpURL, 0, -1); $wpURL .= '/?p='.$pid; return array('postID'=>$pid, 'isPosted'=>1, 'postURL'=>$wpURL, 'pDate'=>date('Y-m-d H:i:s'));
        } return $badOut;
    }
}}
?>