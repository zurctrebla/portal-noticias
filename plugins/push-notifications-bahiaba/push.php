<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');
global $wpdb;

$idPost = $_POST['idPost'];
$objPost = $wpdb->get_row("SELECT * FROM wp_posts WHERE id = {$idPost}");

if ($objPost) {
    $idPush = savePushSent($wpdb, $idPost);
    sendAndroid($wpdb, $objPost, $idPush);
    sendIOS($wpdb, $objPost, $idPush);
}

function sendAndroid($wpdb, $objPost, $idPush) {
    
    // API access key from Google API's Console
    $apiAcessKey = 'AIzaSyCZ3JbK9gB2HXszFdTrpB441zDstAYdsTo';
    $subtitulo = get_post_meta($objPost->ID, 'subtitulo', true);

    $msg = array (
        'id' => $objPost->ID,
        'post_type' => getNamePostType($objPost->post_type),
        'id_push' => $idPush,
        'message' => 'Leia agora no aplicativo do Bahia.ba',
        'title' => $objPost->post_title,
        'subtitle' => resumo2(80, $subtitulo),
        'vibrate' => 1,
        'sound' => 1
    );
    
    $tokens = $wpdb->get_results("SELECT * FROM wp_push_token WHERE os = 'Android' AND active = 1");
    
    if($tokens) {
        $listTokens = array();
        foreach ($tokens as $token) {
            $listTokens[] = $token->token;
        }

        $fields = array (
            'registration_ids' => $listTokens,
            'data' => $msg
        );

        $headers = array (
            'Authorization: key=' . $apiAcessKey,
            'Content-Type: application/json'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://android.googleapis.com/gcm/send');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        curl_close($ch);
        echo $result;
    }
}

function sendIOS($wpdb, $objPost, $idPush) {
    
}

function savePushSent($wpdb, $idPost) {
    $wpdb->insert('wp_push_sent', array(
        'post_id' => $idPost,
        'send_date' => current_time('mysql')
    ));
    
    return $wpdb->insert_id;
}