<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $e($d['titulo']) ?> · Admisión hospitalaria</title>
  <style>
    body { font-family: system-ui, sans-serif; margin: 0; color: #1f2933; background: #f5f7fa; }
    main { max-width: 44rem; margin: 0 auto; padding: 1rem; }
    label { display: block; margin-top: .75rem; font-weight: 600; }
    input, select, button { font: inherit; padding: .5rem; margin-top: .25rem; }
    button { background: #1d4e89; color: #fff; border: 0; border-radius: .25rem; margin-top: 1rem; }
    :focus-visible { outline: 3px solid #b45309; outline-offset: 2px; }
    .alerta { border-left: 4px solid #b91c1c; background: #fff; padding: .75rem; }
    .aviso { color: #52606d; font-size: .9rem; }
  </style>
</head>
<body>
<main>
  <p class="aviso">Micro-HIS · datos ficticios · pacientes solo por código</p>
  <?= $d['contenido'] /* ya escapado por cada plantilla */ ?>
</main>
</body>
</html>
