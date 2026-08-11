 <?php

/**
 * @var string $titulo
 * @var string $created_at
 * @var string $id
 */
?>
<article class="tarjeta-doc">
                        <div>
                            <h3 class="titulo-doc"><?= htmlspecialchars($titulo) ?></h3>
                            <span class="fecha-doc">Publicado: <?= date('d/m/Y', strtotime($created_at)) ?></span>
                        </div>
                        <!-- Enlace al segundo método del controlador público para ver el PDF -->
                        <a href="/d/doc/<?= urlencode($id) ?>" class="btn-ver">Ver Documento</a>
</article>