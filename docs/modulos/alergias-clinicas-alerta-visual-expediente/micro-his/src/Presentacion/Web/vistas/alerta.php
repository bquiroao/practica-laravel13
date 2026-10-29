<?php
/** @var \Alergias\Dominio\AlertaExpediente $alerta */
$titulos = [
    'CRITICA' => 'Alergia crítica', 'ALTA' => 'Alergia de severidad alta', 'MODERADA' => 'Alergia moderada',
    'INFORMATIVA' => 'Alergia leve', 'SIN_REGISTRO' => 'Sin alergias registradas',
];
$nivel = $alerta->nivel->value;
?>
<h1>Expediente <?= $e($alerta->codigoPaciente) ?></h1>
<section class="alerta <?= $e($nivel) ?>" <?= $alerta->requiereReconocimiento() ? 'role="alert"' : '' ?> aria-labelledby="titulo-alerta">
  <h2 id="titulo-alerta"><span class="nivel">[<?= $e($nivel) ?>]</span> <?= $e($titulos[$nivel]) ?></h2>
  <?php if ($alerta->detalle === []): ?>
    <p>No hay alergias registradas. Esto <strong>no</strong> confirma que el paciente no tenga alergias: pregunte antes de indicar.</p>
  <?php else: ?>
    <ul>
    <?php foreach ($alerta->detalle as $d): ?>
      <li><span class="nivel">[<?= $e($d['nivel']->value) ?>]</span> <?= $e($d['alergia']->alergeno->etiqueta()) ?>
        (<?= $e($d['alergia']->severidad->value) ?>)<?= $d['alergia']->reaccion ? ' — reacción: ' . $e($d['alergia']->reaccion) : '' ?></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if ($alerta->requiereReconocimiento()): ?>
    <form method="post" action="/pacientes/<?= $e($alerta->codigoPaciente) ?>/alerta/reconocimiento">
      <input type="hidden" name="huella" value="<?= $e($alerta->huella) ?>">
      <button type="submit">He leído las alergias de este paciente</button>
    </form>
  <?php elseif ($alerta->reconocida): ?>
    <p>Usted ya reconoció esta alerta.</p>
  <?php endif; ?>
</section>
<p><a href="/pacientes/<?= $e($alerta->codigoPaciente) ?>/alergias/nueva">Registrar alergia</a></p>
