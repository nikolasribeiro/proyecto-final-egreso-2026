/**
 * Detalle Traslado - Layout rediseñado (2 columnas)
 * Timeline vertical + action panel + CSRF + toasts + reportes colapsables
 */
(function () {
  "use strict";

  const CONFIG = {
    API_BASE: "/api/traslados",
  };

  // Estado
  let state = {
    trasladoId: null,
    pasoActual: 1,
    destinos: [],
    volverAlOrigen: false,
    estado: "",
    prioridad: "verde",
    tipo: "paciente_alta",
    pasoInfo: null,
    reportesExpandidos: new Set(), // keys: `${trasladoId}-${orden}` para los que el usuario expandió
  };

  // Cache
  let elements = {};

  // ==========================================
  // HELPERS
  // ==========================================

  function getCsrf() {
    return document.getElementById("csrf-token")?.value || "";
  }

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text == null ? "" : String(text);
    return div.innerHTML;
  }

  function formatTime(value) {
    if (!value) return null;
    try {
      return new Date(value).toLocaleTimeString("es-UY", {
        hour: "2-digit",
        minute: "2-digit",
      });
    } catch {
      return null;
    }
  }

  async function apiPost(endpoint, body) {
    const csrf = getCsrf();
    const r = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}${endpoint}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": csrf,
      },
      body: JSON.stringify({ _csrf: csrf, ...body }),
    });
    const json = await r.json().catch(() => ({}));
    return { ok: r.ok, status: r.status, data: json };
  }

  // ==========================================
  // TOASTS (no más alerts)
  // ==========================================

  function ensureToastContainer() {
    let c = document.getElementById("toast-container");
    if (!c) {
      c = document.createElement("div");
      c.id = "toast-container";
      c.className = "toast-container";
      document.body.appendChild(c);
    }
    return c;
  }

  function toast(message, tipo = "info", duracion = 3500) {
    const container = ensureToastContainer();
    const t = document.createElement("div");
    t.className = `toast toast-${tipo}`;
    const iconos = {
      success:
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
      error:
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
      info:
        '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    };
    t.innerHTML = `
      <span class="toast-icon">${iconos[tipo] || iconos.info}</span>
      <span class="toast-msg">${escapeHtml(message)}</span>
    `;
    container.appendChild(t);
    requestAnimationFrame(() => t.classList.add("toast-visible"));
    setTimeout(() => {
      t.classList.remove("toast-visible");
      setTimeout(() => t.remove(), 300);
    }, duracion);
  }

  // ==========================================
  // INITIALIZATION
  // ==========================================

  function init() {
    const container = document.getElementById("transfer-detail");
    if (!container) return;

    state.trasladoId = parseInt(container.dataset.trasladoId, 10);
    state.estado = (container.dataset.estado || "").toLowerCase();
    if (!state.trasladoId) return;

    cacheElements();
    bindEvents();
    cargarDatosTraslado();
  }

  function cacheElements() {
    elements = {
      container: document.getElementById("transfer-detail"),
      timelineList: document.getElementById("timeline-list"),
      actionPanel: document.getElementById("action-panel"),
      actionDesc: document.getElementById("action-desc"),
      actionText: document.getElementById("action-text"),
      actionButton: document.getElementById("btn-main-action"),
      reportButton: document.getElementById("btn-report"),
      modalReport: document.getElementById("report-modal"),
      reportForm: document.getElementById("report-form"),
      btnCancelTraslado: document.getElementById("btn-cancelar-traslado"),
    };
  }

  function bindEvents() {
    elements.actionButton?.addEventListener("click", handleMainAction);
    elements.reportButton?.addEventListener("click", abrirModalReporte);

    document.querySelectorAll(".modal-close").forEach((btn) => {
      btn.addEventListener("click", cerrarModales);
    });

    document.querySelectorAll(".modal-overlay").forEach((overlay) => {
      overlay.addEventListener("click", (e) => {
        if (e.target === overlay) cerrarModales();
      });
    });

    elements.reportForm?.addEventListener("submit", handleReportSubmit);
    elements.btnCancelTraslado?.addEventListener("click", handleCancelarTraslado);
  }

  // ==========================================
  // DATA LOADING
  // ==========================================

  async function cargarDatosTraslado() {
    try {
      const response = await fetch(`${CONFIG.API_BASE}/${state.trasladoId}`);
      if (!response.ok) {
        toast(`Error al cargar el traslado (HTTP ${response.status})`, "error");
        return;
      }
      const result = await response.json();
      if (result.success) {
        mapResponseToState(result.data);
        renderTimeline();
        renderActionPanel();
        renderReportButton();
      } else {
        toast(result.message || "Traslado no encontrado", "error");
      }
    } catch (error) {
      console.error("Error cargando traslado:", error);
      toast("Error al cargar los datos del traslado", "error");
    }
  }

  function mapResponseToState(data) {
    state.destinos = data.destinos || [];
    state.volverAlOrigen = !!data.volver_al_origen;
    state.pasoActual = data.paso_actual || 1;
    state.estado = (data.estado || "").toLowerCase();
    state.prioridad = data.prioridad || "verde";
    state.tipo = data.tipo || "paciente_alta";
    state.pasoInfo = calcularPasoInfo();
  }

  function calcularPasoInfo() {
    const totalDestinos = state.destinos.length;
    let pasoLogico = state.pasoActual;

    let contador = 1;
    for (let i = 0; i < totalDestinos; i++) {
      if (pasoLogico === contador) {
        return {
          tipo: "en_transito",
          destinoOrden: state.destinos[i].orden,
          destinoNombre: state.destinos[i].nombre,
        };
      }
      contador++;
      if (pasoLogico === contador) {
        return {
          tipo: "arribo",
          destinoOrden: state.destinos[i].orden,
          destinoNombre: state.destinos[i].nombre,
        };
      }
      contador++;
    }
    if (state.volverAlOrigen) {
      if (pasoLogico === contador) {
        return { tipo: "en_transito_retorno", destinoOrden: null, destinoNombre: "Regreso" };
      }
      contador++;
      if (pasoLogico === contador) {
        return { tipo: "arribo_central", destinoOrden: null, destinoNombre: "Central" };
      }
    }
    return null;
  }

  // ==========================================
  // RENDERING
  // ==========================================

  function renderTimeline() {
    const list = elements.timelineList;
    if (!list) return;

    const isFinalizado =
      state.estado === "finalizado" || state.estado === "cancelado";

    let html = "";

    state.destinos.forEach((destino) => {
      const isCurrent =
        !isFinalizado &&
        state.pasoInfo &&
        state.pasoInfo.destinoOrden === destino.orden &&
        (state.pasoInfo.tipo === "en_transito" ||
          state.pasoInfo.tipo === "arribo");
      const isPast = destino.estado_destino === "ARRIBADO";
      const itemClass = isPast ? "done" : isCurrent ? "current" : "pending";

      html += `<li class="timeline-item timeline-item-${itemClass}"
                     data-orden="${escapeHtml(destino.orden)}"
                     data-estado="${escapeHtml(destino.estado_destino)}">
                <div class="timeline-marker">
                  ${
                    isPast
                      ? `<svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>`
                      : `<span>${escapeHtml(destino.orden)}</span>`
                  }
                </div>
                <div class="timeline-body">
                  <h4>${escapeHtml(destino.nombre)}</h4>
                  ${destino.direccion ? `<p class="timeline-direction">${escapeHtml(destino.direccion)}</p>` : ""}
                  <p class="timeline-time">
                    ${
                      destino.fecha_llegada_efectiva
                        ? `<span class="timeline-time-label">Arribado:</span> <strong>${escapeHtml(formatTime(destino.fecha_llegada_efectiva))}</strong>`
                        : destino.fecha_llegada_estimada
                          ? `<span class="timeline-time-label">Estimado:</span> <strong>${escapeHtml(formatTime(destino.fecha_llegada_estimada))}</strong>`
                          : ""
                    }
                  </p>
                  ${renderReportes(destino)}
                </div>
              </li>`;
    });

    if (state.volverAlOrigen) {
      const retornoClass =
        state.pasoInfo && state.pasoInfo.tipo === "en_transito_retorno"
          ? "current"
          : "pending";
      html += `<li class="timeline-item timeline-item-${retornoClass}" data-tipo="retorno">
                <div class="timeline-marker"><span>↩</span></div>
                <div class="timeline-body">
                  <h4>Regreso a Central</h4>
                  <p class="timeline-direction">Hospital de Clínicas</p>
                </div>
              </li>`;
    }

    list.innerHTML = html;
    bindReportesToggles();
  }

  /**
   * Render de reportes con colapso automático si hay más de 2.
   * Los primeros 2 siempre se muestran. Los adicionales se ocultan
   * detrás de un botón toggle que muestra "Ver N más" / "Ver menos".
   */
  function renderReportes(destino) {
    const reportes = destino.reportes || [];
    if (reportes.length === 0) return "";

    const key = `${state.trasladoId}-${destino.orden}`;
    const expandido = state.reportesExpandidos.has(key);
    const VISIBLES_INICIAL = 2;
    const tieneMas = reportes.length > VISIBLES_INICIAL;

    const visibles = expandido || !tieneMas
      ? reportes
      : reportes.slice(0, VISIBLES_INICIAL);

    const ocultos = tieneMas && !expandido
      ? reportes.length - VISIBLES_INICIAL
      : 0;

    let html = `<div class="timeline-reports" data-key="${escapeHtml(key)}">`;
    visibles.forEach((rep) => {
      html += `<div class="report-card">
                  <div class="report-card-header">
                    <span class="report-card-tipo">${escapeHtml(rep.tipo_problema)}</span>
                    <time class="report-card-time">${escapeHtml(formatTime(rep.fecha_reporte))}</time>
                  </div>
                  <p class="report-card-msg">${escapeHtml(rep.mensaje)}</p>
                </div>`;
    });
    if (tieneMas) {
      const label = expandido
        ? "Ver menos"
        : `Ver ${ocultos} reporte${ocultos > 1 ? "s" : ""} más`;
      html += `<button type="button" class="report-toggle" data-orden="${escapeHtml(destino.orden)}" data-accion="${expandido ? "collapse" : "expand"}">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${expandido ? "M5 15l7-7 7 7" : "M19 9l-7 7-7-7"}"/>
                </svg>
                ${escapeHtml(label)}
              </button>`;
    }
    html += `</div>`;
    return html;
  }

  function bindReportesToggles() {
    document.querySelectorAll(".report-toggle").forEach((btn) => {
      btn.addEventListener("click", () => {
        const orden = btn.dataset.orden;
        const key = `${state.trasladoId}-${orden}`;
        if (btn.dataset.accion === "expand") {
          state.reportesExpandidos.add(key);
        } else {
          state.reportesExpandidos.delete(key);
        }
        renderTimeline();
      });
    });
  }

  function renderActionPanel() {
    const desc = elements.actionDesc;
    const text = elements.actionText;
    const btn = elements.actionButton;
    if (!desc || !text || !btn) return;

    if (state.estado === "cancelado") {
      desc.textContent = "Este traslado fue cancelado y no admite más acciones.";
      text.textContent = "Traslado cancelado";
      btn.disabled = true;
      btn.classList.remove("btn-primary", "btn-success");
      btn.classList.add("btn-secondary");
      return;
    }
    if (state.estado === "finalizado") {
      desc.textContent = "El traslado se completó exitosamente. Todos los destinos fueron visitados.";
      text.textContent = "Traslado completado";
      btn.disabled = true;
      btn.classList.remove("btn-primary", "btn-success");
      btn.classList.add("btn-secondary");
      return;
    }

    if (!state.pasoInfo) {
      desc.textContent = "No hay acciones pendientes para este traslado.";
      text.textContent = "Sin acciones";
      btn.disabled = true;
      return;
    }

    btn.disabled = false;
    btn.classList.remove("btn-secondary");

    const p = state.pasoInfo;
    if (p.tipo === "en_transito") {
      desc.textContent = `El vehículo debe salir hacia ${p.destinoNombre}. Registra la salida cuando el vehículo parta.`;
      text.textContent = `Registrar salida hacia ${p.destinoNombre}`;
      btn.classList.remove("btn-success");
      btn.classList.add("btn-primary");
    } else if (p.tipo === "arribo") {
      desc.textContent = `El vehículo debe arribar a ${p.destinoNombre}. Registra el arribo cuando confirmes la entrega.`;
      text.textContent = `Registrar arribo a ${p.destinoNombre}`;
      btn.classList.remove("btn-primary");
      btn.classList.add("btn-success");
    } else if (p.tipo === "en_transito_retorno") {
      desc.textContent = "El vehículo debe regresar a Central. Registra la salida cuando parta desde el último destino.";
      text.textContent = "Registrar salida (regreso)";
      btn.classList.remove("btn-success");
      btn.classList.add("btn-primary");
    } else if (p.tipo === "arribo_central") {
      desc.textContent = "El vehículo está regresando al Hospital de Clínicas. Registra el arribo para finalizar el traslado.";
      text.textContent = "Finalizar traslado";
      btn.classList.remove("btn-primary");
      btn.classList.add("btn-success");
    }
  }

  function renderReportButton() {
    if (!elements.reportButton) return;
    const isFinal = state.estado === "finalizado" || state.estado === "cancelado";
    elements.reportButton.disabled = isFinal;
    elements.reportButton.classList.toggle("btn-disabled", isFinal);
  }

  // ==========================================
  // ACTIONS
  // ==========================================

  async function handleMainAction() {
    if (!state.pasoInfo) return;
    if (state.estado === "cancelado" || state.estado === "finalizado") return;

    elements.actionButton.disabled = true;
    elements.actionButton.classList.add("loading");

    try {
      const p = state.pasoInfo;

      if (p.tipo === "arribo" || p.tipo === "arribo_central") {
        const result = await apiPost("/arribo", {
          destino_orden: p.destinoOrden ?? 0,
          timestamp: new Date().toISOString(),
        });
        if (!result.ok || !result.data.success) {
          throw new Error(result.data.message || `Error HTTP ${result.status}`);
        }
        toast("Arribo registrado correctamente", "success");
      } else if (p.tipo === "en_transito" || p.tipo === "en_transito_retorno") {
        // Llamada real al backend para registrar salida
        const result = await apiPost("/salida", {
          destino_orden: p.destinoOrden ?? 0,
        });
        if (!result.ok || !result.data.success) {
          throw new Error(result.data.message || `Error HTTP ${result.status}`);
        }
        toast("Salida registrada. Continúa con el arribo al destino.", "success");
      }

      await cargarDatosTraslado();
    } catch (error) {
      console.error("Error en accion:", error);
      toast(error.message || "Error al procesar la acción", "error");
    } finally {
      elements.actionButton?.classList.remove("loading");
    }
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
    elements.reportForm?.reset();
  }

  async function handleReportSubmit(e) {
    e.preventDefault();
    const tipo = document.getElementById("reporte-tipo")?.value;
    const mensaje = document.getElementById("reporte-mensaje")?.value;
    if (!tipo || !mensaje) {
      toast("Complete todos los campos del reporte", "error");
      return;
    }

    const destinoOrden = state.pasoInfo?.destinoOrden ?? 1;

    try {
      const result = await apiPost("/reportes", {
        destino_orden: destinoOrden,
        tipo_problema: tipo,
        mensaje,
      });
      if (!result.ok || !result.data.success) {
        throw new Error(result.data.message || `Error HTTP ${result.status}`);
      }
      cerrarModales();
      toast("Reporte registrado correctamente", "success");
      await cargarDatosTraslado();
    } catch (error) {
      console.error("Error al guardar reporte:", error);
      toast(error.message || "Error al guardar el reporte", "error");
    }
  }

  async function handleCancelarTraslado() {
    const tipo = document.getElementById("reporte-tipo")?.value;
    const mensaje = document.getElementById("reporte-mensaje")?.value;
    if (!tipo || !mensaje) {
      toast("Complete todos los campos antes de cancelar", "error");
      return;
    }

    const destinoOrden = state.pasoInfo?.destinoOrden ?? 1;

    if (
      !confirm(
        "¿Estás seguro de cancelar este traslado? Esta acción no se puede deshacer.",
      )
    ) {
      return;
    }

    try {
      const result = await apiPost("/cancelar", {
        destino_orden: destinoOrden,
        tipo_problema: tipo,
        mensaje,
      });
      if (!result.ok || !result.data.success) {
        throw new Error(result.data.message || `Error HTTP ${result.status}`);
      }
      cerrarModales();
      toast("Traslado cancelado correctamente", "success");
      await cargarDatosTraslado();
    } catch (error) {
      console.error("Error al cancelar traslado:", error);
      toast(error.message || "Error al cancelar el traslado", "error");
    }
  }

  // ==========================================
  // BOOTSTRAP
  // ==========================================

  document.addEventListener("DOMContentLoaded", init);
})();