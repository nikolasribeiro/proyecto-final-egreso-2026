<?php

/**
 * Componente Sidebar del panel administrativo
 *
 * @var string $nombre Nombre del usuario
 * @var string $rol    Rol del usuario
 */

use Nucleo\Constantes\Roles;

// Obtener la ruta actual desde la URL
$rutaActual = $_SERVER['REQUEST_URI'] ?? '/';

/**
 * Helper para determinar si un link está activo
 * Compara la ruta actual con el href del link
 */
function esLinkActivo(string $href, string $rutaActual): bool
{
    return $rutaActual === $href || str_starts_with($rutaActual, $href . '/');
}

// Mapa de roles para mostrar el nombre legible
$rolLabels = Roles::labels();

// URLs de los módulos
$urlsModulos = [
    'documentos' => '/dashboard/documentos',
    'traslados'  => '/dashboard/traslados',
    'encuestas'  => '/dashboard/encuestas',
    'usuarios'   => '/dashboard/usuarios',
    'permisos'   => '/dashboard/permisos',
];
?>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <!-- Header con Logo -->
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">HC</div>
            <div>
                <div class="logo-text">S.I.G.S.M.</div>
                <div class="logo-subtitle">Hospital Clinicas</div>
            </div>
        </div>
    </div>

    <!-- Navegación -->
    <nav class="sidebar-nav">
        <p class="nav-section-title">Operaciones</p>
        <ul class="nav-list">
            <!-- Trazabilidad / Traslados -->
            <li class="nav-item">
                <a
                    href="<?= e($urlsModulos['traslados']) ?>"
                    class="nav-link <?= esLinkActivo($urlsModulos['traslados'], $rutaActual) ? 'active' : '' ?>"
                    data-section="traslados">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    Traslados
                </a>
            </li>

            <!-- Documentos -->
            <li class="nav-item">
                <a
                    href="<?= e($urlsModulos['documentos']) ?>"
                    class="nav-link <?= esLinkActivo($urlsModulos['documentos'], $rutaActual) ? 'active' : '' ?>"
                    data-section="documentos">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Documentos
                </a>
            </li>

            <!-- Encuestas (todos los roles la ven) -->
            <li class="nav-item">
                <a
                    href="<?= e($urlsModulos['encuestas']) ?>"
                    class="nav-link <?= esLinkActivo($urlsModulos['encuestas'], $rutaActual) ? 'active' : '' ?>"
                    data-section="encuestas">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                    Encuestas
                </a>
            </li>
        </ul>

        <?php if (Roles::permiso($rol ?? '', 'usuarios', 'ver') || Roles::permiso($rol ?? '', 'permisos', 'ver')): ?>
            <p class="nav-section-title">Administración</p>
            <ul class="nav-list">
                <?php if (Roles::permiso($rol ?? '', 'usuarios', 'ver')): ?>
                    <li class="nav-item">
                        <a
                            href="<?= e($urlsModulos['usuarios']) ?>"
                            class="nav-link <?= esLinkActivo($urlsModulos['usuarios'], $rutaActual) ? 'active' : '' ?>"
                            data-section="usuarios">
                            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Usuarios
                        </a>
                    </li>
                <?php endif; ?>

                <?php if (Roles::permiso($rol ?? '', 'permisos', 'ver')): ?>
                    <li class="nav-item">
                        <a
                            href="<?= e($urlsModulos['permisos']) ?>"
                            class="nav-link <?= esLinkActivo($urlsModulos['permisos'], $rutaActual) ? 'active' : '' ?>"
                            data-section="permisos">
                            <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Permisos
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>
    </nav>

    <!-- Footer con info del usuario -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= e(substr($nombre ?? 'U', 0, 2)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= e($nombre ?? 'Usuario') ?></div>
                <div class="user-role"><?= e($rolLabels[$rol ?? ''] ?? ucfirst($rol ?? 'Usuario')) ?></div>
            </div>
        </div>
    </div>
</aside>