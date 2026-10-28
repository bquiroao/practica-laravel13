'use strict';
// Prototipo navegable del tablero de ocupación (semana 11): rutas por hash, datos ficticios en memoria y reglas
// R-01 (fórmula), R-02 («no aplica»), R-05 (hora del corte), R-06 (aviso a los 15 min) y R-09 (consolidar no cambia camas).
const ESTADOS = { disponible: 'Disponibles', ocupada: 'Ocupadas', mantenimiento: 'Mantenimiento', inactiva: 'Inactivas' };
const SALAS = { A: ['Medicina interna', [5, 6, 1, 0]], B: ['Cirugía', [0, 6, 2, 0]], C: ['Pediatría', [0, 0, 3, 1]] };
const ROLES = { jefatura: 'jefatura de enfermería', admin: 'administrador' };
const FUENTE = 'catálogo de camas y estancias de admisión';
let ahora = 10 * 60 + 39; // reloj del prototipo: 10:39; avanza un minuto al generar un corte
let rol = 'jefatura';
let corteGenerado = null; // minuto del día del corte que el administrador generó

const hora = (m) => `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`;
const minutoCorte = () => corteGenerado ?? ahora - Number(document.getElementById('edad').value);
const cuentas = (id) => Object.fromEntries(Object.keys(ESTADOS).map((e, i) => [e, SALAS[id][1][i]]));

/** R-01 y R-02: ocupadas ÷ (disponibles + ocupadas); sin camas operativas, «no aplica». Nivel en texto (semana 9, B-04). */
function ocupacion(id) {
  const c = cuentas(id);
  const operativas = c.disponible + c.ocupada;
  if (operativas === 0) return 'Sin camas operativas · no aplica';
  const p = (100 * c.ocupada) / operativas;
  return `${p.toFixed(1)} % · ${p < 70 ? 'baja' : p < 90 ? 'media' : 'alta'}`;
}

function cabecera() {
  const edad = ahora - minutoCorte();
  return `<p>Corte de las <strong>${hora(minutoCorte())}</strong> · <span id="antiguedad">${edad === 0 ? 'recién generado' : `hace ${edad} min`}</span><br>
    <small>Fuente: ${FUENTE}</small></p>${edad > 15 ? '<p id="aviso-viejo" class="impacto" role="status"></p>' : ''}`;
}

const vistas = {
  salas: (estado) => {
    const ids = Object.keys(SALAS).filter((id) => !ESTADOS[estado] || cuentas(id)[estado] > 0)
      .sort((a, b) => cuentas(b).disponible - cuentas(a).disponible); // más camas libres primero
    return ['Ocupación por sala', `<h1 tabindex="-1">Ocupación por sala</h1>${cabecera()}
      <label for="filtro">Estado de cama</label> <select id="filtro"><option value="">Todos</option>
      ${Object.keys(ESTADOS).map((e) => `<option value="${e}"${e === estado ? ' selected' : ''}>${e}</option>`).join('')}</select>
      <table><thead><tr><th>Sala</th><th>Disponibles</th><th>Ocupación</th><th>Detalle</th></tr></thead><tbody>
      ${ids.map((id) => `<tr><td><strong>${id} · ${SALAS[id][0]}</strong></td>
        <td data-etiqueta="Disponibles"><strong>${cuentas(id).disponible}</strong> de ${cuentas(id).disponible + cuentas(id).ocupada}</td>
        <td data-etiqueta="Ocupación">${ocupacion(id)}</td><td><a class="boton" href="#sala/${id}">Ver sala ${id}</a></td></tr>`).join('')}
      </tbody></table>${rol === 'admin' ? '<div class="acciones"><a class="boton" href="#corte">Generar un corte ahora</a></div>' : ''}`,
      () => {
        document.getElementById('filtro').addEventListener('change', (e) => { location.hash = `salas/${e.target.value}`; });
        if (estado) avisar(`${ids.length} de ${Object.keys(SALAS).length} salas con camas en estado «${estado}».`);
        avisarViejo();
      }];
  },

  sala: (id) => (SALAS[id] ? [`Sala ${id}`, `<p><a href="#salas">‹ Ocupación</a></p><h1 tabindex="-1">${id} · ${SALAS[id][0]}</h1>${cabecera()}
    <p><strong>${ocupacion(id)}</strong><br><small>ocupadas ÷ (disponibles + ocupadas) (R-01)</small></p>
    <table><tbody>${Object.entries(ESTADOS).map(([e, t]) => `<tr><td>${t}</td><td>${cuentas(id)[e]}</td></tr>`).join('')}</tbody></table>
    <p><small>Mantenimiento e inactivas no cuentan en el porcentaje.</small></p>`, avisarViejo] : vistas.salas()),

  corte: () => (rol !== 'admin' ? vistas.salas() : ['Generar corte', `<p><a href="#salas">‹ Ocupación</a></p>
    <h1 tabindex="-1">Generar un corte ahora</h1><p>Último corte: ${hora(minutoCorte())}. Se leerán las camas y estancias actuales.</p>
    <p>No cambia ningún estado de cama (R-09).</p>
    <div class="acciones"><button class="principal" id="generar">Generar un corte ahora</button><a class="boton" href="#salas">Volver</a></div>`,
    () => document.getElementById('generar').addEventListener('click', generar)]),
};

function generar(e) {
  e.currentTarget.setAttribute('aria-disabled', 'true');
  e.currentTarget.textContent = 'Consolidando…';
  if (document.getElementById('falla-corte').checked) {
    location.hash = 'salas';
    setTimeout(() => avisar(`No pudimos leer la ocupación (E2). Se conserva el corte de las ${hora(minutoCorte())}: sus cifras siguen siendo válidas para esa hora.`, true));
    return;
  }
  ahora += 1;
  corteGenerado = ahora;
  location.hash = 'salas';
  setTimeout(() => avisar(`Corte de las ${hora(corteGenerado)} guardado: ${Object.keys(SALAS).length} salas.`));
}

/** R-06: la región viva se pinta vacía y se llena después, para que el lector anuncie el aviso una vez. */
function avisarViejo() {
  const aviso = document.getElementById('aviso-viejo');
  if (aviso) setTimeout(() => { aviso.innerHTML = `<strong>Datos de las ${hora(minutoCorte())}: pueden estar desactualizados.</strong>
    La consolidación automática no ha guardado cortes nuevos. Avisa al administrador.`; });
}

function avisar(texto, alerta = false) {
  const estado = document.getElementById('estado');
  estado.textContent = texto;
  estado.classList.toggle('alerta', alerta);
}

function mostrar() {
  const [nombre, ...args] = (location.hash.slice(1) || 'salas').split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.salas)(...args);
  avisar('');
  document.getElementById('contenido').innerHTML = html;
  document.title = `${titulo} · Ocupación · Hospital HIS`;
  document.querySelector('h1').focus();
  despues?.();
}

for (const id of ['rol', 'edad']) document.getElementById(id).addEventListener('change', () => {
  rol = document.getElementById('rol').value;
  document.getElementById('rol-actual').textContent = ROLES[rol];
  mostrar();
});
document.querySelector('.saltar').addEventListener('click', (e) => { e.preventDefault(); document.getElementById('contenido').focus(); });
document.getElementById('rol-actual').textContent = ROLES[rol];
window.addEventListener('hashchange', mostrar);
mostrar();
