<?php
/**
 * Envio de PDF via WhatsApp - OTIMIZADO
 * Busca todos os pendentes de uma vez, gera e envia direto.
 * Cron recomendado: a cada 2 minutos
 */

$host = "127.0.0.1";
$usuario = "root";
$senha = "vertrigo";
$db = "mkradius";
$con = new mysqli($host, $usuario, $senha, $db);
if ($con->connect_error) die("Erro DB: " . $con->connect_error);

require_once __DIR__ . '/gerar_pdf.php';

$logFile = '/tmp/Recibo_Whatsapp/log_pagamentos.txt';

// Garante diretório existe
if (!is_dir('/tmp/Recibo_Whatsapp')) mkdir('/tmp/Recibo_Whatsapp', 0755, true);

// Config API
$cfg = $con->query("SELECT ip, token, instancia, provedor, ignorar_zap, envio_pdf_ativo FROM config_recibo_zap LIMIT 1");
if (!$cfg || $cfg->num_rows == 0) exit;
$c = $cfg->fetch_assoc();

// Verifica se o envio de PDF está ativo
$envio_pdf = $c['envio_pdf_ativo'] ?? 'sim';
$enviarPdf = ($envio_pdf === 'sim');

$parts = explode(':', $c['ip']);
$ip = $parts[0];
$porta = $parts[1] ?? '8080';
$token = $c['token'];
$instanceName = $c['instancia'];
$nomeprovedor = $c['provedor'];
$ignorarZap = strtolower($c['ignorar_zap']);

// Provedor
$prov = $con->query("SELECT nome, cnpj, fone FROM sis_provedor LIMIT 1");
$cnpjProv = '';
$foneProv = '';
if ($prov && $prov->num_rows > 0) {
    $p = $prov->fetch_assoc();
    $cnpjProv = $p['cnpj'] ?? '';
    $foneProv = $p['fone'] ?? '';
}

$dataHoje = date('Y-m-d');

// QUERY: busca recibos pendentes + dados do cliente
$sql = "SELECT r.id, r.login, r.datapag, r.datavenc, r.valor, r.valorpag, r.formapag, r.coletor,
               c.nome, c.celular, c.cpf_cnpj, c.zap, c.email, c.plano
        FROM recibo_pago_config r
        JOIN sis_cliente c ON r.login = c.login
        WHERE r.envio = 0 AND DATE(r.datapag) = '$dataHoje'
        ORDER BY r.id ASC";

$result = $con->query($sql);
if (!$result || $result->num_rows == 0) { 
    $con->close(); 
    exit; 
}

$enviados = 0;
$erros = 0;

while ($row = $result->fetch_assoc()) {
    $tituloId = $row['id'];
    $login = $row['login'];
    $nome = $row['nome'];
    $celular = formatarNumero($row['celular']);
    $zap = $row['zap'];

    // Verifica permissão
    if ($ignorarZap !== 'sim' && strtolower($zap) != 'sim') continue;

    // Verifica número válido
    if (!$celular || strlen($celular) < 12) {
        logUnico("⚠️ Número inválido: $nome ($celular)");
        continue;
    }

    // Gera PDF com dados do cliente
    $pdfPath = null;
    if ($enviarPdf) {
        $dadosPdf = [
            "nome" => $nome,
            "cpfCnpj" => formatarCpfCnpjCompleto($row['cpf_cnpj']),
            "email" => $row['email'] ?? '',
            "datavenc" => date('d/m/Y', strtotime($row['datavenc'])),
            "datapag" => date('d/m/Y', strtotime($row['datapag'])),
            "horapag" => date('H:i', strtotime($row['datapag'])),
            "valor" => number_format($row['valor'], 2, ',', '.'),
            "valorpag" => number_format($row['valorpag'], 2, ',', '.'),
            "coletor" => $row['coletor'],
            "formapag" => $row['formapag'],
            "plano" => $row['plano'] ?? '',
            "nomeprovedor" => $nomeprovedor,
            "cnpj_provedor" => $cnpjProv,
            "fone_provedor" => $foneProv
        ];

        $pdfPath = gerarPdfRecibo($tituloId, $dadosPdf);
        if (!$pdfPath) {
            escreverLog("❌ Falha gerar PDF: $nome (Título: $tituloId)");
            $erros++;
            continue;
        }
    }

    // Monta mensagem que vai como caption do PDF
    $cpfFormatado = formatarCpfCnpjCompleto($row['cpf_cnpj']);
    $datapagF = date('d/m/Y', strtotime($row['datapag']));
    $datavencF = date('d/m/Y', strtotime($row['datavenc']));
    $valorpagF = number_format($row['valorpag'], 2, ',', '.');
    $valorF = number_format($row['valor'], 2, ',', '.');

    // Busca template salvo no banco
    $caption = obterTemplateMensagem($con);
    // Substitui variáveis
    $caption = str_replace(
        ['{nome}', '{cpfCnpj}', '{id}', '{datavenc}', '{datapag}', '{valorpag}', '{formapag}', '{nomeprovedor}', '{login}', '{plano}', '{valor}', '{email}'],
        [$nome, $cpfFormatado, $tituloId, $datavencF, $datapagF, $valorpagF, $row['formapag'], $nomeprovedor, $login, $row['plano'] ?? '', $valorF, $row['email'] ?? ''],
        $caption
    );

    // Envia PDF + caption OU apenas mensagem de texto
    $enviou = false;
    if ($enviarPdf && $pdfPath) {
        $enviou = enviarPdfEvolutionAPI($celular, $pdfPath, $ip, $porta, $instanceName, $token, $caption);
    } else {
        $enviou = enviarTextoEvolutionAPI($celular, $caption, $ip, $porta, $instanceName, $token);
    }

    if ($enviou) {
        // Marca enviado com hora do envio
        $con->query("UPDATE recibo_pago_config SET envio = 1, data_envio = NOW() WHERE id = $tituloId");

        // Registra
        $tipo_envio = $enviarPdf ? '📄 PDF enviado' : '💬 Texto enviado';
        $msg = $con->real_escape_string("$tipo_envio: $nome ($celular) - Título: $tituloId");
        $con->query("INSERT INTO sis_enviadas (login, mensagem, data, tipo, xto) VALUES ('$login', '$msg', NOW(), 'app', '$celular')");

        escreverLog("✅ $tipo_envio: $nome ($celular) - Título: $tituloId");
        $enviados++;
    } else {
        logUnico("❌ Erro envio: $nome ($celular) - Título: $tituloId");
        $erros++;
    }
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

function escreverLog($mensagem) {
    global $logFile;
    file_put_contents($logFile, "[" . date('d/m/Y H:i:s') . "] $mensagem\n", FILE_APPEND);
}

function logUnico($mensagem) {
    global $logFile;
    $dataHoje = date('d/m/Y');
    if (file_exists($logFile)) {
        $linhas = array_slice(file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -50);
        foreach ($linhas as $linha) {
            if (strpos($linha, "[$dataHoje") !== false && strpos($linha, $mensagem) !== false) return;
        }
    }
    escreverLog($mensagem);
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
