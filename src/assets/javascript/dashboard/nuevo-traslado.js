/**
 * Script para el formulario de nueva solicitud de traslado
 * Wizard de un paso a la vez con sessionStorage para persistencia
 */

document.addEventListener("DOMContentLoaded", function () {
  // Clave para sessionStorage
  const SESSION_KEY = "traslado_form_data";

  // Nombres legibles para tipos de traslado
  const nombresTipo = {
    paciente_alta: "Paciente Dado de Alta",
    biologico: "Material Biológico",
    equipamiento: "Equipamiento",
  };

  // Estado del formulario (cargado desde sessionStorage)
  let estado = cargarEstado();

  // Elementos del DOM
  const elementos = {
    pasos: document.querySelectorAll(".stepper-item"),
    lineas: document.querySelectorAll(".stepper-line"),
    steps: document.querySelectorAll(".form-step"),
    btnAddDestino: document.getElementById("btn-add-destino"),
    btnConfirmar: document.getElementById("btn-confirmar-traslado"),
    modalDestino: document.getElementById("destino-modal"),
    closeModalBtn: document.getElementById("close-destino-modal"),
    searchDestino: document.getElementById("search-destino"),
    destinosList: document.getElementById("destinos-list"),
    destinosSuggestions: document.getElementById("destinos-suggestions"),
    destinosEmpty: document.getElementById("destinos-empty"),
    nuevoDestino: document.querySelector("[data-nuevo-destino]"),
    volverOrigen: document.getElementById("volver-origen"),
    vehiculosGrid: document.getElementById("vehiculos-grid"),
    resumenTipo: document.getElementById("resumen-tipo"),
    resumenDestinos: document.getElementById("resumen-destinos"),
    resumenConductor: document.getElementById("resumen-conductor"),
    resumenEnfermeroRow: document.getElementById("resumen-enfermero-row"),
    resumenEnfermero: document.getElementById("resumen-enfermero"),
    resumenJerarquiaRow: document.getElementById("resumen-jerarquia-row"),
    resumenJerarquia: document.getElementById("resumen-jerarquia"),
    resumenVehiculo: document.getElementById("resumen-vehiculo"),
    resumenVueltaRow: document.getElementById("resumen-vuelta-row"),
    resumenEstadoCriticoRow: document.getElementById("resumen-estado-critico-row"),
    resumenEstadoCritico: document.getElementById("resumen-estado-critico"),
    resumenCamillaRow: document.getElementById("resumen-camilla-row"),
    resumenCamilla: document.getElementById("resumen-camilla"),
    resumenDiagnosticoRow: document.getElementById("resumen-diagnostico-row"),
    resumenDiagnostico: document.getElementById("resumen-diagnostico"),
    btnSolicitarSame: document.getElementById("btn-solicitar-same"),
    conductorInput: document.getElementById("conductor-input"),
    conductorSelect: document.getElementById("conductor"),
    conductorWrapper: document.getElementById("conductor-wrapper"),
    conductorDropdown: document.getElementById("conductor-dropdown"),
    enfermeroInput: document.getElementById("enfermero-input"),
    enfermeroSelect: document.getElementById("enfermero"),
    enfermeroWrapper: document.getElementById("enfermero-wrapper"),
    enfermeroDropdown: document.getElementById("enfermero-dropdown"),
    jerarquiaSelect: document.getElementById("jerarquia-enfermero"),
    jerarquiaGroup: document.getElementById("jerarquia-enfermero-group"),
    estadoCritico: document.getElementById("estado-critico"),
    requiereCamilla: document.getElementById("requiere-camilla"),
    tipoDiagnostico: document.getElementById("tipo-diagnostico"),
    csrfToken: document.getElementById("csrf-token"),
  };

  // Labels legibles para los diagnósticos
  const diagnosticoLabels = {};
  if (elementos.tipoDiagnostico) {
    Array.from(elementos.tipoDiagnostico.options).forEach((opt) => {
      if (opt.value) diagnosticoLabels[opt.value] = opt.textContent;
    });
  }

  // Labels legibles para las jerarquías
  const jerarquiaLabels = {
    licenciado: "Licenciado en Enfermería",
    auxiliar: "Auxiliar de Enfermería",
    profesional: "Enfermero Profesional",
  };

  /**
   * Cargar estado desde sessionStorage
   */
  function cargarEstado() {
    const saved = sessionStorage.getItem(SESSION_KEY);
    if (saved) {
      return JSON.parse(saved);
    }
    return {
      pasoActual: 1,
      tipoTraslado: null,
      estadoCritico: false,
      requiereCamilla: false,
      tipoDiagnostico: null,
      destinos: [],
      conductor: null,
      enfermero: null,
      jerarquiaEnfermero: null,
      vehiculo: null,
      volverOrigen: false,
    };
  }

  /**
   * Guardar estado en sessionStorage
   */
  function guardarEstado() {
    sessionStorage.setItem(SESSION_KEY, JSON.stringify(estado));
  }

  /**
   * Limpiar sessionStorage
   */
  function limpiarEstado() {
    sessionStorage.removeItem(SESSION_KEY);
  }

  /**
   * Devuelve el paso anterior VISIBLE, saltando los pasos que estén skipped.
   * Sirve para que los botones "Volver" no caigan en un paso oculto
   * (ej: paso 2 cuando el tipo de traslado no es paciente_alta).
   */
  function pasoAnteriorVisible(actual) {
    let prev = actual - 1;
    while (prev >= 1) {
      if (
        prev === 2 &&
        estado.tipoTraslado &&
        estado.tipoTraslado !== "paciente_alta"
      ) {
        prev--;
        continue;
      }
      return prev;
    }
    return 1;
  }

  /**
   * Inicializar el formulario
   */
  function init() {
    // Restaurar estado visual
    restaurarEstado();

    // Actualizar UI
    actualizarUI();

    // Vincular eventos
    bindEventos();
    bindDestinos();
    bindAutocompletes();

    // Aplicar restricciones iniciales de vehículos
    aplicarRestriccionesVehiculos();
  }

  /**
   * Restaurar estado desde sessionStorage
   */
  function restaurarEstado() {
    // Restaurar tipo de traslado
    if (estado.tipoTraslado) {
      const radio = document.querySelector(
        `input[name="tipo_traslado"][value="${estado.tipoTraslado}"]`,
      );
      if (radio) radio.checked = true;
    }

    // Restaurar datos clínicos
    if (elementos.estadoCritico) {
      elementos.estadoCritico.checked = !!estado.estadoCritico;
    }
    if (elementos.requiereCamilla) {
      elementos.requiereCamilla.checked = !!estado.requiereCamilla;
    }
    if (elementos.tipoDiagnostico && estado.tipoDiagnostico) {
      elementos.tipoDiagnostico.value = estado.tipoDiagnostico;
    }

    // Restaurar destinos
    renderizarDestinos();

    // Restaurar checkbox volver al origen
    if (estado.volverOrigen) {
      elementos.volverOrigen.checked = true;
    }

    // Restaurar vehículo seleccionado
    if (estado.vehiculo) {
      const radio = document.querySelector(
        `input[name="vehiculo"][value="${estado.vehiculo}"]`,
      );
      if (radio) radio.checked = true;
    }

    // Restaurar conductor y enfermero (select oculto + input visible)
    const restoreSelect = (selectEl, inputEl, val) => {
      if (!selectEl || !val) return;
      selectEl.value = val;
      if (inputEl) {
        const opt = Array.from(selectEl.options).find(
          (o) => String(o.value) === String(val),
        );
        if (opt && opt.dataset.nombre) {
          inputEl.value = opt.dataset.nombre;
        }
      }
    };
    restoreSelect(
      elementos.conductorSelect,
      elementos.conductorInput,
      estado.conductor,
    );
    restoreSelect(
      elementos.enfermeroSelect,
      elementos.enfermeroInput,
      estado.enfermero,
    );
    if (elementos.jerarquiaSelect && estado.jerarquiaEnfermero) {
      elementos.jerarquiaSelect.value = estado.jerarquiaEnfermero;
    }
    actualizarVisibilidadJerarquia();

    // Actualizar botones según estado
    actualizarBotonPaso1();
    actualizarBotonPaso4();
    actualizarBotonPaso5();
    actualizarBotonPaso6();
  }

  /**
   * Vincular eventos principales
   */
  function bindEventos() {
    // Botón Solicitar SAME
    if (elementos.btnSolicitarSame) {
      elementos.btnSolicitarSame.addEventListener("click", function () {
        alert(
          "En un entorno real, esto enviaría una solicitud al servicio de SAME.",
        );
      });
    }

    // Navegación entre pasos (7 pasos totales)
    document
      .getElementById("btn-step-1")
      ?.addEventListener("click", () => {
        // Si el tipo NO es paciente_alta, saltar paso 2 (datos clínicos)
        irAPaso(estado.tipoTraslado === "paciente_alta" ? 2 : 3);
      });
    document
      .getElementById("btn-back-2")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(2)));
    document
      .getElementById("btn-step-2")
      ?.addEventListener("click", () => irAPaso(3));
    document
      .getElementById("btn-back-3")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(3)));
    document
      .getElementById("btn-step-3")
      ?.addEventListener("click", () => irAPaso(4));
    document
      .getElementById("btn-back-4")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(4)));
    document
      .getElementById("btn-step-4")
      ?.addEventListener("click", () => irAPaso(5));
    document
      .getElementById("btn-back-5")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(5)));
    document
      .getElementById("btn-step-5")
      ?.addEventListener("click", () => irAPaso(6));
    document
      .getElementById("btn-back-6")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(6)));
    document
      .getElementById("btn-step-6")
      ?.addEventListener("click", () => irAPaso(7));
    document
      .getElementById("btn-back-7")
      ?.addEventListener("click", () => irAPaso(pasoAnteriorVisible(7)));
    elementos.btnConfirmar.addEventListener("click", confirmarTraslado);

    // Conductor y Enfermero
    if (elementos.conductorSelect) {
      elementos.conductorSelect.addEventListener("change", function () {
        estado.conductor = this.value || null;
        guardarEstado();
        actualizarBotonPaso5();
      });
    }

    if (elementos.enfermeroSelect) {
      elementos.enfermeroSelect.addEventListener("change", function () {
        estado.enfermero = this.value || null;
        // Si se deselecciona el enfermero, también limpiamos la jerarquía
        if (!estado.enfermero) {
          estado.jerarquiaEnfermero = null;
          if (elementos.jerarquiaSelect) elementos.jerarquiaSelect.value = "";
        }
        guardarEstado();
        actualizarVisibilidadJerarquia();
      });
    }

    if (elementos.jerarquiaSelect) {
      elementos.jerarquiaSelect.addEventListener("change", function () {
        estado.jerarquiaEnfermero = this.value || null;
        guardarEstado();
      });
    }

    // Datos clínicos (Paso 2)
    if (elementos.estadoCritico) {
      elementos.estadoCritico.addEventListener("change", function () {
        estado.estadoCritico = this.checked;
        guardarEstado();
      });
    }
    if (elementos.requiereCamilla) {
      elementos.requiereCamilla.addEventListener("change", function () {
        estado.requiereCamilla = this.checked;
        guardarEstado();
      });
    }
    if (elementos.tipoDiagnostico) {
      elementos.tipoDiagnostico.addEventListener("change", function () {
        estado.tipoDiagnostico = this.value || null;
        guardarEstado();
      });
    }

    // Tipo de traslado - actualizar botón al seleccionar
    document
      .querySelectorAll('input[name="tipo_traslado"]')
      .forEach((radio) => {
        radio.addEventListener("change", function (e) {
          estado.tipoTraslado = e.target.value;
          guardarEstado();
          aplicarRestriccionesVehiculos();
          actualizarBotonPaso1();
        });
      });

    // Modal de destinos
    elementos.btnAddDestino.addEventListener("click", abrirModalDestino);
    elementos.closeModalBtn.addEventListener("click", cerrarModalDestino);
    elementos.searchDestino.addEventListener("input", filtrarDestinos);

    // Crear destino nuevo desde el modal (componente nuevo-destino-form).
    // Delegación sobre la raíz del componente: no depende de ids concretos,
    // así el componente se puede montar con otro prefijo sin tocar este JS.
    if (elementos.nuevoDestino) {
      elementos.nuevoDestino.addEventListener("click", function (e) {
        const accion = e.target.closest("[data-rol]");
        if (!accion || !this.contains(accion)) return;
        if (accion.dataset.rol === "toggle") mostrarFormNuevoDestino();
        else if (accion.dataset.rol === "cancelar") ocultarFormNuevoDestino();
        else if (accion.dataset.rol === "guardar") crearDestino();
      });
    }

    // Checkbox volver al origen
    elementos.volverOrigen.addEventListener("change", function () {
      estado.volverOrigen = this.checked;
      guardarEstado();
    });

    // Vehículos
    if (elementos.vehiculosGrid) {
      elementos.vehiculosGrid.addEventListener("change", function (e) {
        if (e.target.name === "vehiculo") {
          estado.vehiculo = e.target.value;
          guardarEstado();
          actualizarBotonPaso6();
        }
      });
    }

    // Cerrar modal al hacer click fuera
    elementos.modalDestino.addEventListener("click", function (e) {
      if (e.target === this) {
        cerrarModalDestino();
      }
    });
  }

  /**
   * Vincular eventos de destinos
   */
  function bindDestinos() {
    // Delegación: cubre también las sugerencias inyectadas dinámicamente
    // al crear un destino nuevo desde el modal.
    elementos.destinosSuggestions.addEventListener("click", function (e) {
      const btn = e.target.closest(".destino-suggestion");
      if (!btn || !this.contains(btn)) return;
      agregarDestino(btn.dataset.id, btn.dataset.nombre);
    });
  }

  /**
   * Vincular combobox custom (input text + dropdown filtrado + select oculto)
   * para conductor y enfermero. Reemplaza al <datalist> que no filtraba
   * consistentemente entre navegadores.
   */
  function bindAutocompletes() {
    const escapeHtml = (s) =>
      String(s).replace(/[&<>"']/g, (ch) => ({
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      })[ch]);

    const highlight = (text, query) => {
      if (!query) return escapeHtml(text);
      const lower = text.toLowerCase();
      const q = query.toLowerCase();
      const idx = lower.indexOf(q);
      if (idx === -1) return escapeHtml(text);
      return (
        escapeHtml(text.slice(0, idx)) +
        "<mark>" +
        escapeHtml(text.slice(idx, idx + q.length)) +
        "</mark>" +
        escapeHtml(text.slice(idx + q.length))
      );
    };

    const wireCombobox = (inputEl, selectEl, dropdownEl, wrapperEl, onPick) => {
      if (!inputEl || !selectEl || !dropdownEl || !wrapperEl) return;

      // Cachear opciones del select oculto
      const allOptions = Array.from(selectEl.options)
        .filter((o) => o.value)
        .map((o) => ({
          ci: o.dataset.ci,
          nombre: o.dataset.nombre,
        }));

      const render = (query) => {
        const q = (query || "").toLowerCase().trim();
        const matches =
          q.length === 0
            ? allOptions
            : allOptions.filter(
                (o) =>
                  o.nombre.toLowerCase().includes(q) ||
                  String(o.ci).includes(q),
              );

        if (matches.length === 0) {
          dropdownEl.innerHTML =
            '<div class="autocomplete-empty">Sin resultados</div>';
          dropdownEl.hidden = false;
          return;
        }

        dropdownEl.innerHTML = matches
          .map(
            (o) =>
              `<button type="button" class="autocomplete-item" data-ci="${escapeHtml(o.ci)}">
                <span class="autocomplete-nombre">${highlight(o.nombre, q)}</span>
                <span class="autocomplete-ci">CI ${escapeHtml(o.ci)}</span>
              </button>`,
          )
          .join("");
        dropdownEl.hidden = false;
      };

      const close = () => {
        dropdownEl.hidden = true;
      };

      const selectOption = (ci) => {
        const opt = allOptions.find((o) => String(o.ci) === String(ci));
        if (!opt) return;
        inputEl.value = opt.nombre;
        selectEl.value = opt.ci;
        inputEl.classList.remove("is-invalid");
        close();
        if (typeof onPick === "function") onPick(opt.ci);
      };

      // Eventos
      inputEl.addEventListener("focus", () => render(inputEl.value));
      inputEl.addEventListener("input", () => {
        render(inputEl.value);
        // Quitar marca de error mientras se tipea (no marcar hasta perder foco)
        inputEl.classList.remove("is-invalid");
        // Resetear selección hasta que el usuario elija del dropdown
        selectEl.value = "";
      });

      // mousedown (no click) para que dispere antes que el blur del input
      dropdownEl.addEventListener("mousedown", (e) => {
        const btn = e.target.closest(".autocomplete-item");
        if (btn) {
          e.preventDefault();
          selectOption(btn.dataset.ci);
        }
      });

      inputEl.addEventListener("keydown", (e) => {
        if (e.key === "Escape") {
          close();
          return;
        }
        if (e.key === "Enter") {
          const firstItem = dropdownEl.querySelector(".autocomplete-item");
          if (firstItem && !dropdownEl.hidden) {
            e.preventDefault();
            selectOption(firstItem.dataset.ci);
          }
        }
      });

      // Validar al perder foco (solo si quedó texto que no matchea)
      inputEl.addEventListener("blur", () => {
        setTimeout(() => {
          const val = inputEl.value.trim();
          const opt = allOptions.find((o) => o.nombre === val);
          inputEl.classList.toggle("is-invalid", !!val && !opt);
          close();
        }, 150);
      });

      // Click fuera cierra
      document.addEventListener("click", (e) => {
        if (!wrapperEl.contains(e.target)) close();
      });
    };

    wireCombobox(
      elementos.conductorInput,
      elementos.conductorSelect,
      elementos.conductorDropdown,
      elementos.conductorWrapper,
      (ci) => {
        estado.conductor = ci;
        guardarEstado();
        actualizarBotonPaso5();
      },
    );

    wireCombobox(
      elementos.enfermeroInput,
      elementos.enfermeroSelect,
      elementos.enfermeroDropdown,
      elementos.enfermeroWrapper,
      (ci) => {
        estado.enfermero = ci;
        if (!ci) {
          estado.jerarquiaEnfermero = null;
          if (elementos.jerarquiaSelect) elementos.jerarquiaSelect.value = "";
        }
        guardarEstado();
        actualizarVisibilidadJerarquia();
      },
    );
  }

  /**
   * Ir a un paso específico
   */
  function irAPaso(paso) {
    estado.pasoActual = paso;
    guardarEstado();
    actualizarUI();

    // Scroll al inicio del card
    document
      .querySelector(".wizard-card")
      ?.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  /**
   * Actualizar la UI según el paso actual
   */
  function actualizarUI() {
    actualizarStepper();
    actualizarPasos();
    actualizarBotonPaso1();
    actualizarBotonPaso4();
    actualizarBotonPaso5();
    actualizarBotonPaso6();

    // Si estamos en el paso 7 (confirmación), actualizar resumen
    if (estado.pasoActual === 7) {
      actualizarResumen();
    }
  }

  /**
   * Muestra/oculta el campo Jerarquía según haya enfermero seleccionado.
   */
  function actualizarVisibilidadJerarquia() {
    if (!elementos.jerarquiaGroup) return;
    if (estado.enfermero) {
      elementos.jerarquiaGroup.style.display = "flex";
    } else {
      elementos.jerarquiaGroup.style.display = "none";
    }
  }

  /**
   * Actualizar el stepper visual
   */
  function actualizarStepper() {
    // pasoActual ya representa el paso real del usuario porque los
    // handlers de los botones ajustan el salto cuando el paso 2 está skipped.
    // El único caso en que difiere es si por alguna razón pasoActual === 2
    // con tipo distinto de paciente_alta (estado defensivo).
    const pasoVisual =
      estado.pasoActual === 2 &&
      estado.tipoTraslado &&
      estado.tipoTraslado !== "paciente_alta"
        ? 3
        : estado.pasoActual;

    elementos.pasos.forEach((paso, index) => {
      const numPaso = index + 1;
      paso.classList.remove("active", "completed", "skipped");

      // Marcar paso 2 como skipped si el tipo no es paciente_alta
      if (
        numPaso === 2 &&
        estado.tipoTraslado &&
        estado.tipoTraslado !== "paciente_alta"
      ) {
        paso.classList.add("skipped");
        return;
      }

      if (numPaso < pasoVisual) {
        paso.classList.add("completed");
      } else if (numPaso === pasoVisual) {
        paso.classList.add("active");
      }
    });

    // Líneas: la línea N (entre paso N y N+1) está completa cuando
    // el usuario ya pasó más allá del paso N+1 (pasoVisual > N+1).
    elementos.lineas.forEach((linea, index) => {
      const numLinea = index + 1;
      linea.classList.toggle("completed", pasoVisual > numLinea + 1);
    });
  }

  /**
   * Actualizar la visibilidad de los pasos del formulario
   */
  function actualizarPasos() {
    const pasoVisual =
      estado.pasoActual === 2 &&
      estado.tipoTraslado &&
      estado.tipoTraslado !== "paciente_alta"
        ? 3
        : estado.pasoActual;

    elementos.steps.forEach((step, index) => {
      const numPaso = index + 1;
      step.classList.toggle("active", numPaso === pasoVisual);
    });
  }

  /**
   * Actualizar botón del paso 1
   */
  function actualizarBotonPaso1() {
    const btn = document.getElementById("btn-step-1");
    if (btn) {
      btn.disabled = !estado.tipoTraslado;
    }
  }

  /**
   * Actualizar botón del paso 4 (Destinos - requiere al menos 1)
   */
  function actualizarBotonPaso4() {
    const btn = document.getElementById("btn-step-4");
    if (btn) {
      btn.disabled = estado.destinos.length === 0;
    }
  }

  /**
   * Actualizar botón del paso 5 (Personal - requiere conductor)
   */
  function actualizarBotonPaso5() {
    const btn = document.getElementById("btn-step-5");
    if (btn) {
      btn.disabled = !estado.conductor;
    }
  }

  /**
   * Actualizar botón del paso 6 (Vehículo)
   */
  function actualizarBotonPaso6() {
    const btn = document.getElementById("btn-step-6");
    if (btn) {
      btn.disabled = !estado.vehiculo;
    }
  }

  /**
   * Mostrar/ocultar vehículos según el tipo de traslado.
   * Camión SOLO visible cuando el tipo es "equipamiento".
   */
  function aplicarRestriccionesVehiculos() {
    if (!elementos.vehiculosGrid) return;

    const esEquipamiento = estado.tipoTraslado === "equipamiento";

    elementos.vehiculosGrid
      .querySelectorAll(".vehiculo-card")
      .forEach((card) => {
        const esCamion = card.dataset.restringido === "true";
        const input = card.querySelector('input[type="radio"]');
        if (esCamion && !esEquipamiento) {
          card.style.display = "none";
          if (input && estado.vehiculo === input.value) {
            estado.vehiculo = null;
            guardarEstado();
            actualizarBotonPaso6();
          }
        } else {
          card.style.display = "";
        }
      });
  }

  /**
   * Abrir modal de destino
   */
  function abrirModalDestino() {
    elementos.modalDestino.classList.add("active");
    elementos.searchDestino.value = "";
    ocultarFormNuevoDestino();
    filtrarDestinos();
    elementos.searchDestino.focus();
  }

  /**
   * Cerrar modal de destino
   */
  function cerrarModalDestino() {
    elementos.modalDestino.classList.remove("active");
    ocultarFormNuevoDestino();
  }

  /**
   * Resuelve un nodo interno del componente nuevo-destino-form por su data-rol.
   */
  function nd(rol) {
    return elementos.nuevoDestino
      ? elementos.nuevoDestino.querySelector(`[data-rol="${rol}"]`)
      : null;
  }

  /**
   * Mostrar / ocultar el formulario de creación de destino
   */
  function mostrarFormNuevoDestino() {
    if (!elementos.nuevoDestino) return;
    nd("form").hidden = false;
    nd("toggle").hidden = true;
    // Prellenar con lo que el usuario venía buscando
    const nombre = nd("nombre");
    if (!nombre.value) nombre.value = elementos.searchDestino.value.trim();
    nombre.focus();
    nd("form").scrollIntoView({ block: "nearest" });
  }

  function ocultarFormNuevoDestino() {
    if (!elementos.nuevoDestino) return;
    nd("form").hidden = true;
    nd("toggle").hidden = false;
    nd("nombre").value = "";
    nd("direccion").value = "";
    mostrarErrorNuevoDestino("");
    nd("guardar").disabled = false;
  }

  function mostrarErrorNuevoDestino(mensaje) {
    const error = nd("error");
    if (!error) return;
    error.textContent = mensaje;
    error.hidden = !mensaje;
  }

  /**
   * Crear una ubicación nueva en la base y agregarla como destino.
   */
  async function crearDestino() {
    const nombre = nd("nombre").value.trim();
    const direccion = nd("direccion").value.trim();
    const btnGuardar = nd("guardar");

    if (!nombre || !direccion) {
      mostrarErrorNuevoDestino("Completá el nombre y la dirección.");
      return;
    }

    mostrarErrorNuevoDestino("");
    btnGuardar.disabled = true;

    try {
      const csrf = elementos.csrfToken ? elementos.csrfToken.value : "";
      const respuesta = await fetch("/api/ubicaciones", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify({ nombre, direccion, _csrf: csrf }),
      });
      const resultado = await respuesta.json();

      if (!respuesta.ok || !resultado.success) {
        mostrarErrorNuevoDestino(
          resultado.message || "No se pudo crear el destino.",
        );
        btnGuardar.disabled = false;
        return;
      }

      const ubicacion = resultado.ubicacion;
      const id = String(ubicacion.id);

      // Inyectar la sugerencia para que quede disponible en el modal
      if (!elementos.destinosSuggestions.querySelector(`[data-id="${id}"]`)) {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "destino-suggestion";
        btn.dataset.id = id;
        btn.dataset.nombre = ubicacion.nombre_lugar;
        btn.innerHTML = `
          <div class="suggestion-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
            </svg>
          </div>
          <div class="suggestion-info">
            <span class="suggestion-nombre"></span>
            <span class="suggestion-direccion"></span>
          </div>`;
        btn.querySelector(".suggestion-nombre").textContent =
          ubicacion.nombre_lugar;
        btn.querySelector(".suggestion-direccion").textContent =
          ubicacion.direccion || "";
        elementos.destinosSuggestions.appendChild(btn);
      }

      agregarDestino(id, ubicacion.nombre_lugar);
    } catch (error) {
      mostrarErrorNuevoDestino("Error de conexión. Intentá de nuevo.");
      btnGuardar.disabled = false;
    }
  }

  /**
   * Filtrar destinos en el modal
   */
  function filtrarDestinos() {
    const busqueda = elementos.searchDestino.value.toLowerCase();
    const suggestions = elementos.destinosSuggestions.querySelectorAll(
      ".destino-suggestion",
    );

    let visibles = 0;
    suggestions.forEach((suggestion) => {
      const nombre = suggestion.dataset.nombre.toLowerCase();
      if (nombre.includes(busqueda)) {
        suggestion.style.display = "flex";
        visibles++;
      } else {
        suggestion.style.display = "none";
      }
    });

    if (elementos.destinosEmpty) {
      elementos.destinosEmpty.textContent = busqueda
        ? "No se encontraron destinos con ese nombre."
        : "No hay destinos cargados todavía.";
      elementos.destinosEmpty.hidden = visibles > 0;
    }
  }

  /**
   * Agregar un destino a la lista
   */
  function agregarDestino(id, nombre) {
    id = String(id);
    // Verificar si ya existe
    if (estado.destinos.some((d) => String(d.id) === id)) {
      alert("Este destino ya fue agregado.");
      return;
    }

    estado.destinos.push({ id, nombre });
    guardarEstado();
    renderizarDestinos();
    cerrarModalDestino();
    actualizarBotonPaso4();
  }

  /**
   * Renderizar la lista de destinos
   */
  function renderizarDestinos() {
    if (estado.destinos.length === 0) {
      elementos.destinosList.innerHTML = "";
      return;
    }

    elementos.destinosList.innerHTML = estado.destinos
      .map(
        (destino, index) => `
            <div class="destino-item" data-id="${destino.id}">
                <div class="destino-numero">${index + 1}</div>
                <div class="destino-icon">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <div class="destino-info">
                    <span class="destino-nombre">${destino.nombre}</span>
                    <span class="destino-orden">Destino ${index + 1}</span>
                </div>
                <button type="button" class="btn-remove-destino" data-id="${destino.id}">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        `,
      )
      .join("");

    // Vincular eventos de eliminar
    elementos.destinosList
      .querySelectorAll(".btn-remove-destino")
      .forEach((btn) => {
        btn.addEventListener("click", function () {
          eliminarDestino(this.dataset.id);
        });
      });
  }

  /**
   * Eliminar un destino de la lista
   */
  function eliminarDestino(id) {
    estado.destinos = estado.destinos.filter((d) => String(d.id) !== String(id));
    guardarEstado();
    renderizarDestinos();
    actualizarBotonPaso4();
  }

  /**
   * Actualizar el resumen de confirmación
   */
  function actualizarResumen() {
    // Tipo de traslado
    elementos.resumenTipo.textContent = nombresTipo[estado.tipoTraslado] || "-";

    // Datos clínicos
    if (elementos.resumenEstadoCriticoRow) {
      if (estado.estadoCritico) {
        elementos.resumenEstadoCriticoRow.style.display = "flex";
        if (elementos.resumenEstadoCritico) {
          elementos.resumenEstadoCritico.textContent = "Sí";
        }
      } else {
        elementos.resumenEstadoCriticoRow.style.display = "none";
      }
    }
    if (elementos.resumenCamillaRow) {
      if (estado.requiereCamilla) {
        elementos.resumenCamillaRow.style.display = "flex";
        if (elementos.resumenCamilla) {
          elementos.resumenCamilla.textContent = "Sí";
        }
      } else {
        elementos.resumenCamillaRow.style.display = "none";
      }
    }
    if (elementos.resumenDiagnosticoRow) {
      if (estado.tipoDiagnostico) {
        elementos.resumenDiagnosticoRow.style.display = "flex";
        if (elementos.resumenDiagnostico) {
          elementos.resumenDiagnostico.textContent =
            diagnosticoLabels[estado.tipoDiagnostico] || estado.tipoDiagnostico;
        }
      } else {
        elementos.resumenDiagnosticoRow.style.display = "none";
      }
    }

    // Destinos
    if (estado.destinos.length > 0) {
      const destinosTexto = estado.destinos.map((d) => d.nombre).join(" → ");
      elementos.resumenDestinos.textContent = destinosTexto;
    } else {
      elementos.resumenDestinos.textContent = "-";
    }

    // Conductor
    if (estado.conductor && elementos.conductorSelect) {
      const conductorOption = elementos.conductorSelect.querySelector(
        `option[value="${estado.conductor}"]`,
      );
      elementos.resumenConductor.textContent = conductorOption
        ? conductorOption.textContent
        : "-";
    } else {
      elementos.resumenConductor.textContent = "-";
    }

    // Enfermero
    if (estado.enfermero && elementos.enfermeroSelect) {
      const enfermeroOption = elementos.enfermeroSelect.querySelector(
        `option[value="${estado.enfermero}"]`,
      );
      elementos.resumenEnfermero.textContent = enfermeroOption
        ? enfermeroOption.textContent
        : "-";
      elementos.resumenEnfermeroRow.style.display = "flex";
    } else {
      elementos.resumenEnfermero.textContent = "-";
      elementos.resumenEnfermeroRow.style.display = "none";
    }

    // Jerarquía de enfermería
    if (elementos.resumenJerarquiaRow) {
      if (estado.enfermero && estado.jerarquiaEnfermero) {
        elementos.resumenJerarquiaRow.style.display = "flex";
        if (elementos.resumenJerarquia) {
          elementos.resumenJerarquia.textContent =
            jerarquiaLabels[estado.jerarquiaEnfermero] || estado.jerarquiaEnfermero;
        }
      } else {
        elementos.resumenJerarquiaRow.style.display = "none";
      }
    }

    // Vehículo
    if (estado.vehiculo) {
      const vehiculoSelected = document.querySelector(
        `input[name="vehiculo"][value="${estado.vehiculo}"]`,
      );
      if (vehiculoSelected) {
        const card = vehiculoSelected.closest(".vehiculo-card");
        const nombreVehiculo =
          card.querySelector(".vehiculo-nombre")?.textContent || "-";
        elementos.resumenVehiculo.textContent = nombreVehiculo;
      }
    } else {
      elementos.resumenVehiculo.textContent = "-";
    }

    // Volver al origen
    elementos.resumenVueltaRow.style.display = estado.volverOrigen
      ? "flex"
      : "none";
  }

  /**
   * Confirmar el traslado: envía los datos al backend.
   */
  async function confirmarTraslado() {
    const csrf = elementos.csrfToken ? elementos.csrfToken.value : "";

    // Validación cliente: camión solo para equipamiento
    const vehiculoRadio = document.querySelector(
      `input[name="vehiculo"][value="${estado.vehiculo}"]`,
    );
    const vehiculoCard = vehiculoRadio ? vehiculoRadio.closest(".vehiculo-card") : null;
    const esCamion = vehiculoCard && vehiculoCard.dataset.restringido === "true";
    if (esCamion && estado.tipoTraslado !== "equipamiento") {
      alert("El camión solo está disponible para traslados de equipamiento.");
      return;
    }

    const payload = {
      _csrf: csrf,
      tipo: estado.tipoTraslado,
      estadoCritico: !!estado.estadoCritico,
      requiereCamilla: !!estado.requiereCamilla,
      tipoDiagnostico: estado.tipoDiagnostico || null,
      id_ubicacion_origen: window.ORIGEN_ID || 1,
      destinos: estado.destinos,
      ci_chofer: parseInt(estado.conductor, 10),
      ci_enfermero: estado.enfermero ? parseInt(estado.enfermero, 10) : null,
      jerarquia_enfermero: estado.jerarquiaEnfermero || null,
      id_vehiculo: parseInt(estado.vehiculo, 10),
      volver_origen: !!estado.volverOrigen,
    };

    if (!elementos.btnConfirmar) return;
    elementos.btnConfirmar.disabled = true;
    const textoOriginal = elementos.btnConfirmar.innerHTML;
    elementos.btnConfirmar.textContent = "Enviando...";

    try {
      const r = await fetch("/api/traslados", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-CSRF-Token": csrf,
        },
        body: JSON.stringify(payload),
      });
      const json = await r.json().catch(() => ({}));
      if (!r.ok || !json.success) {
        throw new Error(json.message || `Error HTTP ${r.status}`);
      }

      limpiarEstado();
      window.location.href = "/dashboard/traslados";
    } catch (e) {
      alert("Error al crear traslado: " + e.message);
      elementos.btnConfirmar.disabled = false;
      elementos.btnConfirmar.innerHTML = textoOriginal;
    }
  }

  // Inicializar
  init();
});
