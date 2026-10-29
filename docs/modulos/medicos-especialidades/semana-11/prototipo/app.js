// Prototipo navegable de Médicos: pantallas, roles y estados sobre la API simulada. Sin dependencias.
const A = window.ApiSimulada, $ = (s) => document.querySelector(s);
const e = (t) => String(t ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const NIVEL = { admin: 'Administración', recepcion: 'Recepción', medico: 'Médico' };
const TABS = [['#/disponibilidad', 'Disponibilidad', ['admin', 'recepcion', 'medico']], ['#/medicos', 'Médicos', ['admin']], ['#/ausencias', 'Ausencias', ['admin']]];
const vista = $('#vista'); let rol = 'admin';
const opciones = (lista, sel) => lista.map(([v, t]) => `<option value="${e(v)}"${v === sel ? ' selected' : ''}>${e(t)}</option>`).join('');
const campo = (id, rotulo, ctl, ayuda = '') => `<div><label for="${id}">${rotulo}</label>${ctl}${ayuda ? `<div class="h" id="${id}-a">${ayuda}</div>` : ''}<div class="m" id="${id}-e" role="alert"></div></div>`;
const fallo = (id, texto) => { const c = $('#' + id); c.setAttribute('aria-invalid', 'true'); c.setAttribute('aria-describedby', id + '-e'); $('#' + id + '-e').textContent = texto; c.focus(); };
const limpiar = () => document.querySelectorAll('[aria-invalid]').forEach((c) => { c.removeAttribute('aria-invalid'); $('#' + c.id + '-e').textContent = ''; });
const aviso = (clase, html) => { const a = $('#aviso'); a.innerHTML = `<div class="${clase}" ${clase === 'al' ? 'role="alert"' : ''} tabindex="-1">${html}</div>`; a.firstChild.focus(); };
const medicosOpc = () => A.listarMedicos().map((m) => [m.id, m.nombre]);
const ESP = [['MI', 'Medicina interna'], ['PED', 'Pediatría']];
const espId = (c) => c === 'MI' ? 'esp-mi' : 'esp-ped';
async function enviar(boton, f) {   // «Guardando…» y botón inactivo mientras responde; el corte de conexión no reintenta solo
  const t = boton.textContent; boton.disabled = true; boton.textContent = 'Guardando…';
  try { return await f(); } catch (x) { return { estado: 0 }; } finally { boton.disabled = false; boton.textContent = t; }
}
const vistas = {
  disponibilidad() {
    vista.innerHTML = `<h1>Disponibilidad</h1><p class="h">* Campo obligatorio. Máximo 14 días.</p><form class="g" id="f" novalidate>
      <div class="full">${campo('esp', 'Especialidad *', `<select id="esp">${opciones(ESP)}</select>`)}</div>
      ${campo('desde', 'Desde *', '<input id="desde" type="date" value="2026-11-02">')}${campo('hasta', 'Hasta *', '<input id="hasta" type="date" value="2026-11-06">')}
      <div class="full"><button id="buscar">Buscar</button></div></form><div id="aviso" aria-live="polite"></div><div id="res" aria-live="polite"></div>`;
    $('#f').onsubmit = async (ev) => {
      ev.preventDefault(); limpiar(); $('#aviso').innerHTML = ''; $('#res').innerHTML = '<p>Cargando…</p>'; $('#buscar').disabled = true;
      let r; try { r = await A.disponibilidad(rol, $('#esp').value, $('#desde').value, $('#hasta').value); } catch (x) { r = { estado: 0 }; }
      $('#buscar').disabled = false; $('#res').innerHTML = '';
      if (r.estado === 422) { return fallo('desde', 'Empiece hoy o después; máximo 14 días.'); }
      if (r.estado !== 200) {
        aviso('al', r.estado === 503 ? '<b>No podemos ofrecer franjas ahora</b><br>Citas no responde. Intente en unos minutos.' : r.estado === 0 ? '<b>Sin conexión</b><br>No se muestran franjas guardadas para no duplicar citas.' : `<b>${e(r.cuerpo.mensaje)}</b>`);
        return $('#res').insertAdjacentHTML('beforeend', '<button class="s" id="otra" type="button">Reintentar</button>'), ($('#otra').onclick = () => $('#f').requestSubmit());
      }
      const fr = r.cuerpo.franjas; if (!fr.length) { return $('#res').innerHTML = '<p>Sin franjas en estas fechas.</p>'; }
      const por = {}; fr.forEach((x) => (por[`${x.inicio.slice(0, 10)}|${x.medico}`] ||= []).push(x.inicio.slice(11)));
      $('#res').innerHTML = `<p class="h">${fr.length} franjas de 30 min.</p><ul class="cards t">${Object.entries(por).map(([k, h]) => `<li class="card"><b>${e(k.split('|')[1])}</b><span class="h">${e(k.split('|')[0])}</span><div class="chips">${h.map((x) => `<button type="button" class="franja">${x}</button>`).join('')}</div></li>`).join('')}</ul>
        <table class="t"><tr><th>Fecha</th><th>Médico</th><th>Franjas libres (30 min)</th></tr>${Object.entries(por).map(([k, h]) => `<tr><td>${e(k.split('|')[0])}</td><td>${e(k.split('|')[1])}</td><td>${h.join(' · ')}</td></tr>`).join('')}</table>`;
    };
  },
  medicos() {
    vista.innerHTML = `<h1>Médicos</h1><a class="btn" href="#/medicos/nuevo">Registrar médico</a><ul class="cards">${A.listarMedicos().map((m) => `<li class="card"><b>${e(m.nombre)}</b><span class="h">Colegiado ${e(m.colegiado)} · ${e(m.especialidades.join(', ') || 'sin especialidad')}</span><div class="chips"><a class="btn s" href="#/medicos/${e(m.id)}/especialidades">Especialidades</a></div></li>`).join('')}</ul>`;
  },
  nuevo() {
    vista.innerHTML = `<h1>Registrar médico</h1><p class="h">* Campo obligatorio</p><form id="f" novalidate>
      ${campo('usuario', 'Usuario del sistema *', `<select id="usuario">${opciones(A.usuariosLibres().map(([u, n]) => [u, n]))}</select>`)}
      ${campo('col', 'Colegiado *', '<input id="col" inputmode="numeric" autocomplete="off">', 'Número de colegiado activo')}
      ${campo('esp', 'Especialidad principal', `<select id="esp"><option value="">(se asocia después)</option>${opciones(ESP.map(([c, n]) => [espId(c), n]))}</select>`)}
      ${campo('desde', 'Vigente desde *', '<input id="desde" type="date" value="2026-11-02">')}<button id="ok" style="margin-top:14px">Registrar médico</button></form><div id="aviso"></div>`;
    $('#f').onsubmit = async (ev) => {
      ev.preventDefault(); limpiar(); $('#aviso').innerHTML = '';
      const r = await enviar($('#ok'), () => A.registrarMedico(rol, { usuario_id: $('#usuario').value, colegiado: $('#col').value, especialidad_id: $('#esp').value || undefined, desde: $('#desde').value }));
      if (r.estado === 201) { const n = A.db.usuarios[$('#usuario').value]; return aviso('ok', `<b>${e(n)} quedó registrada.</b> <a href="#/medicos/${e(r.cuerpo.medico_id)}/especialidades">Asociar otra especialidad</a>`); }
      if (r.estado === 0) { return aviso('al', 'Sin conexión. Compruebe en «Médicos» si quedó registrado antes de reintentar.'); }
      fallo(r.cuerpo.codigo === 'medico_duplicado' || !$('#col').value.trim() ? 'col' : 'esp', r.cuerpo.codigo === 'medico_duplicado' ? 'Ese colegiado ya tiene perfil de médico. Revise el número.' : r.cuerpo.mensaje);
    };
  },
  especialidades(id) {
    const m = A.db.medicos.find((x) => x.id === id); if (!m) { return (vista.innerHTML = '<h1>Médico no encontrado</h1>'); }
    vista.innerHTML = `<h1>Especialidades de ${e(A.db.usuarios[m.usuario])}</h1><ul class="cards">${m.asig.map((a) => `<li class="card"><b>${e(A.db.especialidades[a.esp].nombre)}</b><span class="h">Desde ${e(a.desde)} · ${a.hasta ? 'hasta ' + e(a.hasta) : 'abierta'}</span></li>`).join('')}</ul>
      <form id="f" novalidate>${campo('esp', 'Especialidad *', `<select id="esp">${opciones(ESP.map(([c, n]) => [espId(c), n]))}</select>`)}
      ${campo('desde', 'Desde *', '<input id="desde" type="date" value="2026-11-02">')}${campo('hasta', 'Hasta (vacío = abierta)', '<input id="hasta" type="date">')}<button id="ok">Asociar</button></form><div id="aviso"></div>`;
    $('#f').onsubmit = async (ev) => {
      ev.preventDefault(); limpiar(); $('#aviso').innerHTML = '';
      const r = await enviar($('#ok'), () => A.asociarEspecialidad(rol, id, { especialidad_id: $('#esp').value, desde: $('#desde').value, hasta: $('#hasta').value || null }));
      if (r.estado === 201) { return vistas.especialidades(id), aviso('ok', '<b>Especialidad asociada.</b>'); }
      if (r.estado === 0) { return aviso('al', 'Sin conexión. Revise la lista antes de reintentar.'); }
      fallo(r.cuerpo.codigo === 'vigencia_invalida' ? 'hasta' : 'desde', r.cuerpo.codigo === 'especialidad_duplicada' ? 'Esa especialidad ya está vigente en esas fechas. Cierre la anterior o elija otro rango.' : r.cuerpo.mensaje);
    };
  },
  ausencias() {
    vista.innerHTML = `<h1>Registrar ausencia</h1><p class="h">* Campo obligatorio</p><form id="f" novalidate>${campo('med', 'Médico *', `<select id="med">${opciones(medicosOpc())}</select>`)}
      ${campo('desde', 'Primer día *', '<input id="desde" type="date" value="2026-11-02">')}${campo('hasta', 'Último día *', '<input id="hasta" type="date" value="2026-11-08">')}${campo('motivo', 'Motivo', '<input id="motivo" value="Congreso">')}
      <button id="rev" style="margin-top:14px">Revisar</button></form><div id="aviso" aria-live="polite"></div>`;
    const datos = () => ({ desde: $('#desde').value, hasta: $('#hasta').value ? new Date(Date.parse($('#hasta').value) + 864e5).toISOString().slice(0, 10) : '', motivo: $('#motivo').value });   // el último día es inclusivo; el servidor usa [desde, hasta)
    $('#f').onsubmit = async (ev) => {
      ev.preventDefault(); limpiar();
      const r = await enviar($('#rev'), () => A.registrarAusencia(rol, $('#med').value, datos(), true));
      if (r.estado === 0) { return aviso('al', 'Sin conexión. No se guardó nada; reintente.'); }
      if (r.estado !== 200) { return fallo('hasta', 'El último día debe ser igual o posterior al primero.'); }
      const c = r.cuerpo.citas_en_conflicto;
      $('#aviso').innerHTML = (c.length ? `<div class="al" role="alert" tabindex="-1" id="conf"><b>Hay ${c.length} citas en esas fechas</b><ul>${c.map((x) => `<li>${e(x)}</li>`).join('')}</ul><span class="h">El módulo no las cancela: Citas debe reprogramarlas.</span></div>
        <button type="button" class="s" id="copiar">Copiar lista para Citas</button><label class="chk"><input type="checkbox" id="ent"> Entiendo que Citas aún no fue avisada</label>` : '<div class="ok">Sin citas en conflicto.</div>')
        + '<button id="reg" style="margin-top:14px"' + (c.length ? ' disabled' : '') + '>Registrar ausencia</button>';
      if (c.length) { $('#conf').focus(); $('#ent').onchange = (x) => ($('#reg').disabled = !x.target.checked); $('#copiar').onclick = () => navigator.clipboard?.writeText(c.join('\n')); }
      $('#reg').onclick = async () => {
        const g = await enviar($('#reg'), () => A.registrarAusencia(rol, $('#med').value, datos()));
        if (g.estado === 201) { return aviso('ok', `<b>Ausencia registrada.</b> ${g.cuerpo.citas_en_conflicto.length} citas deben reprogramarse. <a href="#/disponibilidad">Ver disponibilidad</a>`); }
        aviso('al', g.estado === 0 ? 'No sabemos si se guardó. Compruebe en Disponibilidad antes de reintentar (no se reintenta solo).' : e(g.cuerpo.mensaje));
      };
    };
  },
};
function ruta() {
  const [, a, b, c] = location.hash.split('/'); const clave = a || 'disponibilidad';
  const permitido = (TABS.find((t) => t[0] === '#/' + clave) || (clave === 'medicos' ? TABS[1] : TABS[0]))[2].includes(rol);
  $('#tabs').innerHTML = TABS.filter((t) => t[2].includes(rol)).map((t) => `<a href="${t[0]}"${'#/' + clave === t[0] ? ' aria-current="page"' : ''}>${t[1]}</a>`).join('');
  if (!permitido) { vista.innerHTML = '<h1>Sin permiso</h1><div class="al" role="alert">Su rol no puede hacer esta operación.</div><a class="btn" href="#/disponibilidad">Ir a Disponibilidad</a>'; }
  else if (clave === 'medicos') { b === 'nuevo' ? vistas.nuevo() : c === 'especialidades' ? vistas.especialidades(b) : vistas.medicos(); }
  else { (vistas[clave] || vistas.disponibilidad)(); }
  vista.focus();
}
$('#rol').onchange = (x) => { rol = x.target.value; ruta(); };
['citasCaida', 'sinConexion'].forEach((k) => ($('#' + k).onchange = (x) => (A.db.simular[k] = x.target.checked)));
addEventListener('hashchange', ruta); ruta();
