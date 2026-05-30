<div class="form-step" id="step-5">
    <h3 class="step-title">
        <span class="step-number">5</span>
        Selección de Vehículo
    </h3>

    <?php
    // Mocks de vehículos
    $vehiculos = [
        ['id' => 'auto-1', 'tipo' => 'auto', 'nombre' => 'Auto 01', 'disponible' => true, 'placa' => 'ABC 1234'],
        ['id' => 'auto-2', 'tipo' => 'auto', 'nombre' => 'Auto 02', 'disponible' => false, 'placa' => 'ABC 1235'],
        ['id' => 'ambulancia-1', 'tipo' => 'ambulancia', 'nombre' => 'Ambulancia 01', 'disponible' => true, 'placa' => 'AMB 0001'],
        ['id' => 'ambulancia-2', 'tipo' => 'ambulancia', 'nombre' => 'Ambulancia 02', 'disponible' => true, 'placa' => 'AMB 0002'],
        ['id' => 'camion-1', 'tipo' => 'camion', 'nombre' => 'Camión Carga', 'disponible' => true, 'placa' => 'CAM 0001'],
    ];

    $hay_vehiculos_disponibles = false;
    foreach ($vehiculos as $v) {
        if ($v['disponible']) {
            $hay_vehiculos_disponibles = true;
            break;
        }
    }
    ?>

    <?php if ($hay_vehiculos_disponibles): ?>
        <div class="vehiculos-grid" id="vehiculos-grid">
            <?php foreach ($vehiculos as $vehiculo):
                $clase_estado = $vehiculo['disponible'] ? 'disponible' : 'no-disponible';
                $disabled = $vehiculo['disponible'] ? '' : 'disabled';

                // Deshabilitar camión para paciente y biológico
                $es_camion = $vehiculo['tipo'] === 'camion';
                $data_tipo_restringido = '';
                if ($es_camion) {
                    $data_tipo_restringido = 'data-restringido="true"';
                }
            ?>
                <label class="vehiculo-card <?= $clase_estado ?>" <?= $disabled ?> <?= $data_tipo_restringido ?>>
                    <input type="radio" name="vehiculo" value="<?= $vehiculo['id'] ?>" <?= $disabled ?>>
                    <div class="vehiculo-content">
                        <div class="vehiculo-icon">
                            <?php if ($vehiculo['tipo'] === 'auto'): ?>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                                </svg>
                            <?php elseif ($vehiculo['tipo'] === 'ambulancia'): ?>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            <?php else: ?>
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="vehiculo-info">
                            <span class="vehiculo-nombre"><?= $vehiculo['nombre'] ?></span>
                            <span class="vehiculo-placa"><?= $vehiculo['placa'] ?></span>
                        </div>
                        <div class="vehiculo-badge <?= $clase_estado ?>">
                            <?= $vehiculo['disponible'] ? 'Disponible' : 'En uso' ?>
                        </div>
                    </div>
                    <?php if ($vehiculo['tipo'] === 'camion'): ?>
                        <div class="vehiculo-restriction" id="restriction-camion">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Solo para equipamiento</span>
                        </div>
                    <?php endif; ?>
                </label>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- No hay vehículos disponibles -->
        <div class="no-vehiculos-alert">
            <div class="alert-icon-large">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M12 2a10 10 0 100 20 10 10 0 000-20z" />
                </svg>
            </div>
            <h4>No se encuentran vehículos disponibles en este momento</h4>
            <p>Todos los vehículos están siendo utilizados en traslados activos.</p>
            <button type="button" class="btn btn-warning btn-large" id="btn-solicitar-same">
                <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                </svg>
                Solicitar SAME
            </button>
        </div>
    <?php endif; ?>

    <div class="step-actions">
        <button type="button" class="btn btn-outline btn-lg" id="btn-back-5">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
        <button type="button" class="btn btn-primary btn-lg" id="btn-step-5" disabled>
            Continuar
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>
</div>