<?php

/**
 * Componente: formulario para alta / edición de un usuario.
 *
 * Variables:
 * @var string $accion_post      URL del POST
 * @var string $csrf             Token CSRF (ya generado por el controlador)
 * @var string $texto_boton      Etiqueta del botón submit
 * @var bool   $mostrar_password Si TRUE, los campos password son obligatorios
 *                              (alta). Si FALSE, son opcionales (edición).
 * @var array  $roles            Roles::labels()
 * @var array  $catalogo_roles   Salida de ModeloUsuario::obtenerCatalogoRoles()
 * @var array  $roles_seleccionados UI keys a marcar como checked
 * @var array  $valores          Valores a pre-llenar (nombre, apellido, email, ci)
 * @var string|null $id_prefijo  Prefijo opcional para IDs del componente
 */

$idPrefijo = trim((string)($id_prefijo ?? 'usuarios'));
if ($idPrefijo === '') {
    $idPrefijo = 'usuarios';
}
$idRolesTitulo = $idPrefijo . '-roles-title';
?>
<form class="usuarios-form-card" method="POST" action="<?= e($accion_post) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="usuarios-ci">CI</label>
            <?php if (!empty($valores['ci'])): ?>
                <div class="usuarios-form-readonly">
                    <div class="form-readonly-value" id="usuarios-ci"><?= e((string)$valores['ci']) ?></div>
                    <small class="form-hint">La CI no puede modificarse después del alta.</small>
                </div>
            <?php else: ?>
                <input
                    type="number"
                    id="usuarios-ci"
                    name="ci"
                    class="form-input"
                    required
                    min="1"
                    max="99999999"
                    placeholder="Ej: 12345678"
                    value="<?= e((string)($valores['ci'] ?? '')) ?>">
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label class="form-label" for="usuarios-nombre">Nombre</label>
            <input
                type="text"
                id="usuarios-nombre"
                name="nombre"
                class="form-input"
                required
                maxlength="100"
                value="<?= e($valores['nombre'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="usuarios-apellido">Apellido</label>
            <input
                type="text"
                id="usuarios-apellido"
                name="apellido"
                class="form-input"
                required
                maxlength="100"
                value="<?= e($valores['apellido'] ?? '') ?>">
        </div>
    </div>

    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="usuarios-email">Email</label>
            <input
                type="email"
                id="usuarios-email"
                name="email"
                class="form-input"
                required
                maxlength="150"
                value="<?= e($valores['email'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="usuarios-contrasena">
                Contraseña <?= $mostrar_password ? '' : '<small>(dejar vacío para no cambiar)</small>' ?>
            </label>
            <input
                type="password"
                id="usuarios-contrasena"
                name="contrasena"
                class="form-input"
                <?= $mostrar_password ? 'required' : '' ?>
                minlength="6"
                maxlength="255"
                autocomplete="new-password">
        </div>
    </div>

    <?php
        $rolesSeleccionadosSet = [];
        foreach (is_array($roles_seleccionados ?? null) ? $roles_seleccionados : [] as $rolSeleccionado) {
            if (is_string($rolSeleccionado) || is_int($rolSeleccionado)) {
                $rolesSeleccionadosSet[(string)$rolSeleccionado] = true;
            }
        }

        // Descripciones alineadas con la matriz de permisos de Roles.php.
        $descripcionesRoles = [
            'administrador' => 'Acceso completo al sistema. Gestiona usuarios, traslados, documentos y la matriz de permisos.',
            'medico' => 'Puede consultar traslados y documentos, y crear encuestas de satisfacción.',
            'enfermero' => 'Puede crear y consultar traslados, consultar documentos y crear encuestas de satisfacción.',
            'chofer' => 'Conduce ambulancias. Crea y edita traslados asignados a su vehículo.',
            'soporte_tecnico' => 'Puede consultar traslados y encuestas, gestionar documentos, y editar o dar de baja usuarios.',
        ];
    ?>
    <section class="form-section form-section-roles" aria-labelledby="<?= e($idRolesTitulo) ?>" data-rol="seccion-roles">
        <div class="form-section-header">
            <h3 id="<?= e($idRolesTitulo) ?>" class="form-section-title">Asignación de Roles</h3>
            <p class="form-section-subtitle">
                Marcá los roles que tendrá este usuario. Un usuario puede tener varios.
                Los roles determinan qué módulos puede ver y qué acciones puede realizar.
            </p>
        </div>

        <div class="roles-grid">
            <?php foreach ($catalogo_roles as $rol): ?>
                <?php
                    $claveRol = (string)($rol['clave'] ?? '');
                    if ($claveRol === '') {
                        continue;
                    }
                    $etiquetaRol = (string)($roles[$claveRol] ?? ($rol['etiqueta'] ?? $claveRol));
                    $descripcionRol = $descripcionesRoles[$claveRol]
                        ?? 'Permisos definidos por la matriz de roles del sistema.';
                ?>
                <label class="rol-card rol-card-<?= e($claveRol) ?>" data-rol="rol-card">
                    <input
                        class="rol-card-checkbox"
                        type="checkbox"
                        name="roles[]"
                        value="<?= e($claveRol) ?>"
                        aria-label="Asignar rol <?= e($etiquetaRol) ?>"
                        <?= isset($rolesSeleccionadosSet[$claveRol]) ? 'checked' : '' ?>>
                    <div class="rol-card-content">
                        <div class="rol-card-header">
                            <span class="rol-badge rol-badge-rol-<?= e($claveRol) ?>">
                                <?= e($etiquetaRol) ?>
                            </span>
                        </div>
                        <p class="rol-card-description"><?= e($descripcionRol) ?></p>
                    </div>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="form-actions">
        <a href="/dashboard/usuarios" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= e($texto_boton) ?></button>
    </div>
</form>
