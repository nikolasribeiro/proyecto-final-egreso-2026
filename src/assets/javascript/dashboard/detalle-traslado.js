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
    destinos: [],
    volverAlOrigen: false,
    estado: "",
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
        syncContainerChrome();
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
    state.estado = (data.estado || "").toLowerCase();
    state.pasoInfo = data.paso_info || null;
  }

  // Sincroniza clase del contenedor + badge del header para que la opacidad
  // CSS y el label reflejen el estado actual sin necesidad de recargar.
  function syncContainerChrome() {
    if (!elements.container) return;
    elements.container.dataset.estado = state.estado;
    elements.container.classList.toggle("detail-transfer-finalizado", state.estado === "finalizado");
    elements.container.classList.toggle("detail-transfer-cancelado", state.estado === "cancelado");

    const badge = elements.container.querySelector(".badge-estado");
    if (badge) {
      badge.textContent = estadoLabel(state.estado);
    }
  }

  function estadoLabel(estado) {
    switch (estado) {
      case "pendiente":   return "PENDIENTE";
      case "en_transito": return "EN TRÁNSITO";
      case "finalizado":  return "FINALIZADO";
      case "cancelado":   return "CANCELADO";
      default:            return estado ? estado.toUpperCase() : "";
    }
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
        Number(state.pasoInfo.destino_orden) === Number(destino.orden);
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

    let html = `<div class="timeline-reports">`;
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

    // Reset total de variantes antes de aplicar la nueva.
    btn.classList.remove("btn-primary", "btn-success", "btn-secondary");

    if (state.estado === "cancelado") {
      desc.textContent = "Este traslado fue cancelado y no admite más acciones.";
      text.textContent = "Traslado cancelado";
      btn.classList.add("btn-secondary");
      btn.disabled = true;
      return;
    }
    if (state.estado === "finalizado") {
      desc.textContent = "El traslado se completó exitosamente. Todos los destinos fueron visitados.";
      text.textContent = "Traslado completado";
      btn.classList.add("btn-secondary");
      btn.disabled = true;
      return;
    }

    if (!state.pasoInfo) {
      desc.textContent = "No hay acciones pendientes para este traslado.";
      text.textContent = "Sin acciones";
      btn.classList.add("btn-secondary");
      btn.disabled = true;
      return;
    }

    const p = state.pasoInfo;
    const destino = p.destino_nombre || "";
    const origen = (state.destinos.find((d) => d.es_retorno) || {}).nombre || destino;

    switch (p.tipo) {
      case "inicio_traslado":
        text.textContent = "Traslado iniciado";
        desc.textContent = `Confirma el inicio del traslado hacia ${destino}.`;
        btn.classList.add("btn-primary");
        btn.disabled = false;
        break;
      case "registrar_llegada":
        text.textContent = `Registrar llegada a ${destino}`;
        desc.textContent = `Confirma la llegada al destino ${destino}.`;
        btn.classList.add("btn-success");
        btn.disabled = false;
        break;
      case "inicio_retorno_central":
        text.textContent = "Inicio retorno central";
        desc.textContent = `Inicia el regreso a ${origen}.`;
        btn.classList.add("btn-primary");
        btn.disabled = false;
        break;
      case "registrar_llegada_central":
        text.textContent = "Registrar llegada a Central Hospital de Clínicas";
        desc.textContent = `Confirma la llegada a ${origen} para finalizar el traslado.`;
        btn.classList.add("btn-success");
        btn.disabled = false;
        break;
      default:
        text.textContent = "Sin acciones";
        desc.textContent = "No hay acciones pendientes para este traslado.";
        btn.classList.add("btn-secondary");
        btn.disabled = true;
    }
  }

  function renderReportButton() {
    if (!elements.reportButton) return;
    const isFinal = state.estado === "finalizado" || state.estado === "cancelado";
    const sinAccion = !state.pasoInfo;
    const disabled = isFinal || sinAccion;
    elements.reportButton.disabled = disabled;
    elements.reportButton.classList.toggle("btn-disabled", disabled);
  }

  // ==========================================
  // ACTIONS
  // ==========================================

  async function handleMainAction() {
    if (!state.pasoInfo) return;
    if (state.estado === "cancelado" || state.estado === "finalizado") return;

    const btn = elements.actionButton;
    btn.disabled = true;
    btn.classList.add("loading");

    try {
      const p = state.pasoInfo;
      const destinoOrden = Number(p.destino_orden);

      // /arribo es para llegadas; /salida para salidas/inicio.
      const esLlegada =
        p.tipo === "registrar_llegada" || p.tipo === "registrar_llegada_central";

      const body = esLlegada
        ? { destino_orden: destinoOrden, timestamp: new Date().toISOString() }
        : { destino_orden: destinoOrden };

      const endpoint = esLlegada ? "/arribo" : "/salida";
      const result = await apiPost(endpoint, body);
      if (!result.ok || !result.data.success) {
        throw new Error(result.data.message || `Error HTTP ${result.status}`);
      }
      toast(
        esLlegada
          ? "Arribo registrado correctamente"
          : "Salida registrada. Continúa con el arribo al destino.",
        "success",
      );
      // Refresco SPA del timeline/badge para feedback inmediato, seguido de
      // un reload completo que garantiza que el botón refleja el nuevo estado
      // (por seguridad contra cualquier race entre el commit del POST y el
      // GET que alimenta `paso_info`).
      await cargarDatosTraslado();
      window.location.reload();
    } catch (error) {
      console.error("Error en accion:", error);
      toast(error.message || "Error al procesar la acción", "error");
    } finally {
      btn.classList.remove("loading");
      // Si el reload aún no ocurrió (por error antes), re-habilitar según
      // el estado actual.
      const terminal =
        state.estado === "cancelado" || state.estado === "finalizado";
      btn.disabled = !state.pasoInfo || terminal;
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

    if (!state.pasoInfo || state.pasoInfo.destino_orden == null) {
      toast("No hay un destino activo para asociar el reporte.", "error");
      return;
    }
    const destinoOrden = Number(state.pasoInfo.destino_orden);

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
      window.location.reload();
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

    // Cancelar no requiere un destino activo; si no hay, usamos 1 como
    // primer destino del itinerario (el backend igual registra el evento
    // a nivel de la solicitud, no del destino individual).
    const destinoOrden = state.pasoInfo?.destino_orden
      ? Number(state.pasoInfo.destino_orden)
      : 1;

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
      window.location.reload();
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