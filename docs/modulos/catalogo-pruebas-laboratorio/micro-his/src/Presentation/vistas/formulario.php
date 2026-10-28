<?php
$campos = [
    'codigo' => 'Código (AAA-000)', 'nombre' => 'Nombre', 'seccion' => 'Sección',
    'tiempo_respuesta_horas' => 'Tiempo de respuesta (h)', 'tipo_muestra' => 'Tipo de muestra',
    'contenedor' => 'Contenedor', 'volumen_minimo_ml' => 'Volumen mínimo (mL)', 'horas_ayuno' => 'Ayuno (h)',
    'instrucciones_preparacion' => 'Instrucciones de preparación', 'conservacion' => 'Conservación',
    'estabilidad_horas' => 'Estabilidad (h)', 'vigente_desde' => 'Vigente desde (AAAA-MM-DD)',
    'vigente_hasta' => 'Vigente hasta (opcional)',
];
?>
<h1>Nueva prueba</h1>
<?php if (isset($errores['general'])): ?><p class="error"><?= $e($errores['general']) ?></p><?php endif; ?>
<form method="post" action="/pruebas">
  <?php foreach ($campos as $campo => $etiqueta): ?>
    <label for="<?= $campo ?>"><?= $e($etiqueta) ?></label>
    <input id="<?= $campo ?>" name="<?= $campo ?>" value="<?= $e($valores[$campo] ?? '') ?>"
      <?= isset($errores[$campo]) ? 'aria-invalid="true" aria-describedby="err-' . $campo . '"' : '' ?>>
    <?php if (isset($errores[$campo])): ?><span id="err-<?= $campo ?>" class="error"><?= $e($errores[$campo]) ?></span><?php endif; ?>
  <?php endforeach; ?>
  <p><button>Guardar prueba</button></p>
</form>
