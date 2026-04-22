<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

global $wpdb;

$action = $_GET['action'];
$id = $_GET['id'];

if($action == "excluir") {
    $wpdb->update('wp_video_destaque',
        array('status' => 2, 'data_exclusao' => current_time('mysql')),
        array('id' => $id)
    );
} elseif($action == "status") {
    $status = $_GET['status'];
    
    if($status == "0") {
        $wpdb->update('wp_video_destaque',
            array('status' => 1, 'data_ativacao' => current_time('mysql')),
            array('id' => $id)
        );
        
    } else {
        $wpdb->update('wp_video_destaque',
            array('status' => 0, 'data_desativacao' => current_time('mysql')),
            array('id' => $id)
        );
    }
    
}

header("Location:" . admin_url('admin.php?page=videos'));