<?php
include('addons.class.php');
session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');

$manifestTitle = $Manifest->{'name'} ?? '';
$cronFilePath = '/tmp/cron_recibo_whatsapp_envio';
$logFilePath = '/tmp/Recibo_Whatsapp/log_pagamentos.txt';

function escreverLog($msg) {
    global $logFilePath;
    file_put_contents($logFilePath, "[" . date('d/m/Y H:i:s') . "] $msg\n", FILE_APPEND);
}

function atualizarCronLimpeza($hora, $dias) {
    global $cronFilePath;
    if ($dias < 1) return;
    list($h, $m) = explode(":", $hora);
    $cmd = "/usr/bin/php -q /opt/mk-auth/admin/addons/Recibo_Whatsapp/limpar_tabela.php $dias >/dev/null 2>&1";
    $cronAtual = shell_exec("crontab -l | grep -v '/limpar_tabela.php'");
    file_put_contents($cronFilePath, $cronAtual . "$m $h */$dias * * $cmd\n");
    exec("crontab $cronFilePath");
    escreverLog("⏰ Limpeza agendada: às $hora, registros com mais de $dias dias.");
}

function obterLimpeza() {
    $output = shell_exec("crontab -l | grep '/limpar_tabela.php'");
    if (!$output) return null;
    $partes = explode(" ", trim($output));
    return ['hora' => $partes[1] . ':' . str_pad($partes[0], 2, '0', STR_PAD_LEFT), 'dias' => str_replace('*/', '', $partes[2])];
}

function excluirLimpeza() {
    shell_exec("crontab -l | grep -v '/limpar_tabela.php' | crontab -");
    escreverLog("🗑️ Agendamento de limpeza removido.");
}

// POST handlers
if (isset($_POST['hora_execucao']) && isset($_POST['intervalo_dias'])) {
    atualizarCronLimpeza($_POST['hora_execucao'], (int)$_POST['intervalo_dias']);
    $_SESSION['msg_limpeza'] = "Agendamento de limpeza salvo com sucesso!";
    header("Location: agendamento_limpar.php");
    exit;
}
if (isset($_POST['delete_clean_schedule'])) {
    excluirLimpeza();
    $_SESSION['msg_limpeza'] = "Agendamento removido.";
    header("Location: agendamento_limpar.php");
    exit;
}

$msgLimpeza = null;
if (isset($_POST['data_limite'])) {
    $con = new mysqli('127.0.0.1', 'root', 'vertrigo', 'mkradius');
    if (!$con->connect_error) {
        $stmt = $con->prepare("DELETE FROM recibo_pago_config WHERE DATE(data) <= ?");
        $stmt->bind_param("s", $_POST['data_limite']);
        $stmt->execute();
        $afetados = $stmt->affected_rows;
        $stmt->close();
        $con->close();
        $dataF = date("d/m/Y", strtotime($_POST['data_limite']));
        escreverLog("🗑️ Limpeza manual: $afetados registros até $dataF excluídos.");
        $msgLimpeza = "$afetados registro(s) até $dataF foram excluídos.";
    }
}

$ag = obterLimpeza();
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

<!-- Agendamento Automático -->
<div class="rwp-card">
    <div class="rwp-card-header h-blue">
        <i class="fa fa-eraser"></i> Limpeza Automática
    </div>
    <div class="rwp-card-body">

        <?php if (isset($_SESSION['msg_limpeza'])): ?>
            <div class="rwp-alert rwp-alert-ok"><i class="fa fa-check-circle"></i> <?= $_SESSION['msg_limpeza'] ?></div>
            <?php unset($_SESSION['msg_limpeza']); ?>
        <?php endif; ?>

        <?php if ($msgLimpeza): ?>
            <div class="rwp-alert rwp-alert-ok"><i class="fa fa-check-circle"></i> <?= $msgLimpeza ?></div>
        <?php endif; ?>

        <div class="rwp-status-box">
            <span class="rwp-status-label">Status Atual</span>
            <span class="rwp-status-value">
                <?php if ($ag): ?>
                    <span style="color:#16a34a;"><i class="fa fa-check-circle"></i> Ativo — Às <strong><?= $ag['hora'] ?></strong>, mantém últimos <strong><?= $ag['dias'] ?></strong> dias</span>
                <?php else: ?>
                    <span style="color:#dc2626;"><i class="fa fa-times-circle"></i> Nenhum agendamento</span>
                <?php endif; ?>
            </span>
        </div>

        <div class="rwp-alert rwp-alert-info">
            <strong><i class="fa fa-info-circle"></i></strong> Remove registros antigos para manter o banco otimizado.
        </div>

        <form method="post" style="margin-top:14px;">
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label class="rwp-label">Horário</label>
                    <input type="time" name="hora_execucao" class="rwp-input" value="<?= $ag['hora'] ?? '03:00' ?>" required style="max-width:130px;">
                </div>
                <div>
                    <label class="rwp-label">Manter (dias)</label>
                    <input type="number" min="1" name="intervalo_dias" class="rwp-input" value="<?= $ag['dias'] ?? 30 ?>" required style="max-width:100px;">
                </div>
                <button type="submit" class="rwp-btn rwp-btn-success" onclick="return confirm('Agendar limpeza?')">
                    <i class="fa fa-save"></i> Agendar
                </button>
            </div>
        </form>

        <?php if ($ag): ?>
        <div style="margin-top:14px;">
            <form method="post">
                <input type="hidden" name="delete_clean_schedule" value="1">
                <button type="submit" class="rwp-btn rwp-btn-danger rwp-btn-sm" onclick="return confirm('Remover?')">
                    <i class="fa fa-trash"></i> Remover Agendamento
                </button>
            </form>
        </div>
        <?php endif; ?>

    </div>
</div>

<!-- Limpeza Manual -->
<div class="rwp-card">
    <div class="rwp-card-header h-orange">
        <i class="fa fa-hand-paper-o"></i> Limpeza Manual
    </div>
    <div class="rwp-card-body">
        <p style="font-size:12px;color:#475569;margin:0 0 14px;">
            Exclui todos os registros anteriores à data selecionada. Ação irreversível.
        </p>
        <form method="post">
            <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                <div>
                    <label class="rwp-label">Excluir até</label>
                    <input type="date" name="data_limite" class="rwp-input" required style="max-width:180px;">
                </div>
                <button type="submit" class="rwp-btn rwp-btn-danger" onclick="return confirm('Excluir registros até esta data?')">
                    <i class="fa fa-trash"></i> Limpar
                </button>
            </div>
        </form>
    </div>
</div>

</div>

<?php include('../../baixo.php'); ?>
<script src="../../menu.js.php"></script>
<?php include('../../rodape.php'); ?>
</body>
</html>
