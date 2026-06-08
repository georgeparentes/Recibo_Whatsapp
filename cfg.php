<?php
include('addons.class.php');
session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');

$manifestTitle = $Manifest->{'name'} ?? '';
$manifestVersion = $Manifest->{'version'} ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR" class="has-navbar-fixed-top">
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta charset="utf-8">
<title>MK-AUTH :: <?php echo $manifestTitle; ?></title>
<link href="../../estilos/mk-auth.css" rel="stylesheet" type="text/css" />
<link href="../../estilos/font-awesome.css" rel="stylesheet" type="text/css" />
<link href="../../estilos/bi-icons.css" rel="stylesheet" type="text/css" />
<link href="../../css/mobile.css" rel="stylesheet" type="text/css" />
<link href="css/estilo.css" rel="stylesheet" type="text/css" />
<link href="css/bootstrap.css" rel="stylesheet" type="text/css" />
<script src="../../scripts/jquery.js"></script>
<script src="../../scripts/mk-auth.js"></script>
</head>
<body>
<?php include('../../topo.php'); ?>
<?php include 'nav/navbar.php'; ?>

<div class="rwp-container" style="max-width:600px;">

<?php
include('config.php');

$provedor = $ip = $instancia = $token = $ignorar_zap = '';
$envio_pdf_ativo = 'sim';

// Garante coluna envio_pdf_ativo existe
$col_envio_pdf = $link->query("SHOW COLUMNS FROM config_recibo_zap LIKE 'envio_pdf_ativo'");
if ($col_envio_pdf && $col_envio_pdf->num_rows == 0) {
    $link->query("ALTER TABLE config_recibo_zap ADD COLUMN envio_pdf_ativo VARCHAR(3) DEFAULT 'sim'");
} else {
    // Converte para VARCHAR se estiver como ENUM para evitar problemas com acentos
    $link->query("ALTER TABLE config_recibo_zap MODIFY COLUMN envio_pdf_ativo VARCHAR(3) DEFAULT 'sim'");
}

// Processa POST antes de ler os dados
if (isset($_POST['salvar_configuracoes'])) {
    $provedor = $link->real_escape_string($_POST['provedor'] ?? '');
    $ip = $link->real_escape_string($_POST['ip'] ?? '');
    $instancia = $link->real_escape_string($_POST['user'] ?? '');
    $token = $link->real_escape_string($_POST['token'] ?? '');
    $ignorar_zap = $link->real_escape_string($_POST['ignorar_zap'] ?? 'não');
    
    // Checkbox: se marcado envia "sim", se desmarcado o hidden envia "nao"
    $envio_pdf_ativo = ($_POST['envio_pdf_ativo'] === 'sim') ? 'sim' : 'nao';

    $link->query("CREATE TABLE IF NOT EXISTS config_recibo_zap (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        provedor VARCHAR(128) NOT NULL,
        ip VARCHAR(45) NOT NULL,
        instancia VARCHAR(64) NOT NULL,
        token VARCHAR(255) NOT NULL,
        ignorar_zap ENUM('sim','não') DEFAULT 'não',
        envio_pdf_ativo VARCHAR(3) DEFAULT 'sim'
    )");

    $col = $link->query("SHOW COLUMNS FROM config_recibo_zap LIKE 'ignorar_zap'");
    if ($col->num_rows == 0) {
        $link->query("ALTER TABLE config_recibo_zap ADD COLUMN ignorar_zap ENUM('sim','não') DEFAULT 'não'");
    }

    $check = $link->query("SELECT id FROM config_recibo_zap WHERE id = 1");
    if ($check->num_rows > 0) {
        $link->query("UPDATE config_recibo_zap SET provedor='$provedor', ip='$ip', instancia='$instancia', token='$token', ignorar_zap='$ignorar_zap', envio_pdf_ativo='$envio_pdf_ativo' WHERE id = 1");
    } else {
        $link->query("INSERT INTO config_recibo_zap (provedor, ip, instancia, token, ignorar_zap, envio_pdf_ativo) VALUES ('$provedor', '$ip', '$instancia', '$token', '$ignorar_zap', '$envio_pdf_ativo')");
    }
    $salvo = true;
}

