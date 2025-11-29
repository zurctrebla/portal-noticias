<?php
/* Plugin Name: Vídeo de destaque do Bahia.ba
 * Description: Plugin para cadastrar os Vídeos de destaque do Bahia.ba
 * Plugin URI: videos.bahia.ba
 * Author: Romário Carvalho
 * Author URI: bahia.ba
 * Version: 1.0
 * License: GPL2
 */

register_activation_hook(__FILE__, 'activateVideos');

function activateVideos() {
    global $wpdb;

    $sql = "CREATE TABLE IF NOT EXISTS `wp_video_destaque` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `nome` VARCHAR(55) NOT NULL,
                `url` VARCHAR(55) NOT NULL,
                `posicao` VARCHAR(500) NULL,
                `data_criacao` DATETIME NULL,
                `data_ativacao` DATETIME NULL,
                `data_desativacao` DATETIME NULL,
                `data_exclusao` DATETIME NULL,
                `status` SMALLINT(1) NOT NULL,
            PRIMARY KEY (`id`));";
    
    $wpdb->query($sql);
}

function acf_get_videos( $field ) {
    
    global $wpdb;
            
    $sql = "SELECT ";
    $sql .= "   id, ";
    $sql .= "   nome ";
    $sql .= "FROM wp_video_destaque ";
    $sql .= "WHERE status IN (0,1) ";
    $sql .= "ORDER BY data_criacao ";

    $objVideo = $wpdb->get_results($sql);
    
    if($objVideo) {
    
        foreach ($objVideo as $obj) {
            $data_from_database[$obj->id] = $obj->nome;
        }

        $field['choices'] = array();

        foreach($data_from_database as $field_key => $field_value) {
            $field['choices'][$field_key] = $field_value;
        }
    }
    
    return $field;
}
add_filter('acf/load_field/name=video', 'acf_get_videos');


add_action('admin_menu', 'add_menu_videos');

function add_menu_videos() {
    add_menu_page('Vídeos do Destaque', 'Vídeos do Destaque', 'read', 'videos', 'listarVideos');
    
    add_submenu_page(
        '', //parent slug
        'Adicionar Vídeo', //page title
        'Adicionar Vídeo', //menu title
        'read', //capability
        'addVideo', //menu slug
        'adicionarVideo' //function
    );
    
    add_submenu_page(
        '', //parent slug
        'Editar Vídeo', //page title
        'Editar Vídeo', //menu title
        'read', //capability
        'editVideo', //menu slug
        'editarVideo' //function
    );
}

// Inserir todos os includes, CSS e JS nesse arquivo
require_once( plugin_dir_path(__FILE__) . 'includes.php');