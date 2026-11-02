/**
 * limite-subida.js — Límite de tamaño de los PDFs que se pueden subir.
 *
 * ¿POR QUÉ EXISTE?
 * El límite real lo impone el servidor, en este orden:
 *
 *   1. nginx  (client_max_body_size) -> si el archivo excede el límite,
 *      nginx corta el POST con HTTP 413 ANTES de que llegue a PHP. Por eso
 *      la respuesta NO es JSON y el frontend tiene que manejar el 413 por
 *      su cuenta.
 *   2. PHP    (upload_max_filesize)   -> si el archivo entra pero excede,
 *      PHP lo descarta y `$_FILES` llega vacío.
 *
 * El número que se muestra al usuario sale del servidor: el backend lo pasa
 * en `data-max-bytes` (ver src/Nucleo/ayudantes.php -> tamano_maximo_subida()).
 * Así no hay un límite duplicado en el JS que pueda quedar desactualizado.
 *
 * Uso:
 *   const max = window.LimiteSubida.leerBytes(inputArchivo);
 *   if (window.LimiteSubida.excede(archivo, max)) {
 *     mostrarFeedback(window.LimiteSubida.mensajeExceso(max), "error");
 *   }
 */
(function () {
  "use strict";

  const MB = 1024 * 1024;

  /**
   * Lee el límite (en bytes) desde el atributo data-max-bytes del elemento.
   * @param {HTMLElement|null} elemento
   * @returns {number} Bytes; 0 si no hay límite informado.
   */
  function leerBytes(elemento) {
    if (!elemento || typeof elemento.getAttribute !== "function") {
      return 0;
    }
    const raw = elemento.getAttribute("data-max-bytes");
    const n = parseInt(raw || "0", 10);
    return Number.isFinite(n) && n > 0 ? n : 0;
  }

  /**
   * @param {number} bytes
   * @returns {string} "50 MB", "1.5 MB"...
   */
  function formatear(bytes) {
    if (!bytes || bytes <= 0) {
      return "";
    }
    const mb = bytes / MB;
    if (mb >= 1 && Number.isInteger(mb)) {
      return mb + " MB";
    }
    return mb.toFixed(1).replace(".", ",") + " MB";
  }

  /**
   * @param {File|Blob|null} archivo
   * @param {number} maxBytes
   * @returns {boolean}
   */
  function excede(archivo, maxBytes) {
    if (!archivo || !maxBytes) {
      return false;
    }
    return archivo.size > maxBytes;
  }

  /**
   * Mensaje para el usuario. Sin detalles técnicos (nocodes de estado, nombres
   * de servidor): solo qué pasó y qué hacer.
   *
   * @param {number} maxBytes
   * @returns {string}
   */
  function mensajeExceso(maxBytes) {
    const limite = formatear(maxBytes);
    if (!limite) {
      return "El archivo es demasiado grande. Reducí el tamaño del PDF e intentá de nuevo.";
    }
    return (
      "El archivo supera el tamaño máximo permitido (" +
      limite +
      "). Reducí el tamaño del PDF e intentá de nuevo."
    );
  }

  window.LimiteSubida = {
    leerBytes: leerBytes,
    formatear: formatear,
    excede: excede,
    mensajeExceso: mensajeExceso,
  };
})();
