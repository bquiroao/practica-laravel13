<?php /** Detalle e historial de una alerta. Los botones dependen del estado; las reglas siguen en el dominio. */ ?>
<?php $a = $d['alerta']; ?>
<h1>Alerta <?= $e(substr($a->id, 0, 8)) ?> · <?= $e($a->paciente->valor) ?></h1>
<p><span class="<?= $e($a->prioridad->value) ?>"><?= $e($a->prioridad->value) ?></span> ·
   Estado <strong><?= $e($a->estado()->value) ?></strong> · Nivel <?= $e($a->nivel()) ?> · Servicio <?= $e($a->servicio) ?></p>
<p><?= $e($a->descripcion) ?></p>
<?php if ($a->escalamientoAgotado()): ?>
  <p class="error" role="alert">Escalamiento agotado: nadie acusó en ningún nivel.</p>
<?php endif; ?>
<?php if ($a->estado()->esperaAcuse()): ?>
  <form method="post" action="/alertas/<?= $e($a->id) ?>/acuse">
    <button type="submit">Acusar como <?= $e($d['sesion']->usuario) ?></button>
  </form>
<?php elseif ($a->estado()->value === 'ACUSADA'): ?>
  <form method="post" action="/alertas/<?= $e($a->id) ?>/cierre">
    <label for="nota">Nota de cierre (obligatoria)</label>
    <textarea id="nota" name="nota" rows="3" cols="50" required></textarea>
    <p><button type="submit">Cerrar alerta</button></p>
  </form>
<?php endif; ?>
<h2>Historial</h2>
<table>
  <thead><tr><th scope="col">#</th><th scope="col">Momento</th><th scope="col">Evento</th><th scope="col">Actor</th><th scope="col">Detalle</th></tr></thead>
  <tbody>
  <?php foreach ($a->historial() as $ev): ?>
    <tr><td><?= $e($ev->orden) ?></td><td><?= $e($ev->momento->format('Y-m-d H:i')) ?></td><td><?= $e($ev->evento) ?></td>
        <td><?= $e($ev->actor) ?></td><td><?= $e($ev->detalle) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table>
<p><a href="/">Volver a la lista</a></p>
