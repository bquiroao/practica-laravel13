'use strict';
// Prototipo navegable (semana 11). Datos FICTICIOS, iguales a micro-his/datos-ficticios.php. Sin dependencias.
// Aplica B-01..B-07 de la semana 9: confirmación, validación temprana, errores asociados al campo,
// foco gestionado, conflicto que nombra la cama, sin doble envío y etiquetas legibles.

const ETIQUETAS = { MEDICINA_INTERNA: 'Medicina interna', CIRUGIA: 'Cirugía', UCI: 'UCI', GENERAL: 'General', PEDIATRICA: 'Pediátrica',
  EMERGENCIA: 'Emergencia', CONSULTA_EXTERNA: 'Consulta externa', REFERENCIA: 'Referencia' };
const CAMAS = [
  { id: 'c-201', codigo: 'B-201', servicio: 'MEDICINA_INTERNA', tipo: 'GENERAL', aislamiento: false, operativa: true },
  { id: 'c-202', codigo: 'B-202', servicio: 'MEDICINA_INTERNA', tipo: 'GENERAL', aislamiento: false, operativa: true },
  { id: 'c-203', codigo: 'B-203', servicio: 'MEDICINA_INTERNA', tipo: 'GENERAL', aislamiento: false, operativa: false },
  { id: 'c-210', codigo: 'B-210', servicio: 'MEDICINA_INTERNA', tipo: 'GENERAL', aislamiento: true, operativa: true },
  { id: 'c-101', codigo: 'C-101', servicio: 'CIRUGIA', tipo: 'GENERAL', aislamiento: false, operativa: true },
  { id: 'c-301', codigo: 'U-01', servicio: 'UCI', tipo: 'UCI', aislamiento: false, operativa: true },
];
const PACIENTES_ACTIVOS = ['PAC-00451', 'PAC-00452', 'PAC-00453'];
const USUARIOS = { ADMISIONISTA: 'usr-adm-01', SUPERVISOR_CAMAS: 'usr-sup-01', MEDICO: 'usr-med-01' };
const ocupadas = new Set();
const admisiones = [];
let datos = { paciente: '', servicio: 'MEDICINA_INTERNA', tipo: 'GENERAL', aislamiento: false, origen: 'EMERGENCIA', cama: null };
let ultimo = null;

const $ = (sel) => document.querySelector(sel);
const esc = (v) => String(v).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const et = (v) => ETIQUETAS[v] || v;
const rol = () => $('#demo-rol').value;
const formatoValido = (c) => /^PAC-[0-9]{5}$/.test(c);
const disponibles = () => CAMAS.filter((c) => c.operativa && !ocupadas.has(c.id) && c.servicio === datos.servicio
  && c.tipo === datos.tipo && c.aislamiento === datos.aislamiento);
const opciones = (valores, actual) => valores.map((v) => `<option value="${v}"${v === actual ? ' selected' : ''}>${et(v)}</option>`).join('');

function mostrar(html, titulo, paso, foco) {
  $('#vista').innerHTML = html;
  document.title = `${titulo} · Admisión hospitalaria`;
  $('#paso').textContent = paso;
  (foco ? $(foco) : $('#vista h1')).focus(); // B-04: el foco va al título o al aviso, nunca queda en BODY
}

