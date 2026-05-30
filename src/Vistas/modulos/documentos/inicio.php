<?php

/**
 * @var array $documentos
 */
$documentos = $documentos ?? [];
?>



<section id="documents" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestion de Documentos</h2>
            <p class="section-description">
                Administre y genere codigos QR para sus documentos
            </p>
        </div>
        <button
            class="btn btn-primary"
            onclick="openModal('upload-modal')">
            <svg
                class="icon"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M12 4v16m8-8H4" />
            </svg>
            Cargar Nuevo Documento
        </button>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="documents-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Tamano</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Document Row 1 -->
                    <?php foreach ($documentos as $doc) : ?>
                        <?php componente('modulos/documentos/tabla/fila', [
                            'idDocumento' => $doc['id'],
                            'nombreDocumento' => $doc['nombre'],
                            'tipoDocumento' => $doc['tipo'],
                            'tamanoDocumento' => $doc['tamano'],
                            'fechaSubidaDocumento' => $doc['fecha_subida'],
                            'rutaDocumento' => $doc['ruta'],
                        ]) ?>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- Upload Document Modal -->
<?php componente('modulos/documentos/subida-documentos-modal') ?>