<?php $v = $d['valores']; ?>
<h1>Nueva admisión</h1>
<?php require __DIR__ . '/_error.php'; ?>
<form method="get" action="/camas">
  <label for="usuario">Usuario ficticio (solo demostración)</label>
  <select id="usuario" name="usuario">
    <?php foreach (['usr-adm-01', 'usr-sup-01', 'usr-med-01', 'usr-adm-02'] as $u): ?>
      <option value="<?= $e($u) ?>"<?= $u === $v['usuario'] ? ' selected' : '' ?>><?= $e($u) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="paciente">Código de paciente</label>
  <input id="paciente" name="paciente" value="<?= $e($v['paciente']) ?>" placeholder="PAC-00451" required aria-describedby="ayuda-paciente">
  <p id="ayuda-paciente" class="aviso">Formato PAC-NNNNN. No se escriben nombres.</p>
  <label for="servicio">Servicio</label>
  <select id="servicio" name="servicio">
    <?php foreach (['MEDICINA_INTERNA', 'CIRUGIA', 'UCI'] as $s): ?>
      <option value="<?= $e($s) ?>"<?= $s === $v['servicio'] ? ' selected' : '' ?>><?= $e($s) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="tipo">Tipo de cama</label>
  <select id="tipo" name="tipo">
    <?php foreach (['GENERAL', 'UCI', 'PEDIATRICA'] as $t): ?>
      <option value="<?= $e($t) ?>"<?= $t === $v['tipo'] ? ' selected' : '' ?>><?= $e($t) ?></option>
    <?php endforeach; ?>
  </select>
  <label><input type="checkbox" name="aislamiento" value="1"<?= $v['aislamiento'] === '1' ? ' checked' : '' ?>> Requiere aislamiento</label>
  <label for="origen">Origen</label>
  <select id="origen" name="origen">
    <?php foreach (['EMERGENCIA', 'CONSULTA_EXTERNA', 'REFERENCIA'] as $o): ?>
      <option value="<?= $e($o) ?>"<?= $o === $v['origen'] ? ' selected' : '' ?>><?= $e($o) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit">Buscar camas disponibles</button>
</form>
