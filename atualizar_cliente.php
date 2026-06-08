<?php
$link = mysqli_connect("127.0.0.1", "root", "vertrigo", "mkradius");
if (!$link) {
    die("Erro na conexão: " . mysqli_connect_error());
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {  
    $nome = mysqli_real_escape_string($link, $_POST['nome']);
    $campo = $_POST['campo'];  
    $valor = $_POST['valor']; 

    if ($campo == 'zap') {
        // Atualizar WhatsApp
        $update_zap = mysqli_prepare($link, "UPDATE sis_cliente SET zap = ? WHERE nome = ?");
        mysqli_stmt_bind_param($update_zap, 'ss', $valor, $nome);
        if (mysqli_stmt_execute($update_zap)) {
            echo "Atualizado!";
        } else {
            echo "Erro ao atualizar o WhatsApp.";
        }
    } elseif ($campo == 'telefone') {
        if ($valor === "") {
            $update_tel = mysqli_prepare($link, "UPDATE sis_cliente SET celular = NULL WHERE nome = ?");
            mysqli_stmt_bind_param($update_tel, 's', $nome);
        } else {
 
            $update_tel = mysqli_prepare($link, "UPDATE sis_cliente SET celular = ? WHERE nome = ?");
            mysqli_stmt_bind_param($update_tel, 'ss', $valor, $nome);
        }

        if (mysqli_stmt_execute($update_tel)) {
            echo "Telefone atualizado com sucesso!";
        } else {
            echo "Erro ao atualizar o telefone.";
        }
    } else {
        echo "Campo inválido.";
    }
} else {
    echo "Método de requisição inválido.";
}

mysqli_close($link);
?>
