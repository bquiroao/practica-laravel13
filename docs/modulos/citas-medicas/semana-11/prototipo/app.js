'use strict';
// Prototipo navegable de la agenda (semana 11): rutas por hash que llevan el estado de la solicitud, datos ficticios en memoria
// y reglas R-02 (sin cruces del médico), R-06 (motivo obligatorio) y R-09 (el médico solo ve y gestiona sus citas).
const PACIENTES = { P0012: 'A. Pérez', P0047: 'L. Gómez', P0050: 'R. Díaz' };
const MEDICOS = { M03: ['Dra. Ruiz', 'Cardiología'], M05: ['Dr. Mena', 'Pediatría'] };
const libres = { M03: ['0830', '1100', '1130'], M05: ['0900', '1030'] };
const citas = [
  { hora: '0800', paciente: 'P0012', medico: 'M03', estado: 'Programada' },
  { hora: '0900', paciente: 'P0047', medico: 'M03', estado: 'Confirmada' },
  { hora: '1000', paciente: 'P0050', medico: 'M05', estado: 'Cancelada' },
];
const ROLES = { recepcion: 'recepcionista', medico: 'médica (Dra. Ruiz)' };
let rol = 'recepcion';
let borrador = ''; // motivo escrito, que se conserva si el horario se ocupa

const esc = (t) => t.replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
const fin = (h) => { const m = Number(h.slice(0, 2)) * 60 + Number(h.slice(2)) + 30; return `${String(Math.floor(m / 60)).padStart(2, '0')}${String(m % 60).padStart(2, '0')}`; };
const franja = (h) => `${h.slice(0, 2)}:${h.slice(2)}–${fin(h).slice(0, 2)}:${fin(h).slice(2)}`;
const paciente = (id) => `${id.replace('P', 'P-')} · ${PACIENTES[id]}`;
const medico = (id) => `${MEDICOS[id][0]} · ${MEDICOS[id][1]}`;
const valido = (p, m, h) => Boolean(PACIENTES[p] && MEDICOS[m] && /^\d{4}$/.test(h ?? ''));
const atras = (href, texto) => `<p><a href="${href}">‹ ${texto}</a></p>`;

// Semana 12: cancelar desde la agenda (CU-03) con motivo; la médica solo ve y cancela sus citas (R-09); una cancelada no tiene acción (R-07).
const clave = (c) => `${c.hora}-${c.medico}`;
const activa = (k) => citas.find((c) => clave(c) === k && c.estado !== 'Cancelada' && (rol === 'recepcion' || c.medico === 'M03'));
const accionCancelar = (c) => (activa(clave(c)) ? `<a class="boton" href="#cancelar/${clave(c)}" aria-label="Cancelar la cita de las ${franja(c.hora).slice(0, 5)} de ${paciente(c.paciente)}">Cancelar</a>` : '—');

