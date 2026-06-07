<?php 
// El componente sidebar es el componente que aparece a la izquierda del panel administrativo

/**
 * @var string $nombre
 * @var string $rol
 */
$uri = $_SERVER['REQUEST_URI'];
?>


<!-- Sidebar Overlay (Mobile) -->
<div
    class="sidebar-overlay"
    id="sidebar-overlay"
    onclick="closeSidebar()"></div>


<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <div class="logo-icon">HC</div>
            <div>
                <div class="logo-text">S.I.G.S.M.</div>
                <div class="logo-subtitle">Hospital Clinicas</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <p class="nav-section-title">Modulos</p>
        <ul class="nav-list">
            <li class="nav-item">
                <!-- Enlace Documentos: Estará activo si NO estamos en la ruta de traslados -->
                <a href="#" class="nav-link <?= strpos($uri, '/traslados') === false ? 'active' : '' ?>" data-section="documents">
                    <svg
                        class="nav-icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Documentos
                </a>
            </li>
            <li class="nav-item">
                <!-- Enlace Traslados: Estará activo SI la URL contiene '/traslados' -->
                <a href="/traslados" class="nav-link <?= strpos($uri, '/traslados') !== false ? 'active' : '' ?>">
                    <svg
                        class="nav-icon"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    Trazabilidad
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">AD</div>
            <div class="user-details">
                <div class="user-name">$nombre</div>
                <div class="user-role">$rol</div>
            </div>
        </div>
    </div>
</aside>