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
        
        <div style="padding: 15px;">
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label">Seleccione la encuesta activa a responder:</label>
                <select id="selector-encuesta-activa" class="form-control" onchange="cambiarEncuestaInterna(this)">
                    <option value="">-- Seleccione una encuesta del listado --</option>
                    
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
    <div class="modal-content card" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Crear Nueva Encuesta</h3>
            <button class="close-btn" onclick="closeModal('modal-crear-encuesta')">&times;</button>
        </div>
        <form action="/dashboard/encuestas/crear" method="POST" style="padding: 15px;">
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label">Servicio o Categoría a Evaluar</label>
                <select name="id_categoria" class="form-control" required>
                    <option value="">-- Seleccione una categoría --</option>
                    <?php if(!empty($lista_categorias)): foreach ($lista_categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars((string)$cat['nombre_categoria']) ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label">Público Objetivo</label>
                <select name="segmento_dirigido" id="segmento-dirigido" class="form-control" onchange="verificarAnonimato()" required>
                    <option value="">-- Seleccione --</option>
                    <option value="Pacientes">Pacientes</option>
                    <option value="Funcionarios">Funcionarios (Equipo de Salud)</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label">Plantilla a Utilizar</label>
                <select name="id_plantilla" id="selector-plantilla" class="form-control" onchange="togglePreguntasDinamicas()" required>
                    <option value="">-- Seleccione --</option>
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

            <div class="form-group" id="contenedor-preguntas-dinamicas" style="display: none; margin-bottom: 15px; padding: 10px; border: 1px dashed #ccc; border-radius: 5px;">
                <label class="form-label">Redacte sus preguntas</label>
                <div id="contenedor-preguntas">
                    <div style="display: flex; gap: 10px; margin-bottom: 10px;">
                        <input type="text" name="preguntas[]" class="form-control" placeholder="Ej: ¿Cómo califica la atención?">
                        <button type="button" class="btn btn-secondary btn-small" onclick="agregarPregunta()">+</button>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-bottom: 15px; background: #f8f9fa; padding: 10px; border-radius: 6px;">
                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 0;">
                    <input type="checkbox" name="es_anonima" id="es-anonima" value="1">
                    Encuesta 100% Anónima
                </label>
                <small id="aviso-anonimato" style="display: none; color: #dc3545; font-size: 0.8rem; margin-top: 5px; margin-left: 23px;">
                    * Obligatorio por protección de datos del paciente.
                </small>
            </div>
            <div class="form-actions" style="margin-top: 25px; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-crear-encuesta')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Crear Encuesta</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL VISOR DE QR -->
<div id="modal-qr-encuesta" class="modal-overlay hidden">
    <!-- Se ajustó el max-width a 450px para dar mayor margen a la botonera -->
    <div class="modal-content card" style="text-align: center; max-width: 450px; border-radius: 12px; width: 90%;">
        
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; color: #1e293b; margin: 0;">Código QR de la Encuesta</h3>
            <button type="button" onclick="closeModal('modal-qr-encuesta')" style="background: #f1f5f9; border: none; border-radius: 8px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; color: #64748b; cursor: pointer; transition: background 0.2s;">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6L6 18M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div style="padding: 20px;">
            <div style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 20px; display: inline-block; margin-bottom: 15px;">
                <img id="qr-encuesta-img" src="" alt="Código QR" style="width: 200px; height: 200px; display: block;">
            </div>
            
            <h4 id="qr-encuesta-titulo" style="margin: 0 0 15px 0; font-size: 1rem; color: #1e293b; font-weight: 600;"></h4>
            
            <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 15px;">
                Escanee este código para acceder a la encuesta.
            </p>
            
            <div style="margin-bottom: 25px; padding: 0 10px;">
                <span id="qr-encuesta-url-text" style="background-color: #f1f5f9; color: #64748b; padding: 8px 16px; border-radius: 20px; font-family: monospace; font-size: 0.85rem; display: inline-block; width: 100%; word-break: break-all; box-sizing: border-box;"></span>
            </div>

            <!-- Se agregó flex-wrap: wrap para adaptar los botones sin romper el diseño -->
            <div style="display: flex; justify-content: center; flex-wrap: wrap; gap: 10px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <button type="button" class="btn btn-secondary" style="background: white; border: 1px solid #0d6efd; color: #0d6efd;" onclick="closeModal('modal-qr-encuesta')">
                    Cerrar
                </button>
                <button type="button" class="btn btn-secondary" style="background: white; border: 1px solid #cbd5e1; color: #475569; display: flex; align-items: center; gap: 5px;" onclick="printQR()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"></path></svg>
                    Imprimir
                </button>
                <button type="button" class="btn btn-primary" style="display: flex; align-items: center; gap: 5px;" onclick="downloadQR()">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-3 3m0 0l-3-3m3 3V4"></path></svg>
                    Descargar
                </button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/javascript/dashboard/encuestas.js"></script>