// Sidebar toggle
function toggleSidebar() {
  document.getElementById("sidebar").classList.toggle("open");
  document.getElementById("sidebar-overlay").classList.toggle("active");
}

function closeSidebar() {
  document.getElementById("sidebar").classList.remove("open");
  document.getElementById("sidebar-overlay").classList.remove("active");
}

// Navigation
const sectionTitles = {
  documents: "Gestion de Documentos",
  transfers: "Trazabilidad de Traslados",
};

// Modal functions
function openModal(modalId) {
  document.getElementById(modalId).classList.add("active");
  document.body.style.overflow = "hidden";
}

function closeModal(modalId) {
  document.getElementById(modalId).classList.remove("active");
  document.body.style.overflow = "";
}

function closeModalOnOverlay(event) {
  if (event.target.classList.contains("modal-overlay")) {
    event.target.classList.remove("active");
    document.body.style.overflow = "";
  }
}

function openQRModal(documentName) {
  document.getElementById("qr-document-name").textContent = documentName;
  openModal("qr-modal");
}

// Close modal on Escape key
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    document.querySelectorAll(".modal-overlay.active").forEach((modal) => {
      modal.classList.remove("active");
    });
    document.body.style.overflow = "";
    closeSidebar();
  }
});
