<?php
include('addons.class.php');

session_name('mka');
if (!isset($_SESSION)) session_start();
if (!isset($_SESSION['mka_logado']) && !isset($_SESSION['MKA_Logado'])) exit('Acesso negado... <a href="/admin/login.php">Fazer Login</a>');

$manifestTitle = isset($Manifest->name) ? htmlspecialchars($Manifest->name) : '';
$manifestVersion = isset($Manifest->version) ? htmlspecialchars($Manifest->version) : '';

include('config.php');
mysqli_set_charset($link, "utf8mb4");

// === ESTRUTURA DA TABELA ===
$tabela_existe = mysqli_query($link, "SHOW TABLES LIKE 'recibo_pago_config'");
if (mysqli_num_rows($tabela_existe) == 0) {
    $sql_criar = "CREATE TABLE recibo_pago_config (
        id INT(11) NOT NULL AUTO_INCREMENT,
        data TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        login VARCHAR(64) NOT NULL,
        coletor VARCHAR(64),
        datavenc DATE,
        datapag DATETIME,
        valor DECIMAL(10,2),
        valorpag DECIMAL(10,2),
        formapag VARCHAR(32),
        envio TINYINT(1) NOT NULL DEFAULT 0,
        data_envio DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        INDEX idx_envio_datapag (envio, datapag)
    )";
    mysqli_query($link, $sql_criar);
}

// Garante coluna envio existe
$col_envio = mysqli_query($link, "SHOW COLUMNS FROM recibo_pago_config LIKE 'envio'");
if ($col_envio && mysqli_num_rows($col_envio) == 0) {
    mysqli_query($link, "ALTER TABLE recibo_pago_config ADD COLUMN envio TINYINT(1) NOT NULL DEFAULT 0");
}

// Garante coluna data_envio existe
$col_check = mysqli_query($link, "SHOW COLUMNS FROM recibo_pago_config LIKE 'data_envio'");
if (mysqli_num_rows($col_check) == 0) {
    mysqli_query($link, "ALTER TABLE recibo_pago_config ADD COLUMN data_envio DATETIME DEFAULT NULL AFTER envio");
}

// Garante índice existe
$idx_check = mysqli_query($link, "SHOW INDEX FROM recibo_pago_config WHERE Key_name = 'idx_envio_datapag'");
if (mysqli_num_rows($idx_check) == 0) {
    mysqli_query($link, "ALTER TABLE recibo_pago_config ADD INDEX idx_envio_datapag (envio, datapag)");
}

// === TRIGGER ===
$trigger_nome = 'tig_recibo_pago_config';
$trigger_sql = "
BEGIN
    IF NEW.status = 'pago'
       AND EXISTS (
           SELECT 1 FROM sis_cliente WHERE login = NEW.login AND cli_ativado <> 'n'
       )
       AND DATE(NEW.datapag) = DATE(NOW()) THEN
        INSERT INTO recibo_pago_config (id, data, login, coletor, datavenc, datapag, valor, valorpag, formapag)
        VALUES (NEW.id, NOW(), NEW.login, NEW.coletor, NEW.datavenc, NEW.datapag, NEW.valor, NEW.valorpag, NEW.formapag);
    END IF;
END";

$trigger_existe = mysqli_query($link, "SELECT ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = 'mkradius' AND TRIGGER_NAME = '$trigger_nome'");
$recriar = true;
if (mysqli_num_rows($trigger_existe) > 0) {
    $t = mysqli_fetch_assoc($trigger_existe)['ACTION_STATEMENT'];
    if (trim($t) === trim($trigger_sql)) $recriar = false;
}
if ($recriar) {
    mysqli_query($link, "DROP TRIGGER IF EXISTS $trigger_nome");
    mysqli_query($link, "CREATE TRIGGER $trigger_nome AFTER UPDATE ON sis_lanc FOR EACH ROW $trigger_sql");
}

// === DIRETÓRIOS ===
$dir_path = '/tmp/Recibo_Whatsapp';
if (!is_dir($dir_path)) mkdir($dir_path, 0755, true);