// Lê dados DEPOIS do POST para pegar valores atualizados
$result = $link->query("SELECT provedor, ip, instancia, token, ignorar_zap, envio_pdf_ativo FROM config_recibo_zap WHERE id = 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $provedor = $row['provedor'];
    $ip = $row['ip'];
    $instancia = $row['instancia'];
    $token = $row['token'];
    $ignorar_zap = $row['ignorar_zap'];
    $envio_pdf_ativo = $row['envio_pdf_ativo'] ?? 'sim';
}

// Normaliza: qualquer coisa diferente de "sim" é desativado
$envio_ativo = ($envio_pdf_ativo === 'sim');
?>

<div class="rwp-card">
    <div class="rwp-card-header h-blue">
        <i class="fa fa-cog"></i> Configurações da Evolution API
    </div>
    <div class="rwp-card-body">

        <?php if (isset($salvo)): ?>
            <div class="rwp-alert rwp-alert-ok"><i class="fa fa-check-circle"></i> Configurações salvas com sucesso!</div>
        <?php endif; ?>

        <div class="rwp-alert rwp-alert-info">
            <strong><i class="fa fa-info-circle"></i> Dados necessários:</strong><br>
            <span style="font-size:11px;">IP:Porta do servidor, nome da instância e token de autenticação da Evolution API.</span>
        </div>

        <form method="post">
            <div style="margin-bottom:16px;">
                <label class="rwp-label">Nome do Provedor</label>
                <input type="text" class="rwp-input" name="provedor" value="<?= htmlspecialchars($provedor) ?>" placeholder="Sua Empresa Internet">
            </div>
            <div style="margin-bottom:16px;">
                <label class="rwp-label">IP:Porta do Servidor</label>
                <input type="text" class="rwp-input" name="ip" value="<?= htmlspecialchars($ip) ?>" placeholder="192.168.1.1:8080">
            </div>
            <div style="margin-bottom:16px;">
                <label class="rwp-label">Instância</label>
                <input type="text" class="rwp-input" name="user" value="<?= htmlspecialchars($instancia) ?>" placeholder="mkauth">
            </div>
            <div style="margin-bottom:16px;">
                <label class="rwp-label">Token de Autenticação</label>
                <div style="display:flex;gap:6px;max-width:320px;">
                    <input type="password" class="rwp-input" id="token" name="token" value="<?= htmlspecialchars($token) ?>" style="flex:1;max-width:none;">
                    <button class="rwp-btn rwp-btn-primary" type="button" onclick="var t=document.getElementById('token'); t.type=t.type==='password'?'text':'password';" style="padding:6px 12px;">
                        <i class="fa fa-eye"></i>
                    </button>
                </div>
            </div>
            <div style="margin-bottom:20px;">
                <label class="rwp-label">Ignorar preferência "Receber WhatsApp"</label>
                <select name="ignorar_zap" class="rwp-input">
                    <option value="sim" <?= $ignorar_zap == 'sim' ? 'selected' : '' ?>>Sim — Envia para todos</option>
                    <option value="não" <?= $ignorar_zap == 'não' ? 'selected' : '' ?>>Não — Respeita preferência</option>
                </select>
                <small style="color:#94a3b8;font-size:10px;margin-top:4px;display:block;">
                    Se "Sim", envia PDF mesmo que o cliente tenha marcado "Não receber".
                </small>
            </div>

            <!-- Toggle Envio de PDF -->
            <div id="toggle_box" style="margin-bottom:20px;padding:14px;border:2px solid <?= $envio_ativo ? '#16a34a' : '#dc2626' ?>;border-radius:10px;background:<?= $envio_ativo ? '#f0fdf4' : '#fef2f2' ?>;transition:all .3s;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div style="display:flex;align-items:center;gap:10px;">
                        <div style="font-size:24px;" id="toggle_icon"><?= $envio_ativo ? '✅' : '⛔' ?></div>
                        <div>
                            <span style="font-size:13px;font-weight:700;color:#1e293b;display:block;">Envio Automático de PDF</span>
                            <span id="toggle_status" style="font-size:11px;color:<?= $envio_ativo ? '#16a34a' : '#dc2626' ?>;font-weight:600;">
                                <?= $envio_ativo ? '● ATIVADO — Enviando PDFs quando pago' : '● DESATIVADO — Nenhum PDF será enviado' ?>
                            </span>
                        </div>
                    </div>
                    <label style="position:relative;display:inline-block;width:50px;height:26px;cursor:pointer;">
                        <input type="hidden" name="envio_pdf_ativo" id="hidden_envio" value="<?= $envio_ativo ? 'sim' : 'nao' ?>">
                        <input type="checkbox" id="chk_envio" <?= $envio_ativo ? 'checked' : '' ?> style="opacity:0;width:0;height:0;position:absolute;">
                        <span id="toggle_track" style="position:absolute;top:0;left:0;right:0;bottom:0;background:<?= $envio_ativo ? '#16a34a' : '#cbd5e1' ?>;border-radius:26px;transition:all .3s;"></span>
                        <span id="toggle_thumb" style="position:absolute;top:3px;left:<?= $envio_ativo ? '27px' : '3px' ?>;width:20px;height:20px;background:white;border-radius:50%;transition:all .3s;box-shadow:0 1px 3px rgba(0,0,0,0.2);"></span>
                    </label>
                </div>
                <div style="margin-top:8px;font-size:10px;color:#64748b;" id="toggle_desc">
                    <?php if ($envio_ativo): ?>
                        Quando um pagamento for confirmado, o recibo em PDF será enviado automaticamente via WhatsApp.
                    <?php else: ?>
                        O envio automático está pausado. Nenhum PDF será enviado mesmo que pagamentos sejam confirmados.
                    <?php endif; ?>
                </div>
            </div>

            <button type="submit" name="salvar_configuracoes" class="rwp-btn rwp-btn-success" style="width:100%;justify-content:center;padding:10px;">
                <i class="fa fa-save"></i> Salvar Configurações
            </button>
        </form>

    </div>
