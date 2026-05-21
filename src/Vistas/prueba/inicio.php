<?php
// Vista para el controlador: ControladorInicio.php

/**
 * @var string $prueba
 * @var int $id
 * @var int $otraPrueba
 */
?>
<div class="container">
    <h1 style="text-align: center;">Panel de Administración Avanzado</h1>
    <p>¡Felicidades! Has accedido al panel administrativo real.</p>
    <div class="box">
        <p>Esto es un ejemplo de una función</p>
        <p>Imprimir el valor de la variable prueba: <?= e($prueba) ?></p>
        <h2>Esto es el ID de prueba: <?= e($id) ?></h2>
        <h3>Esto es otra prueba: <?= e($otraPrueba ?? 'No se pasó') ?></h3>
    </div>
    <div>
        <a href="/" style="color: blue;">Volver al inicio</a>
    </div>
</div>