<?php
if(is_mobile()){
    include("page-ultimas-noticias-mobile.php");
} else {
    include("page-ultimas-noticias-web.php");
}
?>