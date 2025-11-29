<?php
/* Plugin Name: Coberturas do Bahia.ba
 * Description: Plugin para cadastrar as coberturas do Bahia.ba
 * Plugin URI: coberturas.bahia.ba
 * Author: Romário Carvalho
 * Author URI: bahia.ba
 * Version: 1.0
 * License: GPL2
 */

register_activation_hook(__FILE__, 'activateCoberturas');

function activateCoberturas() {
    global $wpdb;

    $sql = "CREATE TABLE IF NOT EXISTS `wp_coberturas` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `titulo` VARCHAR(55) NOT NULL,
                `chamada` VARCHAR(55) NOT NULL,
                `descricao` VARCHAR(500) NULL,
                `data_criacao` DATETIME NULL,
                `data_ativacao` DATETIME NULL,
                `data_desativacao` DATETIME NULL,
                `data_exclusao` DATETIME NULL,
                `status` SMALLINT(1) NOT NULL,
            PRIMARY KEY (`id`));";
    
    $wpdb->query($sql);
}

function acf_get_coberturas( $field ) {
    
    global $wpdb;
            
    $sql = "SELECT ";
    $sql .= "   id, ";
    $sql .= "   titulo ";
    $sql .= "FROM wp_coberturas ";
    $sql .= "WHERE status IN (0,1) ";
    $sql .= "ORDER BY data_criacao ";

    $objCobertura = $wpdb->get_results($sql);
    
    if($objCobertura) {
    
        foreach ($objCobertura as $obj) {
            $data_from_database[$obj->id] = $obj->titulo;
        }

        $field['choices'] = array();

        foreach($data_from_database as $field_key => $field_value) {
            $field['choices'][$field_key] = $field_value;
        }
    }
    
    return $field;
}
add_filter('acf/load_field/name=cobertura', 'acf_get_coberturas');


add_action('admin_menu', 'add_menu_coberturas');

function add_menu_coberturas() {
    add_menu_page('Coberturas', 'Coberturas', 'read', 'coberturas', 'listarCoberturas');
    
    add_submenu_page(
        '', //parent slug
        'Adicionar Cobertura', //page title
        'Adicionar Cobertura', //menu title
        'read', //capability
        'addCobertura', //menu slug
        'adicionarCobertura' //function
    );
    
    add_submenu_page(
        '', //parent slug
        'Editar Cobertura', //page title
        'Editar Cobertura', //menu title
        'read', //capability
        'editCobertura', //menu slug
        'editarCobertura' //function
    );
}

// Inserir todos os includes, CSS e JS nesse arquivo
require_once( plugin_dir_path(__FILE__) . 'includes.php');
