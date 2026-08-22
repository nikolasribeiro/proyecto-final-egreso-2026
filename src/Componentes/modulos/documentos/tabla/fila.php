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

// El QR por archivo apunta al DOCUMENTO puntual (no a la categoría).
// El QR por categoría vive en el botón "Generar QR de la categoría"
// al lado del filtro (ver /dashboard/documentos, inicio.php).
$qrData        = $rutaDocumento;
$qrNombreLabel = $nombreDocumento;
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
        <div class="table-actions">
            <button
                type="button"
                class="btn btn-secondary btn-small"
                data-doc-action="edit"
                data-doc-id="<?= e((string)$idDocumento) ?>"
                data-doc-titulo="<?= e($nombreDocumento) ?>"
                data-doc-categoria="<?= e((string)($categoriaDocumento['slug'] ?? '')) ?>"
                data-doc-categoria-id="<?= e((string)($doc['id_categoria'] ?? '')) ?>"
                data-doc-ruta="<?= e($rutaDocumento) ?>"
                title="Editar documento">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                </svg>
                Editar
            </button>
            <button
                type="button"
                class="btn btn-danger btn-small"
                data-doc-action="delete"
                data-doc-id="<?= e((string)$idDocumento) ?>"
                data-doc-titulo="<?= e($nombreDocumento) ?>"
                title="Eliminar documento">
                <svg
                    class="icon"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3" />
                </svg>
                Eliminar
            </button>
            <button
                type="button"
                class="btn btn-secondary btn-small"
                onclick="openQRModal(<?= htmlspecialchars(json_encode($qrNombreLabel), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode((string)$idDocumento), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode($qrData), ENT_QUOTES, 'UTF-8') ?>)">
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
                QR
            </button>
        </div>
    </td>
</tr>

<!-- QR Code Modal: apunta al documento puntual (no a su categoría). -->
<?php
componente(
    'modulos/documentos/qr-modal',
    [
        'idDocumento'     => $idDocumento,
        'rutaDocumento'   => $qrData,
        'nombreDocumento' => $qrNombreLabel,
    ]
);
?>