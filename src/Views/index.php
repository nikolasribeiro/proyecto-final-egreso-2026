<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documento de prueba</title>
</head>

<body>
    <?php echo "Hola Mundo desde PHP, sin haber instalado nada ni necesitado nada raro" ?>
    <br>
    Paciente: <?= $new_client ? htmlspecialchars($new_client, ENT_QUOTES, 'UTF-8') : "No existe este paciente" ?>
</body>

</html>