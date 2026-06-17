<?php
/**
 * Envio manual de TEXTO - botão na tabela (sem PDF)
 */
session_name('mka');
session_start();

$host = "127.0.0.1";
$usuario = "root";
$senha = "vertrigo";
$db = "mkradius";
$con = new mysqli($host, $usuario, $senha, $db);
if ($con->connect_error) die("Erro DB: " . $con->connect_error);

$logFile = '/tmp/Recibo_Whatsapp/log_pagamentos.txt';

// Config API
$cfg = $con->query("SELECT ip, token, instancia, provedor FROM config_recibo_zap LIMIT 1");
if (!$cfg || $cfg->num_rows == 0) die("Erro: config não encontrada.");
$c = $cfg->fetch_assoc();
$parts = explode(':', $c['ip']);
$ip = $parts[0];
$porta = $parts[1] ?? '8080';
$token = $c['token'];
$instanceName = $c['instancia'];
$nomeprovedor = $c['provedor'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['id']) || empty($_POST['login'])) {
    die("Requisição inválida.");
}

$id = (int)$_POST['id'];
$login = $_POST['login'];

// Busca recibo
$recibo = $con->query("SELECT * FROM recibo_pago_config WHERE id = $id")->fetch_assoc();
if (!$recibo) die("Recibo não encontrado.");

// Busca cliente
$stmt = $con->prepare("SELECT nome, celular, cpf_cnpj, email, plano FROM sis_cliente WHERE login = ?");
$stmt->bind_param('s', $login);
$stmt->execute();
$cliente = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$cliente) die("Cliente não encontrado.");

$celular = formatarNumero($cliente['celular']);
if (!$celular || strlen($celular) < 12) die("⚠️ Número inválido: " . $cliente['celular']);

// Formata CPF/CNPJ
$cpfF = formatarCpfCnpjCompleto($cliente['cpf_cnpj']);

// Datas formatadas
$datapagF = date('d/m/Y', strtotime($recibo['datapag']));
$datavencF = date('d/m/Y', strtotime($recibo['datavenc']));
$valorpagF = number_format($recibo['valorpag'], 2, ',', '.');
$valorF = number_format($recibo['valor'], 2, ',', '.');

// Busca template de mensagem do banco
$mensagem = obterTemplateMensagem($con);

// Substitui variáveis
$mensagem = str_replace(
    ['{nome}', '{cpfCnpj}', '{id}', '{datavenc}', '{datapag}', '{valorpag}', '{formapag}', '{nomeprovedor}', '{login}', '{plano}', '{valor}', '{email}'],
    [$cliente['nome'], $cpfF, $id, $datavencF, $datapagF, $valorpagF, $recibo['formapag'], $nomeprovedor, $login, $cliente['plano'] ?? '', $valorF, $cliente['email'] ?? ''],
    $mensagem
);

// Envia mensagem de texto via Evolution API
if (enviarTextoEvolutionAPI($celular, $mensagem, $ip, $porta, $instanceName, $token)) {
    $con->query("UPDATE recibo_pago_config SET envio = 1, data_envio = NOW() WHERE id = $id");

    $msg = "💬 Texto manual - Título: $id";
    $ins = $con->prepare("INSERT INTO sis_enviadas (login, mensagem, data, tipo, xto) VALUES (?, ?, NOW(), 'app', ?)");
    if ($ins) { $ins->bind_param('sss', $login, $msg, $celular); $ins->execute(); $ins->close(); }

    escreverLog("✅ Texto manual: " . $cliente['nome'] . " ($celular) - Título: $id");

    echo "<script>window.location.href='index.php?msg=texto_ok&nome=" . urlencode($cliente['nome']) . "';</script>";
} else {
    escreverLog("❌ Falha texto manual: " . $cliente['nome'] . " ($celular)");
    echo "<script>window.location.href='index.php?msg=texto_erro';</script>";
}

$con->close();

// === FUNÇÕES ===

function obterTemplateMensagem($con) {
    $msg_padrao = "✅ *Recebemos seu Pagamento*\n\n"
        . "👤 *Cliente*: {nome}\n"
        . "📑 {cpfCnpj}\n"
        . "📄 *Número do título*: {id}\n"
        . "📅 *Vencimento*: {datavenc}\n"
        . "✅ *Recebido em*: {datapag}\n"
        . "💸 *Valor do pagamento*: R$ {valorpag}\n"
        . "💳 *Forma de pagamento*: {formapag}\n"
        . "••••••••••••••••••••••••••••••••••\n"
        . "*Atenciosamente*\n"
        . "*{nomeprovedor}, Obrigado* 🤝\n"
        . "••••••••••••••••••••••••••••••••••\n"
        . "Mensagem automática. não é necessário responder.";

    $result = $con->query("SELECT mensagem FROM config_recibo_msg WHERE id = 1");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['mensagem'];
    }
    return $msg_padrao;
}

function formatarNumero($numero) {
    $numero = preg_replace('/\D/', '', $numero);
    if (strlen($numero) == 10) $numero = '55' . substr($numero, 0, 2) . '9' . substr($numero, 2);
    elseif (strlen($numero) == 11) $numero = '55' . $numero;
    elseif (strlen($numero) == 12) $numero = substr($numero, 0, 4) . '9' . substr($numero, 4);
    elseif (strlen($numero) != 13) $numero = '55' . $numero;
    return $numero;
}

function formatarCpfCnpjCompleto($cpfCnpj) {
    $cpfCnpj = preg_replace('/\D/', '', $cpfCnpj);
    if (strlen($cpfCnpj) === 11) return substr($cpfCnpj, 0, 3) . '.' . substr($cpfCnpj, 3, 3) . '.' . substr($cpfCnpj, 6, 3) . '-' . substr($cpfCnpj, 9, 2);
    if (strlen($cpfCnpj) === 14) return substr($cpfCnpj, 0, 2) . '.' . substr($cpfCnpj, 2, 3) . '.' . substr($cpfCnpj, 5, 3) . '/' . substr($cpfCnpj, 8, 4) . '-' . substr($cpfCnpj, 12, 2);
    return $cpfCnpj;
}

function enviarTextoEvolutionAPI($celular, $mensagem, $ip, $porta, $instanceName, $token) {
    global $logFile;
    $apiURL = "http://{$ip}:{$porta}/message/sendText/{$instanceName}";
    
    // Formato Evolution API v1.x
    $data = json_encode([
        "number" => $celular,
        "options" => [
            "delay" => 1200,
            "presence" => "composing"
        ],
        "textMessage" => [
            "text" => $mensagem
        ]
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["apikey: $token", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 && $httpCode !== 201) {
        file_put_contents($logFile, "[" . date('d/m/Y H:i:s') . "] ⚠️ Texto não enviado HTTP $httpCode: " . substr($response, 0, 300) . "\n", FILE_APPEND);
    }
    
    return ($httpCode === 200 || $httpCode === 201);
}

function escreverLog($mensagem) {
    global $logFile;
    file_put_contents($logFile, "[" . date('d/m/Y H:i:s') . "] $mensagem\n", FILE_APPEND);
}
?>
