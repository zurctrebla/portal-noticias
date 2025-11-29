<?php
if(is_mobile()){
    include("archive-mobile.php");
} else {
    include("archive-web.php");
}
?>