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

<div class="rwp-container">
<?php
include('config.php');

$itens_por_pagina = 50;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $itens_por_pagina;

$busca = isset($_GET['busca']) ? trim($_GET['busca']) : '';
$ordem = isset($_GET['ordem']) ? $_GET['ordem'] : 'zap, nome';
$filtro_zap = isset($_GET['filtro']) ? $_GET['filtro'] : '';

$busca_esc = '';
if (!empty($busca)) {
    $busca_esc = mysqli_real_escape_string($link, $busca);
}

// Filtro por status do WhatsApp
$filtro_sql = '';
if ($filtro_zap === 'sim') {
    $filtro_sql = " AND zap = 'sim'";
} elseif ($filtro_zap === 'nao') {
    $filtro_sql = " AND (zap = 'nao' OR zap IS NULL)";
}

$query_total = "SELECT COUNT(*) as total FROM sis_cliente WHERE cli_ativado = 's'" . $filtro_sql;
if (!empty($busca)) {
    $query_total .= " AND (nome LIKE '%$busca_esc%' OR login LIKE '%$busca_esc%')";
}
$result_total = mysqli_query($link, $query_total);
$total_registros = mysqli_fetch_assoc($result_total)['total'];
$total_paginas = ceil($total_registros / $itens_por_pagina);

$query = "SELECT nome, zap, uuid_cliente, celular, login FROM sis_cliente WHERE cli_ativado = 's'" . $filtro_sql;
if (!empty($busca)) {
    $query .= " AND (nome LIKE '%$busca_esc%' OR login LIKE '%$busca_esc%')";
}
$query .= " ORDER BY $ordem LIMIT $itens_por_pagina OFFSET $offset";
$consulta_pagos = mysqli_query($link, $query);

$total_zap_sim = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM sis_cliente WHERE cli_ativado = 's' AND zap = 'sim'"))['t'] ?? 0;
$total_zap_nao = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM sis_cliente WHERE cli_ativado = 's' AND (zap = 'nao' OR zap IS NULL)"))['t'] ?? 0;
$total_clientes_geral = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM sis_cliente WHERE cli_ativado = 's'"))['t'] ?? 0;
?>

<!-- Métricas -->
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <a href="ativarnumeros.php" style="flex:1 1 140px;background:white;border:1px solid #e2e8f0;border-left:4px solid #2563eb;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .2s;<?= $filtro_zap === '' ? 'box-shadow:0 0 0 2px #2563eb;' : '' ?>">
        <div style="width:34px;height:34px;background:#eff6ff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">👥</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#1e293b;display:block;line-height:1;"><?= $total_clientes_geral ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Total Clientes</span>
        </div>
    </a>
    <a href="ativarnumeros.php?filtro=sim" style="flex:1 1 140px;background:white;border:1px solid #e2e8f0;border-left:4px solid #16a34a;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .2s;<?= $filtro_zap === 'sim' ? 'box-shadow:0 0 0 2px #16a34a;' : '' ?>">
        <div style="width:34px;height:34px;background:#f0fdf4;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">✅</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#16a34a;display:block;line-height:1;"><?= $total_zap_sim ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Recebem WhatsApp</span>
        </div>
    </a>
    <a href="ativarnumeros.php?filtro=nao" style="flex:1 1 140px;background:white;border:1px solid #e2e8f0;border-left:4px solid #dc2626;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .2s;<?= $filtro_zap === 'nao' ? 'box-shadow:0 0 0 2px #dc2626;' : '' ?>">
        <div style="width:34px;height:34px;background:#fef2f2;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">🚫</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#dc2626;display:block;line-height:1;"><?= $total_zap_nao ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Não Recebem</span>
        </div>
    </a>
</div>

