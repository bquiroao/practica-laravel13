// Semana 11 · Prototipo navegable de Notas SOAP: una sola app para escritorio y teléfono.
// Rutas por hash con guard; cada pantalla pide sus datos a la API simulada y reacciona a sus respuestas.
const PERSONAS = { 'token-mendez': ['Dra. Méndez', 'dra-mendez', 'Médico'], 'token-lopez': ['Dr. López', 'dr-lopez', 'Médico'], 'token-ana': ['Ana Pérez', 'enf-ana', 'Enfermería'] };
const CATALOGO = { 'J18.9': 'Neumonía no especificada', 'J18.0': 'Bronconeumonía no especificada', 'J15.9': 'Neumonía bacteriana no especificada', 'R50.9': 'Fiebre no especificada', 'I10': 'Hipertensión esencial' }; // B-2
const SECCIONES = [['subjetivo', 'S · Subjetivo'], ['objetivo', 'O · Objetivo'], ['analisis', 'A · Análisis'], ['plan', 'P · Plan']];
const PASOS = ['S y O', 'A y P', 'Diagnóstico', 'Revisar y guardar'];
const est = { token: null, borrador: null, aviso: null, ref: null };
const $ = (s) => document.querySelector(s);
const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => `&#${c.charCodeAt(0)};`);
const fecha = (iso) => new Date(iso).toLocaleString('es-GT', { dateStyle: 'short', timeStyle: 'short' });
const escribe = () => PERSONAS[est.token]?.[2] === 'Médico';
const diag = (d) => `${esc(d.code)} · ${esc(d.description)}`;
function pintar(titulo, html, acciones = '', clinica = true) {
  document.title = `${titulo} · HIS`;
  const persona = PERSONAS[est.token];
  $('#usuario').textContent = persona ? `${persona[1]} · ${persona[2]}` : '';
  $('#abrir-demo').hidden = !persona;
  $('#paciente').hidden = !clinica;
  $('#vista').innerHTML = html + (acciones && `<div class="accion-fija">${acciones}</div>`);
  ($('#aviso') || $('h1')).focus();
}
function aviso() { // Avisos de éxito persistentes con «×» (BL-08); error e incierto se anuncian como alerta.
  if (!est.aviso) return '';
  const [tipo, titulo, texto] = est.aviso; est.aviso = null;
  const cerrar = tipo === 'exito' ? '<button type="button" class="cerrar" aria-label="Cerrar aviso" onclick="this.parentNode.remove()">×</button>' : '';
  return `<div class="aviso ${tipo}" id="aviso" tabindex="-1" role="${tipo === 'exito' ? 'status' : 'alert'}">${cerrar}${titulo}<p>${texto}</p></div>`;
}
async function pedir(metodo, ruta, cuerpo, clave) {
  const desde = location.hash;
  try {
    const r = await API.pedir(metodo, ruta, est.token, cuerpo, clave);
    if (location.hash !== desde) return null; // la persona ya cambió de pantalla: no se pinta una vista vieja
    if (r.estado === 403) { est.ref = r.ref; location.hash = '#/acceso-denegado'; return null; }
    if (r.estado === 401) { location.hash = '#/ingresar'; return null; }
    return r;
  } catch { return { estado: 0 }; } // sin respuesta: resultado incierto (R-M4)
}
function ocupado(boton, texto) { boton.setAttribute('aria-disabled', 'true'); boton.setAttribute('aria-busy', 'true'); boton.textContent = texto; } // I-01, BL-14
function vIngresar() {
  est.token = null; est.borrador = null;
  pintar('Ingresar', '<h1 tabindex="-1">¿Con quién entra?</h1><p class="secundario">Personas de demostración del Micro-HIS.</p>' +
    Object.entries(PERSONAS).map(([t, [nombre, , rol]]) => `<button type="button" class="boton" data-token="${t}">${nombre} · ${rol}</button>`).join(''), '', false);
  document.querySelectorAll('[data-token]').forEach((b) => { b.onclick = () => { est.token = b.dataset.token; location.hash = '#/notas'; }; });
}
async function vNotas() {
  const r = await pedir('GET', '/expedientes/exp-carlos/notas-soap'); if (!r) return;
  if (!r.estado) return pintar('Sin conexión', '<h1 tabindex="-1">Notas SOAP</h1><div class="aviso incierto" id="aviso" tabindex="-1" role="alert">No pudimos cargar las notas.<p>Revise la conexión y vuelva a intentar.</p></div>', '<a class="boton primario" href="#/notas" onclick="ir()">Reintentar</a>');
  const items = [...r.cuerpo.items].reverse();
  const estado = (n) => n.status === 'signed' ? '<span class="estado firmada">✓ Firmada</span>' : '<span class="estado borrador">✎ Borrador</span>'; // icono + texto
  const codigos = (n) => n.diagnosis_codes.map((c) => `${esc(c)} · ${esc(CATALOGO[c] || '')}`).join(', '); // BL-15
  const ver = (n) => `<a class="ver" href="#/notas/${n.clinical_note_id}" aria-label="Ver nota versión ${n.version_no} de ${esc(n.author_user_ref_id)}">Ver nota</a>`; // BL-16
  pintar('Notas del expediente', `<h1 tabindex="-1">Notas SOAP</h1>${aviso()}<div class="tarjetas">${items.map((n) =>
    `<div class="tarjeta"><div class="fila"><b>Versión ${n.version_no}</b>${estado(n)}</div><p>${codigos(n)}</p>
     <p class="secundario">${esc(n.author_user_ref_id)} · ${fecha(n.created_at)}</p>${ver(n)}</div>`).join('')}</div>
    <table class="tabla"><caption class="secundario">Notas del expediente exp-carlos</caption><thead><tr><th>Versión</th><th>Estado</th><th>Diagnóstico</th><th>Autor</th><th>Fecha</th><th>Acción</th></tr></thead>
    <tbody>${items.map((n) => `<tr><td>v${n.version_no}</td><td>${estado(n)}</td><td>${codigos(n)}</td><td>${esc(n.author_user_ref_id)}</td><td>${fecha(n.created_at)}</td><td>${ver(n)}</td></tr>`).join('')}</tbody></table>`,
    escribe() ? '<a class="boton primario" href="#/notas/nueva">+ Nueva nota</a>' : ''); // enfermería no ve el botón (R-02)
}
function vNueva() {
  const b = (est.borrador ??= { paso: 0, subjetivo: '', objetivo: '', analisis: '', plan: '', diagnostico: null, clave: null, busca: '' });
  const area = ([id, etiqueta]) => `<label for="${id}">${etiqueta} *</label><textarea id="${id}">${esc(b[id])}</textarea><p class="error-campo" id="e-${id}" hidden>Complete esta sección: no puede ir vacía.</p>`;
  const opciones = Object.entries(CATALOGO).filter(([c, d]) => b.busca && `${c} ${d}`.toLowerCase().includes(b.busca.toLowerCase()));
  const cuerpo = [
    () => SECCIONES.slice(0, 2).map(area).join(''),
    () => SECCIONES.slice(2).map(area).join(''),
    () => `<label for="buscar">Buscar diagnóstico CIE-10 *</label><input id="buscar" type="search" autocomplete="off" role="combobox" aria-controls="lista" aria-expanded="${opciones.length > 0}" value="${esc(b.busca)}">
      <p class="ayuda">Escriba el código o el nombre y elija de la lista.</p><ul class="opciones" id="lista" role="listbox" aria-label="Diagnósticos">${opciones.map(([c, d]) =>
      `<li role="option" tabindex="0" data-codigo="${c}" aria-selected="${b.diagnostico?.code === c}">${c} · ${esc(d)}</li>`).join('')}</ul>
      <p class="error-campo" id="e-diagnostico" hidden>Elija un diagnóstico de la lista.</p>${b.diagnostico ? `<p>Elegido: <b>${diag(b.diagnostico)}</b></p>` : ''}`,
    () => `<dl class="resumen"><dt>Paciente</dt><dd>Carlos R. · exp-carlos · epi-100</dd>${SECCIONES.map(([id, et]) => `<dt>${et}</dt><dd>${esc(b[id])}</dd>`).join('')}<dt>Diagnóstico</dt><dd>${diag(b.diagnostico)} · principal</dd></dl>`,
  ][b.paso];
  pintar('Nueva nota SOAP', `<h1 tabindex="-1">Nueva nota SOAP</h1><p class="pasos">Paso ${b.paso + 1} de 4 · ${PASOS[b.paso]}</p>
    <div class="progreso"><div style="width:${(b.paso + 1) * 25}%"></div></div>${aviso()}<p class="ayuda">* obligatorio. Lo escrito no se guarda en el teléfono (D-5).</p>${cuerpo()}`,
    `<button type="button" class="boton" id="atras">${b.paso ? 'Atrás' : 'Cancelar'}</button><button type="button" class="boton primario" id="seguir">${b.paso === 3 ? 'Guardar nota' : 'Siguiente'}</button>`);
  const leer = () => SECCIONES.forEach(([id]) => { if ($(`#${id}`)) b[id] = $(`#${id}`).value; });
  $('#buscar')?.addEventListener('input', (e) => { b.busca = e.target.value; vNueva(); $('#buscar').focus(); $('#buscar').setSelectionRange(99, 99); });
  document.querySelectorAll('[data-codigo]').forEach((li) => { li.onclick = li.onkeydown = (e) => { if (e.key && e.key !== 'Enter') return; b.diagnostico = { code: li.dataset.codigo, description: CATALOGO[li.dataset.codigo], type: 'primary' }; vNueva(); }; });
  $('#atras').onclick = () => { leer(); if (b.paso) { b.paso--; vNueva(); } else { est.borrador = null; location.hash = '#/notas'; } };
  $('#seguir').onclick = async (e) => {
    leer();
    const faltan = b.paso < 2 ? SECCIONES.slice(b.paso * 2, b.paso * 2 + 2).filter(([id]) => !b[id].trim()).map(([id]) => id) : b.paso === 2 && !b.diagnostico ? ['diagnostico'] : [];
    faltan.forEach((id) => { $(`#e-${id}`).hidden = false; $(`#${id === 'diagnostico' ? 'buscar' : id}`).setAttribute('aria-invalid', 'true'); });
    if (faltan.length) return $(`#${faltan[0] === 'diagnostico' ? 'buscar' : faltan[0]}`).focus();
    if (b.paso < 3) { b.paso++; if (b.paso === 3) b.clave ??= crypto.randomUUID(); return vNueva(); } // R-M5: una clave por guardado
    if (e.target.getAttribute('aria-busy')) return;
    ocupado(e.target, 'Guardando nota…');
    const r = await pedir('POST', '/notas-soap', { ...b, diagnostico: b.diagnostico }, b.clave); if (!r) return;
    if (r.estado === 201) { est.borrador = null; est.aviso = ['exito', '✓ Nota guardada como borrador.', 'Aparece en las notas del expediente. Puede firmarla ahora o después.']; location.hash = `#/notas/${r.cuerpo.clinical_note_id}`; return; }
    est.aviso = r.estado ? ['error', 'No se guardó la nota.', esc(r.cuerpo.error.message)]
      : ['incierto', 'No sabemos si la nota se guardó: se cortó la conexión.', 'Puede reintentar sin miedo: la solicitud lleva la misma clave y el servidor no duplica la nota.'];
    vNueva();
    $('#seguir').textContent = r.estado ? 'Guardar nota' : 'Reintentar';
  };
}
async function vDetalle(id, conocida) {
  const r = conocida ? { estado: 200, cuerpo: conocida } : await pedir('GET', `/notas-soap/${id}`); if (!r) return;
  if (r.estado !== 200) return pintar('Nota no disponible', `<h1 tabindex="-1">Nota no disponible</h1><p>${r.estado ? 'La nota no existe o ya no está disponible.' : 'No pudimos cargar la nota: revise la conexión.'}</p>`, '<a class="boton" href="#/notas">Volver a las notas</a>');
  const n = r.cuerpo;
  const mia = n.author_user_ref_id === PERSONAS[est.token][1];
  const firmada = n.status === 'signed';
  const acciones = conocida ? '<button type="button" class="boton primario" id="consultar">Consultar estado de la nota</button>' // I-06: la firma no se repite sola
    : mia && !firmada ? '<button type="button" class="boton primario" id="abrir-firma">Firmar nota</button>' : ''; // corregir: M4 de la semana 10
  pintar(`Nota versión ${n.version_no}`, `${aviso()}<h1 tabindex="-1">Nota versión ${n.version_no} ${firmada ? '<span class="estado firmada">✓ Firmada</span>' : '<span class="estado borrador">✎ Borrador</span>'}</h1>
    <p class="secundario">${esc(n.author_user_ref_id)} · ${fecha(n.created_at)}</p>
    ${SECCIONES.map(([s, et]) => `<div class="seccion-soap"><b>${et[0]}</b>${esc(n.soap[s])}</div>`).join('')}<h2>Diagnóstico</h2><p>${n.diagnoses.map((d) => `${diag(d)} · principal`).join('<br>')}</p>
    ${escribe() ? '' : '<p class="secundario">Solo lectura: enfermería consulta las notas para dar seguimiento.</p>'}`, acciones);
  $('#consultar')?.addEventListener('click', async () => {
    const actual = await pedir('GET', `/notas-soap/${id}`);
    if (actual?.estado === 200 && actual.cuerpo.status === 'signed') est.aviso = ['exito', '✓ La firma sí llegó: la nota está firmada.', 'No hace falta volver a firmar.'];
    actual?.estado === 200 ? vDetalle(id) : vDetalle(id, n);
  });
  $('#abrir-firma')?.addEventListener('click', () => abrirFirma(n));
}
function abrirFirma(n) { // Hoja inferior en el teléfono, diálogo centrado desde 768 px; el fondo queda inert (I-05, BL-02)
  $('#fondo').inert = true;
  $('#capa').innerHTML = `<div class="velo"></div><div class="hoja" role="dialog" aria-modal="true" aria-labelledby="t-firma"><h2 id="t-firma">¿Firmar la nota versión ${n.version_no} de Carlos R.?</h2>
    <p class="secundario">${fecha(n.created_at)} · ${diag(n.diagnoses[0])}</p><p>La firma no se puede deshacer. Si después encuentra un error, se corrige con una versión nueva.</p>
    <div class="acciones"><button type="button" class="boton" id="cancelar">Cancelar</button><button type="button" class="boton primario" id="firmar">Firmar nota</button></div></div>`;
  const cerrar = () => { $('#capa').innerHTML = ''; $('#fondo').inert = false; $('#abrir-firma')?.focus(); };
  $('#capa').onkeydown = (e) => e.key === 'Escape' && cerrar(); // Esc cierra sin cambios
  $('#cancelar').onclick = cerrar; $('#cancelar').focus();
  $('#firmar').onclick = async (e) => {
    if (e.target.getAttribute('aria-busy')) return; // un segundo clic no firma de nuevo
    ocupado(e.target, 'Firmando…');
    const r = await pedir('POST', `/notas-soap/${n.clinical_note_id}/firma`);
    cerrar();
    if (!r) return;
    if (!r.estado) { est.aviso = ['incierto', 'No sabemos si la firma llegó: se cortó la conexión.', 'No la repetimos sola. Consulte el estado de la nota antes de volver a firmar.']; return vDetalle(n.clinical_note_id, n); }
    est.aviso = r.estado === 200 ? ['exito', '✓ Nota firmada.', 'Ya no se puede modificar. Si hay un error, se corrige con una versión nueva.']
      : r.estado === 409 ? ['exito', '✓ Esta nota ya estaba firmada.', 'Su firma anterior sí llegó (R-M6).'] : ['error', 'No se firmó la nota.', esc(r.cuerpo.error.message)];
    vDetalle(n.clinical_note_id);
  };
}
function vDenegado() { // D-1: mensaje genérico; la referencia solo existe si el servidor respondió 403
  const ref = est.ref ? `<p>Si cree que debería poder hacerlo, comparta esta referencia con el administrador del hospital.</p><p class="referencia">${est.ref.replace(/-/g, ' · ')}</p>`
    : '<p>Esta sección no está disponible para su rol. Si la necesita, hable con el administrador del hospital.</p>';
  pintar('Acceso denegado', `<h1 tabindex="-1">No tiene permiso para hacer esto</h1>${ref}`, '<a class="boton primario" href="#/notas">Volver a las notas</a>', false);
  est.ref = null;
}
const RUTAS = [[/^#\/ingresar$/, vIngresar], [/^#\/notas$/, vNotas], [/^#\/notas\/nueva$/, vNueva, true], [/^#\/notas\/([\w-]+)$/, vDetalle], [/^#\/acceso-denegado$/, vDenegado]];
function ir() {
  const hash = location.hash || '#/ingresar';
  if (!est.token && hash !== '#/ingresar') { location.hash = '#/ingresar'; return; }
  const [patron, vista, escritura] = RUTAS.find(([p]) => p.test(hash)) || RUTAS[1];
  if (escritura && !escribe()) { location.hash = '#/acceso-denegado'; return; } // guard de ruta; el servidor igual vuelve a autorizar
  vista(...(hash.match(patron) || []).slice(1));
}
$('#abrir-demo').onclick = () => { const d = $('#demo'); d.hidden = !d.hidden; $('#abrir-demo').setAttribute('aria-expanded', String(!d.hidden)); };
$('#perder').onclick = () => { API.perderProximaRespuesta(); $('#demo').hidden = true; $('#anuncio').textContent = 'La próxima respuesta del servidor se perderá.'; };
$('#salir').onclick = () => { $('#demo').hidden = true; location.hash = '#/ingresar'; };
window.addEventListener('hashchange', ir);
ir();