$log_file_path = $dir_path . '/log_pagamentos.txt';
if (!file_exists($log_file_path)) {
    file_put_contents($log_file_path, "Log criado em: " . date("Y-m-d H:i:s") . "\n");
    chmod($log_file_path, 0666);
}

// === SINCRONIZA PAGAMENTOS DO DIA ===
$sql_sync = "SELECT * FROM sis_lanc 
             WHERE status = 'pago' 
             AND NOT EXISTS (SELECT 1 FROM recibo_pago_config WHERE id = sis_lanc.id)
             AND DATE(datapag) = CURDATE()";
$result_sync = mysqli_query($link, $sql_sync);
if ($result_sync && mysqli_num_rows($result_sync) > 0) {
    while ($row = mysqli_fetch_assoc($result_sync)) {
        $stmt = mysqli_prepare($link, "INSERT INTO recibo_pago_config (id, login, coletor, datavenc, datapag, valor, valorpag, formapag, envio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issssdds", $row['id'], $row['login'], $row['coletor'], $row['datavenc'], $row['datapag'], $row['valor'], $row['valorpag'], $row['formapag']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
    }
}

// === MÉTRICAS ===
$hoje = date('Y-m-d');
$total_hoje = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM recibo_pago_config WHERE DATE(datapag) = '$hoje'"))['t'] ?? 0;
$enviados_hoje = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM recibo_pago_config WHERE DATE(datapag) = '$hoje' AND envio = 1"))['t'] ?? 0;
$pendentes_hoje = mysqli_fetch_assoc(mysqli_query($link, "SELECT COUNT(*) as t FROM recibo_pago_config WHERE envio = 0"))['t'] ?? 0;
$valor_total_hoje = mysqli_fetch_assoc(mysqli_query($link, "SELECT COALESCE(SUM(valorpag), 0) as t FROM recibo_pago_config WHERE DATE(datapag) = '$hoje'"))['t'] ?? 0;
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

<div class="rwp-container" style="max-width:1280px;padding:10px 15px;">

<!-- Métricas clicáveis -->
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <a href="?filtro=todos" style="flex:1 1 120px;background:white;border:1px solid #e2e8f0;border-left:4px solid #2563eb;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .15s;<?= (isset($_GET['filtro']) && $_GET['filtro']=='todos') ? 'box-shadow:0 0 0 2px #2563eb33;' : '' ?>">
        <div style="width:34px;height:34px;background:#eff6ff;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">📊</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#1e293b;display:block;line-height:1;"><?= $total_hoje ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Pagamentos</span>
        </div>
    </a>
    <a href="?filtro=enviados" style="flex:1 1 120px;background:white;border:1px solid #e2e8f0;border-left:4px solid #16a34a;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .15s;<?= (!isset($_GET['filtro']) || (isset($_GET['filtro']) && $_GET['filtro']=='enviados')) ? 'box-shadow:0 0 0 2px #16a34a33;' : '' ?>">
        <div style="width:34px;height:34px;background:#f0fdf4;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">✅</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#16a34a;display:block;line-height:1;"><?= $enviados_hoje ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Enviados</span>
        </div>
    </a>
    <a href="?filtro=pendentes" style="flex:1 1 120px;background:white;border:1px solid #e2e8f0;border-left:4px solid #dc2626;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .15s;<?= (isset($_GET['filtro']) && $_GET['filtro']=='pendentes') ? 'box-shadow:0 0 0 2px #dc262633;' : '' ?>">
        <div style="width:34px;height:34px;background:#fef2f2;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">⏳</div>
        <div>
            <span style="font-size:20px;font-weight:800;color:#dc2626;display:block;line-height:1;"><?= $pendentes_hoje ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Pendentes</span>
        </div>
    </a>
    <a href="?filtro=todos" style="flex:1 1 120px;background:white;border:1px solid #e2e8f0;border-left:4px solid #d97706;border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;text-decoration:none;cursor:pointer;transition:box-shadow .15s;">
        <div style="width:34px;height:34px;background:#fffbeb;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">💰</div>
        <div>
            <span style="font-size:15px;font-weight:800;color:#1e293b;display:block;line-height:1;">R$<?= number_format($valor_total_hoje, 2, ',', '.') ?></span>
            <span style="font-size:9px;color:#64748b;text-transform:uppercase;letter-spacing:0.3px;font-weight:600;">Valor Total</span>
        </div>
    </a>
</div>

<?php
// === PESQUISA, FILTRO E PAGINAÇÃO ===
$termo = isset($_GET['pesquisa']) ? trim($_GET['pesquisa']) : '';
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'enviados';
if (!in_array($filtro, ['todos', 'enviados', 'pendentes'])) $filtro = 'enviados';

$where_parts = [];
if (!empty($termo)) {
    $termo_esc = mysqli_real_escape_string($link, $termo);
    $where_parts[] = "(c.nome LIKE '%$termo_esc%' OR r.login LIKE '%$termo_esc%')";
}
if ($filtro === 'enviados') {
    $where_parts[] = "r.envio = 1";
    $where_parts[] = "DATE(r.datapag) = '$hoje'";
} elseif ($filtro === 'pendentes') {
    $where_parts[] = "r.envio = 0";
} elseif ($filtro === 'todos') {
    // Sem filtro - mostra tudo
}

$where = !empty($where_parts) ? 'WHERE ' . implode(' AND ', $where_parts) : '';

$por_pagina = 12;
$pagina = isset($_GET['pagina']) && is_numeric($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina - 1) * $por_pagina;

$total_q = "SELECT COUNT(*) as total FROM recibo_pago_config r JOIN sis_cliente c ON r.login = c.login $where";
$total_result = mysqli_query($link, $total_q);
$total = $total_result ? mysqli_fetch_assoc($total_result)['total'] : 0;
$total_paginas = ceil($total / $por_pagina);

// Label do filtro ativo
$filtro_labels = ['todos' => 'Todos', 'enviados' => 'Enviados', 'pendentes' => 'Pendentes'];
$filtro_label = $filtro_labels[$filtro] ?? 'Todos';

$sql = "SELECT r.id, r.data, r.login, c.nome, r.coletor, r.datavenc, r.datapag, r.valor, r.valorpag, r.formapag, r.envio, r.data_envio, c.uuid_cliente, c.zap
        FROM recibo_pago_config r
        JOIN sis_cliente c ON r.login = c.login
        $where
        ORDER BY r.data DESC
        LIMIT ? OFFSET ?";

$stmt = mysqli_prepare($link, $sql);

if (!$stmt) {
    // Se a query falhar, mostra erro e continua
    $query_error = mysqli_error($link);
}

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $por_pagina, $offset);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $id, $data, $login, $nome, $coletor, $datavenc, $datapag, $valor, $valorpag, $formapag, $envio, $data_envio, $uuid_cliente, $zap);
}
?>

