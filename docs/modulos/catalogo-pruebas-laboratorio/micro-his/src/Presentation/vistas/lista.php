<h1>Pruebas vigentes</h1>
<form method="get" action="/pruebas">
  <label>Buscar <input name="texto" value="<?= $e($filtros['texto'] ?? '') ?>"></label>
  <label>Fecha de referencia <input type="date" name="fecha" value="<?= $e($filtros['fecha'] ?? '') ?>"></label>
  <button>Consultar</button>
</form>
<?php foreach ($errores ?? [] as $msg): ?><p class="error"><?= $e($msg) ?></p><?php endforeach; ?>
<?php if ($pruebas === []): ?>
  <p>No hay pruebas vigentes para esos filtros.</p>
<?php else: ?>
  <table>
    <tr><th>Código</th><th>Nombre</th><th>Sección</th><th>Muestra</th><th>Vigencia</th></tr>
    <?php foreach ($pruebas as $p): ?>
      <tr>
        <td><a href="/pruebas/<?= $e($p->codigo) ?>?fecha=<?= $e($filtros['fecha'] ?? '') ?>"><?= $e($p->codigo) ?></a></td>
        <td><?= $e($p->nombre) ?></td><td><?= $e($p->seccion) ?></td>
        <td><?= $e($p->requisito->tipoMuestra) ?></td><td><?= $e($p->vigencia->texto()) ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php endif; ?>
