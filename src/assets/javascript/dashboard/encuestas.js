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
function mostrarQREncuesta(token, servicio, segmento) {
        const urlCompleta = window.location.origin + '/encuesta/' + token;
        const nombreEncuesta = servicio + ' - ' + segmento;
        
        // El título principal del documento (debajo del QR)
        document.getElementById('qr-encuesta-titulo').innerText = nombreEncuesta;
        document.getElementById('qr-encuesta-img').src = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' + encodeURIComponent(urlCompleta);
        
        // La etiqueta limpia de la URL (Formato: /encuesta/token...)
        document.getElementById('qr-encuesta-url-text').innerText = '/encuesta/' + token;
        
        // MAGIA DE INTEGRACIÓN (Mantiene botones imprimir/descargar funcionando)
        window.currentQRData = {
            documentName: 'Encuesta: ' + nombreEncuesta,
            documentId: token,
            documentUrl: urlCompleta
        };
        
        openModal('modal-qr-encuesta');
    }

    function agregarPregunta() {
    const contenedor = document.getElementById('contenedor-preguntas');
    const num = contenedor.querySelectorAll('input').length + 1;
    const div = document.createElement('div');
    div.style.cssText = 'display: flex; gap: 10px; margin-bottom: 10px;';
    div.innerHTML = `<input type="text" name="preguntas[]" class="form-control" placeholder="Pregunta ${num}" required>
                     <button type="button" class="btn btn-danger btn-small" onclick="this.parentElement.remove()">X</button>`;
    contenedor.appendChild(div);
    }

    function togglePreguntasDinamicas() {
        const selector = document.getElementById('selector-plantilla');
        const contenedor = document.getElementById('contenedor-preguntas-dinamicas');
        contenedor.style.display = (selector.value === 'personalizada') ? 'block' : 'none';
    }

    function verificarAnonimato() {
        const segmento = document.getElementById('segmento-dirigido').value;
        const checkbox = document.getElementById('es-anonima');
        const aviso = document.getElementById('aviso-anonimato');

        if (segmento === 'Pacientes') {
            checkbox.checked = true; // Lo marcamos automáticamente
            
            // Truco: prevenimos el clic para que no puedan desmarcarlo, 
            // pero NO usamos "disabled" para que el backend igual reciba el dato.
            checkbox.onclick = function(e) { e.preventDefault(); }; 
            aviso.style.display = 'block';
        } else {
            checkbox.checked = false; // Lo desmarcamos
            
            // Liberamos el clic por si quieren hacer una encuesta anónima a funcionarios
            checkbox.onclick = null; 
            aviso.style.display = 'none';
        }
    }

    function cambiarEncuestaInterna(select) {
        // Ocultar todos los formularios y el mensaje inicial
        document.querySelectorAll('.formulario-interno').forEach(form => form.style.display = 'none');
        document.getElementById('mensaje-seleccione-encuesta').style.display = 'none';
        
        if (select.value) {
            // Mostrar únicamente el formulario seleccionado
            document.getElementById('form-encuesta-' + select.value).style.display = 'block';
        } else {
            // Mostrar mensaje inicial si vuelven a la opción por defecto
            document.getElementById('mensaje-seleccione-encuesta').style.display = 'block';
        }
    }
