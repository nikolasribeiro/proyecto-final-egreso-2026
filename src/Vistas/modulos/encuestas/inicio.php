<?php
/**
 * Vista del módulo de encuestas.
 */
?>

<section id="encuestas" class="section active">
   <div class="section-header">
        <div>
            <h2 class="section-title">Encuestas de Satisfacción</h2>
            <p class="section-description">Administración de encuestas y medición de la calidad del servicio hospitalario.</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('modal-crear-encuesta')">+ Crear Nueva Encuesta</button>
    </div>
    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?>" role="alert">
            <?= e($flash['mensaje'] ?? '') ?>
        </div>
    <?php endif; ?>

    <!-- TABLA CRUD -->
    <div class="card" style="margin-bottom: 20px;">
        <div style="padding: 15px; border-bottom: 1px solid #eee;">
            <h3 style="margin:0; font-size: 1.1rem; color: #333;">Encuestas Activas</h3>
        </div>
        <div style="overflow-x: auto;">
            <table class="documents-table" style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background-color: #f8f9fa;">
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd;">Servicio/Categoría</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd;">Público Objetivo</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd;">Privacidad</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd;">Token de Acceso</th>
                        <th style="padding: 12px 15px; border-bottom: 2px solid #ddd; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($lista_encuestas)): ?>
                        <?php foreach ($lista_encuestas as $campana): ?>
                            <tr style="border-bottom: 1px solid #eee;">
                                <td style="padding: 12px 15px; font-weight: bold;"><?= htmlspecialchars((string)($campana['nombre_categoria'] ?? 'General')) ?></td>
                                <td style="padding: 12px 15px;"><?= htmlspecialchars((string)$campana['segmento_dirigido']) ?></td>
                                <td style="padding: 12px 15px;">
                                    <span class="badge <?= $campana['es_anonima'] ? 'badge-warning' : 'badge-info' ?>">
                                        <?= $campana['es_anonima'] ? 'Anónima' : 'Identificada' ?>
                                    </span>
                                </td>
                                <td style="padding: 12px 15px; font-family: monospace; color: #555;">
                                    <?= htmlspecialchars((string)$campana['token_publico']) ?>
                                </td>
                                <td style="padding: 12px 15px; text-align: right;">
                                    <div class="action-buttons" style="display: flex; gap: 8px; justify-content: flex-end;">
                                        
                                        <!-- AQUÍ ENVIAMOS TAMBIÉN EL SEGMENTO DIRIGIDO A LA FUNCIÓN JS -->
                                        <button type="button" class="btn btn-secondary btn-small" style="padding: 4px 8px; font-size: 0.85rem;" onclick="mostrarQREncuesta('<?= htmlspecialchars((string)$campana['token_publico']) ?>', '<?= htmlspecialchars((string)($campana['nombre_categoria'] ?? 'General')) ?>', '<?= htmlspecialchars((string)$campana['segmento_dirigido']) ?>')">Ver QR</button>
                                        
                                        <a href="/dashboard/encuestas/resultados/<?= $campana['id'] ?>" class="btn btn-primary btn-small" style="padding: 4px 8px; font-size: 0.85rem;">Resultados</a>
                                        <form action="/dashboard/encuestas/eliminar/<?= $campana['id'] ?>" method="POST" style="margin: 0;">
                                            <button type="submit" class="btn btn-danger btn-small" style="padding: 4px 8px; font-size: 0.85rem;" onclick="return confirm('¿Dar de baja esta encuesta? Los resultados históricos se conservarán para estadísticas.');">Dar de Baja</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align: center; padding: 30px;">No hay encuestas activas en el sistema.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FORMULARIO EXISTENTE INTERNO -->
    <!-- 2. SECCIÓN INTERACTIVA PARA REALIZAR ENCUESTA (FUNCIONARIOS) -->
    <div class="card encuesta-card" style="margin-bottom: 30px;">
        <div style="padding: 15px; border-bottom: 1px solid #eee; margin-bottom: 15px;">
            <h3 style="margin:0; font-size: 1.1rem; color: #333;">Realizar Encuesta Interna</h3>
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" style="font-weight: 600;">Seleccione la encuesta activa a responder:</label>
                <select id="selector-encuesta-activa" class="form-select" onchange="cambiarEncuestaInterna(this)" style="margin-bottom: 20px;">
                    <option value=""> Seleccione una encuesta del listado </option>
                    
                    <!-- 1. PLANTILLAS FIJAS (SIEMPRE DISPONIBLES) -->
                    <optgroup label="Plantillas Fijas (Oficiales)">
                        <?php foreach ($plantillas as $key => $tpl): ?>
                            <!-- Le agregamos el prefijo 'fija-' para separarlas de las dinámicas -->
                            <option value="fija-<?= $key ?>"><?= htmlspecialchars($tpl['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>

                    <!-- 2. ENCUESTAS DINÁMICAS (SOLO FUNCIONARIOS) -->
                    <optgroup label="Encuestas Dinámicas Activas">
                        <?php 
                            $encuestasFuncionarios = array_filter($lista_encuestas, function($e) {
                                return strpos(strtolower($e['segmento_dirigido']), 'funcionario') !== false;
                            });
                        ?>
                        <?php if (!empty($encuestasFuncionarios)): ?>
                            <?php foreach ($encuestasFuncionarios as $enc): ?>
                                <option value="<?= $enc['id'] ?>">
                                    [ID: #<?= $enc['id'] ?>] <?= htmlspecialchars($enc['nombre_categoria'] ?? 'General') ?> - <?= htmlspecialchars($enc['segmento_dirigido']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No hay encuestas dinámicas activas.</option>
                        <?php endif; ?>
                    </optgroup>
                </select>
            </div>

            <!-- CONTENEDOR DE FORMULARIOS NATIVOS -->
            <div id="contenedor-formularios-nativos" style="border-top: 1px solid #eee; padding-top: 20px;">
                <p id="mensaje-seleccione-encuesta" style="text-align: center; color: #888; font-style: italic;">
                    Seleccione una encuesta del menú superior para comenzar.
                </p>

                <!-- RENDERIZAR FORMULARIOS DE LAS PLANTILLAS FIJAS -->
                <?php $idFalso = 1; ?>
                <?php foreach ($plantillas as $key => $tpl): ?>
                    <div id="form-encuesta-fija-<?= $key ?>" class="formulario-interno" style="display: none;">
                        <form action="/dashboard/encuestas" method="POST">
                            <!-- Si mandan una fija, el sistema asume los IDs por defecto (1 o 2) -->
                            <input type="hidden" name="id_encuesta" value="<?= $idFalso++ ?>">
                            
                            <?php foreach ($tpl['preguntas'] as $idx => $preg): ?>
                                <div class="encuesta-pregunta" style="margin-bottom: 1.5rem;">
                                    <div class="encuesta-pregunta-titulo">
                                        <span class="encuesta-pregunta-numero"><?= $idx + 1 ?></span>
                                        <?= htmlspecialchars($preg['texto']) ?>
                                    </div>
                                    <div class="encuesta-escala-extremos" style="margin-top: 1rem; margin-bottom: 0.5rem;">
                                        <span><?= htmlspecialchars($preg['minLabel']) ?></span>
                                        <span><?= htmlspecialchars($preg['maxLabel']) ?></span>
                                    </div>
                                    <div class="encuesta-escala">
                                        <?php for($i = 1; $i <= 10; $i++): ?>
                                            <div style="position: relative;">
                                                <input type="radio" name="p_<?= $idx + 1 ?>" id="p_fija_<?= $key ?>_<?= $idx ?>_<?= $i ?>" value="<?= $i ?>" class="encuesta-radio" required>
                                                <label for="p_fija_<?= $key ?>_<?= $idx ?>_<?= $i ?>" class="encuesta-radio-label"><?= $i ?></label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="encuesta-actions">
                                <button type="submit" class="btn btn-primary">Enviar Respuestas</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>

                <!-- RENDERIZAR FORMULARIOS DINÁMICOS DE FUNCIONARIOS -->
                <?php foreach ($encuestasFuncionarios as $enc): ?>
                    <?php 
                        $preguntasFormulario = [];
                        if (($enc['id_plantilla'] ?? '') === 'personalizada') {
                            $arr = json_decode($enc['preguntas'] ?? '[]', true) ?: [];
                            foreach ($arr as $texto) {
                                $preguntasFormulario[] = ['texto' => $texto, 'minLabel' => 'Insuficiente', 'maxLabel' => 'Excelente'];
                            }
                        } else {
                            $plantillaOficial = \Nucleo\Constantes\PlantillasEncuestas::obtener($enc['id_plantilla'] ?? 'general');
                            $preguntasFormulario = $plantillaOficial['preguntas'] ?? [];
                        }
                    ?>
                    
                    <div id="form-encuesta-<?= $enc['id'] ?>" class="formulario-interno" style="display: none;">
                        <form action="/dashboard/encuestas" method="POST">
                            <input type="hidden" name="id_encuesta" value="<?= $enc['id'] ?>">
                            
                            <?php foreach ($preguntasFormulario as $idx => $preg): ?>
                                <div class="encuesta-pregunta" style="margin-bottom: 1.5rem;">
                                    <div class="encuesta-pregunta-titulo">
                                        <span class="encuesta-pregunta-numero"><?= $idx + 1 ?></span>
                                        <?= htmlspecialchars($preg['texto'] ?? 'Pregunta ' . ($idx + 1)) ?>
                                    </div>
                                    <div class="encuesta-escala-extremos" style="margin-top: 1rem; margin-bottom: 0.5rem;">
                                        <span><?= htmlspecialchars($preg['minLabel'] ?? 'Insuficiente') ?></span>
                                        <span><?= htmlspecialchars($preg['maxLabel'] ?? 'Excelente') ?></span>
                                    </div>
                                    <div class="encuesta-escala">
                                        <?php for($i = 1; $i <= 10; $i++): ?>
                                            <div style="position: relative;">
                                                <input type="radio" name="p_<?= $idx + 1 ?>" id="p_<?= $enc['id'] ?>_<?= $idx + 1 ?>_<?= $i ?>" value="<?= $i ?>" class="encuesta-radio" required>
                                                <label for="p_<?= $enc['id'] ?>_<?= $idx + 1 ?>_<?= $i ?>" class="encuesta-radio-label"><?= $i ?></label>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="encuesta-actions">
                                <button type="submit" class="btn btn-primary">Enviar Respuestas</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- MODAL CREACIÓN -->
<div id="modal-crear-encuesta" class="modal-overlay hidden">
    <div class="modal-content card modal-encuesta-content">
        
        <div class="modal-header">
            <h3>Crear Nueva Encuesta</h3>
            <button type="button" class="close-btn" onclick="closeModal('modal-crear-encuesta')">&times;</button>
        </div>
        
        <form action="/dashboard/encuestas/crear" method="POST" class="encuesta-modal-body">
            <div class="form-group">
                <label class="form-label">Servicio o Categoría a Evaluar</label>
                <select name="id_categoria" class="form-select" required>
                    <option value=""> Seleccione una categoría </option>
                    <?php if(!empty($lista_categorias)): foreach ($lista_categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars((string)$cat['nombre_categoria']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Público Objetivo</label>
                <select name="segmento_dirigido" id="segmento-dirigido" class="form-select" onchange="verificarAnonimato()" required>
                    <option value=""> Seleccione publico</option>
                    <option value="Pacientes">Pacientes</option>
                    <option value="Funcionarios">Funcionarios (Equipo de Salud)</option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Plantilla a Utilizar</label>
                <select name="id_plantilla" id="selector-plantilla" class="form-select" onchange="togglePreguntasDinamicas()" required>
                    <option value=""> Seleccione plantilla</option>
                    <optgroup label="Plantillas Oficiales (FNR)">
                        <?php foreach ($plantillas as $key => $tpl): ?>
                            <option value="<?= htmlspecialchars((string)$key) ?>"><?= htmlspecialchars((string)$tpl['nombre']) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="Otras Opciones">
                        <option value="personalizada">Crear Encuesta Dinámica (Personalizada)</option>
                    </optgroup>
                </select>
            </div>

            <div id="contenedor-preguntas-dinamicas" class="form-group hidden">
                <label class="form-label">Redacte sus preguntas</label>
                <div id="contenedor-preguntas">
                    <div class="encuesta-flex-input">
                        <input type="text" name="preguntas[]" class="form-control" placeholder="Ej: ¿Cómo califica la atención?">
                        <button type="button" class="btn btn-secondary btn-small" onclick="agregarPregunta()">+</button>
                    </div>
                </div>
            </div>
            
            <div class="encuesta-anonimo-box">
                <input type="checkbox" name="es_anonima" id="es-anonima" value="1">
                <label for="es-anonima" class="encuesta-label-pointer">Encuesta 100% Anónima</label>
            </div>
            <small id="aviso-anonimato" class="hidden encuesta-text-danger">
                * Obligatorio por protección de datos del paciente.
            </small>
            
            <div class="form-group encuesta-form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-crear-encuesta')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Encuesta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VISOR DE QR -->
<div id="modal-qr-encuesta" class="modal-overlay hidden">
    <div class="modal-content card modal-qr-content">
        
        <div class="modal-header encuesta-header-clean">
            <h3>Código QR</h3>
            <button type="button" class="close-btn" onclick="closeModal('modal-qr-encuesta')">&times;</button>
        </div>
        
        <div class="encuesta-qr-body">
            <div class="encuesta-qr-box">
                <img id="qr-encuesta-img" src="" alt="Código QR">
            </div>
            
            <h4 id="qr-encuesta-titulo" class="encuesta-mb-15"></h4>
            
            <p class="encuesta-text-desc">
                Escanee este código para acceder a la encuesta.
            </p>
            
            <div class="form-group">
                <span id="qr-encuesta-url-text" class="encuesta-qr-pill"></span>
            </div>

            <div class="encuesta-actions-flex">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-qr-encuesta')">Cerrar</button>
                <button type="button" class="btn btn-secondary encuesta-btn-icon" onclick="printQR()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"></path></svg>
                    Imprimir
                </button>
                <button type="button" class="btn btn-primary encuesta-btn-icon" onclick="downloadQR()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Descargar
                </button>
            </div>
        </div>
    </div>
</div>
<script src="/assets/javascript/dashboard/encuestas.js"></script>