</div>

</div>

<?php include('../../baixo.php'); ?>
<script>
document.getElementById('chk_envio').addEventListener('change', function() {
    var ativo = this.checked;
    var valor = ativo ? 'sim' : 'nao';
    var box = document.getElementById('toggle_box');
    var icon = document.getElementById('toggle_icon');
    var status = document.getElementById('toggle_status');
    var track = document.getElementById('toggle_track');
    var thumb = document.getElementById('toggle_thumb');
    var desc = document.getElementById('toggle_desc');
    var hidden = document.getElementById('hidden_envio');

    // Atualiza visual imediatamente
    hidden.value = valor;
    box.style.borderColor = ativo ? '#16a34a' : '#dc2626';
    box.style.background = ativo ? '#f0fdf4' : '#fef2f2';
    icon.textContent = ativo ? '✅' : '⛔';
    status.style.color = ativo ? '#16a34a' : '#dc2626';
    status.textContent = ativo ? '● ATIVADO — Enviando PDFs quando pago' : '● DESATIVADO — Nenhum PDF será enviado';
    track.style.background = ativo ? '#16a34a' : '#cbd5e1';
    thumb.style.left = ativo ? '27px' : '3px';
    desc.textContent = ativo ? 'Quando um pagamento for confirmado, o recibo em PDF será enviado automaticamente via WhatsApp.' : 'O envio automático está pausado. Nenhum PDF será enviado mesmo que pagamentos sejam confirmados.';

    // Salva no banco via AJAX
    fetch('toggle_envio_pdf.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'envio_pdf_ativo=' + valor
    });
});
</script>
<script src="../../menu.js.php"></script>
<?php include('../../rodape.php'); ?>
</body>
</html>
