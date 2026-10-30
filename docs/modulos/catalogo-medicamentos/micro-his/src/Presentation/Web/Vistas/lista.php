<?php /** @var callable $e @var array $filtros @var ?array $resultados @var array $errores @var array $opciones */ ?>
<form method="get" action="/medicamentos" role="search">
  <label for="q">Nombre, principio activo o código</label>
  <input id="q" name="q" value="<?= $e($filtros['q'] ?? '') ?>" maxlength="60" <?= isset($errores['q']) ? 'aria-invalid="true" aria-describedby="q-error"' : '' ?>>
  <?php if (isset($errores['q'])): ?><p id="q-error" class="error"><?= $e($errores['q']) ?></p><?php endif ?>
  <label for="forma">Forma farmacéutica (opcional)</label>
  <select id="forma" name="forma">
    <option value="">Todas</option>
    <?php foreach ($opciones['formas'] as $forma): ?>
      <option value="<?= $e($forma) ?>" <?= ($filtros['forma'] ?? '') === $forma ? 'selected' : '' ?>><?= $e($forma) ?></option>
    <?php endforeach ?>
  </select>
  <label for="fecha">Vigentes a la fecha (vacío = hoy)</label>
  <input id="fecha" name="fecha" type="date" value="<?= $e($filtros['fecha'] ?? '') ?>" <?= isset($errores['fecha']) ? 'aria-invalid="true" aria-describedby="fecha-error"' : '' ?>>
  <?php if (isset($errores['fecha'])): ?><p id="fecha-error" class="error"><?= $e($errores['fecha']) ?></p><?php endif ?>
  <button type="submit">Buscar</button>
</form>

<?php if ($resultados === []): ?>
  <p role="status">No hay medicamentos vigentes con ese criterio. <a href="/medicamentos/nuevo">Registrar uno nuevo</a></p>
<?php elseif ($resultados !== null): ?>
  <p role="status"><?= count($resultados) ?> resultado(s) vigentes.</p>
  <div class="tabla"><table>
    <thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Principio activo</th><th scope="col">Concentración</th><th scope="col">Forma / vía</th><th scope="col">Versión</th></tr></thead>
    <tbody>
    <?php foreach ($resultados as $m): ?>
      <tr>
        <td><a href="/medicamentos/<?= $e(rawurlencode((string) $m['codigo'])) ?>"><?= $e($m['codigo']) ?></a></td>
        <td><?= $e($m['nombre']) ?></td>
        <td><?= $e($m['principio_activo']) ?></td>
        <td><?= $e($m['concentracion_valor'] . ' ' . $m['concentracion_unidad']) ?></td>
        <td><?= $e($m['forma_farmaceutica'] . ' / ' . $m['via_administracion']) ?></td>
        <td><?= $e($m['version']) ?></td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table></div>
<?php endif ?>
