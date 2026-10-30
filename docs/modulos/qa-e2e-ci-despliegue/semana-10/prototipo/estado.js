// Muestra el estado pedido en la URL (?estado=sin-conexion). Solo sirve para el prototipo:
// cada elemento con data-estado="a b" se ve únicamente en los estados a o b. Sin parámetro: "base".
(function () {
  const estado = new URLSearchParams(location.search).get('estado') || 'base';
  document.querySelectorAll('[data-estado]').forEach((el) => {
    el.hidden = !el.dataset.estado.split(' ').includes(estado);
  });
  document.body.dataset.estadoActual = estado;
})();
// Semana 9 B-03 (solo prototipo): al mostrar un error, el foco va al campo inválido y su mensaje queda a la vista,
// por encima de la barra de acción fija (lo mide R-F1 en verificar-responsive.cjs).
(function () {
  const campo = [...document.querySelectorAll('[aria-invalid="true"]')].find((el) => !el.closest('[hidden]'));
  if (!campo) return;
  campo.focus({ preventScroll: true });
  const error = document.getElementById(campo.getAttribute('aria-describedby'));
  (error || campo).scrollIntoView({ block: 'end' });
})();
