(function () {
  "use strict";

  const MAX_FILE_SIZE = 10 * 1024 * 1024;
  const ALLOWED_EXTENSIONS = ["pdf", "doc", "docx", "odt"];

  function initUploadModal() {
    const form = document.getElementById("upload-document-form");
    if (!form || form.dataset.initialized === "true") return;
    form.dataset.initialized = "true";

    const fileInput = document.getElementById("document-file");
    const dropzone = document.getElementById("upload-dropzone");
    const fileName = document.getElementById("upload-file-name");
    const fileError = document.getElementById("upload-file-error");
    const select = document.getElementById("document-category");
    const selectWrapper = document.getElementById("category-select-wrapper");
    const createWrapper = document.getElementById("category-create-wrapper");
    const addCategoryButton = document.getElementById("add-category-button");
    const cancelCategoryButton = document.getElementById("cancel-category-button");
    const saveCategoryButton = document.getElementById("save-category-button");
    const newCategoryName = document.getElementById("new-category-name");
    const toast = document.getElementById("upload-toast");
    const submitButton = document.getElementById("upload-submit-button");
    const csrfToken = form.querySelector('input[name="csrf_token"]')?.value || "";
    let toastTimer;

    function mostrarToast(mensaje, tipo) {
      if (!toast) return;
      window.clearTimeout(toastTimer);
      toast.textContent = mensaje;
      toast.className = `upload-toast upload-toast--${tipo}`;
      toast.hidden = false;
      toastTimer = window.setTimeout(() => {
        toast.hidden = true;
      }, 4500);
    }

    function mostrarError(mensaje) {
      if (!fileError) return;
      fileError.textContent = mensaje;
      fileError.hidden = !mensaje;
      if (dropzone) dropzone.classList.toggle("upload-dropzone--error", Boolean(mensaje));
    }

    function obtenerExtension(nombre) {
      const partes = nombre.toLowerCase().split(".");
      return partes.length > 1 ? partes.pop() : "";
    }

    function validarArchivo(file) {
      if (!file) return "Seleccioná un archivo para cargar.";

      const extension = obtenerExtension(file.name);
      if (!ALLOWED_EXTENSIONS.includes(extension)) {
        return "El archivo debe ser PDF, DOC, DOCX u ODT.";
      }

      if (file.size > MAX_FILE_SIZE) {
        return "El archivo no puede superar los 10 MB.";
      }

      return "";
    }

    function asignarArchivo(file) {
      const error = validarArchivo(file);
      if (error) {
        fileInput.value = "";
        if (fileName) fileName.textContent = "Arrastrá un archivo aquí o hacé clic para seleccionar";
        mostrarError(error);
        return;
      }

      // Conserva el archivo soltado dentro del input que viajará en el formulario.
      if (window.DataTransfer) {
        const transferencia = new DataTransfer();
        transferencia.items.add(file);
        fileInput.files = transferencia.files;
      }

      if (fileName) fileName.textContent = `${file.name} (${formatearTamano(file.size)})`;
      mostrarError("");
      if (dropzone) dropzone.classList.add("upload-dropzone--selected");
    }

    function formatearTamano(bytes) {
      if (bytes < 1024) return `${bytes} B`;
      if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
      return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
    }

    function mostrarCreacionCategoria() {
      if (!selectWrapper || !createWrapper) return;
      selectWrapper.hidden = true;
      createWrapper.hidden = false;
      newCategoryName?.focus();
    }

    function cancelarCreacionCategoria() {
      if (!selectWrapper || !createWrapper) return;
      selectWrapper.hidden = false;
      createWrapper.hidden = true;
      if (newCategoryName) newCategoryName.value = "";
    }

    async function guardarCategoria() {
      const nombre = newCategoryName?.value.trim() || "";
      if (!nombre) {
        mostrarToast("Escribí un nombre para la categoría.", "error");
        newCategoryName?.focus();
        return;
      }

      if (nombre.length > 100) {
        mostrarToast("La categoría no puede superar los 100 caracteres.", "error");
        return;
      }

      if (saveCategoryButton) saveCategoryButton.disabled = true;

      try {
        const respuesta = await fetch("/dashboard/documentos/categorias", {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({
            nombre_categoria: nombre,
            csrf_token: csrfToken,
          }),
        });
        const datos = await respuesta.json();

        if (!respuesta.ok || !datos.success) {
          throw new Error(datos.message || "No se pudo guardar la categoría.");
        }

        const categoria = datos.data;
        const opcionExistente = Array.from(select.options).find(
          (opcion) => opcion.value === String(categoria.id)
        );
        if (!opcionExistente) {
          select.add(new Option(categoria.nombre_categoria, categoria.id));
        }
        select.disabled = false;
        select.value = String(categoria.id);
        cancelarCreacionCategoria();
        mostrarToast("Categoría creada correctamente.", "success");
      } catch (error) {
        mostrarToast(error.message || "No se pudo guardar la categoría.", "error");
      } finally {
        if (saveCategoryButton) saveCategoryButton.disabled = false;
      }
    }

    fileInput?.addEventListener("change", () => {
      asignarArchivo(fileInput.files?.[0]);
    });

    ["dragenter", "dragover"].forEach((evento) => {
      dropzone?.addEventListener(evento, (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.add("upload-dropzone--dragging");
      });
    });

    ["dragleave", "drop"].forEach((evento) => {
      dropzone?.addEventListener(evento, (event) => {
        event.preventDefault();
        event.stopPropagation();
        dropzone.classList.remove("upload-dropzone--dragging");
      });
    });

    dropzone?.addEventListener("drop", (event) => {
      asignarArchivo(event.dataTransfer?.files?.[0]);
    });

    addCategoryButton?.addEventListener("click", mostrarCreacionCategoria);
    cancelCategoryButton?.addEventListener("click", cancelarCreacionCategoria);
    saveCategoryButton?.addEventListener("click", guardarCategoria);
    newCategoryName?.addEventListener("keydown", (event) => {
      if (event.key === "Enter") {
        event.preventDefault();
        guardarCategoria();
      }
    });

    form.addEventListener("submit", (event) => {
      const error = validarArchivo(fileInput?.files?.[0]);
      if (error || !select?.value) {
        event.preventDefault();
        mostrarError(error || "Seleccioná una categoría.");
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = "Subiendo…";
      }
    });

    window.resetUploadModal = function () {
      const categoriaSeleccionada = select?.value || "";
      form.reset();
      if (select && categoriaSeleccionada) select.value = categoriaSeleccionada;
      cancelarCreacionCategoria();
      mostrarError("");
      if (fileName) fileName.textContent = "Arrastrá un archivo aquí o hacé clic para seleccionar";
      dropzone?.classList.remove("upload-dropzone--selected", "upload-dropzone--error");
      if (toast) toast.hidden = true;
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = "Subir Documento";
      }
    };
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initUploadModal);
  } else {
    initUploadModal();
  }
})();