<!-- Busca + Filtro ativo -->
<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:14px;">
    <form method="GET" class="rwp-search" style="margin-bottom:0;">
        <input type="hidden" name="filtro" value="<?= htmlspecialchars($filtro) ?>">
        <input type="text" name="pesquisa" placeholder="Pesquisar por nome ou login..." value="<?= htmlspecialchars($termo) ?>">
        <button type="submit" class="rwp-btn rwp-btn-primary"><i class="fa fa-search"></i> Buscar</button>
        <?php if (!empty($termo)): ?>
            <a href="?filtro=<?= $filtro ?>" class="rwp-btn rwp-btn-danger"><i class="fa fa-times"></i></a>
        <?php endif; ?>
    </form>
    <?php if ($filtro !== 'todos'): ?>
    <div style="display:flex;align-items:center;gap:6px;">
        <span style="font-size:11px;color:#475569;font-weight:500;">Filtro:</span>
        <span style="background:<?= $filtro=='enviados' ? '#f0fdf4;color:#16a34a;border:1px solid #bbf7d0' : '#fef2f2;color:#dc2626;border:1px solid #fecaca' ?>;padding:3px 10px;border-radius:50px;font-size:10px;font-weight:600;"><?= $filtro_label ?></span>
        <a href="?" style="font-size:10px;color:#94a3b8;text-decoration:none;" title="Remover filtro">✕</a>
    </div>
    <?php endif; ?>
</div>

