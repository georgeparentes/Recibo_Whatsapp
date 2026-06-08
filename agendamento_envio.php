<?php
include('addons.class.php');
session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');

$manifestTitle = $Manifest->{'name'} ?? '';
$manifestVersion = $Manifest->{'version'} ?? '';

$cronFilePath = '/tmp/cron_recibo_whatsapp_envio';
$logFilePath = '/tmp/Recibo_Whatsapp/log_pagamentos.txt';

function escreverLog($msg) {
    global $logFilePath;
    file_put_contents($logFilePath, "[" . date('d/m/Y H:i:s') . "] $msg\n", FILE_APPEND);
}

function atualizarCron($intervaloMinutos) {
    global $cronFilePath;
    if ($intervaloMinutos < 1 || $intervaloMinutos > 60) {
        $_SESSION['mensagem'] = "Intervalo fora do permitido (1-60 min).";
        $_SESSION['mensagem_tipo'] = 'erro';
        return;
    }

    $cronAtual = shell_exec("crontab -l | grep -v '/opt/mk-auth/admin/addons/Recibo_Whatsapp/'");
    $comando = "/usr/bin/php -q /opt/mk-auth/admin/addons/Recibo_Whatsapp/enviozap.php >/dev/null 2>&1";
    $linhaEnvio = "*/$intervaloMinutos * * * * $comando\n";

    file_put_contents($cronFilePath, $cronAtual . $linhaEnvio);
    exec("crontab $cronFilePath");

    escreverLog("⏰ Agendamento: envio a cada {$intervaloMinutos} minutos");
    $_SESSION['mensagem'] = "Agendamento salvo! Envio a cada {$intervaloMinutos} minutos.";
    $_SESSION['mensagem_tipo'] = 'sucesso';
}

function obterAgendamento() {
    $envio = shell_exec("crontab -l | grep 'enviozap.php'");
    if (!$envio) return null;
    preg_match('/\*\/(\d+)/', $envio, $m);
    $intervalo = $m[1] ?? '?';
    return ['intervalo' => $intervalo];
}

function excluirAgendamento() {
    shell_exec("crontab -l | grep -v '/opt/mk-auth/admin/addons/Recibo_Whatsapp/' | crontab -");
    escreverLog("🗑️ Agendamento removido.");
    $_SESSION['mensagem'] = "Agendamento removido com sucesso.";
    $_SESSION['mensagem_tipo'] = 'sucesso';
}

// POST handlers
if (isset($_POST['intervalo_minutos'])) {
    atualizarCron((int)$_POST['intervalo_minutos']);
    header("Location: agendamento_envio.php");
    exit;
}
if (isset($_POST['delete_schedule'])) {
    excluirAgendamento();
    header("Location: agendamento_envio.php");
    exit;
}

$ag = obterAgendamento();
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

<div class="rwp-card">
    <div class="rwp-card-header h-blue">
        <i class="fa fa-clock-o"></i> Agendamento de Envio de PDF
    </div>
    <div class="rwp-card-body">

        <?php if (isset($_SESSION['mensagem'])): ?>
            <div class="rwp-alert rwp-alert-<?= $_SESSION['mensagem_tipo'] === 'sucesso' ? 'ok' : 'err' ?>">
                <?= $_SESSION['mensagem'] ?>
            </div>
            <?php unset($_SESSION['mensagem'], $_SESSION['mensagem_tipo']); ?>
        <?php endif; ?>

        <!-- Status -->
        <div class="rwp-status-box">
            <span class="rwp-status-label">Status Atual</span>
            <span class="rwp-status-value">
                <?php if ($ag): ?>
                    <span style="color:#16a34a;"><i class="fa fa-check-circle"></i> Ativo — Envio a cada <strong><?= $ag['intervalo'] ?></strong> minutos</span>
                <?php else: ?>
                    <span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Nenhum agendamento configurado</span>
                <?php endif; ?>
            </span>
        </div>

        <div class="rwp-alert rwp-alert-info">
            <strong><i class="fa fa-bolt"></i> Como funciona:</strong> O cron gera o PDF e envia via WhatsApp para cada pagamento pendente do dia.
        </div>

        <form method="post" style="margin-top:16px;">
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div style="flex:1;min-width:140px;">
                    <label class="rwp-label">Intervalo (min)</label>
                    <input type="number" min="1" max="60" name="intervalo_minutos" class="rwp-input" value="<?= $ag['intervalo'] ?? 2 ?>" required style="max-width:140px;">
                </div>
                <button type="submit" class="rwp-btn rwp-btn-success" onclick="return confirm('Confirmar agendamento?')">
                    <i class="fa fa-save"></i> Agendar
                </button>
            </div>
            <small style="color:#94a3b8;font-size:10px;margin-top:6px;display:block;">Recomendação: entre 2 e 5 minutos.</small>
        </form>

        <?php if ($ag): ?>
        <div style="margin-top:16px;padding-top:14px;border-top:1px solid #e2e8f0;">
            <form method="post">
                <input type="hidden" name="delete_schedule" value="1">
                <button type="submit" class="rwp-btn rwp-btn-danger rwp-btn-sm" onclick="return confirm('Remover agendamento?')">
                    <i class="fa fa-trash"></i> Remover Agendamento
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

</div>

<?php include('../../baixo.php'); ?>
<script src="../../menu.js.php"></script>
<?php include('../../rodape.php'); ?>
</body>
</html>
