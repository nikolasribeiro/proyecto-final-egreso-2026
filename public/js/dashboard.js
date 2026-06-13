
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

        document.querySelectorAll(".nav-link").forEach((link) => {
            link.addEventListener("click", (e) => {
                // 1. Obtenemos el data-section del enlace
                const sectionId = link.dataset.section;
                
                // 2. Si NO tiene data-section (ej: nuestro enlace /traslados), salimos de la función y dejamos que el navegador vaya a la URL real.
                
                if (!sectionId) {
                    return;
                }

                e.preventDefault();

                // 3. Si SÍ tiene data-section (ej: módulo de Documentos), prevenimos la navegación y ejecutamos el cambio visual con JS.

                // Update nav
                document
                    .querySelectorAll(".nav-link")
                    .forEach((l) => l.classList.remove("active"));
                link.classList.add("active");

                // Update sections
                document
                    .querySelectorAll(".section")
                    .forEach((s) => s.classList.remove("active"));
                document.getElementById(sectionId).classList.add("active");

                // Update header title
                document.getElementById("header-title").textContent =
                    sectionTitles[sectionId] || sectionId;

                    // Update browser tab title
                    document.title = "HC - " + (sectionTitles[sectionId] || sectionId);

                // Close sidebar on mobile after navigation
                closeSidebar();
            });
        });


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
                document
                    .querySelectorAll(".modal-overlay.active")
                    .forEach((modal) => {
                        modal.classList.remove("active");
                    });
                document.body.style.overflow = "";
                closeSidebar();
            }
        });
