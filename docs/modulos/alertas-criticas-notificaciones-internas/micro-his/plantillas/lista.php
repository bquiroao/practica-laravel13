<?php /** Lista de alertas del tenant del usuario. */ ?>
<h1>Alertas de <?= $e($d['sesion']->tenant()) ?></h1>
<form method="post" action="/escalamientos">
  <button type="submit">Revisar plazos vencidos (simula el planificador)</button>
</form>
<?php if ($d['alertas'] === []): ?>
  <p>No hay alertas registradas para este hospital.</p>
<?php else: ?>
  <table>
    <caption>Alertas más recientes primero</caption>
    <thead><tr><th scope="col">Paciente</th><th scope="col">Prioridad</th><th scope="col">Estado</th><th scope="col">Nivel</th><th scope="col">Descripción</th></tr></thead>
    <tbody>
    <?php foreach ($d['alertas'] as $a): ?>
      <tr>
        <td><a href="/alertas/<?= $e($a->id) ?>"><?= $e($a->paciente->valor) ?></a></td>
        <td class="<?= $e($a->prioridad->value) ?>"><?= $e($a->prioridad->value) ?></td>
        <td><?= $e($a->estado()->value) ?><?= $a->escalamientoAgotado() ? ' (sin atención)' : '' ?></td>
        <td><?= $e($a->nivel()) ?></td>
        <td><?= $e($a->descripcion) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<form method="post" action="/alertas">
  <fieldset>
    <legend>Simular evento del sistema origen (datos ficticios)</legend>
    <label for="codigo_paciente">Código de paciente</label>
    <input id="codigo_paciente" name="codigo_paciente" placeholder="PAC-00451" required pattern="PAC-[0-9]{5}">
    <label for="servicio">Servicio</label>
    <select id="servicio" name="servicio"><option>MED-INT</option><option>PED</option></select>
    <label for="origen">Origen</label>
    <input id="origen" name="origen" value="ingreso-resultados-laboratorio" required>
    <label for="id_origen">Id del evento en el origen</label>
    <input id="id_origen" name="id_origen" placeholder="LAB-77801" required>
    <label for="prioridad">Prioridad</label>
    <select id="prioridad" name="prioridad"><option>CRITICA</option><option>ALTA</option></select>
    <label for="descripcion">Descripción (máx. 200)</label>
    <input id="descripcion" name="descripcion" maxlength="200" required size="50">
    <p><button type="submit">Registrar alerta</button></p>
  </fieldset>
</form>
