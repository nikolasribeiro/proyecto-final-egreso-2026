<?php

/**
 * @var string titulo_pagina
 */

?>

<header class="header">
    <div class="header-left">
        <button class="hamburger" id="hamburger" onclick="toggleSidebar()">
            <svg
                width="24"
                height="24"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24">
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
        <h1 class="header-title" id="header-title">
            $titulo_pagina
        </h1>
    </div>
    <div class="header-right">
        <div class="header-user">
            <form method="post">
                <input type="button" value="Cerrar Sesion">
            </form>
        </div>
    </div>
</header>