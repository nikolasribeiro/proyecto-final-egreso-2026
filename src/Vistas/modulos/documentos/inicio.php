<?php

/**
 * @var array $documentos
 */
$documentos = $documentos ?? [];

// Calcular categorías únicas para el filtro
$categoriasUnicas = [];
foreach ($documentos as $doc) {
    $slug = $doc['categoria']['slug'] ?? 'general';
    $nombre = $doc['categoria']['nombre'] ?? 'General';
    $categoriasUnicas[$slug] = $nombre;
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
            <?php foreach ($categoriasUnicas as $slug => $nombre): ?>
                <option value="<?= e($slug) ?>"><?= e($nombre) ?></option>
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
                        <?php componente('modulos/documentos/tabla/fila', [
                            'idDocumento' => $doc['id'],
                            'nombreDocumento' => $doc['nombre'],
                            'tipoDocumento' => $doc['tipo'],
                            'tamanoDocumento' => $doc['tamano'],
                            'fechaSubidaDocumento' => $doc['fecha_subida'],
                            'rutaDocumento' => $doc['ruta'],
                            'categoriaDocumento' => $doc['categoria'] ?? ['slug' => 'general', 'nombre' => 'General'],
                        ]) ?>
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