<?php $v = $d['valores']; ?>
<h1>Elegir cama para <?= $e($v['paciente']) ?></h1>
<?php require __DIR__ . '/_error.php'; ?>
<?php if ($d['camas'] === []): ?>
  <p role="status">No hay camas disponibles compatibles con <?= $e($v['servicio']) ?> / <?= $e($v['tipo']) ?>.</p>
  <p><a href="/?<?= $e(http_build_query($v)) ?>">Cambiar el requerimiento</a></p>
<?php else: ?>
  <form method="post" action="/admisiones">
    <?php foreach ($v as $campo => $valor): ?>
      <input type="hidden" name="<?= $e($campo) ?>" value="<?= $e($valor) ?>">
    <?php endforeach; ?>
    <fieldset>
      <legend>Camas disponibles (<?= count($d['camas']) ?>)</legend>
      <?php foreach ($d['camas'] as $i => $cama): ?>
        <label><input type="radio" name="cama" value="<?= $e($cama->id) ?>" required<?= $i === 0 ? ' checked' : '' ?>>
          <?= $e($cama->codigo) ?> · <?= $e($cama->servicio) ?> · <?= $e($cama->tipo->value) ?><?= $cama->aislamiento ? ' · aislamiento' : '' ?></label>
      <?php endforeach; ?>
    </fieldset>
    <button type="submit">Confirmar admisión y asignar cama</button>
  </form>
<?php endif; ?>
