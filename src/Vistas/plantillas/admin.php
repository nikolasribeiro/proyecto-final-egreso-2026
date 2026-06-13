<?php 
// Plantilla para el panel administrativo

/**
 * @var string titulo_pagina
 * @var string $nombre
 * @var string $rol
 * @var string $contenido
 */
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>HC - <?php echo e($titulo_pagina) ?> </title>
    <link rel="stylesheet" href="/public/css/dashboard.css">
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <?php componente('admin/sidebar'); ?>

        <!-- Main Content Wrapper -->
        <div class="main-wrapper">
            <!-- Header -->
            <?php componente('admin/header',["titulo_pagina" => $titulo_pagina]) ?>

            <!-- Main Content -->
            <main class="main">

                <div class="section active">
                    <?= $contenido ?? '' ?>
                </div>
               
            </main>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div
        id="qr-modal"
        class="modal-overlay"
        onclick="closeModalOnOverlay(event)">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Codigo QR del Documento</h3>
                <button class="modal-close" onclick="closeModal('qr-modal')">
                    <svg
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="qr-container">
                    <div class="qr-code">
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=placeholder-url"
                            alt="Codigo QR"
                            width="200"
                            height="200" />
                    </div>
                    <p class="qr-document-name" id="qr-document-name">
                        Nombre del documento
                    </p>
                    <p style="font-size: 0.875rem; color: var(--secondary-gray)">
                        Escanee este codigo para acceder al documento
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn btn-secondary btn-small"
                    onclick="closeModal('qr-modal')">
                    Cerrar
                </button>
                <button class="btn btn-primary btn-small">
                    <svg
                        class="icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Descargar QR
                </button>
            </div>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div
        id="upload-modal"
        class="modal-overlay"
        onclick="closeModalOnOverlay(event)">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Cargar Nuevo Documento</h3>
                <button class="modal-close" onclick="closeModal('upload-modal')">
                    <svg
                        width="16"
                        height="16"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div
                    class="empty-state"
                    style="border-style: dashed; cursor: pointer">
                    <div class="empty-icon">
                        <svg
                            width="32"
                            height="32"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                    </div>
                    <p class="empty-title">Arrastre un archivo aqui</p>
                    <p class="empty-text">o haga clic para seleccionar</p>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    class="btn btn-secondary btn-small"
                    onclick="closeModal('upload-modal')">
                    Cancelar
                </button>
                <button class="btn btn-primary btn-small">Subir Documento</button>
            </div>
        </div>
    </div>
<script src="/public/js/dashboard.js"></script>
</body>

</html>