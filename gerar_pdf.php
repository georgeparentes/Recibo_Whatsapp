<?php
/**
 * Gerador de PDF do Comprovante de Pagamento
 * Gera HTML localmente com layout idêntico ao MK-AUTH
 * Converte com wkhtmltopdf
 */

function valorPorExtenso($valor) {
    if (is_string($valor)) {
        $valor = floatval(str_replace(['.', ','], ['', '.'], $valor));
    }
    if ($valor == 0) return 'zero reais';
    
    $unidades = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove',
                 'dez', 'onze', 'doze', 'treze', 'quatorze', 'quinze', 'dezesseis', 'dezessete', 'dezoito', 'dezenove'];
    $dezenas = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
    $centenas = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];
    
    $inteiro = intval($valor);
    $centavos = round(($valor - $inteiro) * 100);
    $resultado = '';
    
    if ($inteiro > 0) {
        if ($inteiro == 100) {
            $resultado = 'cem';
        } elseif ($inteiro >= 1000) {
            $milhares = intval($inteiro / 1000);
            $resto = $inteiro % 1000;
            $resultado = ($milhares == 1) ? 'mil' : $unidades[$milhares] . ' mil';
            if ($resto > 0) {
                $resultado .= ' ';
                if ($resto < 100 || $resto % 100 == 0) $resultado .= 'e ';
                $c = intval($resto / 100);
                $d = $resto % 100;
                if ($resto == 100) { $resultado .= 'cem'; }
                else {
                    if ($c > 0) $resultado .= $centenas[$c];
                    if ($c > 0 && $d > 0) $resultado .= ' e ';
                    if ($d > 0 && $d < 20) $resultado .= $unidades[$d];
                    elseif ($d >= 20) {
                        $resultado .= $dezenas[intval($d / 10)];
                        if ($d % 10 > 0) $resultado .= ' e ' . $unidades[$d % 10];
                    }
                }
            }
        } else {
            $c = intval($inteiro / 100);
            $d = $inteiro % 100;
            if ($c > 0) $resultado .= $centenas[$c];
            if ($c > 0 && $d > 0) $resultado .= ' e ';
            if ($d > 0 && $d < 20) $resultado .= $unidades[$d];
            elseif ($d >= 20) {
                $resultado .= $dezenas[intval($d / 10)];
                if ($d % 10 > 0) $resultado .= ' e ' . $unidades[$d % 10];
            }
        }
        $resultado .= ($inteiro == 1) ? ' real' : ' reais';
    }
    
    if ($centavos > 0) {
        if ($inteiro > 0) $resultado .= ' e ';
        if ($centavos < 20) $resultado .= $unidades[$centavos];
        else {
            $resultado .= $dezenas[intval($centavos / 10)];
            if ($centavos % 10 > 0) $resultado .= ' e ' . $unidades[$centavos % 10];
        }
        $resultado .= ($centavos == 1) ? ' centavo' : ' centavos';
    }
    
    return $resultado;
}

/**
 * Gera o PDF do recibo
 */
function gerarPdfRecibo($tituloId, $dados = null) {
    $dirPdf = '/tmp/Recibo_Whatsapp/pdfs';
    if (!is_dir($dirPdf)) mkdir($dirPdf, 0755, true);
    
    $pdfFile = $dirPdf . '/recibo_' . $tituloId . '_' . time() . '.pdf';
    
    // Código de segurança
    $codigoSeg = strtoupper(substr(md5($tituloId . date('Ymd') . ($dados['nome'] ?? '')), 0, 16));
    $codigoSegF = implode('-', str_split($codigoSeg, 4));
    
    $valorExtenso = valorPorExtenso($dados['valorpag'] ?? '0,00');
    
    $html = montarHtmlRecibo([
        'id_titulo'     => $tituloId,
        'valorpag'      => $dados['valorpag'] ?? '0,00',
        'valorpagext'   => $valorExtenso,
        'nomecliente'   => $dados['nome'] ?? '',
        'cpfcliente'    => $dados['cpfCnpj'] ?? '',
        'emailcliente'  => $dados['email'] ?? '',
        'provedornome'  => $dados['nomeprovedor'] ?? '',
        'provedorcnpj'  => $dados['cnpj_provedor'] ?? '',
        'provedorfone'  => $dados['fone_provedor'] ?? '',
        'datapag'       => $dados['datapag'] ?? '',
        'horapag'       => $dados['horapag'] ?? '',
        'vencimento'    => $dados['datavenc'] ?? '',
        'planodeacesso' => $dados['plano'] ?? '',
        'formapag'      => $dados['formapag'] ?? '',
        'codigoseg'     => $codigoSegF,
    ]);
    
    $htmlFile = $dirPdf . '/recibo_' . $tituloId . '.html';
    file_put_contents($htmlFile, $html);
    
    $cmd = "/usr/bin/wkhtmltopdf"
        . " --quiet"
        . " --page-size A4"
        . " --margin-top 0mm"
        . " --margin-bottom 0mm"
        . " --margin-left 0mm"
        . " --margin-right 0mm"
        . " --encoding utf-8"
        . " --enable-local-file-access"
        . " --disable-javascript"
        . " " . escapeshellarg($htmlFile)
        . " " . escapeshellarg($pdfFile)
        . " 2>&1";
    
    exec($cmd, $output, $returnCode);
    @unlink($htmlFile);
    
    if ($returnCode === 0 && file_exists($pdfFile) && filesize($pdfFile) > 500) {
        return $pdfFile;
    }
    
    escreverLog("❌ wkhtmltopdf falhou título $tituloId (código: $returnCode) - " . implode(' ', $output));
    return false;
}

