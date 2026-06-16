

<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$link = mysqli_connect("127.0.0.1", "root", "vertrigo", 'mkradius');

if (!$link) {
    die("Falha na conexão: " . mysqli_connect_error());
}


$usuario_logado = isset($_SESSION['MKA_Usuario']) ? $_SESSION['MKA_Usuario'] : (isset($_SESSION['MM_Usuario']) ? $_SESSION['MM_Usuario'] : null);

$permissao = "perm_relFat";

if ($usuario_logado) {
    $query_permissao = mysqli_query($link, "SELECT usuario FROM sis_perm WHERE nome = '$permissao' AND usuario = '$usuario_logado' AND permissao = 'sim'");

    if ($query_permissao) {
        $liberar_permissao = mysqli_num_rows($query_permissao);
        $acesso_permitido = $liberar_permissao >= 1;
    } else {
        echo "Erro na consulta de permissão: " . mysqli_error($link);
        $acesso_permitido = false;
    }
} else {
    $acesso_permitido = false;
}

$ext_mk = (file_exists("../../index.hhvm")) ? '.hhvm' : '.php';
?>

