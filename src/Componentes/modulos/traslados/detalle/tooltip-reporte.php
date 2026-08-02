<!-- Report Tooltip - Shows reports for a specific step on hover -->
<?php if (!empty($reportes)): ?>
<div class="report-tooltip" data-tooltip-reportes>
    <div class="report-tooltip-title">Reportes (<?= count($reportes) ?>)</div>
    <ul class="report-tooltip-list">
        <?php foreach ($reportes as $reporte): ?>
            <li>
                <div class="report-tooltip-tipo"><?= htmlspecialchars($reporte['tipo']) ?></div>
                <div class="report-tooltip-mensaje"><?= htmlspecialchars($reporte['mensaje']) ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
