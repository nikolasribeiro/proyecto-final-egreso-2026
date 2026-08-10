/**
 * permisos.js — toggle interactivo de la matriz de permisos (#130).
 *
 * Patrón:
 *   - Event delegation en .permisos-recursos-grid (un listener para
 *     todas las celdas, sin importar cuántos recursos haya).
 *   - Optimistic update: cambiamos visualmente la celda ANTES de ir al
 *     server. Si el server rechaza (4xx/5xx), revertimos.
 *   - CSRF: header X-CSRF-Token + body _csrf (doble, como el resto del
 *     proyecto). El token viene de data-permisos-csrf en el wrapper.
 *   - Debounce: si el usuario click-spamea una celda, las peticiones
 *     se cancelan con AbortController para evitar race conditions.
 *
 * Sin dependencias externas (vanilla JS).
 */

(function () {
    'use strict';

    const wrapper = document.querySelector('.permisos-recursos-grid');
    if (!wrapper) return;

    const csrfToken     = wrapper.dataset.permisosCsrf || '';
    const puedeEditar   = wrapper.dataset.permisosPuedeEditar === '1';
    if (!puedeEditar || !csrfToken) {
        // El usuario no puede editar — no enganchamos listeners.
        return;
    }

    const API_TOGGLE = '/api/permisos/toggle';
    const DEBOUNCE_MS = 250;

    // Mapa in-flight: claveCelda → AbortController, para cancelar la
    // request anterior si el usuario click-spamea la misma celda.
    const inFlight = new Map();

    /**
     * Devuelve la clave única para identificar una celda (id_rol +
     * recurso + acción). Si dos requests vuelan con la misma clave,
     * podemos cancelar la anterior.
     */
    function claveCelda(btn) {
        return [
            btn.dataset.permisoIdRol,
            btn.dataset.permisoRecurso,
            btn.dataset.permisoAccion,
        ].join('|');
    }

    /**
     * Cambia el aspecto visual del botón: on/off, glyph, aria, tooltip.
     * El `data-permiso-valor` queda como source-of-truth para el JS.
     */
    function pintarCelda(btn, permitido) {
        btn.classList.toggle('permisos-check-on', permitido);
        btn.classList.toggle('permisos-check-off', !permitido);
        btn.dataset.permisoValor = permitido ? '1' : '0';
        btn.setAttribute('aria-pressed', permitido ? 'true' : 'false');
        btn.textContent = permitido ? '✓' : '✗';
        btn.setAttribute(
            'title',
            permitido ? 'Permitido (click para denegar)' : 'Denegado (click para permitir)'
        );
    }

    /**
     * Marca la celda como "guardando": clase visual + deshabilita el
     * botón para evitar double-click. Se quita cuando termina la
     * request (con éxito o rollback).
     */
    function setPendiente(btn, pendiente) {
        btn.classList.toggle('permisos-check-pending', pendiente);
        btn.disabled = pendiente;
    }

    /**
     * Muestra un toast efímero arriba a la derecha. Reusa el patrón
     * simple de alert-info / alert-danger del proyecto.
     */
    function toast(msg, tipo) {
        const div = document.createElement('div');
        div.className = 'permisos-toast permisos-toast-' + (tipo || 'info');
        div.textContent = msg;
        document.body.appendChild(div);
        setTimeout(() => div.classList.add('permisos-toast-out'), 10);
        setTimeout(() => div.remove(), 2500);
    }

    /**
     * Click handler — delegado en el wrapper para no atar listeners
     * nodo-por-nodo.
     */
    wrapper.addEventListener('click', async function (ev) {
        const btn = ev.target.closest('button.permisos-check');
        if (!btn || !wrapper.contains(btn)) return;
        ev.preventDefault();

        const idRol    = parseInt(btn.dataset.permisoIdRol || '0', 10);
        const recurso  = btn.dataset.permisoRecurso || '';
        const accion   = btn.dataset.permisoAccion  || '';
        const valorActual = btn.dataset.permisoValor === '1';
        const valorNuevo  = !valorActual;

        if (!idRol || !recurso || !accion) {
            toast('Celda mal armada (faltan data-attributes).', 'danger');
            return;
        }

        // Debounce/cancel: si ya hay una request en vuelo para esta
        // misma celda, la cancelamos antes de lanzar la nueva.
        const key = claveCelda(btn);
        if (inFlight.has(key)) {
            try { inFlight.get(key).abort(); } catch (_) {}
            inFlight.delete(key);
        }

        // Optimistic update.
        pintarCelda(btn, valorNuevo);
        setPendiente(btn, true);

        const controller = new AbortController();
        inFlight.set(key, controller);

        // Debounce suave: si el usuario click-spamea, esperamos un
        // poco antes de ir al server (sin bloquear la UI).
        await new Promise(r => setTimeout(r, DEBOUNCE_MS));
        if (controller.signal.aborted) return;

        try {
            const resp = await fetch(API_TOGGLE, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({
                    _csrf: csrfToken,
                    id_rol: idRol,
                    recurso: recurso,
                    accion: accion,
                    permitido: valorNuevo,
                }),
                signal: controller.signal,
            });

            // Si la request fue abortada por un click más rápido, no
            // procesamos la respuesta.
            if (controller.signal.aborted) return;

            const data = await resp.json().catch(() => ({}));

            if (!resp.ok || data.success !== true) {
                // Rollback al valor anterior.
                pintarCelda(btn, valorActual);
                toast(data.message || 'No se pudo guardar el cambio.', 'danger');
                return;
            }

            // Éxito. Si el server devolvió un valor distinto al que
            // mandamos (caso raro de una edición concurrente),
            // respetamos la verdad del server.
            if (typeof data.despues === 'boolean') {
                pintarCelda(btn, data.despues);
            }
            toast('Permiso actualizado.', 'info');
        } catch (err) {
            if (err.name === 'AbortError') {
                // Cancelado por un click posterior — no hacer nada.
                return;
            }
            // Error de red: rollback.
            pintarCelda(btn, valorActual);
            toast('Error de red: ' + (err.message || 'sin detalle'), 'danger');
        } finally {
            inFlight.delete(key);
            setPendiente(btn, false);
        }
    });
})();