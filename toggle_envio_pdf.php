<?php
include('addons.class.php');
session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) {
    http_response_code(403);
    exit('Acesso negado');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

include('config.php');

$valor = ($_POST['envio_pdf_ativo'] ?? '') === 'sim' ? 'sim' : 'nao';

$link->query("UPDATE config_recibo_zap SET envio_pdf_ativo = '$valor' WHERE id = 1");

echo json_encode(['status' => 'ok', 'envio_pdf_ativo' => $valor]);
