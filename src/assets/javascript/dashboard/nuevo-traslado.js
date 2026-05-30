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
    volverOrigen: document.getElementById("volver-origen"),
    vehiculosGrid: document.getElementById("vehiculos-grid"),
    resumenTipo: document.getElementById("resumen-tipo"),
    resumenDestinos: document.getElementById("resumen-destinos"),
    resumenConductor: document.getElementById("resumen-conductor"),
    resumenEnfermeroRow: document.getElementById("resumen-enfermero-row"),
    resumenEnfermero: document.getElementById("resumen-enfermero"),
    resumenVehiculo: document.getElementById("resumen-vehiculo"),
    resumenVueltaRow: document.getElementById("resumen-vuelta-row"),
    btnSolicitarSame: document.getElementById("btn-solicitar-same"),
    conductorSelect: document.getElementById("conductor"),
    enfermeroSelect: document.getElementById("enfermero"),
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
      destinos: [],
      conductor: null,
      enfermero: null,
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

    // Restaurar conductor y enfermero
    if (elementos.conductorSelect && estado.conductor) {
      elementos.conductorSelect.value = estado.conductor;
    }
    if (elementos.enfermeroSelect && estado.enfermero) {
      elementos.enfermeroSelect.value = estado.enfermero;
    }

    // Actualizar botones según estado
    actualizarBotonPaso1();
    actualizarBotonPaso3();
    actualizarBotonPaso4();
    actualizarBotonPaso5();
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

    // Navegación entre pasos
    document
      .getElementById("btn-step-1")
      ?.addEventListener("click", () => irAPaso(2));
    document
      .getElementById("btn-back-2")
      ?.addEventListener("click", () => irAPaso(1));
    document
      .getElementById("btn-step-2")
      ?.addEventListener("click", () => irAPaso(3));
    document
      .getElementById("btn-back-3")
      ?.addEventListener("click", () => irAPaso(2));
    document
      .getElementById("btn-step-3")
      ?.addEventListener("click", () => irAPaso(4));
    document
      .getElementById("btn-back-4")
      ?.addEventListener("click", () => irAPaso(3));
    document
      .getElementById("btn-step-4")
      ?.addEventListener("click", () => irAPaso(5));
    document
      .getElementById("btn-back-5")
      ?.addEventListener("click", () => irAPaso(4));
    document
      .getElementById("btn-step-5")
      ?.addEventListener("click", () => irAPaso(6));
    document
      .getElementById("btn-back-6")
      ?.addEventListener("click", () => irAPaso(5));
    elementos.btnConfirmar.addEventListener("click", confirmarTraslado);

    // Conductor y Enfermero
    if (elementos.conductorSelect) {
      elementos.conductorSelect.addEventListener("change", function () {
        estado.conductor = this.value || null;
        guardarEstado();
        actualizarBotonPaso4();
      });
    }

    if (elementos.enfermeroSelect) {
      elementos.enfermeroSelect.addEventListener("change", function () {
        estado.enfermero = this.value || null;
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
          actualizarBotonPaso5();
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
    elementos.destinosSuggestions
      .querySelectorAll(".destino-suggestion")
      .forEach((btn) => {
        btn.addEventListener("click", function () {
          const id = this.dataset.id;
          const nombre = this.dataset.nombre;
          agregarDestino(id, nombre);
        });
      });
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
    actualizarBotonPaso3();
    actualizarBotonPaso4();
    actualizarBotonPaso5();

    // Si estamos en el paso 6 (confirmación), actualizar resumen
    if (estado.pasoActual === 6) {
      actualizarResumen();
    }
  }

  /**
   * Actualizar el stepper visual
   */
  function actualizarStepper() {
    elementos.pasos.forEach((paso, index) => {
      const numPaso = index + 1;
      paso.classList.remove("active", "completed");

      if (numPaso < estado.pasoActual) {
        paso.classList.add("completed");
      } else if (numPaso === estado.pasoActual) {
        paso.classList.add("active");
      }
    });

    elementos.lineas.forEach((linea, index) => {
      if (index < estado.pasoActual - 1) {
        linea.classList.add("completed");
      } else {
        linea.classList.remove("completed");
      }
    });
  }

  /**
   * Actualizar la visibilidad de los pasos del formulario
   */
  function actualizarPasos() {
    elementos.steps.forEach((step, index) => {
      const numPaso = index + 1;
      if (numPaso === estado.pasoActual) {
        step.classList.add("active");
      } else {
        step.classList.remove("active");
      }
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
   * Actualizar botón del paso 3
   */
  function actualizarBotonPaso3() {
    const btn = document.getElementById("btn-step-3");
    if (btn) {
      btn.disabled = estado.destinos.length === 0;
    }
  }

  /**
   * Actualizar botón del paso 4 (Personal - requiere conductor)
   */
  function actualizarBotonPaso4() {
    const btn = document.getElementById("btn-step-4");
    if (btn) {
      btn.disabled = !estado.conductor;
    }
  }

  /**
   * Actualizar botón del paso 5 (Vehículo)
   */
  function actualizarBotonPaso5() {
    const btn = document.getElementById("btn-step-5");
    if (btn) {
      btn.disabled = !estado.vehiculo;
    }
  }

  /**
   * Aplicar restricciones de vehículos según tipo de traslado
   */
  function aplicarRestriccionesVehiculos() {
    if (!elementos.vehiculosGrid) return;

    const cards = elementos.vehiculosGrid.querySelectorAll(".vehiculo-card");
    const esPacienteOBiologico =
      estado.tipoTraslado === "paciente_alta" ||
      estado.tipoTraslado === "biologico";

    cards.forEach((card) => {
      const esCamion = card.dataset.restringido === "true";
      const esDisponible = !card.classList.contains("no-disponible");
      const input = card.querySelector('input[type="radio"]');

      if (esCamion && esPacienteOBiologico && esDisponible) {
        // Deshabilitar completamente el camión para paciente/biológico
        card.classList.add("restringido");
        if (input) {
          input.disabled = true;
          input.checked = false;
          // Resetear vehículo si era el camión
          if (estado.vehiculo === input.value) {
            estado.vehiculo = null;
            guardarEstado();
            actualizarBotonPaso5();
          }
        }
      } else {
        card.classList.remove("restringido");
        if (input && !card.classList.contains("no-disponible")) {
          input.disabled = false;
        }
      }
    });
  }

  /**
   * Abrir modal de destino
   */
  function abrirModalDestino() {
    elementos.modalDestino.classList.add("active");
    elementos.searchDestino.value = "";
    filtrarDestinos();
    elementos.searchDestino.focus();
  }

  /**
   * Cerrar modal de destino
   */
  function cerrarModalDestino() {
    elementos.modalDestino.classList.remove("active");
  }

  /**
   * Filtrar destinos en el modal
   */
  function filtrarDestinos() {
    const busqueda = elementos.searchDestino.value.toLowerCase();
    const suggestions = elementos.destinosSuggestions.querySelectorAll(
      ".destino-suggestion",
    );

    suggestions.forEach((suggestion) => {
      const nombre = suggestion.dataset.nombre.toLowerCase();
      if (nombre.includes(busqueda)) {
        suggestion.style.display = "flex";
      } else {
        suggestion.style.display = "none";
      }
    });
  }

  /**
   * Agregar un destino a la lista
   */
  function agregarDestino(id, nombre) {
    // Verificar si ya existe
    if (estado.destinos.some((d) => d.id === id)) {
      alert("Este destino ya fue agregado.");
      return;
    }

    estado.destinos.push({ id, nombre });
    guardarEstado();
    renderizarDestinos();
    cerrarModalDestino();
    actualizarBotonPaso3();
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
    estado.destinos = estado.destinos.filter((d) => d.id !== id);
    guardarEstado();
    renderizarDestinos();
    actualizarBotonPaso3();
  }

  /**
   * Actualizar el resumen de confirmación
   */
  function actualizarResumen() {
    // Tipo de traslado
    elementos.resumenTipo.textContent = nombresTipo[estado.tipoTraslado] || "-";

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
   * Confirmar el traslado
   */
  function confirmarTraslado() {
    // Recopilar todos los datos
    const datosTraslado = {
      tipo: estado.tipoTraslado,
      origen: "Hospital de Clínicas",
      destinos: estado.destinos,
      conductor: estado.conductor,
      enfermero: estado.enfermero,
      vehiculo: estado.vehiculo,
      volverOrigen: estado.volverOrigen,
    };

    console.log("Datos del traslado:", datosTraslado);

    // En un entorno real, aquí se haría la llamada al servidor
    alert(
      "Traslado confirmado correctamente. En un entorno real, esto enviaría los datos al servidor.",
    );

    // Limpiar sessionStorage y redirigir
    limpiarEstado();
    // window.location.href = '/dashboard/traslados';
  }

  // Inicializar
  init();
});
