'use strict';
// Prototipo navegable del catálogo (semana 11): rutas por hash, datos ficticios en memoria y reglas R-05, R-06 y R-09.
const NOMBRES = { d: 'Disponible', o: 'Ocupada', m: 'Mantenimiento', i: 'Inactiva' };
const VERBOS = { d: 'Pasar a disponible', m: 'Pasar a mantenimiento', i: 'Inactivar cama' };
const DESTINOS = { supervisor: { d: 'm', m: 'd' }, admin: { d: 'mi', m: 'di', i: 'd' } }; // R-05; «ocupada» no tiene destinos (R-06)
const ROLES = { supervisor: 'supervisor de enfermería', admin: 'administrador' };
const salas = { A: sala('Medicina interna', 101, 'domdodododoo'), B: sala('Cirugía', 201, 'oooooomm') };
let rol = 'supervisor';
let borrador = ''; // motivo escrito, que se conserva si hay un conflicto

function sala(nombre, primera, estados) {
  return { nombre, camas: [...estados].map((estado, i) => ({ codigo: String(primera + i), estado })) };
}
const cuenta = (s, e) => s.camas.filter((c) => c.estado === e).length;
const esc = (t) => t.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
const cama = (codigo) => salas[codigo[0]]?.camas.find((c) => c.codigo === codigo.slice(1));
const etiqueta = (codigo) => `${codigo[0]}/${codigo.slice(1)}`;

const vistas = {
  salas: () => ['Salas', `<h1 tabindex="-1">Salas</h1>
    <table><thead><tr><th>Sala</th><th>Disponibles</th><th>Ocupadas</th><th>No operativas</th><th>Acción</th></tr></thead><tbody>
    ${Object.entries(salas).map(([id, s]) => `<tr><td><strong>${id} · ${s.nombre}</strong></td>
      <td data-etiqueta="Disponibles"><strong>${cuenta(s, 'd')}</strong> de ${cuenta(s, 'd') + cuenta(s, 'o')} operativas</td>
      <td data-etiqueta="Ocupadas">${cuenta(s, 'o')}</td><td data-etiqueta="No operativas">${cuenta(s, 'm') + cuenta(s, 'i')}</td>
      <td><a class="boton" href="#sala/${id}">Ver camas de la sala ${id}</a></td></tr>`).join('')}</tbody></table>`],

  sala: (id) => (salas[id] ? [`Sala ${id}`, `<p><a href="#salas">‹ Salas</a></p><h1 tabindex="-1">Sala ${id} · ${salas[id].nombre}</h1>
    <table><thead><tr><th>Cama</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
    ${salas[id].camas.map((c) => `<tr><td><strong>${id}/${c.codigo}</strong></td><td data-etiqueta="Estado">${NOMBRES[c.estado]}</td>
      <td>${c.estado === 'o' ? 'La gestiona admisión' : `<a class="boton" href="#cambiar/${id}${c.codigo}">Cambiar estado de ${id}/${c.codigo}</a>`}</td></tr>`).join('')}
    </tbody></table>`] : vistas.salas()),

  cambiar: (codigo) => {
    const c = cama(codigo ?? '');
    const destinos = c ? [...(DESTINOS[rol][c.estado] ?? '')] : [];
    if (!destinos.length) return vistas.salas();
    return [`Cambiar ${etiqueta(codigo)}`, `<p><a href="#sala/${codigo[0]}">‹ Sala ${codigo[0]}</a></p>
      <h1 tabindex="-1">Cama ${etiqueta(codigo)}</h1><p>Estado actual: <strong>${NOMBRES[c.estado]}</strong></p>
      <form id="cambio" novalidate><fieldset><legend>Nuevo estado *</legend>
      ${destinos.map((d, i) => `<label><input type="radio" name="destino" value="${d}"${i ? '' : ' checked'}> ${NOMBRES[d]}</label>`).join('')}
      </fieldset><label for="motivo">Motivo *</label><textarea id="motivo" rows="2" aria-describedby="error-motivo">${esc(borrador)}</textarea>
      <p id="error-motivo" class="error"></p><p id="impacto" class="impacto"></p>
      <div class="acciones"><button class="principal" id="confirmar"></button><a class="boton" href="#sala/${codigo[0]}">Volver sin cambiar</a></div></form>`,
      () => prepararFormulario(codigo, c)];
  },

  hecho: (codigo, destino) => {
    const c = cama(codigo ?? '');
    if (!c || !NOMBRES[destino]) return vistas.salas();
    c.estado = destino;
    borrador = '';
    const [, html] = vistas.sala(codigo[0]);
    return ['Cambio guardado', html, () => avisar(`Cama ${etiqueta(codigo)}: ahora está en ${NOMBRES[destino].toLowerCase()}. El motivo quedó en el historial (R-09).`)];
  },

  conflicto: (codigo) => {
    const c = cama(codigo ?? '');
    if (!c) return vistas.salas();
    c.estado = 'o'; // simulación: admisión ocupó la cama mientras se editaba (E7)
    return [`Conflicto en ${etiqueta(codigo)}`, `<h1 tabindex="-1">La cama ${etiqueta(codigo)} cambió mientras la editabas</h1>
      <p>Ahora está <strong>Ocupada</strong>: admisión la asignó a las 10:42. Tu cambio no se guardó.</p>
      ${borrador ? `<p>Tu motivo se conserva: «${esc(borrador)}».</p>` : ''}
      <p>Una cama ocupada no cambia de estado desde el catálogo (R-06).</p>
      <div class="acciones"><a class="boton principal" href="#sala/${codigo[0]}">Ver la sala ${codigo[0]} actualizada</a><a class="boton" href="#salas">Volver a las salas</a></div>`];
  },
};

function prepararFormulario(codigo, c) {
  const form = document.getElementById('cambio');
  const motivo = document.getElementById('motivo');
  const actualizar = () => {
    const destino = form.destino.value;
    const disponibles = cuenta(salas[codigo[0]], 'd');
    const despues = disponibles + Number(destino === 'd') - Number(c.estado === 'd');
    document.getElementById('impacto').textContent = `La sala ${codigo[0]} pasará de ${disponibles} a ${despues} camas disponibles.`;
    document.getElementById('confirmar').textContent = VERBOS[destino];
  };
  form.addEventListener('change', actualizar);
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    borrador = motivo.value.trim();
    if (!borrador) {
      motivo.setAttribute('aria-invalid', 'true');
      document.getElementById('error-motivo').textContent = 'Error: escribe el motivo del cambio; queda en el historial (R-09).';
      motivo.focus();
      return;
    }
    const simular = document.getElementById('simular-conflicto').checked;
    location.hash = simular ? `conflicto/${codigo}` : `hecho/${codigo}/${form.destino.value}`;
  });
  actualizar();
}

function avisar(texto) { document.getElementById('estado').textContent = texto; }

function mostrar() {
  const [nombre, ...args] = (location.hash.slice(1) || 'salas').split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.salas)(...args);
  avisar('');
  document.getElementById('contenido').innerHTML = html;
  document.title = `${titulo} · Catálogo de camas · Hospital HIS`;
  document.querySelector('h1').focus();
  despues?.();
}

document.getElementById('rol').addEventListener('change', (e) => {
  rol = e.target.value;
  document.getElementById('rol-actual').textContent = ROLES[rol];
  mostrar();
});
document.querySelector('.saltar').addEventListener('click', (e) => { e.preventDefault(); document.getElementById('contenido').focus(); });
document.getElementById('rol-actual').textContent = ROLES[rol];
window.addEventListener('hashchange', mostrar);
mostrar();
