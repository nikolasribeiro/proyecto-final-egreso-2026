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
        <select id="filtro-categoria" class="form-select" onchange="filtrarDocumentos(this.value)">
            <option value="all">Todas las categorías</option>
            <?php foreach ($categoriasUnicas as $catSlug => $catNombre): ?>
                <option value="<?= e($catSlug) ?>"><?= e($catNombre) ?></option>
            <?php endforeach; ?>
        </select>
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
    }
</script>

<!-- Upload Document Modal -->
<?php componente('modulos/documentos/subida-documentos-modal') ?>