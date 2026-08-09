<?php

/**
 * Vista de gestión de usuarios — resumen, filtros y listado paginado.
 *
 * Variables disponibles:
 * @var array $usuarios            Filas hidratadas por ModeloUsuario::listar()
 * @var array $roles               Roles::labels() (UI key => etiqueta legible)
 * @var array $filtros             {estado, rol, q, pagina, por_pagina, total, total_paginas}
 * @var array $stats_estado        {total, activos, inactivos} del universo completo
 * @var array $stats_roles         Conteos por rol del universo completo
 * @var bool  $puede_crear         Permiso usuarios.crear
 * @var bool  $puede_editar        Permiso usuarios.editar (incluye baja/reactivar)
 * @var array|null $flash           {tipo, mensaje}
 */

$statsEstado = is_array($stats_estado ?? null) ? $stats_estado : [];
$statsRoles = is_array($stats_roles ?? null) ? $stats_roles : [];
?>
<section id="usuarios" class="section active usuarios-page">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestión de Usuarios</h2>
            <p class="section-description">Administración de cuentas del sistema.</p>
        </div>
        <?php if ($puede_crear ?? false): ?>
            <a class="btn btn-primary" href="/dashboard/usuarios/nuevo">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Nuevo Usuario
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e((string)($flash['tipo'] ?? 'info')) ?>" role="alert">
            <?= e((string)($flash['mensaje'] ?? '')) ?>
        </div>
    <?php endif; ?>

    <section class="stats-row" aria-label="Resumen de usuarios">
        <article class="stat-card stat-card-total">
            <div class="stat-card-content">
                <span class="stat-card-label">Total</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['total'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-4-4h-1m-4 6H6a4 4 0 01-4-4v-1a4 4 0 014-4h8a4 4 0 014 4v1a4 4 0 01-4 4zm0-10a4 4 0 10-8 0 4 4 0 008 0zm5 1a3 3 0 10-2.83-4" />
                </svg>
            </span>
        </article>

        <article class="stat-card stat-card-activos">
            <div class="stat-card-content">
                <span class="stat-card-label">Activos</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['activos'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </article>

        <article class="stat-card stat-card-inactivos">
            <div class="stat-card-content">
                <span class="stat-card-label">Inactivos</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsEstado['inactivos'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 12H6m12 0a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
            </span>
        </article>

        <article class="stat-card stat-card-administradores">
            <div class="stat-card-content">
                <span class="stat-card-label">Administradores</span>
                <strong class="stat-card-value">
                    <?= e((string)(int)($statsRoles['administrador'] ?? 0)) ?>
                </strong>
            </div>
            <span class="stat-card-icon" aria-hidden="true">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3l7 4v5c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V7l7-4z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.5 12l1.7 1.7 3.3-3.4" />
                </svg>
            </span>
        </article>
    </section>

    <?php componente('modulos/usuarios/filtros', [
        'roles' => $roles,
        'filtros' => $filtros,
    ]); ?>

    <?php componente('modulos/usuarios/tabla', [
        'usuarios' => $usuarios,
        'roles' => $roles,
        'puede_editar' => $puede_editar ?? false,
    ]); ?>

    <?php componente('modulos/usuarios/paginacion', [
        'filtros' => $filtros,
    ]); ?>
</section>