<!-- Busca -->
<form method="get" class="rwp-search">
    <input type="text" name="busca" placeholder="Buscar por nome ou login..." value="<?= htmlspecialchars($busca) ?>">
    <input type="hidden" name="ordem" value="<?= htmlspecialchars($ordem) ?>">
    <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro_zap) ?>">
    <button type="submit" class="rwp-btn rwp-btn-primary"><i class="fa fa-search"></i> Buscar</button>
    <?php if (!empty($busca)): ?>
        <a href="ativarnumeros.php<?= $filtro_zap ? '?filtro=' . urlencode($filtro_zap) : '' ?>" class="rwp-btn rwp-btn-danger"><i class="fa fa-times"></i> Limpar</a>
    <?php endif; ?>
</form>

<!-- Tabela -->
<div class="rwp-table-wrap">
<table>
    <thead>
        <tr>
            <th style="text-align:left;">Cliente / Login</th>
            <th>Celular Atual</th>
            <th>Atualizar Celular</th>
            <th>Receber WhatsApp</th>
        </tr>
    </thead>
    <tbody>
    <?php while ($res = mysqli_fetch_array($consulta_pagos)):
        $nome = $res['nome'];
        $login = $res['login'];
        $celular = $res['celular'];
        $zap = $res['zap'];
        $uuid = $res['uuid_cliente'];
        $nomeJs = htmlspecialchars($nome, ENT_QUOTES);
    ?>
        <tr>
            <td class="rwp-cell-id">
                <a href="../../cliente_alt.hhvm?uuid=<?= $uuid ?>" target="_blank" class="rwp-name" style="color:#2563eb;text-decoration:none;">
                    <?= htmlspecialchars($nome) ?>
                </a>
                <span class="rwp-login"><?= htmlspecialchars($login) ?></span>
            </td>
            <td>
                <span id="cel-<?= $nomeJs ?>">
                    <?php if ($celular): ?>
                        <a href="https://wa.me/55<?= $celular ?>" target="_blank" style="color:#16a34a;text-decoration:none;font-size:12px;font-weight:500;">
                            <?= htmlspecialchars($celular) ?>
                        </a>
                    <?php else: ?>
                        <span style="color:#94a3b8;font-size:11px;">—</span>
                    <?php endif; ?>
                </span>
                <?php if ($celular): ?>
                    <button type="button" onclick="remover('<?= $nomeJs ?>')" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:11px;margin-left:4px;">
                        <i class="fa fa-trash"></i>
                    </button>
                <?php endif; ?>
                <div id="msg-cel-<?= $nomeJs ?>" style="color:#16a34a;font-size:10px;margin-top:2px;"></div>
            </td>
            <td>
                <div style="display:flex;align-items:center;justify-content:center;gap:4px;">
                    <input type="text" id="telefone-<?= $nomeJs ?>" class="rwp-input" style="width:120px;padding:5px 8px;font-size:12px;max-width:none;" maxlength="15" placeholder="(00) 00000-0000" oninput="formatarTelefone(this)">
                    <button type="button" class="rwp-btn rwp-btn-primary rwp-btn-sm" onclick="atualizarCliente('<?= $nomeJs ?>', 'telefone', document.getElementById('telefone-<?= $nomeJs ?>').value)">
                        <i class="fa fa-check"></i>
                    </button>
                </div>
            </td>
            <td>
                <div class="rwp-zap" style="justify-content:center;">
                    <label class="z-sim"><input type="radio" name="zap_<?= $nomeJs ?>" value="sim" <?= $zap === "sim" ? "checked" : "" ?> onclick="atualizarCliente('<?= $nomeJs ?>', 'zap', 'sim')"> Sim</label>
                    <label class="z-nao"><input type="radio" name="zap_<?= $nomeJs ?>" value="nao" <?= $zap === "nao" ? "checked" : "" ?> onclick="atualizarCliente('<?= $nomeJs ?>', 'zap', 'nao')"> Não</label>
                </div>
                <div id="msg-<?= $nomeJs ?>" style="color:#16a34a;font-size:10px;text-align:center;margin-top:2px;"></div>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</div>

