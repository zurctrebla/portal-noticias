<?php
if (is_mobile()) {
    include('author-mobile.php');
} else {
    include('author-web.php');
}
