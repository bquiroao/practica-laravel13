<?php /** Error recuperable: explica qué pasó y ofrece volver. */ ?>
<h1>No se pudo completar la acción</h1>
<p class="error" role="alert">(<?= $e($d['codigo']) ?>) <?= $e($d['error']) ?></p>
<p><a href="/">Volver a la lista</a></p>