<?php if (isset($query_error)): ?>
    <div style="padding:10px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#dc2626;font-size:12px;margin-bottom:14px;">
        <i class="fa fa-exclamation-triangle"></i> Erro na consulta: <?= htmlspecialchars($query_error) ?>
    </div>
<?php endif; ?>

<!-- Tabela -->
<div class="rwp-table-wrap">
<table>
    <thead>
        <tr>
            <th style="text-align:left;">Cliente</th>
            <th>WhatsApp</th>
            <th>ID</th>
            <th>Pagamento</th>
            <th class="th-red">Vencimento</th>
            <th>Valor</th>
            <th>Recebido</th>
            <th>Forma</th>
            <th>Status / Envio</th>
            <th>Ação</th>
        </tr>
    </thead>
    <tbody>
<?php
if ($stmt) {
    $tem_registros = false;
    while (mysqli_stmt_fetch($stmt)) {
        $tem_registros = true;
        $datavencF = (new DateTime($datavenc))->format('d/m/Y');
        $datapagF = (new DateTime($datapag))->format('d/m/Y H:i');
        $horaEnvio = ($envio == 1 && $data_envio) ? (new DateTime($data_envio))->format('d/m H:i') : '';
?>
        <tr>
            <td class="rwp-cell-id">
                <span class="rwp-name"><i class="fa fa-user" style="color:#2563eb;font-size:9px;"></i> <?= htmlspecialchars($nome) ?></span>
                <span class="rwp-login"><?= htmlspecialchars($login) ?></span>
                <a href="/admin/cliente_det.hhvm?uuid=<?= urlencode($uuid_cliente) ?>" class="rwp-link"><i class="fa fa-external-link"></i> Detalhes</a>
            </td>
            <td>
                <div class="rwp-zap">
                    <label class="z-sim"><input type="radio" name="zap_<?= $id ?>" value="sim" onclick="atualizarZap('<?= $login ?>','sim')" <?= ($zap === 'sim' ? 'checked' : '') ?>> Sim</label>
                    <label class="z-nao"><input type="radio" name="zap_<?= $id ?>" value="nao" onclick="atualizarZap('<?= $login ?>','nao')" <?= ($zap === 'nao' ? 'checked' : '') ?>> Não</label>
                    <span id="msg-<?= $login ?>" class="rwp-zap-ok">✔</span>
                </div>
            </td>
            <td style="font-size:11px;color:#64748b;"><?= $id ?></td>
            <td style="font-size:11px;"><?= $datapagF ?></td>
            <td style="font-size:11px;color:#dc2626;font-weight:600;"><?= $datavencF ?></td>
            <td style="font-size:11px;">R$ <?= number_format($valor, 2, ',', '.') ?></td>
            <td style="font-weight:700;color:#16a34a;font-size:11px;">R$ <?= number_format($valorpag, 2, ',', '.') ?></td>
            <td style="font-size:11px;"><?= htmlspecialchars($formapag) ?></td>
            <td>
                <?php if ($envio == 1): ?>
                    <span class="rwp-badge rwp-badge-ok">✓ Enviado</span>
                    <?php if ($horaEnvio): ?><br><span style="font-size:9px;color:#16a34a;"><?= $horaEnvio ?></span><?php endif; ?>
                <?php else: ?>
                    <span class="rwp-badge rwp-badge-wait">⏳ Pendente</span>
                <?php endif; ?>
            </td>
            <td>
                <form method="post" action="envio_manual.php" style="margin:0;">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="login" value="<?= htmlspecialchars($login) ?>">
                    <button type="submit" class="rwp-btn rwp-btn-primary rwp-btn-sm" onclick="return confirm('Enviar PDF manualmente?')">
                        <i class="fa fa-paper-plane"></i> Enviar
                    </button>
                </form>
            </td>
        </tr>
<?php
    }
    if (!$tem_registros) {
        echo "<tr><td colspan='10' style='text-align:center;padding:30px;color:#94a3b8;font-size:13px;'>Nenhum registro encontrado para este filtro.</td></tr>";
    }
    mysqli_stmt_close($stmt);
} else {
    echo "<tr><td colspan='10' style='text-align:center;padding:30px;color:#94a3b8;font-size:13px;'>Nenhum registro encontrado.</td></tr>";
}
?>
    </tbody>
