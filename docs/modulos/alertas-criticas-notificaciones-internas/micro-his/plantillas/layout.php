<?php /** Layout común. Toda variable se imprime con $e() salvo $d['contenido'], que ya viene escapado. */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($d['titulo']) ?> · Alertas críticas</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; color: #1a1a1a; background: #f6f7f9; }
    header { background: #12355b; color: #fff; padding: .75rem 1rem; display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
    header a { color: #fff; font-weight: 600; text-decoration: none; }
    main { max-width: 960px; margin: 1rem auto; padding: 0 1rem; }
    table { width: 100%; border-collapse: collapse; background: #fff; }
    th, td { text-align: left; padding: .5rem; border-bottom: 1px solid #d0d5dd; vertical-align: top; }
    .aviso { background: #e6f4ea; border-left: 4px solid #1e7e34; padding: .5rem 1rem; }
    .error { background: #fdecea; border-left: 4px solid #b42318; padding: .5rem 1rem; }
    .CRITICA { color: #b42318; font-weight: 700; }
    fieldset { background: #fff; border: 1px solid #d0d5dd; margin: 1rem 0; }
    label { display: block; margin: .4rem 0 .1rem; }
    button { padding: .45rem .9rem; }
  </style>
</head>
<body>
<header>
  <a href="/">Alertas críticas y notificaciones internas</a>
  <form method="post" action="/sesion">
    <label for="usuario" style="display:inline">Usuario de demostración (<?= $e($d['sesion']->tenant()) ?>):</label>
    <select id="usuario" name="usuario">
      <?php foreach ($d['usuarios'] as $u): ?>
        <option value="<?= $e($u) ?>" <?= $u === $d['sesion']->usuario ? 'selected' : '' ?>><?= $e($u) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit">Cambiar</button>
  </form>
</header>
<main>
  <?php if ($d['mensaje']): ?><p class="aviso" role="status"><?= $e($d['mensaje']) ?></p><?php endif; ?>
  <?= $d['contenido'] ?>
</main>
</body>
</html>
