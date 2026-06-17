<?php
/**
 * Envio manual de PDF - botão na tabela
 */
session_name('mka');
session_start();

$host = "127.0.0.1";
$usuario = "root";
$senha = "vertrigo";
$db = "mkradius";
$con = new mysqli($host, $usuario, $senha, $db);
if ($con->connect_error) die("Erro DB: " . $con->connect_error);

require_once __DIR__ . '/gerar_pdf.php';

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

// Provedor
$prov = $con->query("SELECT cnpj, fone FROM sis_provedor LIMIT 1");
$cnpjProv = '';
$foneProv = '';
if ($prov && $prov->num_rows > 0) {
    $pr = $prov->fetch_assoc();
    $cnpjProv = $pr['cnpj'] ?? '';
    $foneProv = $pr['fone'] ?? '';
}

// Gera PDF
$cpf = preg_replace('/\D/', '', $cliente['cpf_cnpj']);
if (strlen($cpf) === 11) $cpfF = substr($cpf,0,3).'.'.substr($cpf,3,3).'.'.substr($cpf,6,3).'-'.substr($cpf,9,2);
elseif (strlen($cpf) === 14) $cpfF = substr($cpf,0,2).'.'.substr($cpf,2,3).'.'.substr($cpf,5,3).'/'.substr($cpf,8,4).'-'.substr($cpf,12,2);
else $cpfF = $cpf;

$dadosPdf = [
    "nome" => $cliente['nome'],
    "cpfCnpj" => $cpfF,
    "email" => $cliente['email'] ?? '',
    "datavenc" => date('d/m/Y', strtotime($recibo['datavenc'])),
    "datapag" => date('d/m/Y', strtotime($recibo['datapag'])),
    "horapag" => date('H:i', strtotime($recibo['datapag'])),
    "valor" => number_format($recibo['valor'], 2, ',', '.'),
    "valorpag" => number_format($recibo['valorpag'], 2, ',', '.'),
    "coletor" => $recibo['coletor'],
    "formapag" => $recibo['formapag'],
    "plano" => $cliente['plano'] ?? '',
    "nomeprovedor" => $nomeprovedor,
    "cnpj_provedor" => $cnpjProv,
    "fone_provedor" => $foneProv
];

$pdfPath = gerarPdfRecibo($id, $dadosPdf);
if (!$pdfPath) die("❌ Erro ao gerar PDF.");

// Monta caption com a mensagem completa
$cpfF2 = $cpfF;
$datapagF = date('d/m/Y', strtotime($recibo['datapag']));
$datavencF = date('d/m/Y', strtotime($recibo['datavenc']));
$valorpagF = number_format($recibo['valorpag'], 2, ',', '.');

$caption = "✅ *Recebemos seu Pagamento*\n\n"
    . "👤 *Cliente*: " . $cliente['nome'] . "\n"
    . "📑 $cpfF2\n"
    . "📄 *Número do título*: $id\n"
    . "📅 *Vencimento*: $datavencF\n"
    . "✅ *Recebido em*: $datapagF\n"
    . "💸 *Valor do pagamento*: R$ $valorpagF\n"
    . "💳 *Forma de pagamento*: " . $recibo['formapag'] . "\n"
    . "••••••••••••••••••••••••••••••••••\n"
    . "*Atenciosamente*\n"
    . "*$nomeprovedor, Obrigado* 🤝\n"
    . "••••••••••••••••••••••••••••••••••\n"
    . "Mensagem automática. não é necessário responder.";
if (enviarPdfEvolutionAPI($celular, $pdfPath, $ip, $porta, $instanceName, $token, $caption)) {
    $con->query("UPDATE recibo_pago_config SET envio = 1, data_envio = NOW() WHERE id = $id");

    $msg = "📄 PDF manual - Título: $id";
    $ins = $con->prepare("INSERT INTO sis_enviadas (login, mensagem, data, tipo, xto) VALUES (?, ?, NOW(), 'app', ?)");
    if ($ins) { $ins->bind_param('sss', $login, $msg, $celular); $ins->execute(); $ins->close(); }

    escreverLog("✅ PDF manual: " . $cliente['nome'] . " ($celular) - Título: $id");

    echo "<!DOCTYPE html><html><head><meta charset='utf-8'>
    <style>
        body{font-family:'Segoe UI',sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;}
        .msg-box{background:white;border:1px solid #bbf7d0;border-radius:14px;padding:40px 50px;text-align:center;box-shadow:0 4px 12px rgba(0,0,0,0.06);}
        .msg-box .icon{font-size:48px;margin-bottom:12px;}
        .msg-box h3{color:#16a34a;font-size:18px;margin:0 0 6px;}
        .msg-box p{color:#64748b;font-size:14px;margin:0;}
    </style></head><body>
    <div class='msg-box'>
        <div class='icon'>✅</div>
        <h3>PDF enviado com sucesso!</h3>
        <p>" . htmlspecialchars($cliente['nome']) . "</p>
    </div>
    <script>setTimeout(function(){ window.location.href='index.php'; }, 2500);</script>
    </body></html>";
} else {
    escreverLog("❌ Falha manual: " . $cliente['nome'] . " ($celular)");
    echo "❌ Erro ao enviar. Verifique o log.";
}

$con->close();

function formatarNumero($numero) {
    $numero = preg_replace('/\D/', '', $numero);
    if (strlen($numero) == 10) $numero = '55' . substr($numero, 0, 2) . '9' . substr($numero, 2);
    elseif (strlen($numero) == 11) $numero = '55' . $numero;
    elseif (strlen($numero) == 12) $numero = substr($numero, 0, 4) . '9' . substr($numero, 4);
    elseif (strlen($numero) != 13) $numero = '55' . $numero;
    return $numero;
}

function escreverLog($mensagem) {
    global $logFile;
    file_put_contents($logFile, "[" . date('d/m/Y H:i:s') . "] $mensagem\n", FILE_APPEND);
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
?>
