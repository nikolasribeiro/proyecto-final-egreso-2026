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
                                 $detallesArray = json_decode($log['detalles'], true);

                                    if (is_array($detallesArray) && !empty($detallesArray)) {
                                     echo "<ul style='margin: 0; padding-left: 20px; list-style-type: square; font-size: 0.9em; color: #555;'>";
        
                                      foreach ($detallesArray as $clave => $valor) {
                                     $claveLegible = ucfirst(str_replace('_', ' ', $clave));
                                     $valorLegible = '';
            
                                    if (is_array($valor)) {
                // Detectar si es un registro de ACTUALIZAR (trae array de 2 posiciones: viejo y nuevo)
                                 if ($log['accion'] === 'ACTUALIZAR' && count($valor) === 2 && isset($valor[0], $valor[1])) {
                    
                    // Si el valor interno también es array (ej. Roles), lo aplanamos
                                 $viejo = is_array($valor[0]) ? implode(', ', $valor[0]) : (string)$valor[0];
                                 $nuevo = is_array($valor[1]) ? implode(', ', $valor[1]) : (string)$valor[1];

                                 if ($viejo === $nuevo) {
                                 $valorLegible = htmlspecialchars($nuevo);
                    } else {
                         // Diseño visual para cambios: viejo tachado -> nuevo
                         $valorLegible = "<del style='color:#a94442;'>".htmlspecialchars($viejo)."</del> <strong style='color:#3c763d;'>➔ ".htmlspecialchars($nuevo)."</strong>";
                    }
                } else {
                    // Para listas normales (ej. Destinos en un CREAR)
                    $flatArray = [];
                    array_walk_recursive($valor, function($a) use (&$flatArray) { $flatArray[] = $a; });
                    $valorLegible = htmlspecialchars(implode(', ', $flatArray));
                }
            } else {
                $valorLegible = htmlspecialchars((string)$valor);
            }
            
            echo "<li><strong>{$claveLegible}:</strong> {$valorLegible}</li>";
        }
        echo "</ul>";
    } else {
        echo "<span style='color: #999; font-style: italic;'>Sin detalles</span>";
    }
    ?>
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