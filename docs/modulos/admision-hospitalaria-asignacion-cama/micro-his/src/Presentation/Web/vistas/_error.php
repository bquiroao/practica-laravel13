<?php if (isset($d['error'])): ?>
  <div class="alerta" role="alert" id="error-general">
    <strong><?= $e($d['error']['mensaje']) ?></strong>
    <span class="aviso">(<?= $e($d['error']['codigo']) ?>)</span>
  </div>
<?php endif; ?>
