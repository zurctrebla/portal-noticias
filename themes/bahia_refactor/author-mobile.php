<?php
get_header();

$default = false;
$news_ids = array();

// SE FOR LEVI A TELA É DIFERENTE DOS OUTROS
if ($author == 17 || $author == 58) {
    include("page-home-levi-mobile.php");
}
get_footer();