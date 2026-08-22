<?php

/**
 * @var array $documentos
 *
 * ModeloDocumento::obtenerTodos() devuelve filas con keys:
 *   - id, id_categoria, titulo, ruta_archivo, documento_activo,
 *     ci_funcionario, created_at, updated_at
 *   - nombre_categoria, slug (del JOIN con categorias_documentos)
 *
 * El componente `tabla/fila` espera un shape distinto:
 *   - nombreDocumento (= titulo)
 *   - tipoDocumento (derivado de la extensión de ruta_archivo)
 *   - tamanoDocumento (no se persiste en BD → 'N/A')
 *   - fechaSubidaDocumento (= created_at)
 *   - rutaDocumento (= ruta_archivo)
 *   - categoriaDocumento = ['slug' => ..., 'nombre' => ...]
 *
 * El mapeo vive acá. Si el modelo agrega más columnas (ej. tamaño
 * real del archivo en BD), alcanza con actualizar este view.
 */
$documentos = $documentos ?? [];

/**
 * Devuelve el tipo MIME aproximado según la extensión de la ruta.
 * Si no se puede inferir, devuelve 'Documento'.
 */
function tipoDocumentoDesdeRuta(?string $ruta): string
{
    if ($ruta === null || $ruta === '') return 'Documento';
    $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
    $map = [
        'pdf'  => 'PDF',
        'doc'  => 'DOC',  'docx' => 'DOCX',
        'xls'  => 'XLS',  'xlsx' => 'XLSX',
        'ppt'  => 'PPT',  'pptx' => 'PPTX',
        'png'  => 'PNG',  'jpg'  => 'JPG', 'jpeg' => 'JPG',
        'gif'  => 'GIF',  'svg'  => 'SVG',
        'txt'  => 'TXT',  'csv'  => 'CSV',
        'zip'  => 'ZIP',  'rar'  => 'RAR',
    ];
    return $map[$ext] ?? strtoupper($ext ?: 'Documento');
}

// Calcular categorías únicas para el filtro. OJO: las variables locales
// de este loop NO deben llamarse $nombre ni $rol porque Vista::mostrar()
// hace extract() en el mismo scope y $nombre/$rol se filtran al layout
// admin.php, donde se usan para mostrar el usuario logueado en el
// sidebar. Si pisamos $nombre acá, el sidebar muestra el nombre de la
// categoría en lugar del nombre real (bug pre-existente que recién se
// notó con el usuario root).
$categoriasUnicas = [];
foreach ($documentos as $doc) {
    $catSlug   = (string)($doc['slug'] ?? 'general');
    $catNombre = (string)($doc['nombre_categoria'] ?? 'General');
    $categoriasUnicas[$catSlug] = $catNombre;
}
?>

<section id="documents" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestion de Documentos</h2>
            <p class="section-description">
                Administre y genere codigos QR para acceder a documentos por categoría
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

    <div class="docs-filtros">
        <label class="form-label" for="filtro-categoria">Filtrar por categoría</label>
        <div class="docs-filtros-row">
            <select id="filtro-categoria" class="form-select" onchange="filtrarDocumentos(this.value)">
                <option value="all">Todas las categorías</option>
                <?php foreach ($categoriasUnicas as $catSlug => $catNombre): ?>
                    <option value="<?= e($catSlug) ?>"><?= e($catNombre) ?></option>
                <?php endforeach; ?>
            </select>
            <div id="qr-categoria-slot" class="docs-filtros-slot"></div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="documents-table">
                <thead>
                    <tr>
                        <th>Documento</th>
                        <th>Categoria</th>
                        <th>Tamano</th>
                        <th>Fecha de Subida</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc) : ?>
                        <?php
                            $ruta = (string)($doc['ruta_archivo'] ?? '');
                            componente('modulos/documentos/tabla/fila', [
                                'idDocumento'         => (int)($doc['id'] ?? 0),
                                'nombreDocumento'     => (string)($doc['titulo'] ?? '(sin título)'),
                                'tipoDocumento'       => tipoDocumentoDesdeRuta($ruta),
                                'tamanoDocumento'     => 'N/A',
                                'fechaSubidaDocumento' => (string)($doc['created_at'] ?? ''),
                                'rutaDocumento'       => $ruta,
                                'categoriaDocumento'  => [
                                    'slug'   => (string)($doc['slug'] ?? 'general'),
                                    'nombre' => (string)($doc['nombre_categoria'] ?? 'General'),
                                ],
                            ]);
                        ?>
                    <?php endforeach; ?>

                </tbody>
            </table>
        </div>
    </div>
