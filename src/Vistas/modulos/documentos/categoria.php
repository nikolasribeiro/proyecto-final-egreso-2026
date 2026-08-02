<?php

/**
 * Vista de documentos por categoría (destino del QR).
 *
 * @var string $slug
 * @var string $nombreCategoria
 * @var array  $documentos
 */
?>

<section id="documentos-categoria" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title"><?= e($nombreCategoria) ?></h2>
            <p class="section-description">
                Documentos disponibles en esta categoría. Toque un documento para abrirlo.
            </p>
        </div>
        <a class="btn btn-outline" href="/dashboard/documentos">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver a Documentos
        </a>
    </div>

    <?php if (empty($documentos)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3>No hay documentos en esta categoría</h3>
            <p>
                La categoría <strong><?= e($slug) ?></strong> no tiene documentos cargados
                o no existe. Volvé a la lista de documentos para explorar otras categorías.
            </p>
            <a class="btn btn-primary" href="/dashboard/documentos">Ir a Documentos</a>
        </div>
    <?php else: ?>
        <div class="docs-categoria-grid">
            <?php foreach ($documentos as $doc): ?>
                <a class="card doc-categoria-card" href="<?= e($doc['ruta']) ?>" target="_blank" rel="noopener">
                    <div class="doc-categoria-icon">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="doc-categoria-info">
                        <h4 class="doc-categoria-titulo"><?= e($doc['nombre']) ?></h4>
                        <p class="doc-categoria-meta">
                            <span><?= e($doc['tipo']) ?></span>
                            <span>·</span>
                            <span><?= e($doc['tamano']) ?></span>
                            <span>·</span>
                            <span><?= e($doc['fecha_subida']) ?></span>
                        </p>
                    </div>
                    <div class="doc-categoria-arrow">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