function montarHtmlRecibo($d) {
    return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>RECIBO</title>
<style>
@page { margin: 0; }
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: "Segoe UI", Arial, sans-serif;
    background: #fff;
    margin: 0;
    padding: 0;
    width: 210mm;
    min-height: 297mm;
}
.recibo-container {
    width: 100%;
    min-height: 297mm;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* HEADER */
.recibo-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #1e40af 100%);
    padding: 25px 40px 20px;
    text-align: center;
    border-bottom: 4px solid #60a5fa;
}
.logo-img { max-height: 90px; width: auto; margin: 0 auto 10px; display: block; }
.titulo-comprovante { font-size: 18px; font-weight: 800; letter-spacing: 0.15em; color: #fff; text-transform: uppercase; }
.codigo-pay { font-size: 10px; color: #93c5fd; font-family: monospace; margin-top: 5px; letter-spacing: 0.05em; }

/* BODY */
.recibo-body {
    flex: 1;
    padding: 35px 40px 20px;
}

/* VALOR */
.bloco-valor {
    text-align: center;
    padding: 25px 30px;
    margin: 0 auto 35px;
    max-width: 380px;
    border: 2px solid #bfdbfe;
    border-radius: 16px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
}
.label-valor { font-size: 11px; text-transform: uppercase; letter-spacing: 0.15em; font-weight: 800; color: #1d4ed8; margin-bottom: 8px; }
.valor-principal { font-size: 42px; font-weight: 900; color: #1d4ed8; line-height: 1; }
.valor-extenso { font-size: 12px; color: #3b82f6; font-style: italic; margin-top: 8px; font-weight: 600; }

/* PAGADOR / BENEFICIÁRIO */
.grid-duas-colunas {
    display: table;
    width: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    margin-bottom: 30px;
}
.coluna-info {
    display: table-cell;
    width: 50%;
    padding: 20px 25px;
    vertical-align: top;
    line-height: 1.8;
}
.coluna-info:last-child { border-left: 1px solid #e2e8f0; }
.label-secao { display: block; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 800; color: #1d4ed8; margin-bottom: 6px; }
.nome-principal { font-weight: 800; color: #0f172a; font-size: 15px; }
.info-secundaria { color: #475569; font-family: monospace; font-size: 11px; margin-top: 3px; }

/* DETALHES */
.secao-detalhes { margin-top: 10px; }
.titulo-detalhes { text-align: center; margin-bottom: 15px; font-size: 12px; text-transform: uppercase; font-weight: 800; color: #1d4ed8; letter-spacing: 0.12em; }
.detalhes-box {
    border-left: 4px solid #2563eb;
    padding: 18px 22px;
    background: #f8fafc;
    border-radius: 0 10px 10px 0;
}
.grid-tres-colunas {
    display: table;
    width: 100%;
    margin-bottom: 15px;
}
.grid-tres-colunas.linha-detalhe-3 {
    padding-top: 15px;
    border-top: 1px dashed #e2e8f0;
    margin-bottom: 0;
}
.detalhe-item {
    display: table-cell;
    width: 33.33%;
    vertical-align: top;
}
.detalhe-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 800; color: #1d4ed8; margin-bottom: 3px; display: block; }
.detalhe-valor { color: #0f172a; font-weight: 800; font-size: 13px; display: block; }

/* ASSINATURA */
.bloco-assinatura {
    margin-top: 50px;
    text-align: center;
    padding-bottom: 25px;
}
.linha-assinatura { width: 220px; border-bottom: 2px solid #334155; margin: 0 auto; }
.label-assinatura { font-size: 10px; color: #64748b; font-weight: 600; margin-top: 8px; }
.nome-assinatura { font-size: 12px; font-weight: 800; color: #0f172a; letter-spacing: 0.08em; text-transform: uppercase; margin-top: 4px; }

/* RODAPÉ */
.rodape-recibo {
    display: table;
    width: 100%;
    padding: 15px 40px;
    font-size: 9px;
    background: #f1f5f9;
    border-top: 1px solid #e2e8f0;
}
.texto-rodape { display: table-cell; vertical-align: middle; color: #475569; width: 60%; }
.autenticacao { display: table-cell; vertical-align: middle; text-align: right; color: #475569; width: 40%; line-height: 1.6; }
.autenticacao span { font-weight: 800; color: #1d4ed8; font-size: 10px; font-family: monospace; }
.barra-inferior { height: 6px; background: linear-gradient(90deg, #1e3a5f 0%, #2563eb 50%, #1e40af 100%); }
</style>
</head>
<body>
<div class="recibo-container">

<div class="recibo-header">
    <img class="logo-img" src="http://172.35.35.2/mkfiles/logo.jpg" alt="Logo">
    <div class="titulo-comprovante">COMPROVANTE DE PAGAMENTO</div>
    <div class="codigo-pay">PAY-' . htmlspecialchars($d['id_titulo']) . '</div>
</div>

<div class="recibo-body">
    <div class="bloco-valor">
        <div class="label-valor">💰 VALOR TOTAL PAGO</div>
        <div class="valor-principal">R$ ' . htmlspecialchars($d['valorpag']) . '</div>
        <div class="valor-extenso">( ' . htmlspecialchars($d['valorpagext']) . ' )</div>
    </div>

    <div class="grid-duas-colunas">
        <div class="coluna-info">
            <div class="label-secao">👤 PAGADOR</div>
            <div class="nome-principal">' . htmlspecialchars($d['nomecliente']) . '</div>
            <div class="info-secundaria">CPF/CNPJ: ' . htmlspecialchars($d['cpfcliente']) . '</div>
            <div class="info-secundaria">EMAIL: ' . htmlspecialchars($d['emailcliente']) . '</div>
        </div>
        <div class="coluna-info">
            <div class="label-secao">🏢 BENEFICIÁRIO</div>
            <div class="nome-principal">' . htmlspecialchars($d['provedornome']) . '</div>
            <div class="info-secundaria">CNPJ: ' . htmlspecialchars($d['provedorcnpj']) . '</div>
            <div class="info-secundaria">Tel: ' . htmlspecialchars($d['provedorfone']) . '</div>
        </div>
    </div>

    <div class="secao-detalhes">
        <div class="titulo-detalhes">📋 DETALHES DA TRANSAÇÃO</div>
        <div class="detalhes-box">
            <div class="grid-tres-colunas">
                <div class="detalhe-item"><span class="detalhe-label">Data Pagamento</span><span class="detalhe-valor">' . htmlspecialchars($d['datapag']) . '</span></div>
                <div class="detalhe-item"><span class="detalhe-label">Hora</span><span class="detalhe-valor">' . htmlspecialchars($d['horapag']) . '</span></div>
                <div class="detalhe-item"><span class="detalhe-label">Vencimento Original</span><span class="detalhe-valor">' . htmlspecialchars($d['vencimento']) . '</span></div>
            </div>
            <div class="grid-tres-colunas linha-detalhe-3">
                <div class="detalhe-item"><span class="detalhe-label">Serviço</span><span class="detalhe-valor">Internet Banda Larga</span></div>
                <div class="detalhe-item"><span class="detalhe-label">Plano</span><span class="detalhe-valor">' . htmlspecialchars($d['planodeacesso']) . '</span></div>
                <div class="detalhe-item"><span class="detalhe-label">Forma de Pagamento</span><span class="detalhe-valor">' . htmlspecialchars($d['formapag']) . '</span></div>
            </div>
        </div>
    </div>

    <div class="bloco-assinatura">
        <div class="linha-assinatura"></div>
        <div class="label-assinatura">Sócio / Proprietário</div>
        <div class="nome-assinatura">' . htmlspecialchars($d['provedornome']) . '</div>
    </div>
</div>

<div class="rodape-recibo">
    <div class="texto-rodape">Este documento serve como comprovante oficial de pagamento.</div>
    <div class="autenticacao">Autenticação:<br><span>' . htmlspecialchars($d['codigoseg']) . '</span></div>
</div>
<div class="barra-inferior"></div>

</div>
</body>
</html>';
}

/**
 * Envia PDF via Evolution API
 */
function enviarPdfEvolutionAPI($celular, $pdfPath, $ip, $porta, $instanceName, $token, $caption = '') {
    if (!file_exists($pdfPath)) return false;
    
    $pdfBase64 = base64_encode(file_get_contents($pdfPath));
    $apiURL = "http://{$ip}:{$porta}/message/sendMedia/{$instanceName}";
    
    $data = json_encode([
        "number" => $celular,
        "mediaMessage" => [
            "mediatype" => "document",
            "fileName" => "Comprovante_Pagamento.pdf",
            "caption" => $caption,
            "media" => $pdfBase64
        ],
        "options" => ["delay" => 200]
    ]);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["apikey: $token", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_CONNECTTIMEOUT => 5
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($pdfPath);
    
    if ($httpCode === 200 || $httpCode === 201) {
        $r = json_decode($response, true);
        if (isset($r['key']['remoteJid']) && !empty($r['key']['remoteJid'])) return true;
        escreverLog("⚠️ API resposta inesperada: " . substr($response, 0, 150));
        return false;
    }
    escreverLog("❌ API HTTP $httpCode: " . substr($response, 0, 150));
    return false;
}
?>
