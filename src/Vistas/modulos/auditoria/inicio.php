<div class="auditoria-container" style="padding: 20px;">
    <h2>Registro de Auditoría</h2>
    <p>Historial de acciones del sistema.</p>

    <div class="table-responsive" style="margin-top: 20px;">
        <table class="tabla-datos" style="width: 100%; border-collapse: collapse; text-align: left;">
            <thead>
                <tr style="border-bottom: 2px solid #333;">
                    <th>ID</th>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Tabla Afectada</th>
                    <th>IP Origen</th>
                    <th>Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logs)): ?>
                    <?php foreach ($logs as $log): ?>
                        <tr style="border-bottom: 1px solid #ddd;">
                            <td><?= htmlspecialchars($log['id']) ?></td>
                            <td><?= htmlspecialchars($log['fecha_hora']) ?></td>
                            <td><?= htmlspecialchars($log['nombre_usuario'] ?? 'ID: ' . $log['id_usuario']) ?></td>
                            <td>
                                <strong><?= htmlspecialchars($log['accion']) ?></strong>
                            </td>
                            <td><?= htmlspecialchars($log['tabla_afectada']) ?></td>
                            <td><?= htmlspecialchars($log['ip_origen']) ?></td>
                            <td>
                                <!-- El JSON se muestra en una etiqueta pre para que mantenga el formato y sea legible -->
                                <pre style="background: #f4f4f4; padding: 5px; font-size: 12px; max-width: 200px; overflow-x: auto;">
                                    <?= htmlspecialchars($log['detalles']) ?>
                                </pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 15px;">No hay registros de auditoría.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>