/**
 * Dashboard JavaScript - Sidebar Navigation & Modals
 * Maneja la navegación del sidebar, modales y detección de ruta activa
 */

(function () {
  "use strict";

  // ==========================================
  // SIDEBAR TOGGLE (Mobile)
  // ==========================================

  function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebar-overlay");

    if (sidebar && overlay) {
      sidebar.classList.toggle("open");
      overlay.classList.toggle("active");
    }
  }

  function closeSidebar() {
    const sidebar = document.getElementById("sidebar");
    const overlay = document.getElementById("sidebar-overlay");

    if (sidebar) sidebar.classList.remove("open");
    if (overlay) overlay.classList.remove("active");
  }

  // ==========================================
  // ACTIVE NAV LINK DETECTION
  // ==========================================

  function setActiveNavLink() {
    const navLinks = document.querySelectorAll(".nav-link");
    const currentPath = window.location.pathname;

    navLinks.forEach((link) => {
      const href = link.getAttribute("href");

      // Remover clase active de todos
      link.classList.remove("active");

      // Agregar active si la URL actual coincide con el href
      // Soporta coincidencias exactas y con prefijo
      if (
        href &&
        (currentPath === href || currentPath.startsWith(href + "/"))
      ) {
        link.classList.add("active");
      }
    });
  }

  // ==========================================
  // NAVIGATION HANDLER
  // ==========================================

  function initNavigation() {
    const navLinks = document.querySelectorAll(".nav-link");

    navLinks.forEach((link) => {
      link.addEventListener("click", function (e) {
        // Solo cerrar sidebar en móvil, dejar que el navegador navegue
        if (window.innerWidth < 1024) {
          e.preventDefault();

          const href = this.getAttribute("href");

          // Cerrar sidebar primero
          closeSidebar();

          // Navegar después de un pequeño delay para que se vea el cierre
          setTimeout(() => {
            window.location.href = href;
          }, 150);
        }
      });
    });
  }

  // ==========================================
  // MODAL FUNCTIONS
  // ==========================================

  function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  }

  function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove("active");
      document.body.style.overflow = "";
      if (modalId === "upload-modal" && typeof window.resetUploadModal === "function") {
        window.resetUploadModal();
      }
    }
  }

  function closeModalOnOverlay(event) {
    if (event.target.classList.contains("modal-overlay")) {
      closeModal(event.target.id);
    }
  }

  function openQRModal(documentName, documentId, documentUrl) {
    const qrDocumentName = document.getElementById("qr-document-name");
    if (qrDocumentName) {
      qrDocumentName.textContent = documentName;
    }

    // Guardar datos para la descarga
    window.currentQRData = {
      documentName,
      documentId,
      documentUrl,
    };

    openModal(`qr-modal-${documentId}`);
  }

  function downloadQR() {
    const qrData = window.currentQRData;
    if (!qrData) return;

    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrData.documentUrl)}`;
    const nombreArchivo = qrData.documentName.replace(/\s+/g, "_") + ".png";

    fetch(qrUrl)
      .then((res) => res.blob())
      .then((blob) => {
        const url = URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = nombreArchivo;
        a.click();
        URL.revokeObjectURL(url);
      })
      .catch((err) => console.error("Error al descargar QR:", err));
  }

  function printQR() {
    const qrData = window.currentQRData;
    if (!qrData) return;

    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrData.documentUrl)}`;

    const ventana = window.open("", "_blank");
    ventana.document.write(`
      <!DOCTYPE html>
      <html lang="es">
      <head>
        <meta charset="UTF-8">
        <title>QR - ${qrData.documentName}</title>
        <style>
          body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 40px;
            margin: 0;
          }
          h2 {
            color: #333;
            margin-bottom: 20px;
          }
          img {
            width: 250px;
            border: 2px solid #ddd;
            border-radius: 8px;
          }
        </style>
      </head>
      <body>
        <h2>${qrData.documentName}</h2>
        <img src="${qrUrl}" alt="Código QR">
      </body>
      </html>
    `);
    ventana.document.close();
    setTimeout(() => ventana.print(), 250);
  }

  // ==========================================
  // CLOSE ON ESCAPE KEY
  // ==========================================

  document.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      // Cerrar todos los modales abiertos
      document.querySelectorAll(".modal-overlay.active").forEach((modal) => {
        closeModal(modal.id);
      });
      document.body.style.overflow = "";

      // Cerrar sidebar en móvil
      if (window.innerWidth < 1024) {
        closeSidebar();
      }
    }
  });

  // ==========================================
  // OVERLAY CLICK TO CLOSE
  // ==========================================

  document.querySelectorAll(".modal-overlay").forEach((overlay) => {
    overlay.addEventListener("click", closeModalOnOverlay);
  });

  // ==========================================
  // HAMBURGER MENU
  // ==========================================

  const hamburger = document.getElementById("hamburger");
  if (hamburger) {
    hamburger.addEventListener("click", toggleSidebar);
  }

  // ==========================================
  // INITIALIZATION
  // ==========================================

  document.addEventListener("DOMContentLoaded", function () {
    // Detectar y marcar link activo
    setActiveNavLink();

    // Inicializar navegación
    initNavigation();

    // Vincular funciones de modales al objeto window para uso inline
    window.toggleSidebar = toggleSidebar;
    window.closeSidebar = closeSidebar;
    window.openModal = openModal;
    window.closeModal = closeModal;
    window.closeModalOnOverlay = closeModalOnOverlay;
    window.openQRModal = openQRModal;
    window.downloadQR = downloadQR;
    window.printQR = printQR;
  });

  // También ejecutar inmediatamente por si DOMContentLoaded ya ocurrió
  setActiveNavLink();
  initNavigation();
})();
