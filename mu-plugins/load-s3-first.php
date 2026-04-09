<?php
// Forçar carregamento do S3 antes de qualquer plugin
$s3_plugin = dirname(dirname(__FILE__)) . "/plugins/amazon-s3-and-cloudfront/wordpress-s3.php";
if (file_exists($s3_plugin) && !class_exists("Amazon_S3_And_CloudFront")) {
    require_once $s3_plugin;
}
