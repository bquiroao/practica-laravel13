// Muestra el estado pedido en la URL (?estado=sin-conexion). Solo sirve para el prototipo:
// cada elemento con data-estado="a b" se ve únicamente en los estados a o b. Sin parámetro: "base".
// data-inerte-en="a" deja el elemento inert en el estado a (fondo de una hoja abierta).
(function () {
  const estado = new URLSearchParams(location.search).get('estado') || 'base';
  document.querySelectorAll('[data-estado]').forEach((el) => {
    el.hidden = !el.dataset.estado.split(' ').includes(estado);
  });
  document.querySelectorAll('[data-inerte-en]').forEach((el) => {
    el.inert = el.dataset.inerteEn.split(' ').includes(estado);
  });
  document.body.dataset.estadoActual = estado;
})();
