<?php

/**
 * Vista de gestión de usuarios.
 *
 * @var array $usuarios
 * @var array $roles
 * @var array $flash
 */
?>

<section id="usuarios" class="section active">
    <div class="section-header">
        <div>
            <h2 class="section-title">Gestión de Usuarios</h2>
            <p class="section-description">
                Administración de cuentas. La baja es lógica (no se eliminan registros)
                para preservar el historial de traslados.
            </p>
        </div>
    </div>

    <?php if (!empty($flash)): ?>
        <div class="alert alert-<?= e($flash['tipo'] ?? 'info') ?>" role="alert">
            <?= e($flash['mensaje'] ?? '') ?>
        </div>
    <?php endif; ?>

    <div class="usuarios-filtros">
        <button class="filtro-chip is-active" data-filtro="all">Todos (<?= count($usuarios) ?>)</button>
        <button class="filtro-chip" data-filtro="activo">Activos</button>
        <button class="filtro-chip" data-filtro="inactivo">Inactivos</button>
    </div>

    <?php
        componente('modulos/usuarios/tabla', [
            'usuarios' => $usuarios,
            'roles' => $roles,
        ]);
    ?>
</section>

<script>
/**
 * Filtro client-side por estado (Activo/Inactivo).
 */
document.addEventListener("DOMContentLoaded", function () {
  const chips = document.querySelectorAll(".usuarios-filtros .filtro-chip");
  const filas = document.querySelectorAll(".usuarios-table tbody tr");

  chips.forEach((chip) => {
    chip.addEventListener("click", function () {
      const filtro = this.dataset.filtro;
      chips.forEach((c) => c.classList.remove("is-active"));
      this.classList.add("is-active");

      filas.forEach((fila) => {
        if (filtro === "all" || fila.dataset.estado === filtro) {
          fila.style.display = "";
        } else {
          fila.style.display = "none";
        }
      });
    });
  });
});
</script>
