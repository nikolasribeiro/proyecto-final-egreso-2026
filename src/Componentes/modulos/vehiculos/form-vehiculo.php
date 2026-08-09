<?php

/**
 * Componente: formulario reutilizable para alta / edición de vehículos.
 *
 * Variables:
 * @var string $accion_post   URL del POST
 * @var string $csrf          Token CSRF (ya generado por el controlador)
 * @var string $texto_boton   Etiqueta del botón submit
 * @var array  $tipos         ModeloVehiculo::obtenerTiposVehiculo()
 * @var array  $valores       Valores a pre-llenar (matricula, id_tipo_vehiculo)
 */
?>
<form class="vehiculos-form-card" method="POST" action="<?= e($accion_post) ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

    <div class="form-row">
        <div class="form-group">
            <label class="form-label" for="vehiculos-matricula">Matrícula</label>
            <input
                type="text"
                id="vehiculos-matricula"
                name="matricula"
                class="form-input"
                required
                maxlength="20"
                pattern="[A-Za-z0-9\-]{1,20}"
                placeholder="Ej: SCH-1234"
                value="<?= e((string)($valores['matricula'] ?? '')) ?>"
                style="text-transform: uppercase;">
            <small class="form-hint">Solo letras, números y guiones. Se guarda en mayúsculas.</small>
        </div>

        <div class="form-group">
            <label class="form-label" for="vehiculos-tipo">Tipo de vehículo</label>
            <select
                id="vehiculos-tipo"
                name="id_tipo_vehiculo"
                class="form-select"
                required>
                <option value="">— Seleccionar tipo —</option>
                <?php foreach (is_array($tipos ?? null) ? $tipos : [] as $tipo): ?>
                    <?php
                        $idTipo = (int)($tipo['id'] ?? 0);
                        $descTipo = (string)($tipo['descripcion'] ?? '');
                        $selected = ((int)($valores['id_tipo_vehiculo'] ?? 0)) === $idTipo;
                    ?>
                    <option value="<?= $idTipo ?>" <?= $selected ? 'selected' : '' ?>>
                        <?= e($descTipo) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small class="form-hint">Define para qué clase de traslados queda habilitado.</small>
        </div>
    </div>

    <div class="form-actions">
        <a href="/dashboard/vehiculos" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= e($texto_boton) ?></button>
    </div>
</form>