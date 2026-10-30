<?php $v = static fn (string $campo): string => $e($valores[$campo] ?? ''); ?>
<h1>Registrar alergia de <?= $e($codigo) ?></h1>
<?php if ($error !== null): ?>
  <p class="error" role="alert" id="error-formulario"><?= $e($error) ?></p>
<?php endif; ?>
<form method="post" action="/pacientes/<?= $e($codigo) ?>/alergias" <?= $error ? 'aria-describedby="error-formulario"' : '' ?>>
  <label for="alergeno">Alérgeno (nombre)</label>
  <input id="alergeno" name="alergeno" value="<?= $v('alergeno') ?>">
  <label for="codigo">Código del alérgeno (opcional si hay nombre)</label>
  <input id="codigo" name="codigo" value="<?= $v('codigo') ?>">
  <label for="tipo">Tipo</label>
  <select id="tipo" name="tipo">
    <?php foreach (['allergy' => 'Alergia', 'intolerance' => 'Intolerancia', 'unknown' => 'Desconocido'] as $valor => $texto): ?>
      <option value="<?= $e($valor) ?>" <?= ($valores['tipo'] ?? 'allergy') === $valor ? 'selected' : '' ?>><?= $e($texto) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="severidad">Severidad</label>
  <select id="severidad" name="severidad" required>
    <option value="">Seleccione…</option>
    <?php foreach (['life_threatening' => 'Pone en riesgo la vida', 'severe' => 'Grave', 'moderate' => 'Moderada', 'mild' => 'Leve', 'unknown' => 'Desconocida (se mostrará como alta)'] as $valor => $texto): ?>
      <option value="<?= $e($valor) ?>" <?= ($valores['severidad'] ?? '') === $valor ? 'selected' : '' ?>><?= $e($texto) ?></option>
    <?php endforeach; ?>
  </select>
  <label for="reaccion">Reacción observada</label>
  <textarea id="reaccion" name="reaccion" maxlength="500"><?= $v('reaccion') ?></textarea>
  <label for="inicio">Fecha de inicio (AAAA-MM-DD)</label>
  <input id="inicio" name="inicio" type="date" value="<?= $v('inicio') ?>">
  <p><button type="submit">Guardar alergia</button></p>
</form>
