<?php

/**
 * Vista: alta de un usuario nuevo.
 *
 * Variables:
 * @var string $nombre
 * @var string $rol
 * @var array  $roles            Roles::labels()
 * @var array  $catalogo_roles   ModeloUsuario::obtenerCatalogoRoles()
 * @var string $csrf
 * @var array|null $flash
 */
?>
<section id="usuarios-nuevo" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Nuevo Usuario</h2>
            <p class="section-description">
                Alta de cuenta nueva. La CI debe ser única, el email debe ser
                válido, y la contraseña se almacena hasheada (bcrypt).
            </p>
        </div>
        <a href="/dashboard/usuarios" class="btn btn-outline btn-small">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver al listado
        </a>
    </div>

    <?php if (!empty($flash)): ?>
        <?php
            // Los controladores guardan `tipo => 'error'`, pero la hoja de
            // estilos solo define `.alert-danger`. Normalizamos acá para
            // no depender de un alias `.alert-error` que pueda faltar por
            // caché del navegador.
            $alertTipo = match ($flash['tipo'] ?? 'info') {
                'error', 'danger' => 'danger',
                'exito', 'success' => 'success',
                default => $flash['tipo'] ?? 'info',
            };
        ?>
        <div class="alert alert-<?= e($alertTipo) ?>" role="alert">
            <?= e($flash['mensaje'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php componente('modulos/usuarios/form-usuario', [
        'accion_post' => '/dashboard/usuarios',
        'csrf' => $csrf,
        'texto_boton' => 'Crear Usuario',
        'mostrar_password' => true,
        'roles' => $roles,
        'catalogo_roles' => $catalogo_roles,
        'roles_seleccionados' => [],
        'valores' => [],
    ]); ?>
</section>
