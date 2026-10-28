<?php if (isset($aviso)): ?><p class="aviso"><?= $e($aviso) ?></p><?php endif; ?>
<h1><?= $e($prueba->codigo) ?> — <?= $e($prueba->nombre) ?> (versión <?= $e($prueba->version) ?>)</h1>
<p>Sección: <?= $e($prueba->seccion) ?> · Tiempo de respuesta: <?= $e($prueba->tiempoRespuestaHoras) ?> h</p>
<h2>Requisitos de muestra</h2>
<ul>
  <li>Tipo de muestra: <?= $e($prueba->requisito->tipoMuestra) ?></li>
  <li>Contenedor: <?= $e($prueba->requisito->contenedor) ?></li>
  <li>Volumen mínimo: <?= $e($prueba->requisito->volumenMinimoMl) ?> mL</li>
  <li>Ayuno: <?= $e($prueba->requisito->horasAyuno) ?> h <?= $e($prueba->requisito->instruccionesPreparacion) ?></li>
  <li>Conservación: <?= $e($prueba->requisito->conservacion) ?> · Estabilidad: <?= $e($prueba->requisito->estabilidadHoras) ?> h</li>
</ul>
<p>Vigencia: <?= $e($prueba->vigencia->texto()) ?></p>
