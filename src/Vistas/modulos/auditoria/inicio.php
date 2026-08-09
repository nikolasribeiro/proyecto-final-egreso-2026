<div class="auditoria-container" style="padding: 20px;">
    <h2>Registro de Auditoría</h2>
    <div class="table-responsive" style="margin-top: 20px;">
        <table class="tabla-datos" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid #333;">
                    <th>ID</th><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>IP</th><th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['fecha_hora']) ?></td>
                            <td><?= htmlspecialchars($log['nombre_usuario'] ?? 'ID: ' . ($log['id_usuario'] ?? 'Desconocido')) ?></td>
                            <td><strong><?= htmlspecialchars($log['accion']) ?></strong></td>
                            <td><?= htmlspecialchars($log['tabla_afectada']) ?></td>
                            <td><?= htmlspecialchars($log['ip_origen']) ?></td>
                            
                            <!-- COLUMNA DETALLES ACTUALIZADA -->
                            <td>
                                <?php
                                // Decodificamos el JSON
                                $detallesArray = json_decode($log['detalles'], true);

                                // Verificamos si es un array válido y tiene datos
                                if (is_array($detallesArray) && !empty($detallesArray)) {
                                    echo "<ul style='margin: 0; padding-left: 20px; list-style-type: square; font-size: 0.9em; color: #555;'>";
                                    
                                    foreach ($detallesArray as $clave => $valor) {
                                        // Limpiamos la clave (ej: "destino_orden" -> "Destino orden")
                                        $claveLegible = ucfirst(str_replace('_', ' ', $clave));
                                        
                                        // Curamos el valor
                                        $valorLegible = is_array($valor) ? htmlspecialchars(implode(', ', $valor)) : htmlspecialchars((string)$valor);
                                        
                                        echo "<li><strong>{$claveLegible}:</strong> {$valorLegible}</li>";
                                    }
                                    
                                    echo "</ul>";
                                } else {
                                    // Si no hay detalles o el JSON es inválido
                                    echo "<span style='color: #999; font-style: italic;'>Sin detalles</span>";
                                }
                                ?>
                            </td>
                            <!-- FIN COLUMNA DETALLES -->
                            
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center;">No hay registros de auditoría.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>