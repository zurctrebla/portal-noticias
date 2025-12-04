<?php
/*
 * Plugin Name: Push Notifications Bahia.ba
 * Description: Plugin para enviar notificações via push para o aplicativo mobile do Bahia.ba
 * Plugin URI: push.bahia.ba
 * Author: Romário Carvalho
 * Author URI: bahia.ba
 * Version: 1.0
 * License: GPL2
 */

register_activation_hook(__FILE__, 'activate');
add_action('add_meta_boxes', 'adding_meta_box');

 function activate() {
    global $wpdb;
    
    $sql = "CREATE TABLE IF NOT EXISTS `wp_push_token` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `token` VARCHAR(1000) NOT NULL,
                `os` VARCHAR(45) NOT NULL,
                `active` TINYINT(1) NOT NULL,
                `created_date` DATETIME NOT NULL,
                PRIMARY KEY (`id`));";
    $wpdb->query($sql);
    
    $sql = "CREATE TABLE IF NOT EXISTS `wp_push_sent` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `post_id` BIGINT(20) UNSIGNED NOT NULL,
                `send_date` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `fk_wp_push_sent_wp_posts_idx` (`post_id` ASC),
            CONSTRAINT `fk_wp_push_sent_wp_posts`
                FOREIGN KEY (`post_id`)
                REFERENCES `wp_posts` (`ID`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION);";
    $wpdb->query($sql);
    
    $sql = "CREATE TABLE IF NOT EXISTS `wp_push_viewed` (
                `id` INT NOT NULL AUTO_INCREMENT,
                `push_id` INT NOT NULL,
                `token_id` INT NOT NULL,
                `viewed_date` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            INDEX `fk_wp_push_viewed_wp_push_sent_idx` (`push_id` ASC),
            INDEX `fk_wp_push_viewed_wp_push_tokens1_idx` (`token_id` ASC),
            CONSTRAINT `fk_wp_push_viewed_wp_push_sent`
                FOREIGN KEY (`push_id`)
                REFERENCES `wp_push_sent` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION,
            CONSTRAINT `fk_wp_push_viewed_wp_push_tokens1`
                FOREIGN KEY (`token_id`)
                REFERENCES `wp_push_token` (`id`)
                ON DELETE NO ACTION
                ON UPDATE NO ACTION);";
    $wpdb->query($sql);
 }

function adding_meta_box() {
    get_ajax();
    global $POST_TYPES;

    foreach ($POST_TYPES as $type) {

        $action = "new";
        if (isset($_GET['action'])) {
            $action = $_GET['action'];
        }

        if ($action === "edit") {
            add_meta_box('push-notification-box', 'Notificação', 'render_meta_box', $type, 'side', 'high');
        }
    }
}

function render_meta_box() {
?>   
    <p>Enviar notificação desta notícia para o aplicativo do Bahia.ba</p>
    <button type="button" class="button-primary" id="btnSender">
        Enviar
    </button>
<?php
}

function get_ajax() {
    $url = plugin_dir_url( __FILE__ ) . 'push.php';
    $idPost = get_the_ID();
?>
    <script src="<?php bloginfo('template_url'); ?>/semantic-ui/dist/jquery-2.2.3.min.js"></script>

    <script>
        $(document).ready(function () {
            $("#btnSender").click(function () {
                if(confirm("Confirma o envio da notificação para o aplicativo do Bahia.ba?")) {
                    $.ajax({
                        method: "POST",
                        url: '<?=$url?>',
                        data: {idPost: <?=$idPost?>}
                    }).done(function (dados) {
                        alert("Notificação enviada!");

                    }).fail(function (jqXHR, textStatus) {
                        alert("Request failed: " + textStatus);
                    });
                }
            });

        });
    </script>

<?php
}