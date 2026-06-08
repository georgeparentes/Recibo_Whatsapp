<?php
require 'config.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = $_POST['login'] ?? '';
    $campo = $_POST['campo'] ?? '';
    $valor = $_POST['valor'] ?? '';

    // Segurança básica
    if ($campo === 'zap' && in_array($valor, ['sim', 'nao']) && !empty($login)) {
        $stmt = $link->prepare("UPDATE sis_cliente SET zap = ? WHERE login = ?");
        $stmt->bind_param("ss", $valor, $login);

        if ($stmt->execute()) {
            echo "ok";
        } else {
            echo "Erro ao atualizar: " . $stmt->error;
        }
    } else {
        echo "Requisição inválida.";
    }
} else {
    echo "Método não permitido.";
}

mysqli_close($link);
?>
