<?php
if (is_mobile()) {
    include('archive-esporte-mobile.php');
} else {
    include('archive-esporte-web.php');
}
