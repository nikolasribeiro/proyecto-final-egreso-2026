<?php

/**
 * Vista: edición de un usuario existente.
 *
 * Variables:
 * @var string $nombre
 * @var string $rol
 * @var array  $usuario          fila de la tabla usuarios + roles UI
 * @var array  $roles            Roles::labels()
 * @var array  $catalogo_roles
 * @var string $csrf
 * @var array|null $flash
 */
?>
<section id="usuarios-editar" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Editar Usuario #<?= (int)$usuario['id'] ?></h2>
            <p class="section-description">
                Modificá nombre, apellido, email y roles del usuario. La CI
                no se puede cambiar post-alta. La contraseña es opcional: si
                la dejás vacía, se conserva la actual.
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
        <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?>" role="alert">
            <?= e($flash['mensaje'] ?? '') ?>
        </div>
    <?php endif; ?>

    <?php componente('modulos/usuarios/form-usuario', [
        'accion_post' => '/dashboard/usuarios/' . (int)$usuario['id'],
        'csrf' => $csrf,
        'texto_boton' => 'Guardar Cambios',
        'mostrar_password' => false,
        'roles' => $roles,
        'catalogo_roles' => $catalogo_roles,
        'roles_seleccionados' => $usuario['roles'] ?? [],
        'valores' => [
            'ci' => $usuario['ci'] ?? null,
            'nombre' => $usuario['nombre'] ?? '',
            'apellido' => $usuario['apellido'] ?? '',
            'email' => $usuario['email'] ?? '',
        ],
    ]); ?>
</section>
