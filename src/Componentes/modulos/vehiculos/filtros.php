<?php

/**
 * Componente: toolbar de filtros server-side del listado de vehículos.
 *
 * El formulario hace GET al endpoint actual para que el navegador construya
 * la URL y el controlador `vehiculos()` valide cada filtro.
 *
 * Variables:
 * @var array $tipos      Catálogo de tipos de vehículo
 * @var array $filtros    estado, activo, tipo, q, pagina, por_pagina, total, total_paginas
 */

$estadoActual = (string)($filtros['estado'] ?? 'todos');
$activoActual = (string)($filtros['activo'] ?? 'todos');
$tipoActual = (int)($filtros['tipo'] ?? 0);
$busquedaActual = trim((string)($filtros['q'] ?? ''));

$hayFiltrosAplicados = $estadoActual !== 'todos'
    || $activoActual !== 'todos'
    || $tipoActual > 0
    || $busquedaActual !== '';
?>
<form
    class="vehiculos-toolbar vehiculos-filtros-form"
    method="GET"
    action="/dashboard/vehiculos"
    role="search"
    data-rol="vehiculos-filtros">
    <div class="form-group vehiculos-toolbar-field vehiculos-toolbar-search">
        <label class="form-label" for="vehiculos-filtro-q">Buscar</label>
        <div class="vehiculos-search-control">
            <svg class="icon vehiculos-search-icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z" />
            </svg>
            <input
                type="search"
                id="vehiculos-filtro-q"
                name="q"
                class="form-input vehiculos-search-input"
                placeholder="Buscar por matrícula"
                value="<?= e($busquedaActual) ?>"
                maxlength="20"
                data-rol="busqueda">
        </div>
    </div>

    <div class="form-group vehiculos-toolbar-field vehiculos-toolbar-filter">
        <label class="form-label" for="vehiculos-filtro-estado">Disponibilidad</label>
        <select
            id="vehiculos-filtro-estado"
            name="estado"
            class="form-select vehiculos-filter-select"
            data-rol="estado">
            <option value="todos" <?= $estadoActual === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="disponibles" <?= $estadoActual === 'disponibles' ? 'selected' : '' ?>>Disponibles</option>
            <option value="no_disponibles" <?= $estadoActual === 'no_disponibles' ? 'selected' : '' ?>>No disponibles</option>
        </select>
    </div>

    <div class="form-group vehiculos-toolbar-field vehiculos-toolbar-filter">
        <label class="form-label" for="vehiculos-filtro-activo">Alta/Baja</label>
        <select
            id="vehiculos-filtro-activo"
            name="activo"
            class="form-select vehiculos-filter-select"
            data-rol="activo">
            <option value="todos" <?= $activoActual === 'todos' ? 'selected' : '' ?>>Todos</option>
            <option value="activos" <?= $activoActual === 'activos' ? 'selected' : '' ?>>Activos</option>
            <option value="inactivos" <?= $activoActual === 'inactivos' ? 'selected' : '' ?>>De baja</option>
        </select>
    </div>

    <div class="form-group vehiculos-toolbar-field vehiculos-toolbar-filter">
        <label class="form-label" for="vehiculos-filtro-tipo">Tipo</label>
        <select
            id="vehiculos-filtro-tipo"
            name="tipo"
            class="form-select vehiculos-filter-select"
            data-rol="tipo">
            <option value="0">Todos los tipos</option>
            <?php foreach (is_array($tipos ?? null) ? $tipos : [] as $tipo): ?>
                <?php $idTipo = (int)($tipo['id'] ?? 0); ?>
                <option value="<?= $idTipo ?>" <?= $tipoActual === $idTipo ? 'selected' : '' ?>>
                    <?= e((string)($tipo['descripcion'] ?? '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="vehiculos-toolbar-actions">
        <button type="submit" class="btn btn-outline vehiculos-filter-submit">
            <svg class="icon" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18l-7 8v6l-4 2v-8L3 4z" />
            </svg>
            Filtrar
        </button>

        <?php if ($hayFiltrosAplicados): ?>
            <a href="/dashboard/vehiculos" class="btn btn-outline vehiculos-filter-clear" data-rol="limpiar">
                Limpiar
            </a>
        <?php endif; ?>
    </div>
</form>