<div class="section-header">
    <div>
        <h2 class="section-title">Trazabilidad de Traslados</h2>
        <p class="section-description">
            Gestione y monitoree los traslados activos
        </p>
    </div>
    <a href="/traslados/nuevo" class="btn btn-primary">
        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Solicitar Traslado
    </a>
</div>

<h3 style="font-size: 1rem; color: var(--secondary-gray); margin-bottom: 1rem;">
    Traslados Activos   
</h3>

<div class="card-grid">
    <?php if (empty($traslados)): ?>
        <p style="color: var(--secondary-gray);">No hay traslados activos en este momento.</p>
    <?php else: ?>
        <?php foreach ($traslados as $t): ?>
            <div class="card transfer-card" onclick="window.location.href='/traslados/detalle?id=<?= $t['id'] ?>'" style="cursor: pointer">
                <div class="transfer-header">
                    <?php if (!empty($t['ci_paciente_externo'])): ?>
                        <span class="transfer-type-badge badge-patient">Paciente</span>
                    <?php else: ?>
                        <span class="transfer-type-badge badge-equipment">Logística</span>
                    <?php endif; ?>
                    <span class="transfer-id">#TRF-<?= date('Y') ?>-<?= str_pad($t['id'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                
                <div class="transfer-details">
                    <div class="transfer-detail-row">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <?= htmlspecialchars($t['origen']) ?> ➔ <?= htmlspecialchars($t['destino']) ?>
                    </div>
                    <div class="transfer-detail-row">
                        <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                        Chofer: <?= htmlspecialchars($t['chofer_nombre']) ?>
                    </div>
                </div>
                
                <div class="transfer-status">
                    <span class="status-dot"></span>
                    <?= htmlspecialchars($t['estado_nombre']) ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>