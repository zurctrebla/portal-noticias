<?php
add_action('plugins_loaded', function() {
    if (!defined('ADROTATE_VERSION')) {
        if (!function_exists('adrotate_group')) {
            function adrotate_group($id = 0) { return ''; }
        }
        if (!function_exists('adrotate_ad')) {
            function adrotate_ad($id = 0) { return ''; }
        }
        if (!function_exists('adrotate_location')) {
            function adrotate_location($id = 0) { return ''; }
        }
    }
}, 1);
