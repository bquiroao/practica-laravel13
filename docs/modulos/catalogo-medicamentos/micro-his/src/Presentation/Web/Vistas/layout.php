<?php /** @var callable $e @var string $titulo @var string $contenido @var ?string $aviso */ ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($titulo) ?> · Catálogo de medicamentos</title>
  <style>
    :root { --texto: #1b1f24; --fondo: #ffffff; --borde: #6b7280; --primario: #0b5cad; --error: #a4161a; --ok: #1d6b35; }
    body { font: 16px/1.5 system-ui, sans-serif; color: var(--texto); background: var(--fondo); margin: 0; }
    header, main { max-width: 64rem; margin: 0 auto; padding: 1rem; }
    header { border-bottom: 1px solid var(--borde); display: flex; gap: 1rem; flex-wrap: wrap; align-items: center; }
    a { color: var(--primario); }
    label { display: block; font-weight: 600; margin-top: .75rem; }
    input, select { font: inherit; padding: .4rem; border: 1px solid var(--borde); border-radius: 4px; width: 100%; max-width: 28rem; box-sizing: border-box; }
    button { font: inherit; padding: .5rem 1rem; background: var(--primario); color: #fff; border: 0; border-radius: 4px; margin-top: 1rem; cursor: pointer; }
    button.peligro { background: var(--error); }
    :focus-visible { outline: 3px solid #f2b705; outline-offset: 2px; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border-bottom: 1px solid #d0d4da; padding: .4rem; text-align: left; vertical-align: top; }
    .error { color: var(--error); }
    .aviso { border-left: 4px solid var(--ok); padding: .5rem 1rem; background: #eef7f0; }
    .alerta { border-left: 4px solid var(--error); padding: .5rem 1rem; background: #fbeeee; }
    .tabla { overflow-x: auto; }
  </style>
</head>
<body>
<header>
  <strong>Catálogo de medicamentos</strong>
  <nav aria-label="Principal"><a href="/medicamentos">Buscar</a> · <a href="/medicamentos/nuevo">Registrar</a></nav>
  <span>Micro-HIS · Hospital Norte (ficticio) · qf.norte</span>
</header>
<main id="contenido">
  <?php if ($aviso !== null): ?><p class="aviso" role="status"><?= $e($aviso) ?></p><?php endif ?>
  <h1><?= $e($titulo) ?></h1>
  <?= $contenido /* ya escapado por cada plantilla */ ?>
</main>
</body>
</html>
