<?php /** @var callable $e @var array $valores @var array $errores @var ?string $codigo @var ?string $mensaje @var string $csrf @var array $opciones */
$campo = function (string $nombre, string $etiqueta, array $extra = []) use ($e, $valores, $errores): string {
    $invalido = isset($errores[$nombre]) ? ' aria-invalid="true" aria-describedby="' . $nombre . '-error"' : '';
    $html = '<label for="' . $nombre . '">' . $e($etiqueta) . '</label>';
    if (isset($extra['opciones'])) {
        $html .= '<select id="' . $nombre . '" name="' . $nombre . '"' . $invalido . '><option value="">Elija…</option>';
        foreach ($extra['opciones'] as $op) {
            $html .= '<option value="' . $e($op) . '"' . (($valores[$nombre] ?? '') === $op ? ' selected' : '') . '>' . $e($op) . '</option>';
        }
        $html .= '</select>';
    } else {
        $html .= '<input id="' . $nombre . '" name="' . $nombre . '" value="' . $e($valores[$nombre] ?? '') . '"' . $invalido
            . (isset($extra['tipo']) ? ' type="' . $extra['tipo'] . '"' : '') . (isset($extra['max']) ? ' maxlength="' . $extra['max'] . '"' : '') . '>';
    }
    if (isset($errores[$nombre])) {
        $html .= '<p id="' . $nombre . '-error" class="error">' . $e($errores[$nombre]) . '</p>';
    }
    return $html;
};
?>
<?php if ($mensaje !== null): ?><p class="alerta" role="alert"><?= $e($mensaje) ?></p><?php endif ?>
<?php if ($errores !== []): ?><p class="alerta" role="alert">Revise los <?= count($errores) ?> campo(s) marcados.</p><?php endif ?>
<form method="post" action="<?= $codigo === null ? '/medicamentos' : '/medicamentos/' . $e(rawurlencode($codigo)) . '/version' ?>" novalidate>
  <input type="hidden" name="csrf" value="<?= $e($csrf) ?>">
  <?= $campo('nombre', 'Nombre', ['max' => 150]) ?>
  <?= $campo('principio_activo', 'Principio activo', ['max' => 120]) ?>
  <?= $campo('concentracion_valor', 'Concentración (número)') ?>
  <?= $campo('concentracion_unidad', 'Unidad', ['opciones' => $opciones['unidades']]) ?>
  <?= $campo('forma_farmaceutica', 'Forma farmacéutica', ['opciones' => $opciones['formas']]) ?>
  <?= $campo('via_administracion', 'Vía de administración', ['opciones' => $opciones['vias']]) ?>
  <?= $campo('presentacion', 'Presentación', ['max' => 120]) ?>
  <?= $campo('registro_sanitario', 'Registro sanitario (opcional, ficticio)', ['max' => 30]) ?>
  <?= $campo('vigente_desde', $codigo === null ? 'Vigente desde (vacío = hoy)' : 'La nueva versión empieza el', ['tipo' => 'date']) ?>
  <button type="submit"><?= $codigo === null ? 'Registrar' : 'Registrar nueva versión' ?></button>
</form>
