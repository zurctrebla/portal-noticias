<?php
if(is_mobile()){
    include("archive-municipios-mobile.php");
} else {
    include("archive-municipios-web.php");
}
?>