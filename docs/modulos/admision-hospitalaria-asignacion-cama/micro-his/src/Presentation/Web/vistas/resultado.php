<?php $r = $d['r']; ?>
<h1>Admisión <?= $e($r->estado) ?></h1>
<div role="status">
  <p>Paciente <strong><?= $e($r->codigoPaciente) ?></strong> en cama <strong><?= $e($r->camaCodigo) ?></strong> (<?= $e($r->servicio) ?>).</p>
  <p class="aviso">Registrada por <?= $e($r->creadaPor) ?> · <?= $e($r->creadaEn) ?> · id <?= $e($r->admisionId) ?></p>
</div>
<p><a href="/?usuario=<?= $e(rawurlencode($d['valores']['usuario'])) ?>">Nueva admisión</a></p>
