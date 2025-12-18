<?php
/**
 * Template Name: Quem Somos
 * Description: Página institucional Quem Somos
 */

if(is_mobile()){
    include("page-quem-somos-mobile.php");
} else {
    include("page-quem-somos-web.php");
}
?>
