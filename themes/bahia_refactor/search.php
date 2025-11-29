<?php
if(is_mobile()){
    include("search-mobile.php");
} else {
    include("search-web.php");
}
?>