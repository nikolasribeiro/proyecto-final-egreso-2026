<?php

/**
 * Componente Sidebar del panel administrativo
 * 
 * @var string $nombre Nombre del usuario
 * @var string $rol Rol del usuario
 * @var string $rutaActiva Ruta actual para marcar el link activo
 */

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

// URLs de los módulos
$urlsModulos = [
    'documentos' => '/dashboard/documentos',
    'traslados'  => '/dashboard/traslados',
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
        <p class="nav-section-title">Modulos</p>
        <ul class="nav-list">
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
        </ul>
    </nav>

    <!-- Footer con info del usuario -->
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar"><?= e(substr($nombre ?? 'U', 0, 2)) ?></div>
            <div class="user-details">
                <div class="user-name"><?= e($nombre ?? 'Usuario') ?></div>
                <div class="user-role"><?= e(ucfirst($rol ?? 'Usuario')) ?></div>
            </div>
        </div>
    </div>
</aside>