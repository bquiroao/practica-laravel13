'use strict';
// Prototipo navegable del centro de documentación (semana 11): rutas por hash, datos ficticios en memoria
// y reglas R-06 (solo lo de la audiencia del rol), R-07 (lo ajeno responde igual que lo inexistente) y R-08 (aviso de obsoleta).
const DOCUMENTOS = {
  'guia-citas': { tipo: 'Manual', audiencia: ['medico'], vigente: '1.1.0', fecha: '26/10/2026', versiones: ['1.1.0', '1.0.0'],
    secciones: [['Cómo solicitar una cita', 'Elige paciente, médico y un horario libre; el motivo es obligatorio.'],
      ['Errores frecuentes', 'E4: el médico ya tiene una cita en ese horario. E5: el paciente ya tiene una cita en ese horario.']] },
  'citas-api': { tipo: 'OpenAPI', audiencia: ['medico', 'integrador'], vigente: '2.0.0', fecha: '20/10/2026', versiones: ['2.0.0', '1.0.0'],
    secciones: [['Endpoints (3)', 'POST /citas · POST /citas/{id}/confirmacion · POST /citas/{id}/cancelacion'],
      ['Errores', 'E2 datos incompletos · E3 especialidad no vigente · E4 y E5 cruce de horario · E7 la cita cambió · E8 servicio no disponible'],
      ['Ejemplo de solicitud', '{ "paciente": "P-0012", "medico": "M-03", "inicio": "2026-10-28T08:30", "motivo": "control" }']] },
  'inventario-farmacia': { tipo: 'Manual', audiencia: ['farmacia'], vigente: '1.0.0', fecha: '15/10/2026', versiones: ['1.0.0'],
    secciones: [['Recepción de lotes', 'Registra el lote y su vencimiento al recibirlo.']] },
};
const ROLES = { medico: 'médico', integrador: 'integrador', farmacia: 'farmacia' };
let rol = 'medico';

const visible = (id) => DOCUMENTOS[id]?.audiencia.includes(rol);
const esc = (t) => t.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);

const vistas = {
  docs: () => {
    const mios = Object.keys(DOCUMENTOS).filter(visible);
    return ['Mi documentación', `<h1 tabindex="-1">Mi documentación</h1><p>Tus roles: <strong>${ROLES[rol]}</strong></p>
      <label for="buscar">Buscar en mi documentación</label><input id="buscar" type="search" autocomplete="off">
      <table><thead><tr><th>Documento</th><th>Tipo</th><th>Vigente</th><th>Actualizado</th></tr></thead><tbody>
      ${mios.map((id) => `<tr data-busqueda="${esc(`${id} ${DOCUMENTOS[id].tipo}`.toLowerCase())}"><td><a href="#doc/${id}"><strong>${id}</strong></a></td><td data-etiqueta="Tipo">${DOCUMENTOS[id].tipo}</td>
        <td data-etiqueta="Vigente">${DOCUMENTOS[id].vigente}</td><td data-etiqueta="Actualizado">${DOCUMENTOS[id].fecha}</td></tr>`).join('')}
      </tbody></table>`, () => { avisar(`${mios.length} documentos para tus roles.`); prepararBusqueda(); }];
  },

  doc: (id, version) => {
    // R-07: un documento ajeno y uno que no existe dan la misma respuesta, para no revelar que existe.
    if (!visible(id)) return ['Documento no encontrado', `<h1 tabindex="-1">Documento no encontrado</h1>
      <p>No existe o no está disponible para tus roles. Si crees que deberías verlo, pide acceso al responsable de integración.</p>
      <div class="acciones"><a class="boton principal" href="#docs">Volver a mi documentación</a></div>`];
    const d = DOCUMENTOS[id];
    const v = d.versiones.includes(version) ? version : d.vigente;
    const obsoleta = v !== d.vigente;
    return [`${id} ${v}`, `<p><a href="#docs">‹ Documentos</a></p><h1 tabindex="-1">${id} · ${d.tipo}</h1>
      ${obsoleta ? '<p id="aviso" class="impacto" role="status"></p>' : ''}
      <label for="version">Versión</label> <select id="version">${d.versiones.map((x) =>
        `<option value="${x}"${x === v ? ' selected' : ''}>${x}${x === d.vigente ? ' (vigente)' : ' (obsoleta)'}</option>`).join('')}</select>
      ${d.secciones.map(([titulo, texto], i) => `<details${i ? '' : ' open'}><summary><strong>${titulo}</strong></summary>
        <p class="texto-largo">${esc(texto)}</p></details>`).join('')}`,
      () => {
        document.getElementById('version').addEventListener('change', (e) => { location.hash = `doc/${id}/${e.target.value}`; });
        // La región viva se pinta vacía y se llena después, para que el lector de pantalla anuncie el aviso (R-08).
        if (obsoleta) setTimeout(() => { document.getElementById('aviso').innerHTML = `<strong>Estás viendo una versión obsoleta.</strong>
          La vigente es la ${d.vigente}. <a href="#doc/${id}">Ir a la ${d.vigente}</a>`; });
      }];
  },
};

/** Semana 12 (B-08): filtra solo lo que el rol ya puede ver (R-06) y anuncia el resultado sin mover el foco. */
function prepararBusqueda() {
  const buscar = document.getElementById('buscar');
  buscar.addEventListener('input', () => {
    const q = buscar.value.trim().toLowerCase();
    const filas = [...document.querySelectorAll('tr[data-busqueda]')];
    filas.forEach((f) => { f.hidden = !f.dataset.busqueda.includes(q); });
    const n = filas.filter((f) => !f.hidden).length;
    avisar(n ? `${n} ${n === 1 ? 'documento encontrado' : 'documentos encontrados'}.` : `Ningún documento de tus roles coincide con «${q}».`);
  });
}

function avisar(texto) { document.getElementById('estado').textContent = texto; }

function mostrar() {
  const [nombre, ...args] = (location.hash.slice(1) || 'docs').split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.docs)(...args);
  avisar('');
  document.getElementById('contenido').innerHTML = html;
  document.title = `${titulo} · Documentación · Hospital HIS`;
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
