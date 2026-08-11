<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Si no hay token, lo creamos en este mismo instante
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

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
            <!-- Alerta para mensajes de error o éxito -->
            <div id="modalFeedback" class="alert" style="display: none; margin-bottom: 1rem;"></div>

            <!-- Zona Drag and Drop -->
            <div
                id="drop-zone"
                class="empty-state"
                style="border-style: dashed; cursor: pointer"
                onclick="document.getElementById('inputDocumento').click()">
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
                <p id="drop-zone-title" class="empty-title">Arrastre un archivo aquí</p>
                <p id="drop-zone-text" class="empty-text">o haga clic para seleccionar (solo .pdf)</p>
            </div>

            <!-- Input invisible para seleccionar archivo -->
            <input type="file" id="inputDocumento" accept="application/pdf" style="display: none;">

            <!-- Selector de Categoría (si existen en la vista) -->
            <?php if (isset($categorias) && !empty($categorias)): ?>
                <div class="form-group" style="margin-top: 1rem;">
                    <label for="selectCategoriaModal">Categoría</label>
                    <select id="selectCategoriaModal" class="form-control">
                        <option value="">Seleccione una categoría</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
        </div>
        <div class="modal-footer">
            <button
                class="btn btn-secondary btn-small"
                onclick="closeModal('upload-modal')">
                Cancelar
            </button>
            <button id="btnSubirDocumento" class="btn btn-primary btn-small">Subir Documento</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const dropZone = document.getElementById('drop-zone');
    const inputArchivo = document.getElementById('inputDocumento');
    const titleText = document.getElementById('drop-zone-title');
    const subText = document.getElementById('drop-zone-text');
    const btnSubir = document.getElementById('btnSubirDocumento');
    const feedbackBox = document.getElementById('modalFeedback');
    const selectCategoria = document.getElementById('selectCategoriaModal');

    if (!btnSubir || !inputArchivo) return;

    // Manejar eventos de Drag and Drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, (e) => {
            e.preventDefault();
            e.stopPropagation();
        }, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '#0056b3', false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.style.borderColor = '', false);
    });

    dropZone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            inputArchivo.files = files;
            actualizarUIArchivo(files[0]);
        }
    });

    inputArchivo.addEventListener('change', () => {
        if (inputArchivo.files.length > 0) {
            actualizarUIArchivo(inputArchivo.files[0]);
        }
    });

    function actualizarUIArchivo(file) {
        titleText.textContent = file.name;
        subText.textContent = `(${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
    }

    // Petición POST al servidor con captura de errores
    btnSubir.addEventListener('click', async (e) => {
        e.preventDefault();

        if (!inputArchivo.files[0]) {
            mostrarFeedback('Por favor, selecciona un archivo PDF.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('documento', inputArchivo.files[0]);
        
        if (selectCategoria && selectCategoria.value) {
            formData.append('id_categoria', selectCategoria.value);
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '<?= $_SESSION["csrf_token"] ?? "" ?>';
        formData.append('csrf_token', csrfToken);

        btnSubir.disabled = true;
        btnSubir.textContent = 'Subiendo...';

        try {

            console.log("Token CSRF a enviar:", csrfToken); // Para depurar en F12 # 116

            const response = await fetch('/api/documentos', { 
            method: 'POST',
            credentials: 'same-origin', // ¡ESTO ES VITAL PARA MANTENER LA SESIÓN PHP! # 116
            headers: {
                'X-CSRF-TOKEN': csrfToken
            },
            body: formData
        });

            const rawText = await response.text();
            let result;

            try {
                result = JSON.parse(rawText);
            } catch (jsonErr) {
                throw new Error(`Error del servidor (${response.status}): ${rawText.replace(/<[^>]*>?/gm, '').substring(0, 120)}`);
            }

            if (!response.ok) {
                throw new Error(result.error || 'Error al procesar la subida.');
            }

            mostrarFeedback(result.mensaje || 'Documento subido con éxito.', 'exito');
            setTimeout(() => window.location.reload(), 1200);

        } catch (err) {
            mostrarFeedback(err.message, 'error');
        } finally {
            btnSubir.disabled = false;
            btnSubir.textContent = 'Subir Documento';
        }
    });

    function mostrarFeedback(msg, tipo) {
        if (!feedbackBox) return;
        feedbackBox.textContent = msg;
        feedbackBox.style.display = 'block';
        feedbackBox.style.padding = '0.75rem';
        feedbackBox.style.borderRadius = '4px';
        feedbackBox.style.color = tipo === 'exito' ? '#155724' : '#721c24';
        feedbackBox.style.backgroundColor = tipo === 'exito' ? '#d4edda' : '#f8d7da';
        feedbackBox.style.borderColor = tipo === 'exito' ? '#c3e6cb' : '#f5c6cb';
    }
});
</script>