</table>
</div>

<?php
// Paginação
if ($total_paginas > 1) {
    $params = 'pesquisa=' . urlencode($termo) . '&filtro=' . urlencode($filtro);
    echo '<div class="rwp-pagination">';
    if ($pagina > 1) echo "<a href='?pagina=" . ($pagina - 1) . "&$params'>‹</a>";
    $ini = max(1, $pagina - 3);
    $fim = min($total_paginas, $pagina + 3);
    for ($p = $ini; $p <= $fim; $p++) {
        if ($p == $pagina) {
            echo "<strong>$p</strong>";
        } else {
            echo "<a href='?pagina=$p&$params'>$p</a>";
        }
    }
    if ($pagina < $total_paginas) echo "<a href='?pagina=" . ($pagina + 1) . "&$params'>›</a>";
    echo '</div>';
}
?>

<!-- Log de Envios -->
<div style="margin-top:16px;background:white;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#1e293b;color:white;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fa fa-paper-plane" style="font-size:12px;color:#60a5fa;"></i>
            <span style="font-size:12px;font-weight:600;letter-spacing:0.3px;">LOG DE ENVIOS</span>
        </div>
        <div style="display:flex;align-items:center;gap:12px;">
            <?php
            $log_ok = 0; $log_err = 0;
            if (file_exists($log_file_path)) {
                $all_lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($all_lines as $l) {
                    if (strpos($l, '✅') !== false) $log_ok++;
                    if (strpos($l, '❌') !== false) $log_err++;
                }
            }
            ?>
            <span style="font-size:10px;color:#86efac;font-weight:600;">✓ <?= $log_ok ?> enviados</span>
            <span style="font-size:10px;color:#fca5a5;font-weight:600;">✗ <?= $log_err ?> erros</span>
            <form action="limpar_log.php" method="post" style="margin:0;">
                <button type="submit" style="background:#dc2626;color:white;border:none;padding:3px 8px;border-radius:4px;font-size:10px;font-weight:600;cursor:pointer;" onclick="return confirm('Limpar todo o log?')">
                    <i class="fa fa-trash"></i> Limpar
                </button>
            </form>
        </div>
    </div>
    <div style="max-height:180px;overflow-y:auto;padding:10px 14px;background:#f8fafc;font-family:'Courier New',monospace;">
        <pre style="font-size:10px;margin:0;line-height:1.7;color:#64748b;white-space:pre-wrap;word-break:break-word;"><?php
        if (file_exists($log_file_path)) {
            $lines = file($log_file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $lines = array_reverse($lines);
            $count = 0;
            foreach ($lines as $line) {
                if (strpos($line, '✅') === false && strpos($line, '❌') === false && strpos($line, '⚠️') === false) continue;
                $escaped = htmlspecialchars($line);
                if (strpos($line, '✅') !== false) {
                    echo "<span style='color:#16a34a;font-weight:500;'>$escaped</span>\n";
                } elseif (strpos($line, '❌') !== false) {
                    echo "<span style='color:#dc2626;font-weight:500;'>$escaped</span>\n";
                } elseif (strpos($line, '⚠️') !== false) {
                    echo "<span style='color:#d97706;font-weight:500;'>$escaped</span>\n";
                }
                $count++;
                if ($count >= 60) break;
            }
            if ($count == 0) echo "<span style='color:#94a3b8;'>Nenhum envio registrado ainda.</span>";
        } else {
            echo "<span style='color:#94a3b8;'>Log não encontrado.</span>";
        }
        ?></pre>
    </div>
</div>

</div><!-- .rwp-container -->

<script>
function atualizarZap(login, valor) {
    fetch('zap_toggle.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'login=' + encodeURIComponent(login) + '&campo=zap&valor=' + valor
    }).then(function(r){ return r.text(); }).then(function(d){
        var msg = document.getElementById('msg-' + login);
        if (msg) { msg.style.display = 'inline'; setTimeout(function(){ msg.style.display = 'none'; }, 2000); }
    });
}
</script>

<?php include('../../baixo.php'); ?>
<script src="../../menu.js.php"></script>
</body>
</html>
