<?php
/* LEVI VASCONCELOS */

/* POST TYPE: post */
function revcon_change_post_label() {
    global $menu;
    global $submenu;
    $menu[5][0] = 'Levi Vasconcelos';
    //$submenu['edit.php'][5][0] = 'Posts';
    //$submenu['edit.php'][10][0] = 'Adicionar';
    //$submenu['edit.php'][16][0] = 'Tags';
    //echo '';
}
add_action( 'admin_menu', 'revcon_change_post_label' );
/**/ 

/**/ 
function revcon_change_post_object() {
    global $wp_post_types;
    $labels = &$wp_post_types['post']->labels;
    $labels->name = 'Levi Vasconcelos';
    $labels->singular_name = 'Post';
    //$labels->add_new = 'Adicionar';
    //$labels->add_new_item = 'Adicionar';
    //$labels->edit_item = 'Editar';
    //$labels->new_item = 'Novo';
    //$labels->view_item = 'Ver';
    //$labels->search_items = 'Procurar';
    //$labels->not_found = 'Não encontrado';
    //$labels->not_found_in_trash = 'Nada encontrado na lixeira';
    //$labels->all_items = 'Todos';
    $labels->menu_name = 'Levi Vasconcelos';
    //$labels->name_admin_bar = 'Posts';
    
    $wp_post_types['post']->label = 'Levi Vasconcelos';
}
 
add_action( 'init', 'revcon_change_post_object' );
/**/