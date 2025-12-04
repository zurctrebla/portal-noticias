<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

$args = array();
foreach ($_POST as $key => $value) {
    if($key != 'editoria' || $key != 'lastDate') {
        if($key === "post_type" || $key === "author__in" || $key === "post__not_in"  || $key === "post__in"){
            $args[$key] = explode(',', $value);
        } else {
            $args[$key] = $value;
        }
    }
}

if($_POST['editoria'] == "true") {
    $editoria = true;
} else {
    $editoria = false;
}

if(isset($_POST['lastDate'])) {
    $lastDate = $_POST['lastDate'];
}

$ret_ids = array();
$news_ids = $args['post__not_in'];
unset($args['post__not_in']);
$query = new WP_Query($args);
$posts_per_page = $args['showposts'];

for ($i = 0; $i <= count($query->posts) && $i < $posts_per_page; $i++) {
    if (isset($query->posts[$i]->ID) && ! in_array( $query->posts[$i]->ID, $news_ids)) {
        $id = $query->posts[$i]->ID;
        $ret_ids[] = $id;
        
		$img = as3cf_get_attachment_url(get_post_meta($id, 'imagem', true));
		$exclusivo = get_post_meta($id, 'exclusivo', true);
		
        if(isset($args['meta_key']) && $args['meta_key'] == 'exclusivo') {
            $exclusivo = false;
        }
        
        $spanCategoria = "";
        $divChamada = "";
        if(!$editoria) {
            if(get_post_type($id) == "especial" || get_post_type($id) == "exclusivo" || get_post_type($id) == "entrevista" || $exclusivo) {
                if(is_mobile()) {
                    $divChamada = "divChamadaPostTypeMobile";
                } else {
                    $divChamada = "divChamadaPostType";
                }
            }
        }
        
        if (get_post_type($id) == "especial") {
            $spanCategoria = "spanEspecial";
        }
    
        if (get_post_type($id) == "exclusivo") {
            $spanCategoria = "spanExclusivo";
        }
    
        if((int) get_post_field('post_author', $id) == 17 || (int) get_post_field('post_author', $id) == 58) {
            $spanCategoria = "spanLevi";
        }
        
        if(is_search() && isset($_POST['lastDate'])) {
            if($lastDate != get_the_date('d \d\e F \d\e Y', $id)){
                $lastDate = get_the_date('d \d\e F \d\e Y', $id);
                echo "<input type='hidden' class='lastDate' value='".$lastDate."'>";
                printDateSearch($lastDate);
            }
        }
        
        if(is_mobile()) {
            showLinePostMobile($id, $img, $divChamada, $spanCategoria, $exclusivo, $editoria);
        } else {
            showLinePostWeb($id, $img, $divChamada, $exclusivo, $editoria);
        }

    }
}

echo ">>>" . implode(',', $ret_ids) . ">>>" . count($ret_ids);
