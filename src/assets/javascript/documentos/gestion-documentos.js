/**
 * gestion-documentos.js — Maneja los botones "Editar" y "Eliminar"
 * de la tabla de documentos (#152).
 *
 * Patrón:
 *   - IIFE con "use strict" para no contaminar scope global.
 *   - Event delegation sobre la tabla: un solo listener para todos los
 *     botones con data-doc-action.
 *   - CSRF: header X-CSRF-Token + body csrf_token.
 *   - Salir silenciosamente si los elementos del modal no están en el DOM.
 *   - Vanilla JS, sin dependencias.
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", () => {
    const tabla = document.querySelector(".documents-table");
    const btnGuardar = document.getElementById("btnGuardarDocumento");
    const btnConfirmarEliminar = document.getElementById("btnConfirmarEliminar");

    if (!tabla || !btnGuardar || !btnConfirmarEliminar) {
      return;
    }

    const csrfToken =
      document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") || "";

    // ==========================================
    // CLICK EN BOTONES DE LA TABLA
    // ==========================================

    tabla.addEventListener("click", (e) => {
      const boton = e.target.closest("button[data-doc-action]");
      if (!boton) return;

      const accion = boton.getAttribute("data-doc-action");
      if (accion === "edit") {
        abrirModalEdicion(boton);
      } else if (accion === "delete") {
        abrirModalEliminar(boton);
      }
    });

    // ==========================================
    // EDICIÓN
    // ==========================================

    function abrirModalEdicion(boton) {
      const id       = boton.getAttribute("data-doc-id") || "";
      const titulo   = boton.getAttribute("data-doc-titulo") || "";
      const catSlug  = boton.getAttribute("data-doc-categoria") || "";

      const inputId        = document.getElementById("editarDocumentoId");
      const inputTitulo    = document.getElementById("editarDocumentoTitulo");
      const selectCategoria = document.getElementById("editarDocumentoCategoria");
      const inputArchivo   = document.getElementById("editarDocumentoArchivo");
      const feedback       = document.getElementById("editarDocumentoFeedback");

      if (inputId) inputId.value = id;
      if (inputTitulo) inputTitulo.value = titulo;
      if (inputArchivo) inputArchivo.value = "";
      if (feedback) {
        feedback.style.display = "none";
        feedback.textContent = "";
      }

      // Pre-seleccionar la categoría por slug (los <option> tienen
      // data-slug). Si no la encontramos por slug, caemos al match
      // por el value (id_categoria que vino en data-doc-categoria-id).
      if (selectCategoria) {
        let encontrado = false;
        const options = selectCategoria.querySelectorAll("option");
        options.forEach((opt) => {
          if (opt.getAttribute("data-slug") === catSlug) {
            opt.selected = true;
            encontrado = true;
          }
        });
        if (!encontrado) {
          const fallbackId = boton.getAttribute("data-doc-categoria-id") || "";
          if (fallbackId) {
            const opt = selectCategoria.querySelector(
              `option[value="${CSS.escape(fallbackId)}"]`,
            );
            if (opt) opt.selected = true;
          }
        }
      }

      openModal("editar-documento-modal");
    }

    btnGuardar.addEventListener("click", async () => {
      const form        = document.getElementById("editarDocumentoForm");
      const inputId     = document.getElementById("editarDocumentoId");
      const inputTitulo = document.getElementById("editarDocumentoTitulo");
      const inputArchivo = document.getElementById("editarDocumentoArchivo");
      const feedback    = document.getElementById("editarDocumentoFeedback");

      if (!form || !inputId || !inputTitulo) return;

      const id     = inputId.value.trim();
      const titulo = inputTitulo.value.trim();

      if (!id) {
        mostrarFeedback(feedback, "Falta el ID del documento.", "error");
        return;
      }
      if (!titulo) {
        mostrarFeedback(feedback, "El título no puede estar vacío.", "error");
        return;
      }
      if (inputArchivo && inputArchivo.files.length > 0) {
        const file = inputArchivo.files[0];
        if (file.type !== "application/pdf") {
          mostrarFeedback(feedback, "El archivo debe ser un PDF.", "error");
          return;
        }
      }

      const formData = new FormData(form);
      // Si no se subió archivo, sacamos el campo para que el backend
      // sepa que no debe reemplazar el PDF existente.
      if (!inputArchivo || inputArchivo.files.length === 0) {
        formData.delete("documento");
      }

      btnGuardar.disabled = true;
      const textoOriginal = btnGuardar.textContent;
      btnGuardar.textContent = "Guardando...";

      try {
        const response = await fetch(`/api/documentos/${encodeURIComponent(id)}`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-CSRF-Token": csrfToken },
          body: formData,
        });

        const data = await safeJson(response);

        if (!response.ok || !data || data.exito === false) {
          throw new Error((data && data.mensaje) || "Error al actualizar el documento.");
        }

        mostrarFeedback(feedback, "Documento actualizado. Recargando...", "exito");
        setTimeout(() => window.location.reload(), 900);
      } catch (err) {
        console.error(err);
        mostrarFeedback(feedback, err.message, "error");
        btnGuardar.disabled = false;
        btnGuardar.textContent = textoOriginal;
      }
    });

    // ==========================================
    // ELIMINACIÓN (SOFT DELETE)
    // ==========================================

    function abrirModalEliminar(boton) {
      const id     = boton.getAttribute("data-doc-id") || "";
      const titulo = boton.getAttribute("data-doc-titulo") || "";

      const inputId     = document.getElementById("eliminarDocumentoId");
      const labelNombre = document.getElementById("eliminarDocumentoNombre");

      if (inputId) inputId.value = id;
      if (labelNombre) labelNombre.textContent = titulo || "(sin título)";

      openModal("eliminar-documento-modal");
    }

    btnConfirmarEliminar.addEventListener("click", async () => {
      const inputId   = document.getElementById("eliminarDocumentoId");
      const feedback  = document.getElementById("modalFeedback"); // reusamos el del upload
      if (!inputId) return;

      const id = inputId.value.trim();
      if (!id) return;

      btnConfirmarEliminar.disabled = true;
      const textoOriginal = btnConfirmarEliminar.textContent;
      btnConfirmarEliminar.textContent = "Eliminando...";

      const formData = new FormData();
      formData.append("csrf_token", csrfToken);

      try {
        const response = await fetch(`/api/documentos/${encodeURIComponent(id)}/eliminar`, {
          method: "POST",
          credentials: "same-origin",
          headers: { "X-CSRF-Token": csrfToken },
          body: formData,
        });

        const data = await safeJson(response);

        if (!response.ok || !data || data.exito === false) {
          throw new Error((data && data.mensaje) || "Error al eliminar el documento.");
        }

        // Cerrar el modal de eliminación y recargar para reflejar el
        // soft delete (la fila desaparece de la tabla).
        closeModal("eliminar-documento-modal");
        if (feedback) {
          mostrarFeedback(feedback, "Documento eliminado. Recargando...", "exito");
        }
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        console.error(err);
        alert(err.message);
        btnConfirmarEliminar.disabled = false;
        btnConfirmarEliminar.textContent = textoOriginal;
      }
    });

    // ==========================================
    // HELPERS
    // ==========================================

    function mostrarFeedback(el, msg, tipo) {
      if (!el) return;
      el.textContent = msg;
      el.style.display = "block";
      el.style.padding = "0.75rem";
      el.style.borderRadius = "4px";
      el.style.color = tipo === "exito" ? "#155724" : "#721c24";
      el.style.backgroundColor = tipo === "exito" ? "#d4edda" : "#f8d7da";
      el.style.borderColor = tipo === "exito" ? "#c3e6cb" : "#f5c6cb";
    }

    async function safeJson(response) {
      try {
        return await response.json();
      } catch (e) {
        return null;
      }
    }
  });
})();
