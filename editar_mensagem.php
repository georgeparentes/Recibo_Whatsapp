<?php
include('addons.class.php');
session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');

$manifestTitle = $Manifest->{'name'} ?? '';
$manifestVersion = $Manifest->{'version'} ?? '';

include('config.php');
mysqli_set_charset($link, "utf8mb4");

// Cria tabela de template se não existir
$link->query("CREATE TABLE IF NOT EXISTS config_recibo_msg (
    id INT(11) NOT NULL AUTO_INCREMENT,
    mensagem TEXT NOT NULL,
    atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) DEFAULT CHARSET=utf8mb4");

// Mensagem padrão
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

// Salvar mensagem
$salvo = false;
$restaurado = false;
if (isset($_POST['salvar_mensagem'])) {
    $mensagem = $_POST['mensagem'] ?? $msg_padrao;
    $mensagem_esc = $link->real_escape_string($mensagem);

    $check = $link->query("SELECT id FROM config_recibo_msg WHERE id = 1");
    if ($check && $check->num_rows > 0) {
        $link->query("UPDATE config_recibo_msg SET mensagem = '$mensagem_esc' WHERE id = 1");
    } else {
        $link->query("INSERT INTO config_recibo_msg (id, mensagem) VALUES (1, '$mensagem_esc')");
    }
    $salvo = true;
}

// Restaurar padrão
if (isset($_POST['restaurar_padrao'])) {
    $mensagem_esc = $link->real_escape_string($msg_padrao);
    $check = $link->query("SELECT id FROM config_recibo_msg WHERE id = 1");
    if ($check && $check->num_rows > 0) {
        $link->query("UPDATE config_recibo_msg SET mensagem = '$mensagem_esc' WHERE id = 1");
    } else {
        $link->query("INSERT INTO config_recibo_msg (id, mensagem) VALUES (1, '$mensagem_esc')");
    }
    $restaurado = true;
}

// Buscar mensagem salva
$mensagem_atual = $msg_padrao;
$atualizado_em = '';
$result = $link->query("SELECT mensagem, atualizado_em FROM config_recibo_msg WHERE id = 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $mensagem_atual = $row['mensagem'];
    $atualizado_em = $row['atualizado_em'];
}
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

<div class="rwp-container" style="max-width:900px;">

<?php if ($salvo): ?>
    <div class="rwp-alert rwp-alert-ok"><i class="fa fa-check-circle"></i> Mensagem salva com sucesso!</div>
<?php endif; ?>
<?php if ($restaurado): ?>
    <div class="rwp-alert rwp-alert-ok"><i class="fa fa-refresh"></i> Mensagem restaurada para o padrão!</div>
<?php endif; ?>

<div style="display:flex;flex-wrap:wrap;gap:16px;">

    <!-- Coluna Esquerda: Editor -->
    <div style="flex:1;min-width:320px;">
        <div class="rwp-card">
            <div class="rwp-card-header h-blue">
                <i class="fa fa-edit"></i> Editar Mensagem do Recibo
            </div>
            <div class="rwp-card-body">

                <div class="rwp-alert rwp-alert-info" style="margin-bottom:14px;">
                    <strong><i class="fa fa-info-circle"></i> Como funciona:</strong><br>
                    <span style="font-size:11px;">Edite a mensagem que será enviada junto com o PDF do recibo. Use as variáveis abaixo para inserir dados do cliente automaticamente.</span>
                </div>

                <form method="post" id="formMsg">
                    <label class="rwp-label" style="margin-bottom:6px;">Mensagem (texto do WhatsApp)</label>
                    <textarea name="mensagem" id="textarea_msg" style="width:100%;min-height:320px;font-family:'Courier New',monospace;font-size:12px;line-height:1.6;padding:14px;border:2px solid #e2e8f0;border-radius:10px;resize:vertical;background:#fafbfc;transition:border-color .2s;" onfocus="this.style.borderColor='#2563eb'" onblur="this.style.borderColor='#e2e8f0'"><?= htmlspecialchars($mensagem_atual) ?></textarea>

                    <?php if ($atualizado_em): ?>
                        <div style="font-size:10px;color:#94a3b8;margin-top:4px;">
                            <i class="fa fa-clock-o"></i> Última atualização: <?= date('d/m/Y H:i', strtotime($atualizado_em)) ?>
                        </div>
                    <?php endif; ?>

                    <div style="display:flex;gap:8px;margin-top:14px;flex-wrap:wrap;">
                        <button type="submit" name="salvar_mensagem" class="rwp-btn rwp-btn-success" style="flex:1;justify-content:center;padding:10px;">
                            <i class="fa fa-save"></i> Salvar Mensagem
                        </button>
                        <button type="submit" name="restaurar_padrao" class="rwp-btn rwp-btn-danger" style="padding:10px;" onclick="return confirm('Restaurar mensagem padrão? A edição atual será perdida.')">
                            <i class="fa fa-undo"></i> Restaurar Padrão
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <!-- Coluna Direita: Preview -->
    <div style="flex:0 0 300px;min-width:260px;">

        <!-- Preview -->
        <div class="rwp-card">
            <div class="rwp-card-header" style="background:#16a34a;color:white;">
                <i class="fa fa-eye"></i> Pré-visualização
            </div>
            <div class="rwp-card-body" style="padding:0;">
                <div id="preview_msg" style="background:#e5ddd5;padding:14px;min-height:200px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;font-size:12px;line-height:1.6;border-radius:0 0 10px 10px;">
                    <div style="background:white;border-radius:8px;padding:10px 12px;box-shadow:0 1px 2px rgba(0,0,0,0.1);white-space:pre-wrap;word-break:break-word;" id="preview_content">
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

</div>

<script>
// Preview em tempo real
function atualizarPreview() {
    var texto = document.getElementById('textarea_msg').value;
    // Substitui variáveis por exemplos
    var exemplos = {
        '{nome}': 'João da Silva',
        '{cpfCnpj}': '123.456.789-00',
        '{id}': '10547',
        '{datavenc}': '05/06/2026',
        '{datapag}': '04/06/2026',
        '{valorpag}': '99,90',
        '{formapag}': 'PIX',
        '{nomeprovedor}': 'AGFNET Telecom',
        '{login}': 'joao.silva',
        '{plano}': '100 Mega',
        '{valor}': '99,90',
        '{email}': 'joao@email.com'
    };
    for (var v in exemplos) {
        texto = texto.split(v).join(exemplos[v]);
    }
    // Formata negrito do WhatsApp (*texto*)
    texto = texto.replace(/\*([^*]+)\*/g, '<strong>$1</strong>');
    // Converte \n em <br>
    texto = texto.replace(/\n/g, '<br>');
    document.getElementById('preview_content').innerHTML = texto;
}

document.getElementById('textarea_msg').addEventListener('input', atualizarPreview);
atualizarPreview();
</script>

<?php include('../../baixo.php'); ?>
<script src="../../menu.js.php"></script>
<?php include('../../rodape.php'); ?>
</body>
</html>
