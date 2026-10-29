<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alergias del expediente</title>
<style>
  body { font-family: system-ui, sans-serif; margin: 0 auto; max-width: 46rem; padding: 1rem; color: #1d2433; }
  .alerta { border: 3px solid; border-radius: 6px; padding: 1rem; margin: 1rem 0; }
  .CRITICA { border-color: #a4161a; background: #fde8e8; }
  .ALTA { border-color: #9a3412; background: #fff1e6; }
  .MODERADA { border-color: #8a6100; background: #fff8db; }
  .INFORMATIVA, .SIN_REGISTRO { border-color: #4b5563; background: #f3f4f6; }
  .nivel { font-weight: 700; }
  label { display: block; margin-top: .75rem; font-weight: 600; }
  input, select, textarea, button { font: inherit; padding: .4rem; }
  :focus-visible { outline: 3px solid #1d4ed8; outline-offset: 2px; }
  .error { color: #a4161a; font-weight: 600; }
</style>
</head>
<body>
<?= $contenido ?>
</body>
</html>
