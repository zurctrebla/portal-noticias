<?php
if(is_mobile()){
    include("page-cobertura-mobile.php");
} else {
    include("page-cobertura-web.php");
}
?>