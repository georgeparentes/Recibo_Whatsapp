<?php
$current_page = basename($_SERVER['PHP_SELF']);
$navItems = [
    ['file' => 'index.php', 'icon' => 'fa-home', 'label' => 'Início'],
    ['file' => 'ativarnumeros.php', 'icon' => 'fa-whatsapp', 'label' => 'Números'],
    ['file' => 'agendamento_envio.php', 'icon' => 'fa-clock-o', 'label' => 'Agendar Envio'],
    ['file' => 'agendamento_limpar.php', 'icon' => 'fa-eraser', 'label' => 'Limpeza'],
    ['file' => 'editar_mensagem.php', 'icon' => 'fa-pencil-square-o', 'label' => 'Mensagem'],
    ['file' => 'cfg.php', 'icon' => 'fa-cog', 'label' => 'Configurações'],
];
?>

<div style="text-align:center;padding:6px 0 2px;">
    <span style="font-size:15px;font-weight:700;color:#1e293b;"><?php echo $Manifest->{'name'}; ?></span>
    <span style="display:block;font-size:10px;color:#94a3b8;">v<?php echo $Manifest->{'version'}; ?> &bull; <?php echo $Manifest->{'author'} ?? 'AGFNET'; ?></span>
</div>

<div style="text-align:center;padding:4px 0 10px;border-bottom:1px solid #e2e8f0;margin-bottom:12px;">
    <div style="display:inline-flex;gap:2px;flex-wrap:wrap;justify-content:center;background:#f1f5f9;border-radius:8px;padding:3px;">
        <?php foreach ($navItems as $item):
            $isActive = ($current_page == $item['file']);
            $bg = $isActive ? 'background:white;box-shadow:0 1px 4px rgba(0,0,0,0.08);' : '';
            $color = $isActive ? 'color:#2563eb;font-weight:600;' : 'color:#475569;';
        ?>
        <a href="<?= $item['file'] ?>" style="display:inline-flex;align-items:center;gap:4px;padding:5px 11px;border-radius:6px;text-decoration:none;font-size:11px;font-weight:500;white-space:nowrap;<?= $bg . $color ?>">
            <i class="fa <?= $item['icon'] ?>" style="font-size:11px;"></i> <?= $item['label'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
