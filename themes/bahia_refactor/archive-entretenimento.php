<?php
if (is_mobile()) {
    include('archive-entretenimento-mobile.php');
} else {
    include('archive-entretenimento-web.php');
}
