<?php

/**
 * Componente: toolbar de filtros server-side del listado de usuarios.
 *
 * El formulario hace GET al endpoint actual para que el navegador construya
 * la URL y el controlador `usuarios()` valide cada filtro.
 *
 * @var array $roles    Roles::labels()
 * @var array $filtros  estado, rol, q, pagina, por_pagina, total, total_paginas
 * @var string|null $id_prefijo  Prefijo opcional para IDs del componente
 */

$idPrefijo = trim((string)($id_prefijo ?? 'usuarios-filtro'));
if ($idPrefijo === '') {
    $idPrefijo = 'usuarios-filtro';
}
$idBusqueda = $idPrefijo . '-q';
$idEstado = $idPrefijo . '-estado';
$idRol = $idPrefijo . '-rol';

$estadoActual = (string)($filtros['estado'] ?? 'todos');
$rolActual = (string)($filtros['rol'] ?? '');
$busquedaActual = trim((string)($filtros['q'] ?? ''));
$hayFiltrosAplicados = $estadoActual !== 'todos'
    || $rolActual !== ''
    || $busquedaActual !== '';
?>
<form
    class="usuarios-toolbar usuarios-filtros-form"
    method="GET"
    action="/dashboard/usuarios"
    role="search"
    data-rol="usuarios-filtros">
    <div class="form-group usuarios-toolbar-field usuarios-toolbar-search">
        <label class="form-label" for="<?= e($idBusqueda) ?>">Buscar</label>
        <div class="usuarios-search-control">
            <svg class="icon usuarios-search-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
            </svg>
            <input
                type="search"
                id="<?= e($idBusqueda) ?>"
                name="q"
                class="form-input usuarios-search-input"
                placeholder="Buscar por CI, nombre, apellido o email"
                value="<?= e($busquedaActual) ?>"
                maxlength="150"
                data-rol="busqueda">
        </div>
    </div>

    <div class="form-group usuarios-toolbar-field usuarios-toolbar-filter">
        <label class="form-label" for="<?= e($idEstado) ?>">Estado</label>
        <select
            id="<?= e($idEstado) ?>"
            name="estado"
            class="form-select usuarios-filter-select"
            data-rol="estado">
            <option value="todos" <?= $estadoActual === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="activos" <?= $estadoActual === 'activos' ? 'selected' : '' ?>>Activos</option>
            <option value="inactivos" <?= $estadoActual === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
        </select>
    </div>

    <div class="form-group usuarios-toolbar-field usuarios-toolbar-filter">
        <label class="form-label" for="<?= e($idRol) ?>">Rol</label>
        <select
            id="<?= e($idRol) ?>"
            name="rol"
            class="form-select usuarios-filter-select"
            data-rol="rol">
            <option value="">Todos los roles</option>
            <?php foreach ($roles as $clave => $etiqueta): ?>
                <?php $claveRol = (string)$clave; ?>
                <option value="<?= e($claveRol) ?>" <?= $rolActual === $claveRol ? 'selected' : '' ?>>
                    <?= e((string)$etiqueta) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="usuarios-toolbar-actions">
        <button type="submit" class="btn btn-outline usuarios-filter-submit">
            <svg class="icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
            </svg>
            Filtrar
        </button>

        <?php if ($hayFiltrosAplicados): ?>
            <a href="/dashboard/usuarios" class="btn btn-outline usuarios-filter-clear" data-rol="limpiar">
                Limpiar
            </a>
        <?php endif; ?>
    </div>
</form>
