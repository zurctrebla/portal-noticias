<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

global $wpdb, $POST_TYPES;

$dataInicio = null;
$dataFim = null;

if (isset($_GET['dataInicio']) && $_GET['dataInicio'] != "") {
    $dataInicio = str_replace('/', '-', $_GET['dataInicio']);
    $dataInicio = date("Y-m-d", strtotime($dataInicio));
}

if (isset($_GET['dataFim']) && $_GET['dataFim'] != "") {
    $dataFim = str_replace('/', '-', $_GET['dataFim']);
    $dataFim = date("Y-m-d", strtotime($dataFim));
}

$sql = "SELECT ";
$sql .= "   ID, ";
$sql .= "   post_title, ";
$sql .= "   DATE(post_date) AS data_post, ";
$sql .= "   TIME(post_date) as hora_post ";
$sql .= "FROM wp_posts ";
$sql .= "WHERE ";
$sql .= "post_status = 'publish' ";

if ($dataInicio && $dataFim) {
    $sql .= "AND DATE(post_date) <= '{$dataFim}' ";
    $sql .= "AND DATE(post_date) >= '{$dataInicio}' ";
}
$sql .= "AND post_type IN ('".implode("','", $POST_TYPES)."')  ";
$sql .= "ORDER BY post_date ASC ";


$objNoticiasPorDia = $wpdb->get_results($sql);

$data = array();
if($objNoticiasPorDia) {
    foreach ($objNoticiasPorDia as $obj) {
        $data[] = array(
                        utf8_decode($obj->post_title),
                        utf8_decode(post_label($obj->ID)),
                        utf8_decode(date("d/m/Y", strtotime($obj->data_post))),
                        utf8_decode($obj->hora_post),
                        utf8_decode(get_permalink($obj->ID))
                    );
    }
}

$filename = 'report' . time() . '.csv';
header('Content-Encoding: UTF-8');
header("Content-type: text/csv; charset=UTF-8");
header('Content-Disposition: attachement; filename="'.$filename.'"');
header('Pragma: no-cache');
header('Expires: 0');

$file = fopen('php://output', 'w');
fputcsv($file, array(utf8_decode('Título'), utf8_decode('Editoria'), utf8_decode('Data'), utf8_decode('Horário'), utf8_decode('URL')), ";");

foreach ($data as $row) {
    fputcsv($file, $row, ";");
}

fclose($file);

exit();