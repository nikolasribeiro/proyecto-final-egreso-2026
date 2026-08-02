/**
 * Encuestas - script del módulo de encuestas cuantitativas.
 *
 * El cambio dinámico de plantilla se maneja server-side via GET (?plantilla=...)
 * al cambiar el <select>. Este script se enfoca en:
 *   - Validación cliente (4 preguntas respondidas con valor 1..10)
 *   - UX al hacer click en los radios (efecto visual de selección)
 */

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("encuesta-form");
  if (!form) return;

  form.addEventListener("submit", function (e) {
    const fieldsets = form.querySelectorAll(".encuesta-pregunta");
    const errores = [];

    fieldsets.forEach((fs) => {
      const preguntaId = fs.dataset.preguntaId;
      const nombreRadio = "p_" + preguntaId;
      const respondida = !!form.querySelector(
        `input[name="${nombreRadio}"]:checked`,
      );
      if (!respondida) {
        const titulo = fs.querySelector(".encuesta-pregunta-titulo")?.textContent?.trim() || preguntaId;
        errores.push(`"${titulo}"`);
        fs.classList.add("encuesta-pregunta-error");
      } else {
        fs.classList.remove("encuesta-pregunta-error");
      }
    });

    if (errores.length > 0) {
      e.preventDefault();
      alert(
        "Por favor, responda todas las preguntas antes de enviar. Faltan: " +
          errores.join(", "),
      );
    }
  });

  // Efecto visual al seleccionar un radio
  form.querySelectorAll(".encuesta-escala").forEach((escala) => {
    escala.addEventListener("change", function () {
      const labels = escala.querySelectorAll(".encuesta-radio-label");
      labels.forEach((l) => l.classList.remove("is-selected"));
      const checked = escala.querySelector("input[type=radio]:checked + label");
      if (checked) checked.classList.add("is-selected");
    });
  });
});
