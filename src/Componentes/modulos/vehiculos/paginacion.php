<?php

/**
 * Componente: paginación del listado de vehículos.
 *
 * Mantiene los query params de filtro actuales al navegar entre páginas.
 *
 * @var array $filtros  pagina, por_pagina, total, total_paginas, estado, activo, tipo, q
 */
$pagina    = (int)($filtros['pagina'] ?? 1);
$total     = (int)($filtros['total'] ?? 0);
$porPagina = (int)($filtros['por_pagina'] ?? 25);
$totalPag  = (int)($filtros['total_paginas'] ?? 1);
$desde     = ($pagina - 1) * $porPagina + 1;
$hasta     = min($pagina * $porPagina, $total);

$queryBase = [];
foreach (
    [
        'estado' => $filtros['estado'] ?? 'todos',
        'activo' => $filtros['activo'] ?? 'todos',
        'tipo'   => $filtros['tipo'] ?? 0,
        'q'      => $filtros['q'] ?? '',
    ] as $k => $v
) {
    if ($k === 'tipo') {
        if ((int)$v > 0) {
            $queryBase[$k] = (int)$v;
        }
    } elseif ($v !== '' && $v !== 'todos') {
        $queryBase[$k] = $v;
    }
}

function urlPaginaVehiculos(int $p, array $base): string {
    $params = $base;
    $params['pagina'] = $p;
    return '/dashboard/vehiculos?' . http_build_query($params);
}
?>
<nav class="vehiculos-paginacion" aria-label="Paginación de vehículos">
    <div class="vehiculos-paginacion-info">
        <?php if ($total > 0): ?>
            Mostrando <?= (int)$desde ?>–<?= (int)$hasta ?> de <?= (int)$total ?>
        <?php else: ?>
            Sin resultados
        <?php endif; ?>
    </div>
    <div class="vehiculos-paginacion-controles">
        <?php if ($pagina > 1): ?>
            <a class="btn btn-outline btn-small" href="<?= e(urlPaginaVehiculos(1, $queryBase)) ?>">&laquo; Primero</a>
            <a class="btn btn-outline btn-small" href="<?= e(urlPaginaVehiculos($pagina - 1, $queryBase)) ?>">&lsaquo; Anterior</a>
        <?php endif; ?>
        <span class="btn btn-outline btn-small is-active" aria-current="page">
            Página <?= (int)$pagina ?> / <?= (int)$totalPag ?>
        </span>
        <?php if ($pagina < $totalPag): ?>
            <a class="btn btn-outline btn-small" href="<?= e(urlPaginaVehiculos($pagina + 1, $queryBase)) ?>">Siguiente &rsaquo;</a>
            <a class="btn btn-outline btn-small" href="<?= e(urlPaginaVehiculos($totalPag, $queryBase)) ?>">Último &raquo;</a>
        <?php endif; ?>
    </div>
</nav>