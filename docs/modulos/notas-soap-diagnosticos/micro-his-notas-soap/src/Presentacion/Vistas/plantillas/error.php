<?php
/**
 * Plantilla de error. Variables: $estado, $codigo, $mensaje, $e (escape).
 * @var int $estado
 * @var string $codigo
 * @var string $mensaje
 * @var callable(mixed): string $e
 */
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Error <?= $e($estado) ?></title></head>
<body style="font-family: system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem;">
  <h1>No se pudo completar la operación</h1>
  <p><?= $e($mensaje) ?></p>
  <p style="color:#52606d">Código: <?= $e($codigo) ?> (HTTP <?= $e($estado) ?>)</p>
</body>
</html>
