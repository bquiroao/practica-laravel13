<?php /** @var callable $e @var array $versiones @var string $codigo @var ?string $mensaje @var array $errores @var string $csrf */
$ultima = $versiones[array_key_last($versiones)];
$abierta = $ultima['vigente_hasta'] === null;
?>
<?php if ($mensaje !== null): ?>
  <div class="alerta" role="alert"><p><?= $e($mensaje) ?></p>
  <?php foreach ($errores as $campo => $texto): ?><p><?= $e($campo . ': ' . $texto) ?></p><?php endforeach ?></div>
<?php endif ?>
<p><?= $e($ultima['nombre']) ?> — <?= $e($ultima['principio_activo'] . ' ' . $ultima['concentracion_valor'] . ' ' . $ultima['concentracion_unidad']) ?>,
  <?= $e($ultima['forma_farmaceutica'] . ', vía ' . $ultima['via_administracion']) ?>.</p>
<h2>Historial de versiones</h2>
<div class="tabla"><table>
  <thead><tr><th scope="col">Versión</th><th scope="col">Presentación</th><th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Vigente hoy</th><th scope="col">Motivo de cierre</th></tr></thead>
  <tbody>
  <?php foreach ($versiones as $v): ?>
    <tr><td><?= $e($v['version']) ?></td><td><?= $e($v['presentacion']) ?></td><td><?= $e($v['vigente_desde']) ?></td>
      <td><?= $e($v['vigente_hasta'] ?? '(abierta)') ?></td><td><?= $e($v['vigente_hoy']) ?></td><td><?= $e($v['motivo_cierre'] ?? '') ?></td></tr>
  <?php endforeach ?>
  </tbody>
</table></div>
<?php if ($abierta): ?>
  <p><a href="/medicamentos/<?= $e(rawurlencode($codigo)) ?>/version">Registrar nueva versión (otra concentración o presentación)</a></p>
  <h2>Dar de baja</h2>
  <form method="post" action="/medicamentos/<?= $e(rawurlencode($codigo)) ?>/baja">
    <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
    <label for="fecha_baja">Fecha de baja (vacío = hoy)</label><input id="fecha_baja" name="fecha_baja" type="date">
    <label for="motivo">Motivo (5 a 200 caracteres)</label><input id="motivo" name="motivo" maxlength="200" required>
    <button type="submit">Dar de baja</button>
  </form>
<?php else: ?>
  <p>Este medicamento está dado de baja; no admite nuevas versiones.</p>
<?php endif ?>
<h2>Eliminar registro erróneo</h2>
<p>Solo se permite si el código nunca se usó en una prescripción. Si se usó, corresponde darlo de baja.</p>
<form method="post" action="/medicamentos/<?= $e(rawurlencode($codigo)) ?>/eliminar">
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <button type="submit" class="peligro">Eliminar <?= $e($codigo) ?></button>
</form>