</section>

<script>
    /**
     * Filtro client-side por categoría. El atributo data-categoria
     * de cada <tr> se setea en fila.php.
     *
     * Además, cuando hay una categoría específica seleccionada
     * (slug !== 'all') inyecta un botón "Generar QR de la categoría"
     * al lado del dropdown. Cuando vuelve a "all", el botón se
     * elimina del DOM.
     */
    function filtrarDocumentos(slug) {
        const filas = document.querySelectorAll('.documents-table tbody tr');
        filas.forEach((fila) => {
            if (slug === 'all' || fila.dataset.categoria === slug) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });

        const slot = document.getElementById('qr-categoria-slot');
        if (!slot) return;

        // Limpiar cualquier botón previo (categoría anterior).
        slot.innerHTML = '';

        if (slug === 'all') return;

        const select = document.getElementById('filtro-categoria');
        const nombreCategoria = select
            ? (select.options[select.selectedIndex]?.text || slug)
            : slug;

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'btn btn-primary';
        boton.innerHTML = `
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h2M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
            Generar QR de la categoria
        `;
        boton.addEventListener('click', function () {
            openCategoryQRModal(slug, nombreCategoria);
        });

        slot.appendChild(boton);
    }

    /**
     * Abre el modal de QR de categoría con la URL pública
     * `/d/{slug}` codificada en el QR. La URL es la misma que
     * usa el botón "Generar QR" de cada fila (ver fila.php:13).
     */
    function openCategoryQRModal(slug, nombreCategoria) {
        const url = '/d/' + encodeURIComponent(slug);
        const img = document.getElementById('qr-categoria-img');
        const nombre = document.getElementById('qr-categoria-nombre');
        const urlLabel = document.getElementById('qr-categoria-url');

        if (img) {
            img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
                + encodeURIComponent(url);
        }
        if (nombre) {
            nombre.textContent = nombreCategoria;
        }
        if (urlLabel) {
            urlLabel.textContent = url;
        }

        // downloadQR() y printQR() en dashboard.js leen
        // window.currentQRData para componer la imagen, así que
        // seteamos el mismo shape que usa openQRModal().
        window.currentQRData = {
            documentName: nombreCategoria,
            documentId: 'categoria',
            documentUrl: url,
        };

        openModal('qr-modal-categoria');
    }
</script>

<!-- Upload Document Modal issue 116 -->
<?php componente('modulos/documentos/subida-documentos-modal', [
    'categorias' => $categoriasUnicas
]) ?>

<!-- Modal de QR por categoría (el botón al lado del filtro lo abre) -->
<?php componente('modulos/documentos/qr-modal-categoria') ?>

<!-- Modal de edición de documento (issue #152) -->
<?php
// El modal de edición necesita la lista COMPLETA de categorías
// (con id, nombre y slug), no el mapa reducido que usa el filtro.
componente('modulos/documentos/editar-documento-modal', [
    'categorias' => $categorias ?? [],
])
?>

<!-- Modal de confirmación de borrado (soft delete, issue #152) -->
<?php componente('modulos/documentos/eliminar-documento-modal') ?>

<script src="/assets/javascript/documentos/gestion-documentos.js"></script>