function inicio(error) {
  if (rol() === 'MEDICO') { // RN-06: el médico no ve la acción de admitir
    const a = admisiones[0];
    mostrar(`<h1 tabindex="-1">Consulta de admisiones</h1><p>Su rol solo permite consultar.</p>
      ${a ? `<button id="ver">Ver admisión de ${esc(a.paciente)}</button>` : '<p role="status">Aún no hay admisiones para consultar.</p>'}`,
    'Consulta', '', null);
    if (a) $('#ver').onclick = () => detalle(a);
    return;
  }
  mostrar(`<h1 tabindex="-1">Nueva admisión</h1>
    ${error ? `<div class="alerta" role="alert" id="aviso" tabindex="-1"><strong>${esc(error)}</strong></div>` : ''}
    <form id="f" novalidate>
      <label class="campo" for="paciente">Código de paciente</label>
      <input type="text" id="paciente" value="${esc(datos.paciente)}" autocapitalize="characters" autocomplete="off" aria-describedby="ayuda-paciente">
      <p class="ayuda" id="ayuda-paciente">Formato PAC-NNNNN, por ejemplo PAC-00451. No escriba nombres.</p>
      <label class="campo" for="servicio">Servicio</label><select id="servicio">${opciones(['MEDICINA_INTERNA', 'CIRUGIA', 'UCI'], datos.servicio)}</select>
      <label class="campo" for="tipo">Tipo de cama</label><select id="tipo">${opciones(['GENERAL', 'UCI', 'PEDIATRICA'], datos.tipo)}</select>
      <label class="casilla"><input type="checkbox" id="aislamiento"${datos.aislamiento ? ' checked' : ''}> Requiere aislamiento</label>
      <p class="ayuda">Las camas de aislamiento se reservan para quien lo requiere.</p>
      <label class="campo" for="origen">Origen</label><select id="origen">${opciones(['EMERGENCIA', 'CONSULTA_EXTERNA', 'REFERENCIA'], datos.origen)}</select>
      <div class="acciones"><button type="submit">Buscar camas disponibles</button></div>
    </form>`, 'Nueva admisión', 'Paso 1 de 3', error ? '#aviso' : null);
  $('#paciente').addEventListener('blur', () => validarCodigo(false)); // B-02: validación temprana
  $('#f').addEventListener('submit', (e) => {
    e.preventDefault();
    Object.assign(datos, { paciente: $('#paciente').value.trim(), servicio: $('#servicio').value, tipo: $('#tipo').value,
      aislamiento: $('#aislamiento').checked, origen: $('#origen').value });
    if (!validarCodigo(true)) return;
    if (!PACIENTES_ACTIVOS.includes(datos.paciente)) return inicio('El paciente no existe o no está activo en este hospital.');
    if (admisiones.some((a) => a.paciente === datos.paciente)) return inicio(`${datos.paciente} ya tiene una admisión activa.`);
    camas();
  });
}

function validarCodigo(enfocar) {
  const campo = $('#paciente');
  const valido = formatoValido(campo.value.trim());
  $('#error-paciente')?.remove();
  campo.setAttribute('aria-invalid', String(!valido)); // B-03: error asociado al campo
  campo.setAttribute('aria-describedby', valido ? 'ayuda-paciente' : 'error-paciente ayuda-paciente');
  if (!valido) {
    campo.insertAdjacentHTML('afterend', '<p class="error-campo" id="error-paciente">El código debe tener el formato PAC-NNNNN.</p>');
    if (enfocar) campo.focus();
  }
  return valido;
}

function camas(aviso) {
  mostrar('<h1 tabindex="-1">Buscando camas disponibles…</h1><p role="status" aria-busy="true">Cargando…</p>', 'Buscando camas', 'Paso 2 de 3', null);
  setTimeout(() => {
    const lista = disponibles();
    const encabezado = `<h1 tabindex="-1">Camas para ${esc(datos.paciente)}</h1><p class="ayuda">${et(datos.servicio)} · ${et(datos.tipo)}${datos.aislamiento ? ' · con aislamiento' : ''}</p>`
      + (aviso ? `<div class="alerta" role="alert" id="aviso" tabindex="-1"><strong>${esc(aviso)}</strong><br>Elija otra cama de la lista actualizada. Sus datos se conservaron.</div>` : '');
    if (lista.length === 0) {
      mostrar(`${encabezado}<p role="status">No hay camas disponibles para ${et(datos.servicio)} / ${et(datos.tipo)}.</p>
        <div class="acciones"><button id="cambiar">Cambiar requerimiento</button></div>`, 'Sin camas', 'Paso 2 de 3', aviso ? '#aviso' : null);
    } else {
      mostrar(`${encabezado}<form id="f"><fieldset class="tarjetas"><legend>Camas disponibles (${lista.length})</legend>
        ${lista.map((c, i) => `<label class="tarjeta"><input type="radio" name="cama" value="${c.id}"${i === 0 ? ' checked' : ''}>
          <span><strong>${c.codigo}</strong><br><span class="ayuda">${et(c.servicio)} · ${et(c.tipo)}${c.aislamiento ? ' · aislamiento' : ''}</span></span></label>`).join('')}
        </fieldset><div class="acciones"><button type="submit">Continuar</button><button type="button" class="secundario" id="cambiar">Cambiar requerimiento</button></div></form>`,
      'Elegir cama', 'Paso 2 de 3', aviso ? '#aviso' : null);
      $('#f').addEventListener('submit', (e) => { e.preventDefault(); datos.cama = CAMAS.find((c) => c.id === $('input[name=cama]:checked').value); confirmar(); });
    }
    $('#cambiar').onclick = () => inicio();
  }, 400);
}

