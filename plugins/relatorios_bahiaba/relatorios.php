<?php
/* Plugin Name: Relatórios do Bahia.ba
 * Description: Plugin para gerar relatórios do Bahia.ba
 * Plugin URI: rel.bahia.ba
 * Author: Romário Carvalho
 * Author URI: bahia.ba
 * Version: 1.0
 * License: GPL2
 */

register_activation_hook(__FILE__, 'activateRel');

function activateRel() {
    global $wpdb;

    $sql = "CREATE TABLE IF NOT EXISTS `wp_posicao_destaque` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `descricao` VARCHAR(100) NOT NULL,
            PRIMARY KEY (`id`));";
    $wpdb->query($sql);

    $exists = $wpdb->get_row("SELECT count(id) qtd FROM wp_posicao_destaque");

    if($exists->qtd == 0) {
        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_grande');";
        $wpdb->query($sql);

        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_pequeno_1');";
        $wpdb->query($sql);

        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_pequeno_2');";
        $wpdb->query($sql);

        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_pequeno_3');";
        $wpdb->query($sql);

        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_pequeno_4');";
        $wpdb->query($sql);

        $sql = "INSERT INTO `wp_posicao_destaque` (descricao) VALUES ('destaque_gigante');";
        $wpdb->query($sql);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `wp_historico_destaques` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `post_id` BIGINT(20) UNSIGNED NOT NULL,
                `data_inicio` DATETIME NOT NULL,
                `data_fim` DATETIME NULL,
                `posicao_id` INT NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `fk_posts_historico_destaques_idx` (`post_id` ASC),
            INDEX `fk_posicao_historico_destaques_idx` (`posicao_id` ASC),
            CONSTRAINT `fk_posicao_historico_destaques`
                FOREIGN KEY (`posicao_id`)
                REFERENCES `wp_posicao_destaque` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION,
            CONSTRAINT `fk_posts_historico_destaques`
                FOREIGN KEY (`post_id`)
                REFERENCES `wp_posts` (`ID`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION);";
    $wpdb->query($sql);
}

/* Ao salvar acf-options-home irá guardar o histórico de destaques */
function acfSaveDestaques() {
    $screen = get_current_screen();
    if (strpos($screen->id, "acf-options-home") == true) {
        
        $positions = array(1,2,3,4,5,6);
        
        foreach ($positions as $p) {
            saveHistoryDestaques($p);
        }
        
    }
}

// run before ACF saves the $_POST['acf'] data
add_action('acf/save_post', 'acfSaveDestaques', 20);

function saveHistoryDestaques($position) {
    
    global $wpdb;
    
    error_reporting(0);
    
    switch ($position) {
        case 1:
            $field = "slider_m1";
            break;
        case 2:
        case 3:
        case 4:
        case 5:
            $field = "semi_destaques_m1";
            break;
        case 6:
            $field = "destaques";
            break;
    }
    
    $acfDestaque = get_field($field, 'options');
    $objDbDestaque = $wpdb->get_row("SELECT id, post_id FROM wp_historico_destaques WHERE posicao_id = {$position} AND data_fim IS NULL");

    if($objDbDestaque){

        if($field == "semi_destaques_m1"){
            $i = $position - 2;
            
            if((int) $objDbDestaque->post_id != $acfDestaque[$i]->ID) {
                $wpdb->update('wp_historico_destaques',
                    array('data_fim' => current_time('mysql')),
                    array('id' => $objDbDestaque->id)
                );
                
                if(isset($acfDestaque[$i])) {
                    $wpdb->insert('wp_historico_destaques', array(
                        'post_id' => $acfDestaque[$i]->ID,
                        'data_inicio' => current_time('mysql'),
                        'posicao_id' => $position
                    ));
                }
            }
            
        } else {
            
            if((int) $objDbDestaque->post_id != $acfDestaque[0]->ID) {
                $wpdb->update('wp_historico_destaques',
                    array('data_fim' => current_time('mysql')),
                    array('id' => $objDbDestaque->id)
                );

                $wpdb->insert('wp_historico_destaques', array(
                    'post_id' => $acfDestaque[0]->ID,
                    'data_inicio' => current_time('mysql'),
                    'posicao_id' => $position
                ));
            }
        }
        
    } else {
        if($field == "semi_destaques_m1"){
            $i = $position - 2;
            
            if(isset($acfDestaque[$i])) {
                $wpdb->insert('wp_historico_destaques', array(
                    'post_id' => $acfDestaque[$i]->ID,
                    'data_inicio' => current_time('mysql'),
                    'posicao_id' => $position
                ));
            }
        } else {
            $wpdb->insert('wp_historico_destaques', array(
                'post_id' => $acfDestaque[0]->ID,
                'data_inicio' => current_time('mysql'),
                'posicao_id' => $position
            ));
        }
    }
}

add_action('admin_menu', 'add_menu_relatorios');

function add_menu_relatorios() {
    add_menu_page('Relatórios', 'Relatórios', 'manage_options', 'relatorios', 'obterRelatorios');
    
    add_submenu_page('', //parent slug
    'Histórico dos Destaques', //page title
    'Histórico dos Destaques', //menu title
    'manage_options', //capability
    'relHistoricoDestaques', //menu slug
    'showHistoricoDestaques'); //function
    
    add_submenu_page('', //parent slug
    'Notícias', //page title
    'Notícias', //menu title
    'manage_options', //capability
    'relNoticiasPorDia', //menu slug
    'showNoticiasPorDia'); //function
}

function obterRelatorios() {
?>
<div class="divRelatorios">
    
    <h2 class="ui header">Relatórios do Bahia.ba</h2>
    
    <h4 class="ui header">
        <i class="file text outline icon"></i>
        <div class="content">
            <a href="<?php echo admin_url('admin.php?page=relHistoricoDestaques'); ?>">Histórico dos Destaques</a>
        </div>
    </h4>
    <h4 class="ui header">
        <i class="file text outline icon"></i>
        <div class="content">
            <a href="<?php echo admin_url('admin.php?page=relNoticiasPorDia'); ?>">Notícias</a>
        </div>
    </h4>
    
            
</div>
    
<?php
}

// Inserir todos os includes, CSS e JS nesse arquivo
require_once( plugin_dir_path(__FILE__) . 'includes.php');