<!-- Paginação -->
<?php if ($total_paginas > 1): ?>
<div class="rwp-pagination">
    <?php if ($pagina_atual > 1): ?>
        <a href="?pagina=<?= $pagina_atual - 1 ?>&busca=<?= urlencode($busca) ?>&ordem=<?= urlencode($ordem) ?>&filtro=<?= urlencode($filtro_zap) ?>">‹</a>
    <?php endif; ?>
    <?php
    $start = max(1, $pagina_atual - 3);
    $end = min($total_paginas, $pagina_atual + 3);
    if ($start > 1) echo '<a href="?pagina=1&busca=' . urlencode($busca) . '&ordem=' . urlencode($ordem) . '&filtro=' . urlencode($filtro_zap) . '">1</a>';
    if ($start > 2) echo '<span style="padding:6px;color:#94a3b8;">...</span>';
    for ($i = $start; $i <= $end; $i++):
        if ($i == $pagina_atual): ?>
            <strong><?= $i ?></strong>
        <?php else: ?>
            <a href="?pagina=<?= $i ?>&busca=<?= urlencode($busca) ?>&ordem=<?= urlencode($ordem) ?>&filtro=<?= urlencode($filtro_zap) ?>"><?= $i ?></a>
        <?php endif;
    endfor;
    if ($end < $total_paginas - 1) echo '<span style="padding:6px;color:#94a3b8;">...</span>';
    if ($end < $total_paginas) echo '<a href="?pagina=' . $total_paginas . '&busca=' . urlencode($busca) . '&ordem=' . urlencode($ordem) . '&filtro=' . urlencode($filtro_zap) . '">' . $total_paginas . '</a>';
    ?>
    <?php if ($pagina_atual < $total_paginas): ?>
        <a href="?pagina=<?= $pagina_atual + 1 ?>&busca=<?= urlencode($busca) ?>&ordem=<?= urlencode($ordem) ?>&filtro=<?= urlencode($filtro_zap) ?>">›</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php mysqli_close($link); ?>
</div>

<script>
function formatarTelefone(input) {
    var numero = input.value.replace(/\D/g, '');
    if (numero.length === 0) { input.value = ''; return; }
    if (numero.length <= 2) input.value = '(' + numero;
    else if (numero.length <= 7) input.value = '(' + numero.substring(0, 2) + ') ' + numero.substring(2);
    else input.value = '(' + numero.substring(0, 2) + ') ' + numero.substring(2, 7) + '-' + numero.substring(7, 11);
}

function atualizarCliente(nome, campo, valor) {
    var formData = new FormData();
    formData.append("nome", nome);
    formData.append("campo", campo);
    formData.append("valor", valor);
    fetch("atualizar_cliente.php", { method: "POST", body: formData })
    .then(function(r){ return r.text(); })
    .then(function(data){
        if (campo === 'zap') {
            var el = document.getElementById('msg-' + nome);
            if (el) { el.innerText = '✔ Atualizado'; setTimeout(function(){ el.innerText = ''; }, 2500); }
        } else if (campo === 'telefone') {
            var el = document.getElementById('msg-cel-' + nome);
            if (el) { el.innerText = '✔ Atualizado'; setTimeout(function(){ el.innerText = ''; }, 2500); }
            var numLimpo = valor.replace(/\D/g, '');
            var celDiv = document.getElementById('cel-' + nome);
            if (celDiv && numLimpo) {
                celDiv.innerHTML = '<a href="https://wa.me/55' + numLimpo + '" target="_blank" style="color:#16a34a;text-decoration:none;font-size:12px;font-weight:500;">' + valor + '</a>';
            }
        }
    })
    .catch(function(err){ console.error("Erro:", err); });
}

function remover(nome) {
    if (confirm("Remover o celular deste cliente?")) {
        var formData = new FormData();
        formData.append("nome", nome);
        formData.append("campo", "telefone");
        formData.append("valor", "");
        fetch("atualizar_cliente.php", { method: "POST", body: formData })
        .then(function(r){ return r.text(); })
        .then(function(){ location.reload(); })
        .catch(function(err){ console.error("Erro:", err); });
    }
}
</script>

<?php include('../../baixo.php'); ?>
<script src="../../menu.js.php"></script>
<?php include('../../rodape.php'); ?>
</body>
</html>