function confirmar() { // B-01: paso de confirmación antes de registrar
  mostrar(`<h1 tabindex="-1">Confirmar admisión</h1>
    <dl class="resumen"><dt>Paciente</dt><dd>${esc(datos.paciente)}</dd><dt>Cama</dt><dd>${datos.cama.codigo} · ${et(datos.cama.servicio)}</dd>
    <dt>Origen</dt><dd>${et(datos.origen)}</dd><dt>Registra</dt><dd>${USUARIOS[rol()]}</dd></dl>
    <p class="ayuda">Si otra admisión toma la cama antes, podrá elegir otra sin volver a escribir.</p>
    <div class="acciones fija"><button id="ok">Confirmar admisión</button><button class="secundario" id="volver">Volver</button></div>`,
  'Confirmar admisión', 'Paso 3 de 3', null);
  $('#volver').onclick = () => camas();
  $('#ok').onclick = () => {
    $('#ok').disabled = true; // B-06: sin doble envío
    $('#ok').textContent = 'Guardando…';
    setTimeout(() => {
      if ($('#demo-conflicto').checked || ocupadas.has(datos.cama.id)) { // error crítico: RN-01 (409 CAMA_YA_ASIGNADA)
        ocupadas.add(datos.cama.id);
        $('#anuncio').textContent = `La cama ${datos.cama.codigo} ya fue asignada a otra admisión.`;
        return camas(`La cama ${datos.cama.codigo} ya fue asignada a otra admisión.`); // B-05: nombra la cama
      }
      ocupadas.add(datos.cama.id);
      ultimo = { paciente: datos.paciente, cama: datos.cama, origen: datos.origen, usuario: USUARIOS[rol()], rol: rol(),
        fecha: new Date().toISOString().slice(0, 16).replace('T', ' ') + ' UTC' };
      admisiones.unshift(ultimo);
      resultado();
    }, 500);
  };
}

function resultado() {
  mostrar(`<h1 tabindex="-1">Admisión ACTIVA</h1><div class="exito" role="status">
    <p>Paciente <strong>${esc(ultimo.paciente)}</strong> en cama <strong>${ultimo.cama.codigo}</strong> (${et(ultimo.cama.servicio)}).</p>
    <p class="ayuda">Registrada ${ultimo.fecha} por ${ultimo.usuario}</p></div>
    <div class="acciones"><button id="nueva">Nueva admisión</button><button class="secundario" id="detalle">Ver detalle</button></div>`,
  'Admisión registrada', 'Listo', null);
  $('#nueva').onclick = () => { datos = { ...datos, paciente: '', cama: null }; inicio(); };
  $('#detalle').onclick = () => detalle(ultimo);
}

function detalle(a) {
  const traza = rol() === 'SUPERVISOR_CAMAS'
    ? `<h2>Trazabilidad</h2><table><thead><tr><th>Evento</th><th>Usuario</th><th>Rol</th><th>Fecha</th></tr></thead><tbody>
      ${['ADMISION_CREADA', 'CAMA_ASIGNADA'].map((t) => `<tr><td>${t}</td><td>${a.usuario}</td><td>${a.rol}</td><td>${a.fecha}</td></tr>`).join('')}</tbody></table>`
    : '<p class="ayuda">La trazabilidad completa solo la ve el rol Supervisor(a) de camas.</p>';
  mostrar(`<h1 tabindex="-1">Admisión de ${esc(a.paciente)}</h1><dl class="resumen"><dt>Estado</dt><dd>ACTIVA</dd>
    <dt>Cama</dt><dd>${a.cama.codigo} · ${et(a.cama.servicio)}</dd><dt>Admitido</dt><dd>${a.fecha} por ${a.usuario}</dd></dl>${traza}
    <div class="acciones"><button id="nueva">Volver al inicio</button></div>`, 'Detalle de admisión', '', null);
  $('#nueva').onclick = () => inicio();
}

$('#demo-rol').addEventListener('change', () => inicio());
inicio();
