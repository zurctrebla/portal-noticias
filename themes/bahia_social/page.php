<?php
if(is_mobile()){
    include("page_mobile.php");
} else {
    include("page_web.php");
}
?>