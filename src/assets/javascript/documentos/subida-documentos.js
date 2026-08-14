/**
 * subida-documentos.js — Drag & drop + POST al endpoint /api/documentos (#116).
 *
 * Patrón:
 *   - IIFE con "use strict" para no contaminar el scope global.
 *   - CSRF: header X-CSRF-Token + body csrf_token (doble, como el resto del
 *     proyecto). El token se obtiene del meta tag <meta name="csrf-token">
 *     que emite la plantilla admin.php.
 *   - Early return si los elementos del modal no existen en el DOM (la
 *     página puede no incluir el modal).
 *   - Sin dependencias externas (vanilla JS).
 */
(function () {
  "use strict";

  document.addEventListener("DOMContentLoaded", () => {
    const dropZone = document.getElementById("drop-zone");
    const inputArchivo = document.getElementById("inputDocumento");
    const titleText = document.getElementById("drop-zone-title");
    const subText = document.getElementById("drop-zone-text");
    const btnSubir = document.getElementById("btnSubirDocumento");
    const feedbackBox = document.getElementById("modalFeedback");
    const selectCategoria = document.getElementById("selectCategoriaModal");

    // Salir silenciosamente si el modal no está presente en el DOM
    if (!btnSubir || !inputArchivo || !dropZone) {
      return;
    }

    // ==========================================
    // DRAG & DROP
    // ==========================================

    ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
      dropZone.addEventListener(
        eventName,
        (e) => {
          e.preventDefault();
          e.stopPropagation();
        },
        false,
      );
    });

    ["dragenter", "dragover"].forEach((eventName) => {
      dropZone.addEventListener(
        eventName,
        () => {
          dropZone.style.borderColor = "#0056b3";
        },
        false,
      );
    });

    ["dragleave", "drop"].forEach((eventName) => {
      dropZone.addEventListener(
        eventName,
        () => {
          dropZone.style.borderColor = "";
        },
        false,
      );
    });

    dropZone.addEventListener("drop", (e) => {
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        inputArchivo.files = files;
        actualizarUIArchivo(files[0]);
      }
    });

    inputArchivo.addEventListener("change", () => {
      if (inputArchivo.files.length > 0) {
        actualizarUIArchivo(inputArchivo.files[0]);
      }
    });

    function actualizarUIArchivo(file) {
      titleText.textContent = file.name;
      subText.textContent = `(${(file.size / (1024 * 1024)).toFixed(2)} MB)`;
    }

    // ==========================================
    // SUBIDA AL SERVIDOR
    // ==========================================

    btnSubir.addEventListener("click", async (e) => {
      e.preventDefault();

      if (!inputArchivo.files[0]) {
        mostrarFeedback("Por favor, selecciona un archivo PDF.", "error");
        return;
      }

      const formData = new FormData();
      formData.append("documento", inputArchivo.files[0]);

      if (selectCategoria && selectCategoria.value) {
        formData.append("id_categoria", selectCategoria.value);
      }

      const csrfToken =
        document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute("content") || "";
      formData.append("csrf_token", csrfToken);

      btnSubir.disabled = true;
      btnSubir.textContent = "Subiendo...";

      try {
        const response = await fetch("/api/documentos", {
          method: "POST",
          credentials: "same-origin", // Vital para mantener la sesión PHP
          headers: {
            "X-CSRF-Token": csrfToken,
          },
          body: formData,
        });

        if (!response.ok) {
          throw new Error("Error al procesar la subida.");
        } else {
          mostrarFeedback("Documento subido con éxito.", "exito");
          setTimeout(() => window.location.reload(), 1200);
        }
      } catch (err) {
        console.error(err);
        mostrarFeedback(err.message, "error");
      } finally {
        btnSubir.disabled = false;
        btnSubir.textContent = "Subir Documento";
      }
    });

    // ==========================================
    // FEEDBACK
    // ==========================================

    function mostrarFeedback(msg, tipo) {
      if (!feedbackBox) return;
      feedbackBox.textContent = msg;
      feedbackBox.style.display = "block";
      feedbackBox.style.padding = "0.75rem";
      feedbackBox.style.borderRadius = "4px";
      feedbackBox.style.color = tipo === "exito" ? "#155724" : "#721c24";
      feedbackBox.style.backgroundColor =
        tipo === "exito" ? "#d4edda" : "#f8d7da";
      feedbackBox.style.borderColor = tipo === "exito" ? "#c3e6cb" : "#f5c6cb";
    }
  });
})();
