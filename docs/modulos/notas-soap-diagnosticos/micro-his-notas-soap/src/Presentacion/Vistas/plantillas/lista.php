<?php
/**
 * Plantilla del listado de notas de un expediente. Variables: $expedienteId, $resumenes, $etiquetas, $e.
 * @var string $expedienteId
 * @var list<\NotasSoap\Aplicacion\Resultados\ResumenNotaResultado> $resumenes
 * @var array<string, string> $etiquetas
 * @var callable(mixed): string $e
 */
?><!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Notas SOAP del expediente <?= $e($expedienteId) ?></title>
<style>body{font-family:system-ui,sans-serif;max-width:46rem;margin:2rem auto;padding:0 1rem;color:#1f2933}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #cbd2d9;padding:.4rem;text-align:left}</style>
</head>
<body>
<main>
  <h1>Notas SOAP del expediente <?= $e($expedienteId) ?></h1>
  <?php if ($resumenes === []): ?>
    <p>No hay notas para mostrar.</p>
  <?php else: ?>
  <table>
    <thead><tr><th>Creada</th><th>Estado</th><th>Autor</th><th>CIE-10</th></tr></thead>
    <tbody>
    <?php foreach ($resumenes as $r): ?>
      <tr><td><?= $e($r->creadaEn) ?></td><td><?= $e($etiquetas[$r->estado] ?? $r->estado) ?></td><td><?= $e($r->autorId) ?></td><td><?= $e(implode(', ', $r->codigosCie10)) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</main>
</body>
</html>