const vistas = {
  cancelar: (k) => {
    const c = activa(k ?? '');
    if (!c) return vistas.agenda();
    return ['Cancelar cita', `${atras('#agenda', 'Agenda')}<h1 tabindex="-1">¿Cancelar la cita de ${PACIENTES[c.paciente]}?</h1>
      <p class="impacto">${franja(c.hora)} · ${medico(c.medico)} · ${c.estado}<br>El horario queda libre para otro paciente (R-08).</p>
      <form id="paso" novalidate><label for="motivo">Motivo de la cancelación *</label>
      <textarea id="motivo" rows="2" aria-describedby="error-motivo"></textarea><p id="error-motivo" class="error"></p>
      <div class="acciones"><button class="peligro">Cancelar la cita de ${PACIENTES[c.paciente]}</button><a class="boton" href="#agenda">Volver</a></div></form>`,
      () => alEnviar(() => { const m = document.getElementById('motivo'); if (m.value.trim()) return `cancelada/${k}`;
        m.setAttribute('aria-invalid', 'true'); document.getElementById('error-motivo').textContent = 'Error: escribe el motivo de la cancelación; queda en la auditoría (R-10).'; m.focus(); return ''; })];
  },

  cancelada: (k) => {
    const c = activa(k ?? '');
    if (!c) return vistas.agenda();
    c.estado = 'Cancelada'; libres[c.medico] = [...libres[c.medico], c.hora].sort();
    const [, html] = vistas.agenda();
    return ['Cita cancelada', html, () => avisar(`Cita de ${PACIENTES[c.paciente]} a las ${franja(c.hora).slice(0, 5)} cancelada. El horario quedó libre.`)];
  },

  agenda: () => {
    const visibles = citas.filter((c) => rol === 'recepcion' || c.medico === 'M03').sort((a, b) => a.hora.localeCompare(b.hora));
    return ['Agenda', `<h1 tabindex="-1">Agenda · Mié 28/10/2026</h1>
      <table><thead><tr><th>Hora</th><th>Paciente</th><th>Médico</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
      ${visibles.map((c) => `<tr><td><strong>${franja(c.hora)}</strong></td><td data-etiqueta="Paciente">${paciente(c.paciente)}</td>
        <td data-etiqueta="Médico">${medico(c.medico)}</td><td data-etiqueta="Estado">${c.estado}</td><td>${accionCancelar(c)}</td></tr>`).join('')}</tbody></table>
      ${rol === 'recepcion' ? '<div class="acciones"><a class="boton principal" href="#nueva">+ Nueva cita</a></div>' : ''}`];
  },

  nueva: (p, m, h) => {
    if (rol !== 'recepcion') return vistas.agenda(); // solo recepción solicita citas
    if (!p) return ['Nueva cita · 1 de 3', `${atras('#agenda', 'Agenda')}<h1 tabindex="-1">Nueva cita · 1 de 3: paciente</h1>
      <form id="paso"><label for="paciente">Paciente *</label>
      <select id="paciente">${Object.keys(PACIENTES).map((id) => `<option value="${id}">${paciente(id)}</option>`).join('')}</select>
      <p><em>* Obligatorio</em></p><div class="acciones"><button class="principal">Siguiente: horario</button></div></form>`,
      () => alEnviar(() => `nueva/${document.getElementById('paciente').value}`)];
    if (!PACIENTES[p]) return vistas.agenda();
    if (!m) return ['Nueva cita · 2 de 3', `${atras('#nueva', 'Paso 1')}<h1 tabindex="-1">Nueva cita · 2 de 3: horario</h1>
      <p>Paciente: <strong>${paciente(p)}</strong></p>
      <form id="paso"><label for="medico">Médico *</label>
      <select id="medico">${Object.keys(MEDICOS).map((id) => `<option value="${id}">${medico(id)}</option>`).join('')}</select>
      <fieldset><legend>Horario libre * (ya sin cruces)</legend><div id="horarios"></div></fieldset>
      <div class="acciones"><button class="principal">Siguiente: revisar</button></div></form>`,
      () => prepararHorarios(p)];
    if (!valido(p, m, h) || !h) return vistas.agenda();
    return ['Nueva cita · 3 de 3', `${atras(`#nueva/${p}`, 'Paso 2')}<h1 tabindex="-1">Nueva cita · 3 de 3: revisar</h1>
      <form id="paso" novalidate><label for="motivo">Motivo *</label>
      <textarea id="motivo" rows="2" aria-describedby="error-motivo">${esc(borrador)}</textarea><p id="error-motivo" class="error"></p>
      <p class="impacto"><strong>${paciente(p)}</strong><br>${medico(m)}<br><strong>Mié 28/10 · ${franja(h)}</strong><br>
      Al confirmar se vuelve a revisar el horario.</p>
      <div class="acciones"><button class="principal" id="confirmar">Agendar cita</button></div></form>`,
      () => prepararRevision(p, m, h)];
  },

  agendada: (p, m, h) => {
    if (!valido(p, m, h)) return vistas.agenda();
    if (!citas.some((c) => c.hora === h && c.medico === m)) citas.push({ hora: h, paciente: p, medico: m, estado: 'Programada' });
    libres[m] = libres[m].filter((x) => x !== h);
    borrador = '';
    const [, html] = vistas.agenda();
    return ['Cita agendada', html, () => avisar(`Cita programada para el Mié 28/10 a las ${franja(h).slice(0, 5)} con ${MEDICOS[m][0]}.`)];
  },

  ocupado: (p, m, h) => {
    if (!valido(p, m, h)) return vistas.agenda();
    libres[m] = libres[m].filter((x) => x !== h); // simulación: otra persona tomó el horario (E4)
    if (!citas.some((c) => c.hora === h && c.medico === m)) citas.push({ hora: h, paciente: 'P0047', medico: m, estado: 'Programada' });
    return ['Horario ocupado', `<h1 tabindex="-1">Ese horario se ocupó mientras confirmabas</h1>
      <p>${MEDICOS[m][0]} ya tiene una cita el Mié 28/10 a las ${franja(h).slice(0, 5)}. La cita no se agendó.</p>
      ${borrador ? `<p>Tu motivo se conserva: «${esc(borrador)}».</p>` : ''}
      <form id="paso"><fieldset><legend>Siguientes horarios libres</legend>${opcionesHorario(m)}</fieldset>
      <div class="acciones"><button class="principal">Agendar en el horario elegido</button><a class="boton" href="#agenda">Volver a la agenda</a></div></form>`,
      () => alEnviar(() => horarioElegido() && `agendada/${p}/${m}/${horarioElegido()}`)];
  },
};

function opcionesHorario(m) {
  if (!libres[m].length) return '<p>No hay horarios libres hoy con este médico: elige otro.</p>';
  return libres[m].map((h, i) => `<label><input type="radio" name="horario" value="${h}"${i ? '' : ' checked'}> Mié 28/10 · ${franja(h)}</label>`).join('');
}

function alEnviar(destino) {
  document.getElementById('paso').addEventListener('submit', (e) => {
    e.preventDefault();
    const ruta = destino();
    if (ruta) location.hash = ruta;
  });
}
const horarioElegido = () => document.querySelector('[name=horario]:checked')?.value;

function prepararHorarios(p) {
  const select = document.getElementById('medico');
  const pintar = () => {
    document.getElementById('horarios').innerHTML = opcionesHorario(select.value);
    avisar(`${libres[select.value].length} horarios libres con ${MEDICOS[select.value][0]}.`);
  };
  select.addEventListener('change', pintar);
  alEnviar(() => horarioElegido() && `nueva/${p}/${select.value}/${horarioElegido()}`);
  pintar();
}

function prepararRevision(p, m, h) {
  const motivo = document.getElementById('motivo');
  document.getElementById('paso').addEventListener('submit', (e) => {
    e.preventDefault();
    borrador = motivo.value.trim();
    if (!borrador) {
      motivo.setAttribute('aria-invalid', 'true');
      document.getElementById('error-motivo').textContent = 'Error: escribe el motivo de la cita (R-06).';
      motivo.focus();
      return;
    }
    const tomado = document.getElementById('simular-conflicto').checked;
    location.hash = `${tomado ? 'ocupado' : 'agendada'}/${p}/${m}/${h}`;
  });
}

function avisar(texto) { document.getElementById('estado').textContent = texto; }

function mostrar() {
  const [nombre, ...args] = (location.hash.slice(1) || 'agenda').split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.agenda)(...args);
  avisar('');
  document.getElementById('contenido').innerHTML = html;
  document.title = `${titulo} · Citas · Hospital HIS`;
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
