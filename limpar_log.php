<?php
$logFile = '/tmp/Recibo_Whatsapp/log_pagamentos.txt';
if (file_exists($logFile)) {
    file_put_contents($logFile, ''); 
    echo "<script>alert('Log limpo com sucesso!');</script>";
} else {
    echo "<script>alert('Arquivo de log não encontrado.');</script>";
}

echo "<script>window.location.href = '{$_SERVER['HTTP_REFERER']}';</script>";
exit;
?>
