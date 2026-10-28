'use strict';
// Prototipo navegable del expediente (semana 11): rutas por hash, datos ficticios en memoria y reglas R-01 (un expediente
// por paciente), R-04 (no se borra), R-05 (atención activa para consultar), R-06 (consentimiento) y R-08 (todo se audita).
const PACIENTES = {
  P1: { nombre: 'M. López', nac: 1990, doc: '4521', expediente: null },
  P2: { nombre: 'M. López Ruiz', nac: 1972, doc: '0987', expediente: 'HC-000123' },
  P3: { nombre: 'R. Díaz', nac: 2015, doc: '3310', expediente: 'HC-000098' },
};
const ATENCIONES = { 'HC-000123': 'E-77' }; // atención activa de la Dra. Ruiz (R-05)
const EPISODIOS = { 'HC-000123': [['12/10/2026', 'E-77 · en curso', 'Medicina interna', 'Nota de ingreso'], ['03/04/2025', 'E-41', 'Consulta externa', 'Control']] };
const EXTERNOS = { 'HC-000123': { vigencia: '31/12/2026', episodios: [['20/08/2025', 'Hospital Regional Norte', 'Urgencias', 'Diagnósticos y alergias']] } };
const ROLES = { tratante: 'profesional tratante (Dra. Ruiz)', admision: 'personal de admisión' };
let rol = 'tratante';
let siguiente = 124; // próximo número de expediente

const abreviado = (nombre) => nombre.replace(/^(\S+ \S+)( (\S)\S*)?$/, (_, a, __, b) => (b ? `${a} ${b}.` : a));
const tarjetaPaciente = (p, accion = '') => `<section class="paciente"><strong>${p.nombre}</strong> (ficticio) · nac. ${p.nac}<br>
  Documento terminado en ${p.doc}${accion ? `<div class="acciones">${accion}</div>` : ''}</section>`; // nunca el documento completo
const episodios = (filas, cabeceras) => `<table><thead><tr>${cabeceras.map((c) => `<th>${c}</th>`).join('')}</tr></thead><tbody>
  ${filas.map((f) => `<tr>${f.map((v, i) => `<td${i ? ` data-etiqueta="${cabeceras[i]}"` : ''}>${i ? v : `<strong>${v}</strong>`}</td>`).join('')}</tr>`).join('')}</tbody></table>`;

const vistas = {
  pacientes: () => ['Pacientes', `<h1 tabindex="-1">Pacientes activos</h1>
    ${Object.entries(PACIENTES).map(([id, p]) => tarjetaPaciente(p,
      p.expediente && rol === 'tratante' ? `<a class="boton" href="#expediente/${p.expediente}">Ver expediente ${p.expediente}</a>`
      : p.expediente ? `<span>Ya tiene el expediente ${p.expediente} (R-01)</span>`
      : rol === 'admision' ? `<a class="boton principal" href="#crear/${id}">Crear expediente</a>` : '<span>Sin expediente</span>')).join('')}`],

  crear: (id) => {
    const p = PACIENTES[id];
    if (rol !== 'admision' || !p || p.expediente) return vistas.pacientes();
    return ['Crear expediente', `<p><a href="#pacientes">‹ Pacientes</a></p><h1 tabindex="-1">¿Crear el expediente de ${p.nombre}?</h1>
      ${tarjetaPaciente(p)}<p>Se crea con un número único y no se puede borrar (R-04). Revisa que sea la persona correcta.</p>
      <div class="acciones"><button class="principal" id="crear">Crear expediente</button><a class="boton" href="#pacientes">Volver sin crear</a></div>`,
      () => document.getElementById('crear').addEventListener('click', () => {
        p.expediente = `HC-000${siguiente++}`;
        location.hash = 'pacientes';
        setTimeout(() => avisar(`Expediente ${p.expediente} abierto para ${p.nombre}. La apertura quedó en la auditoría (R-08).`));
      })];
  },

  expediente: (hc) => {
    const p = Object.values(PACIENTES).find((x) => x.expediente === hc);
    if (rol !== 'tratante' || !p) return vistas.pacientes();
    // R-05: sin atención activa no se ve nada, ni siquiera si hay episodios; el intento también se audita (R-08).
    if (!ATENCIONES[hc]) return ['Sin atención activa', `<h1 tabindex="-1">No tienes una atención activa con este paciente</h1>
      <p>Si lo estás atendiendo, pide a admisión o a tu jefatura que registre la asignación. Este intento quedó en la auditoría.</p>
      <div class="acciones"><a class="boton principal" href="#pacientes">Volver a pacientes</a></div>`];
    return [`Expediente ${hc}`, `<p><a href="#pacientes">‹ Pacientes</a></p><h1 tabindex="-1">Expediente ${hc}</h1>
      <p><strong id="nombre">${abreviado(p.nombre)}</strong> · ${2026 - p.nac} años
      <button type="button" id="mostrar" aria-pressed="false">Mostrar nombre</button><br>Tu atención: ${ATENCIONES[hc]} (activa)</p>
      ${episodios(EPISODIOS[hc], ['Fecha', 'Episodio', 'Servicio', 'Resumen'])}
      <div class="acciones"><a class="boton" href="#externa/${hc}">Historia de otros hospitales</a></div><p><small>Este acceso queda en la auditoría (R-08).</small></p>`,
      () => document.getElementById('mostrar').addEventListener('click', (e) => {
        const ver = e.currentTarget.getAttribute('aria-pressed') !== 'true';
        e.currentTarget.setAttribute('aria-pressed', String(ver));
        e.currentTarget.textContent = ver ? 'Ocultar nombre' : 'Mostrar nombre';
        document.getElementById('nombre').textContent = ver ? p.nombre : abreviado(p.nombre);
      })];
  },

  externa: (hc) => {
    if (rol !== 'tratante' || !ATENCIONES[hc] || !EXTERNOS[hc]) return vistas.pacientes();
    return ['Otros hospitales', `<p><a href="#expediente/${hc}">‹ ${hc}</a></p><h1 tabindex="-1">Historia de otros hospitales</h1>
      <p>Consentimiento vigente hasta ${EXTERNOS[hc].vigencia} (R-06). Solo se muestra el alcance consentido.</p>
      ${episodios(EXTERNOS[hc].episodios, ['Fecha', 'Hospital de origen', 'Episodio', 'Alcance'])}`,
      () => avisar(`${EXTERNOS[hc].episodios.length} episodio externo.`)];
  },
};

function avisar(texto) { document.getElementById('estado').textContent = texto; }

function mostrar() {
  const [nombre, ...args] = (location.hash.slice(1) || 'pacientes').split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.pacientes)(...args);
  avisar('');
  document.getElementById('contenido').innerHTML = html;
  document.title = `${titulo} · Expedientes · Hospital HIS`;
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
