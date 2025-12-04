<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/wp-load.php');

global $wpdb;

$posicao = null;
$dataInicio = null;
$dataFim = null;
if (isset($_GET['posicao']) && $_GET['posicao'] != "") {
    $posicao = $_GET['posicao'];
}
if (isset($_GET['dataInicio']) && $_GET['dataInicio'] != "") {
    $dataInicio = str_replace('/', '-', $_GET['dataInicio']);
    $dataInicio = date("Y-m-d", strtotime($dataInicio));
}
if (isset($_GET['dataFim']) && $_GET['dataFim'] != "") {
    $dataFim = str_replace('/', '-', $_GET['dataFim']);
    $dataFim = date("Y-m-d", strtotime($dataFim));
}

$sql = "SELECT ";
$sql .= "   p.post_title, ";
$sql .= "   p.ID, ";
$sql .= "   hd.data_inicio, ";
$sql .= "   hd.data_fim, ";
$sql .= "   TIMEDIFF(hd.data_fim, hd.data_inicio) as ativo, ";
$sql .= "   pd.descricao ";
$sql .= "FROM wp_historico_destaques hd ";
$sql .= "INNER JOIN wp_posicao_destaque pd ON hd.posicao_id = pd.id ";
$sql .= "INNER JOIN wp_posts p ON p.id = hd.post_id ";
$sql .= "WHERE ";
$sql .= "   data_fim IS NOT NULL ";
if ($posicao) {
    $sql .= "AND hd.posicao_id = {$posicao} ";
}
if ($dataInicio && $dataFim) {
    $sql .= "AND DATE(data_inicio) <= '{$dataFim}' ";
    $sql .= "AND DATE(data_fim) >= '{$dataInicio}' ";
}
$sql .= "ORDER BY hd.data_inicio ";

$objHistoricoDestaques = $wpdb->get_results($sql);

$data = array();
if($objHistoricoDestaques) {
    foreach ($objHistoricoDestaques as $obj) {
        $data[] = array(
                        utf8_decode($obj->post_title),
                        utf8_decode(post_label($obj->ID)),
                        utf8_decode($obj->data_inicio),
                        utf8_decode($obj->data_fim),
                        utf8_decode($obj->ativo),
                        utf8_decode($obj->descricao),
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
fputcsv($file, array(utf8_decode('Título'), utf8_decode('Editoria'), utf8_decode('Data Início'), utf8_decode('Data Fim'), utf8_decode('Tempo Ativo'), utf8_decode('Posição')), ";");

foreach ($data as $row) {
    fputcsv($file, $row, ";");
}

fclose($file);

exit();