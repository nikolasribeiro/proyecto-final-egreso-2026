<div class="form-step" id="step-3">
    <h3 class="step-title">
        <span class="step-number">3</span>
        Destinos del Traslado
    </h3>

    <div class="destinos-container">
        <div class="destinos-list" id="destinos-list">
            <!-- Destinos dynamically added here -->
        </div>

        <button type="button" class="btn btn-secondary btn-add-destino" id="btn-add-destino">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
            Agregar Destino
        </button>

        <label class="checkbox-volver-origen">
            <input type="checkbox" id="volver-origen">
            <span class="checkbox-custom"></span>
            <span>Finalizar regreso en Hospital de Clínicas</span>
        </label>
    </div>

    <div class="step-actions">
        <button type="button" class="btn btn-outline btn-lg" id="btn-back-3">
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Volver
        </button>
        <button type="button" class="btn btn-primary btn-lg" id="btn-step-3" disabled>
            Continuar
            <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
        </button>
    </div>

    <!-- Modal para agregar destino -->
    <div class="destino-modal" id="destino-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Seleccionar Destino</h4>
                <button type="button" class="modal-close" id="close-destino-modal">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="modal-search">
                <input type="text" id="search-destino" placeholder="Buscar destino..." class="form-input">
            </div>
            <div class="destinos-suggestions" id="destinos-suggestions">
                <?php
                $destinos_disponibles = [
                    ['id' => 1, 'nombre' => 'Hospital Maciel', 'direccion' => '25 de Mayo 174'],
                    ['id' => 2, 'nombre' => 'Hospital Británico', 'direccion' => 'Av. Italia s/n'],
                    ['id' => 3, 'nombre' => 'ASSE Central', 'direccion' => '18 de Julio 1892'],
                    ['id' => 4, 'nombre' => 'Médica Uruguaya', 'direccion' => 'Constituyente 1824'],
                    ['id' => 5, 'nombre' => 'Sanatorio Americano', 'direccion' => 'Guido 1900'],
                    ['id' => 6, 'nombre' => 'Hospital Pasteur', 'direccion' => 'Monte Caseros 2629'],
                ];
                foreach ($destinos_disponibles as $destino):
                ?>
                    <button type="button" class="destino-suggestion" data-id="<?= $destino['id'] ?>" data-nombre="<?= $destino['nombre'] ?>">
                        <div class="suggestion-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </div>
                        <div class="suggestion-info">
                            <span class="suggestion-nombre"><?= $destino['nombre'] ?></span>
                            <span class="suggestion-direccion"><?= $destino['direccion'] ?></span>
                        </div>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>