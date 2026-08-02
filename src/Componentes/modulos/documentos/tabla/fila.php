<?php

/**
 * @var int     $idDocumento
 * @var string  $nombreDocumento
 * @var string  $tipoDocumento
 * @var int     $tamanoDocumento
 * @var string  $fechaSubidaDocumento
 * @var string  $rutaDocumento
 * @var array   $categoriaDocumento
 */
$categoriaDocumento = $categoriaDocumento ?? ['slug' => 'general', 'nombre' => 'General'];
$qrData = '/dashboard/documentos/categoria/' . $categoriaDocumento['slug'];
?>
<tr data-categoria="<?= e($categoriaDocumento['slug']) ?>">
    <td>
        <div class="document-cell">
            <div class="document-icon">
                <svg
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </div>
            <div>
                <div class="document-name">
                    <?php echo e($nombreDocumento) ?>
                </div>
                <div class="document-type">
                    <?php echo e($tipoDocumento) ?>
                </div>
            </div>
        </div>
    </td>
    <td data-label="Categoria">
        <span class="document-category badge">
            <?= e($categoriaDocumento['nombre']) ?>
        </span>
    </td>
    <td data-label="Tamano">
        <span class="document-size">
            <?php echo e($tamanoDocumento) ?>
        </span>
    </td>
    <td data-label="Fecha">
        <span class="document-date">
            <?php echo e($fechaSubidaDocumento) ?>
        </span>
    </td>
    <td data-label="Acciones">
        <button
            class="btn btn-secondary btn-small"
            onclick="openQRModal('<?= e($nombreDocumento) ?>', '<?= e($idDocumento) ?>', '<?= e($qrData) ?>')">
            <svg
                class="icon"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            Generar QR
        </button>
    </td>
</tr>

<!-- QR Code Modal -->
<?php
componente(
    'modulos/documentos/qr-modal',
    [
        'idDocumento' => $idDocumento,
        'rutaDocumento' => $qrData,
        'nombreDocumento' => $nombreDocumento,
    ]
);
?>