/**
 * Detalle Traslado - One Button Workflow
 * Handles stepper advancement, arrival registration, and reports
 */
(function () {
  "use strict";

  const CONFIG = {
    API_BASE: "/api/traslados",
  };

  // State
  let state = {
    trasladoId: null,
    pasoActual: 1,
    totalPasos: 0,
    destinos: [],
    stepperData: [],
    volverAlOrigen: false,
    estado: "",
  };

  // DOM Elements
  let elements = {};

  // ==========================================
  // INITIALIZATION
  // ==========================================

  function init() {
    const container = document.getElementById("transfer-detail");
    if (!container) return;

    state.trasladoId = parseInt(container.dataset.trasladoId, 10);
    if (!state.trasladoId) return;

    cacheElements();
    bindEvents();
    cargarDatosTraslado();
  }

  function cacheElements() {
    elements = {
      container: document.getElementById("transfer-detail"),
      stepper: document.querySelector(".detail-stepper"),
      actionSection: document.querySelector(".detail-action-section"),
      actionButton: document.getElementById("btn-main-action"),
      reportSection: document.querySelector(".detail-report-section"),
      reportButton: document.getElementById("btn-report"),
      modalReport: document.getElementById("report-modal"),
      reportForm: document.getElementById("report-form"),
      btnCancelTraslado: document.getElementById("btn-cancelar-traslado"),
      transferInfo: document.querySelector(".transfer-detail-info"),
      transferMeta: document.querySelector(".transfer-detail-meta"),
    };
  }

  function bindEvents() {
    // Main action button
    elements.actionButton?.addEventListener("click", handleMainAction);

    // Report button
    elements.reportButton?.addEventListener("click", abrirModalReporte);

    // Modal close handlers
    document.querySelectorAll(".modal-close").forEach((btn) => {
      btn.addEventListener("click", cerrarModales);
    });

    // Overlay click to close
    document.querySelectorAll(".modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) cerrarModales();
      });
    });

    // Report form submission
    elements.reportForm?.addEventListener("submit", handleReportSubmit);

    // Cancelar traslado button
    elements.btnCancelTraslado?.addEventListener("click", handleCancelarTraslado);
  }

  // ==========================================
  // DATA LOADING
  // ==========================================

  async function cargarDatosTraslado() {
    try {
      const response = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}`);

      if (!response.ok) {
        const text = await response.text();
        console.error("API Error:", response.status, text);
        mostrarError(`Error: ${response.status} - No se pudo cargar el traslado`);
        return;
      }

      const result = await response.json();

      if (result.success) {
        mapResponseToState(result.data);
        renderAll();
      } else {
        mostrarError(result.message || "Traslado no encontrado");
      }
    } catch (error) {
      console.error("Error cargando traslado:", error);
      mostrarError("Error al cargar los datos del traslado");
    }
  }

  function mapResponseToState(data) {
    state.destinos = data.destinos || [];
    state.volverAlOrigen = data.volver_al_origen || false;
    state.pasoActual = data.paso_actual || 1;
    state.estado = data.estado || "";
    state.stepperData = construirStepper(data.destinos, data.volver_al_origen);
    state.totalPasos = state.stepperData.length;
  }

  function construirStepper(destinos, volverAlOrigen) {
    const pasos = [];

    destinos.forEach((destino, index) => {
      // Departure to destination
      pasos.push({
        tipo: "en_transito",
        destinoOrden: destino.orden,
        titulo: `En transito a ${destino.nombre}`,
        destino: destino,
 reportesCount: destino.reportes?.length || 0,
        reportes: destino.reportes || [],
      });

      // Arrival at destination
      pasos.push({
        tipo: "arribo",
        destinoOrden: destino.orden,
        titulo: `Arribo a ${destino.nombre}`,
        destino: destino,
        reportesCount: destino.reportes?.length || 0,
        reportes: destino.reportes || [],
      });
    });

    if (volverAlOrigen) {
      // Return journey
      pasos.push({
        tipo: "en_transito_retorno",
        destinoOrden: null,
        titulo: "En transito regreso",
        destino: null,
        reportesCount: 0,
        reportes: [],
      });
      pasos.push({
        tipo: "arribo_central",
        destinoOrden: null,
        titulo: "Arribo a Central",
        destino: null,
        reportesCount: 0,
        reportes: [],
      });
    }

    return pasos;
  }

  // ==========================================
  // RENDERING
  // ==========================================

  function renderAll() {
    renderTransferInfo();
    renderStepper();
    renderActionButton();
    renderReportButton();
    renderTransferState();
  }

  function renderTransferState() {
    if (!elements.container) return;

    // Remove all state classes
    elements.container.classList.remove("detail-transfer-completed", "detail-transfer-cancelled");

    // Add appropriate state class
    if (state.estado === "completado") {
      elements.container.classList.add("detail-transfer-completed");
    } else if (state.estado === "cancelado") {
      elements.container.classList.add("detail-transfer-cancelled");
    }
  }

  function renderReportButton() {
    if (!elements.reportButton) return;

    const isDisabled = state.estado === "cancelado" || state.estado === "completado";
    elements.reportButton.disabled = isDisabled;

    if (isDisabled) {
      elements.reportButton.classList.add("btn-disabled");
    } else {
      elements.reportButton.classList.remove("btn-disabled");
    }
  }

  function renderTransferInfo() {
    if (!elements.transferInfo || !state.destinos.length) return;

    const primerDestino = state.destinos[0];
    const info = elements.container.dataset;

    // Determine badge class based on estado
    let estadoBadge = "";
    if (state.estado === "completado") {
      estadoBadge = '<span class="transfer-type-badge badge-success">Completado</span>';
    } else if (state.estado === "cancelado") {
      estadoBadge = '<span class="transfer-type-badge badge-danger">Cancelado</span>';
    } else if (state.estado === "en_proceso") {
      estadoBadge = '<span class="transfer-type-badge badge-warning">En Proceso</span>';
    }

    // Tipo badge based on transfer type
    let tipoBadge = "";
    const tipoMap = {
      "paciente_alta": "Paciente",
      "biologico": "Biológico",
      "equipamiento": "Equipamiento",
      "doctor": "Doctor"
    };
    const tipoTexto = tipoMap[info.tipo] || info.tipo || "Traslado";
    tipoBadge = `<span class="transfer-type-badge badge-patient">${tipoTexto}</span>`;

    elements.transferInfo.innerHTML = `
      <h3>Traslado #${info.numero}</h3>
      <p>${info.paciente} - ${info.origen} → ${primerDestino.nombre}</p>
    `;

    // Update badges in header
    const header = document.querySelector(".transfer-detail-header");
    if (header) {
      const existingBadges = header.querySelectorAll(".transfer-type-badge");
      existingBadges.forEach(b => b.remove());

      // Add estado badge
      if (estadoBadge) {
        header.insertAdjacentHTML("beforeend", estadoBadge);
      }
      // Add tipo badge
      if (tipoBadge) {
        header.insertAdjacentHTML("beforeend", tipoBadge);
      }
    }

    if (elements.transferMeta) {
      elements.transferMeta.innerHTML = `
        <div class="transfer-detail-meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
          </svg>
          <span>${info.conductor}</span>
        </div>
        <div class="transfer-detail-meta-item">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
          </svg>
          <span>${info.vehiculo}</span>
        </div>
      `;
    }
  }

  function renderStepper() {
    if (!elements.stepper) return;

    const isFinalizado = state.estado === "completado" || state.estado === "cancelado";

    let html = "";

    state.stepperData.forEach((paso, index) => {
      const pasoIndex = index + 1;

      // Si está completado o cancelado, todos los pasos son completed
      let esCompleted, esActive, esPending;
      if (isFinalizado) {
        esCompleted = true;
        esActive = false;
        esPending = false;
      } else {
        esCompleted = pasoIndex < state.pasoActual;
        esActive = pasoIndex === state.pasoActual;
        esPending = pasoIndex > state.pasoActual;
      }

      const estadoClase = esCompleted ? "completed" : esActive ? "active" : "pending";

      // Step indicator content
      let indicatorContent = "";
      if (esCompleted) {
        indicatorContent = `<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`;
      } else {
        indicatorContent = pasoIndex;
      }

      // Time difference display
      let timeInfo = "";
      if (paso.destino?.tiempo_real && paso.tipo === "arribo") {
        const diff = paso.destino.diferencia_minutos;
        const sign = diff >= 0 ? "+" : "";
        const diffClass = diff > 0 ? "time-late" : diff < 0 ? "time-early" : "time-ontime";
        timeInfo = `<div class="detail-step-time ${diffClass}">${sign}${diff} min</div>`;
      }

      // Reports badge
      let reportsBadge = "";
      if (paso.reportesCount > 0) {
        reportsBadge = `<span class="step-reports-badge" data-reportes='${JSON.stringify(paso.reportes)}'>${paso.reportesCount}</span>`;
      }

      // Connector
      let connector = "";
      if (index < state.stepperData.length - 1) {
        const connectorClass = esCompleted ? "completed" : "";
        connector = `<div class="detail-step-connector ${connectorClass}"></div>`;
      }

      html += `
        <div class="detail-stepper-step ${estadoClase}" data-step="${pasoIndex}" data-tipo="${paso.tipo}" data-destino-orden="${paso.destinoOrden || ""}">
          <div class="detail-step-indicator">${indicatorContent}${reportsBadge}</div>
          <div class="detail-step-content">
            <div class="detail-step-title">${paso.titulo}</div>
            ${timeInfo}
          </div>
        </div>
        ${connector}
      `;
    });

    elements.stepper.innerHTML = html;

    // Bind hover events for reports badges
    document.querySelectorAll(".step-reports-badge").forEach((badge) => {
      badge.addEventListener("mouseenter", mostrarTooltipReporte);
      badge.addEventListener("mouseleave", ocultarTooltipReporte);
    });
  }

  function renderActionButton() {
    if (!elements.actionButton) return;

    // Determine current step info
    const paso = state.stepperData[state.pasoActual - 1];
    if (!paso) return;

    let buttonText = "";
    let buttonClass = "btn btn-success btn-large";

    if (state.estado === "cancelado") {
      buttonText = "Traslado Cancelado";
      buttonClass = "btn btn-secondary btn-large";
      elements.actionButton.disabled = true;
    } else if (state.estado === "completado") {
      buttonText = "Traslado Completado";
      buttonClass = "btn btn-primary btn-large";
      elements.actionButton.disabled = true;
    } else if (paso.tipo === "arribo") {
      buttonText = `Registrar Arribo a ${paso.destino?.nombre || "destino"}`;
    } else if (paso.tipo === "en_transito" || paso.tipo === "en_transito_retorno") {
      buttonText = "Registrar Salida";
    } else if (paso.tipo === "arribo_central") {
      buttonText = "Finalizar Traslado";
      buttonClass = "btn btn-primary btn-large";
    }

    elements.actionButton.innerHTML = `
      <svg class="icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
      ${buttonText}
    `;
    elements.actionButton.className = buttonClass;
    elements.actionButton.disabled = false;
  }

  // ==========================================
  // ACTIONS
  // ==========================================

  async function handleMainAction() {
    const paso = state.stepperData[state.pasoActual - 1];
    if (!paso || state.estado === "cancelado" || state.estado === "completado") return;

    elements.actionButton.disabled = true;
    elements.actionButton.classList.add("loading");

    try {
      if (paso.tipo === "arribo") {
        await registrarArribo(paso);
      } else if (paso.tipo === "en_transito" || paso.tipo === "en_transito_retorno") {
        await registrarSalida(paso);
      } else if (paso.tipo === "arribo_central") {
        await finalizarTraslado();
      }
    } catch (error) {
      console.error("Error en accion:", error);
      mostrarError("Error al procesar la accion");
    } finally {
      elements.actionButton.disabled = false;
      elements.actionButton.classList.remove("loading");
    }
  }

  async function registrarArribo(paso) {
    const timestamp = new Date().toISOString();

    const response = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}/arribo`, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        destino_orden: paso.destinoOrden,
        timestamp: timestamp,
      }),
    });

    const result = await response.json();
    if (result.success) {
      state.pasoActual++;
      renderAll();
    } else {
      throw new Error(result.message || "Error en servidor");
    }
  }

  async function registrarSalida(paso) {
    // For departure, we just advance the step without calling an API
    // since there's no specific "departure" endpoint
    state.pasoActual++;
    renderAll();
  }

  async function finalizarTraslado() {
    // Similar to departure - just advance and mark as completed
    state.estado = "completado";
    state.pasoActual++;
    renderAll();
    mostrarExito("Traslado completado exitosamente");
  }

  // ==========================================
  // REPORTS
  // ==========================================

  function abrirModalReporte() {
    if (!elements.modalReport) return;
    elements.modalReport.classList.add("active");
    document.getElementById("reporte-tipo")?.focus();
  }

  function cerrarModales() {
    document.querySelectorAll(".modal-overlay").forEach((m) => {
      m.classList.remove("active");
    });
    // Reset form
    elements.reportForm?.reset();
  }

  async function handleReportSubmit(e) {
    e.preventDefault();

    const tipo = document.getElementById("reporte-tipo")?.value;
    const mensaje = document.getElementById("reporte-mensaje")?.value;

    if (!tipo || !mensaje) {
      mostrarError("Complete todos los campos");
      return;
    }

    const paso = state.stepperData[state.pasoActual - 1];

    try {
      const response = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}/reportes`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          destino_orden: paso.destinoOrden,
          tipo_problema: tipo,
          mensaje: mensaje,
        }),
      });

      const result = await response.json();
      if (result.success) {
        cerrarModales();
        mostrarExito("Reporte registrado");
        cargarDatosTraslado();
      }
    } catch (error) {
      console.error("Error al guardar reporte:", error);
      mostrarError("Error al guardar el reporte");
    }
  }

  async function handleCancelarTraslado() {
    const tipo = document.getElementById("reporte-tipo")?.value;
    const mensaje = document.getElementById("reporte-mensaje")?.value;

    if (!tipo || !mensaje) {
      mostrarError("Complete todos los campos antes de cancelar");
      return;
    }

    const paso = state.stepperData[state.pasoActual - 1];

    try {
      const response = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}/cancelar`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          destino_orden: paso.destinoOrden,
          tipo_problema: tipo,
          mensaje: mensaje,
        }),
      });

      const result = await response.json();
      if (result.success) {
        cerrarModales();
        state.estado = "cancelado";
        renderAll();
        mostrarExito("Traslado cancelado");
      }
    } catch (error) {
      console.error("Error al cancelar traslado:", error);
      mostrarError("Error al cancelar el traslado");
    }
  }

  // ==========================================
  // TOOLTIP
  // ==========================================

  function mostrarTooltipReporte(e) {
    const badge = e.target;
    const reportes = JSON.parse(badge.dataset.reportes || "[]");

    if (!reportes.length) return;

    // Find the step element (parent of the badge)
    const step = badge.closest(".detail-stepper-step");
    if (!step) return;

    // Create tooltip if it doesn't exist - append to body for fixed positioning
    let tooltip = document.body.querySelector(".report-tooltip");
    if (!tooltip) {
      tooltip = document.createElement("div");
      tooltip.className = "report-tooltip";
      document.body.appendChild(tooltip);
    }

    // Update tooltip content
    tooltip.innerHTML = `
      <div class="report-tooltip-title">Reportes (${reportes.length})</div>
      <ul class="report-tooltip-list">
        ${reportes.map((r) => `
          <li>
            <div class="report-tooltip-tipo">${escapeHtml(r.tipo)}</div>
            <div class="report-tooltip-mensaje">${escapeHtml(r.mensaje)}</div>
          </li>
        `).join("")}
      </ul>
    `;

    // Position tooltip using fixed positioning based on badge's viewport position
    const rect = badge.getBoundingClientRect();

    // Position above the badge, centered
    const top = rect.top - 12;
    const left = rect.left + rect.width / 2;

    tooltip.style.top = `${top}px`;
    tooltip.style.left = `${left}px`;
    tooltip.style.transform = "translateX(-50%) translateY(-100%)";

    requestAnimationFrame(() => {
      tooltip.classList.add("visible");
    });
  }

  function ocultarTooltipReporte(e) {
    const tooltip = document.body.querySelector(".report-tooltip");
    if (tooltip) {
      tooltip.classList.remove("visible");
    }
  }

  // ==========================================
  // HELPERS
  // ==========================================

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  function mostrarError(mensaje) {
    alert(mensaje);
  }

  function mostrarExito(mensaje) {
    alert(mensaje);
  }

  // ==========================================
  // BOOTSTRAP
  // ==========================================

  document.addEventListener("DOMContentLoaded", init);
})();
