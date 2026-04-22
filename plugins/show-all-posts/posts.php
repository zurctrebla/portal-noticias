<?php
/*
 * Plugin Name: Posts do Dia
 * Description: Exibe todos os posts do dia independente do post-type
 * Plugin URI: bahia.ba
 * Author: Romário Carvalho
 * Author URI: bahia.ba
 * Version: 1.0
 * License: GPL2
 */

if (is_admin()) {
    new Show_All_Posts();
}

class Show_All_Posts {

    /**
     * Construtor irá criar o item de menu
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu_list_all_posts'));
    }

    /**
     * Item de menu nos permitirá carregar a página para visualizar a tabela
     */
    public function add_menu_list_all_posts() {
        add_menu_page('Posts do Dia', 'Posts do Dia', 'read', 'list_all_posts.php', array($this, 'list_table_page'));
    }

    /**
     * Exibe a página com a tabela
     * @return Void
     */
    public function list_table_page() {
        $listTable = new List_Table();
        $listTable->prepare_items();
        ?>
        <div class="wrap">
            <div id="icon-users" class="icon32"></div>
            <h2>Posts do Dia</h2>
            <?php $listTable->views(); ?>
            <?php $listTable->display(); ?>
        </div>
        <?php
    }

}

// WP_List_Table não é carregado automaticamente, assim nós precisamos carregá-lo em nossa aplicação
if (!class_exists('WP_List_Table')) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Cria uma nova classe para a tabela que irá estender o WP_List_Table
 */
class List_Table extends WP_List_Table {
    
    private $dia;
    private $mes;
    private $ano;
    private $post_type;
    
    public function __construct($args = array()) {
        parent::__construct($args);
        
        $type = array('bahia','brasil','digital','economia','emprego','entretenimento','esporte','gente','justica','politica','memoria','mundo','saude','servicos','ssa','meioambiente', 'carnaval2016', 'carnaval2017', 'carnaval2018', 'eleicoes2018', 'entrevista', 'pipocou', 'exclusivo', 'eleicoes2016', 'municipios', 'salvador');
        
        $this->dia = date("d");
        $this->mes = date("m");
        $this->ano = date("Y");
        $this->post_type = $type;
    }

    function get_views() {
        $views = array();
        $current = (!empty($_REQUEST['post_status']) ? $_REQUEST['post_status'] : 'all');

        $class = ($current == 'all' ? ' class="current"' : '');
        $all_url = remove_query_arg(array('paged', 'post_status'));
        $views['all'] = "<a href='{$all_url}' {$class} >Tudo <span class='count'>({$this->count_posts('any')})</span></a>";

        $publish_url = add_query_arg(array('post_status' => 'publish', 'paged' => 1));
        $class = ($current == 'publish' ? ' class="current"' : '');
        $views['publish'] = "<a href='{$publish_url}' {$class} >Publicados <span class='count'>({$this->count_posts('publish')})</span></a>";

        $future_url = add_query_arg(array('post_status' => 'future', 'paged' => 1));
        $class = ($current == 'future' ? ' class="current"' : '');
        $views['future'] = "<a href='{$future_url}' {$class} >Agendados <span class='count'>({$this->count_posts('future')})</span></a>";
        
        $private_url = add_query_arg(array('post_status' => 'private', 'paged' => 1));
        $class = ($current == 'private' ? ' class="current"' : '');
        $views['private'] = "<a href='{$private_url}' {$class} >Privados <span class='count'>({$this->count_posts('private')})</span></a>";

        $draft_url = add_query_arg(array('post_status' => 'draft', 'paged' => 1));
        $class = ($current == 'draft' ? ' class="current"' : '');
        $views['draft'] = "<a href='{$draft_url}' {$class} >Rascunhos <span class='count'>({$this->count_posts('draft')})</span></a>";

        return $views;
    }

    /**
     * Prepara os itens para serem processados na tabela
     *
     * @return Void
     */
    public function prepare_items() {
        $data = $this->table_data();
        $perPage = 20;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page' => $perPage
        ));

        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);

        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Define as colunas para usar em sua tabela
     *
     * @return Array
     */
    public function get_columns() {
        $columns = array(
            'title' => 'Título',
            'status' => 'Status',
            'date' => 'Data',
            'type' => 'Editoria',
            'author' => 'Autor',
            'tags' => 'Tags'
        );

        return $columns;
    }

    /**
     * Obtém os dados da tabela
     *
     * @return Array
     */
    private function table_data() {
        $data = array();

        $post_status = (!empty($_REQUEST['post_status']) ? $_REQUEST['post_status'] : 'any');
		
        $args = array(
            'post_type' => $this->post_type,
            'orderby' => 'post_date',
            'day'     => $this->dia,
            'month'     => $this->mes,
            'year'     => $this->ano,
            'order' => 'DESC',
            'post_status' => $post_status,
            'showposts' => $this->count_posts($post_status)
        );

        query_posts($args);

        while (have_posts()): the_post();
            $id = get_the_ID();

            $title = "<a class='row-title' href='" . get_edit_post_link($id) . "' title='Editar “" . get_the_title() . "”'>" . get_the_title() . "</a>";

            $tags = tags();
            if ($tags == "") {
                $tags = "<span aria-hidden='true'>—</span>";
            }

            if (get_post_status($id) == "future") {
                $status = "Agendado";
            } else if (get_post_status($id) == "publish") {
                $status = "Publicado";
            } else if (get_post_status($id) == "draft") {
                $status = "Rascunho";
            } else if (get_post_status($id) == "private") {
                $status = "Privado";
            } else if (get_post_status($id) == "pending") {
                $status = "Revisão Pendente";
            } else {
                $status = "Não definido";
            }

            $data[] = array(
                'title' => $title,
                'author' => get_the_author(),
                'tags' => $tags,
                'date' => get_the_date('d/m/Y \à\s H\hi', $id),
                'type' => post_label($id),
                'status' => $status
            );

        endwhile;

        return $data;
    }

    /**
     * Define quais dados serão mostrados em cada coluna da tabela
     *
     * @param  Array $item        Data
     * @param  String $column_name - Nome da coluna atual
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'title':
            case 'author':
            case 'tags':
            case 'date':
            case 'type':
            case 'status':
                return $item[$column_name];

            default:
                return print_r($item, true);
        }
    }

    function count_posts($post_status = 'any') {
        global $wpdb;

        $today = date('Y-m-d');

        $query = "SELECT * FROM wp_posts WHERE DATE(post_date) = '{$today}' AND post_type IN ('" . implode("','", $this->post_type) . "') ";
        
        if($post_status != 'any') {
            $query .= " AND post_status = '{$post_status}'";
        } else {
            $query .= " AND post_status IN ('publish',  'draft',  'future', 'private')";
        }
        
        $results = $wpdb->get_results($query);
        return count($results);
    }

}
?>