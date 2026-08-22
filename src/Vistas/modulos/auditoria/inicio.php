<?php

/**
 * Vista read-only del registro de auditoría. Los datos llegan ya ordenados
 * DESC por fecha_hora desde ModeloAuditoria::obtenerLogs().
 *
 * @var array $logs  Cada fila trae: id, fecha_hora, id_usuario,
 *                   nombre_usuario, accion, tabla_afectada, ip_origen, detalles
 */

/**
 * Devuelve la clase de badge según la acción. Cualquier acción que no
 * sea CREAR / ACTUALIZAR / ELIMINAR cae en el bucket "other".
 */
function claseAccionAuditoria(string $accion): string
{
    $up = strtoupper($accion);
    return match ($up) {
        'CREAR', 'CREATE', 'INSERT', 'REGISTRAR'  => 'auditoria-action-create',
        'ACTUALIZAR', 'UPDATE', 'EDITAR'         => 'auditoria-action-update',
        'ELIMINAR', 'DELETE', 'CANCELAR'         => 'auditoria-action-delete',
        default                                  => 'auditoria-action-other',
    };
}

/**
 * Iniciales para el avatar del usuario. Si no hay nombre, devuelve "?".
 */
function inicialesUsuario(?string $nombre): string
{
    $nombre = trim((string)$nombre);
    if ($nombre === '') return '?';
    $partes = preg_split('/\s+/', $nombre);
    $primera = mb_substr($partes[0] ?? '', 0, 1);
    $ultima  = mb_substr($partes[count($partes) - 1] ?? '', 0, 1);
    return mb_strtoupper($primera . $ultima);
}

/**
 * Formatea un timestamp MySQL (Y-m-d H:i:s) a "DD/MM/YYYY" + "HH:MM".
 * Si no parsea, devuelve el valor original escapado.
 */
function formatearFechaAuditoria(string $ts): array
{
    $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $ts)
       ?: \DateTime::createFromFormat('Y-m-d H:i:s.u', $ts);
    if ($dt instanceof \DateTime) {
        return [
            'fecha' => $dt->format('d/m/Y'),
            'hora'  => $dt->format('H:i'),
        ];
    }
    return ['fecha' => $ts, 'hora' => ''];
}

$totalLogs = is_array($logs) ? count($logs) : 0;
?>

<section id="auditoria" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Registro de Auditoría</h2>
            <p class="section-description">
                Historial de acciones realizadas en el sistema. Solo lectura.
            </p>
        </div>
    </div>

    <div class="auditoria-container">
        <div class="auditoria-meta">
            <span class="auditoria-count"><?= (int)$totalLogs ?></span>
            <span>registro<?= $totalLogs === 1 ? '' : 's' ?> encontrado<?= $totalLogs === 1 ? '' : 's' ?></span>
        </div>

        <div class="table-container">
            <div class="table-responsive">
                <table class="auditoria-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Fecha y hora</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Tabla</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($totalLogs > 0): ?>
                            <?php foreach ($logs as $log): ?>
                                <?php
                                    $accion      = strtoupper((string)($log['accion'] ?? ''));
                                    $badgeClass  = claseAccionAuditoria($accion);
                                    $usuario     = (string)($log['nombre_usuario'] ?? '');
                                    $iniciales   = inicialesUsuario($usuario !== '' ? $usuario : ($log['id_usuario'] ?? null));
                                    $ts          = formatearFechaAuditoria((string)($log['fecha_hora'] ?? ''));
                                    $detallesRaw = (string)($log['detalles'] ?? '');
                                    $detallesArr = json_decode($detallesRaw, true);
                                ?>
                                <tr>
                                    <td data-label="ID">
                                        <span class="auditoria-id">#<?= e((string)($log['id'] ?? '')) ?></span>
                                    </td>
                                    <td data-label="Fecha y hora">
                                        <div class="auditoria-timestamp">
                                            <span class="auditoria-timestamp-date"><?= e($ts['fecha']) ?></span>
                                            <?php if ($ts['hora'] !== ''): ?>
                                                <span class="auditoria-timestamp-time"><?= e($ts['hora']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td data-label="Usuario">
                                        <span class="auditoria-user">
                                            <span class="auditoria-user-icon" aria-hidden="true"><?= e($iniciales) ?></span>
                                            <span>
                                                <?php if ($usuario !== ''): ?>
                                                    <?= e($usuario) ?>
                                                <?php else: ?>
                                                    <span class="auditoria-empty-details">
                                                        Usuario #<?= e((string)($log['id_usuario'] ?? '?')) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </span>
                                    </td>
                                    <td data-label="Acción">
                                        <span class="auditoria-action-badge <?= e($badgeClass) ?>">
                                            <?= e($accion) ?>
                                        </span>
                                    </td>
                                    <td data-label="Tabla">
                                        <span class="auditoria-table-name"><?= e((string)($log['tabla_afectada'] ?? '')) ?></span>
                                    </td>
                                    <td data-label="IP">
                                        <span class="auditoria-ip"><?= e((string)($log['ip_origen'] ?? '')) ?></span>
                                    </td>
                                    <td data-label="Detalles">
                                        <?php if (is_array($detallesArr) && !empty($detallesArr)): ?>
                                            <ul class="auditoria-details">
                                                <?php foreach ($detallesArr as $clave => $valor): ?>
                                                    <?php
                                                        $claveLegible = ucfirst(str_replace('_', ' ', (string)$clave));
                                                        $render = '';

                                                        if (is_array($valor)) {
                                                            // ACTUALIZAR: array de 2 posiciones (viejo, nuevo)
                                                            if (
                                                                $accion === 'ACTUALIZAR'
                                                                && count($valor) === 2
                                                                && array_is_list($valor)
                                                            ) {
                                                                $viejo = is_array($valor[0]) ? implode(', ', $valor[0]) : (string)$valor[0];
                                                                $nuevo = is_array($valor[1]) ? implode(', ', $valor[1]) : (string)$valor[1];

                                                                if ($viejo === $nuevo) {
                                                                    $render = '<span class="auditoria-details-flat">'
                                                                        . e($nuevo) . '</span>';
                                                                } else {
                                                                    $render = '<span class="auditoria-details-old">'
                                                                        . e($viejo) . '</span>'
                                                                        . '<span class="auditoria-details-arrow">→</span>'
                                                                        . '<span class="auditoria-details-new">'
                                                                        . e($nuevo) . '</span>';
                                                                }
                                                            } else {
                                                                // Lista normal: aplanar recursivamente
                                                                $plano = [];
                                                                array_walk_recursive($valor, function ($a) use (&$plano) {
                                                                    $plano[] = $a;
                                                                });
                                                                $render = '<span class="auditoria-details-flat">'
                                                                    . e(implode(', ', $plano)) . '</span>';
                                                            }
                                                        } else {
                                                            $render = '<span class="auditoria-details-flat">'
                                                                . e((string)$valor) . '</span>';
                                                        }
                                                    ?>
                                                    <li>
                                                        <span class="auditoria-details-key"><?= e($claveLegible) ?>:</span>
                                                        <?= $render ?>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php else: ?>
                                            <span class="auditoria-empty-details">Sin detalles</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">
                                    <div class="auditoria-empty">
                                        <div class="auditoria-empty-icon">
                                            <svg width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p class="auditoria-empty-title">Sin registros de auditoría</p>
                                        <p class="auditoria-empty-text">
                                            Cuando los usuarios realicen acciones en el sistema, aparecerán acá.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
