<?php
/**
 * Plantilla de la vista HTML de una nota. Variables: $nota (NotaResultado), $estadoTexto, $e (escape).
 * @var \NotasSoap\Aplicacion\Resultados\NotaResultado $nota
 * @var string $estadoTexto
 * @var callable(mixed): string $e
 */
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nota SOAP <?= $e($nota->notaId) ?></title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 46rem; margin: 2rem auto; padding: 0 1rem; color: #1f2933; line-height: 1.5; }
  h1 { font-size: 1.4rem; margin-bottom: .25rem; }
  .meta { color: #52606d; font-size: .9rem; }
  .estado { display: inline-block; padding: .1rem .6rem; border-radius: 1rem; background: #e4e7eb; font-weight: 600; }
  .estado.signed { background: #d1f2e0; color: #0b5d34; }
  dl { display: grid; grid-template-columns: 8rem 1fr; gap: .5rem 1rem; }
  dt { font-weight: 600; }
  dd { margin: 0; white-space: pre-wrap; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border-bottom: 1px solid #cbd2d9; padding: .4rem; text-align: left; }
</style>
</head>
<body>
<main>
  <h1>Nota SOAP</h1>
  <p class="meta">
    <span class="estado <?= $e($nota->estado) ?>"><?= $e($estadoTexto) ?></span>
    · Versión <?= $e($nota->version) ?> · Autor: <?= $e($nota->autorId) ?>
    · Expediente <?= $e($nota->expedienteId) ?> · Episodio <?= $e($nota->episodioId) ?>
    <?php if ($nota->firmadaEn !== null): ?> · Firmada: <?= $e($nota->firmadaEn) ?><?php endif; ?>
  </p>
  <?php if ($nota->reemplazaA !== null): ?>
  <p class="meta">Corrige la nota <?= $e($nota->reemplazaA) ?>. Motivo: <?= $e($nota->motivoCorreccion) ?></p>
  <?php endif; ?>
  <dl>
    <dt>Subjetivo</dt><dd><?= $e($nota->soap['subjetivo']) ?></dd>
    <dt>Objetivo</dt><dd><?= $e($nota->soap['objetivo']) ?></dd>
    <dt>Análisis</dt><dd><?= $e($nota->soap['analisis']) ?></dd>
    <dt>Plan</dt><dd><?= $e($nota->soap['plan']) ?></dd>
  </dl>
  <h2>Diagnósticos</h2>
  <table>
    <thead><tr><th>CIE-10</th><th>Descripción</th><th>Tipo</th></tr></thead>
    <tbody>
    <?php foreach ($nota->diagnosticos as $d): ?>
      <tr><td><?= $e($d['code']) ?></td><td><?= $e($d['description']) ?></td><td><?= $e($d['type']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</main>
</body>
</html>
