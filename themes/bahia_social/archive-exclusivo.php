<?php
if(is_mobile()){
    include("archive-exclusivo-mobile.php");
} else {
    include("archive-exclusivo-web.php");
}
?>