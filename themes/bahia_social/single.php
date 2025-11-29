<?php
if(is_mobile()){
    include("single_mobile.php");
} else {
    include("single_web.php");
}
?>