// Prototipo navegable de Pacientes (semana 11; semana 12: edición sin el documento, cambio B-4): una sola aplicación para escritorio y móvil.
// La interfaz NO decide reglas de la ficha: muestra lo que responde la API (simulada, con paridad probada contra
// el Micro-HIS) y traduce sus códigos a mensajes. Los permisos locales solo sirven para mostrar u ocultar acciones.
(function () {
  'use strict';
  const D = window.DATOS;
  const api = window.crearApi(D);
  const RED = { latenciaMs: 300, sinConexion: false, perderRespuesta: false };
  window.__demo = { red: RED }; // para el recorrido automático (herramientas/recorrido.cjs)

  // Todo vive en memoria: nada de pacientes en localStorage ni en la URL (P-3, R-D1).
  const est = { sesion: null, busqueda: { texto: '', fecha: '', resultado: null, error: null }, registro: null, ficha: null, edicion: null, aviso: null };

  // ---------- utilidades ----------
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const MAPA_TILDES = { 'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ü': 'u', 'ñ': 'n' };
  // La MISMA normalización del backend (Texto::normalizar), para decidir cuándo pedir motivo (semana 9, BL-01)
  const normalizar = (t) => String(t).trim().toLowerCase().replace(/[áéíóúüñ]/g, (c) => MAPA_TILDES[c]).replace(/\s+/g, ' ');
  const fecha = (iso) => (iso ? iso.split('-').reverse().join('/') : '');
  const SEXO = { female: 'Femenino', male: 'Masculino', other: 'Otro', unknown: 'Desconocido' };
  const puede = (permiso) => (D.permisos[`${est.sesion.actor}|${est.sesion.hospital}`] || []).includes(permiso);
  const persona = () => D.personas[est.sesion.actor];
  const anunciar = (txt, urgente = false) => { const n = $(urgente ? '#alerta' : '#vivo'); n.textContent = ''; setTimeout(() => { n.textContent = txt; }, 30); };
  const claveNueva = () => 'reg-' + Array.from(crypto.getRandomValues(new Uint8Array(8)), (b) => b.toString(16).padStart(2, '0')).join('');

  async function llamar(metodo, url, cuerpo = null, cabeceras = {}) {
    pintarRed('lenta-temporizador');
    const t = setTimeout(() => pintarRed('lenta'), 2000);
    try {
      const r = await api.solicitarRed(metodo, url, { token: est.sesion.token, tenant: est.sesion.hospital, cuerpo, cabeceras }, RED);
      pintarRed();
      return r;
    } catch (e) {
      pintarRed(e.red);
      return { estado: 0, red: e.red, cuerpo: {} };
    } finally { clearTimeout(t); }
  }

  // ---------- marco: cabecera, red, acción fija y navegación ----------
  function pintarRed(estado) {
    const n = $('#red');
    if (estado === 'lenta-temporizador') return;
    if (RED.sinConexion || estado === 'sin-conexion') {
      n.innerHTML = '<div class="red" role="status"><span aria-hidden="true">⦸</span> Sin conexión. Lo que ves se cargó antes; para buscar o guardar hace falta conexión. <button type="button" data-accion="reintentar-red">Reintentar</button></div>';
    } else if (estado === 'lenta') {
      n.innerHTML = '<div class="red lenta" role="status"><span aria-hidden="true">◔</span> Conexión lenta. Seguimos intentando; no cierres esta pantalla.</div>';
    } else n.innerHTML = '';
  }
  function pintarMarco(def, volver) {
    const barra = $('#barra'), nav = $('#nav');
    if (!est.sesion) { barra.hidden = true; nav.hidden = true; document.body.classList.add('sin-nav'); return; }
    const p = persona();
    barra.hidden = false;
    barra.innerHTML = `${volver ? `<a class="atras btn texto" href="${volver}" aria-label="Volver" style="color:#fff">‹</a>` : ''}
      <span class="hospital-etiqueta" aria-label="Hospital activo: ${esc(D.hospitales[est.sesion.hospital])}">${esc(D.hospitales[est.sesion.hospital])}</span>
      <span class="rol-actual">${esc(p.rol)}</span>
      <span class="avatar" role="img" aria-label="${esc(p.nombre)}, ${esc(p.rol)}" style="background:${p.color}">${esc(p.iniciales)}</span>`;
    const conNav = !def.tarea;
    document.body.classList.toggle('sin-nav', !conNav);
    nav.hidden = !conNav;
    const secciones = [['#/pacientes', '🔎', 'Pacientes', 'Pacientes'], ...(puede('pacientes.registrar') ? [['#/pacientes/nuevo', '＋', 'Registrar', 'Registrar']] : []), ['#/mas', '☰', 'Más', 'Más']];
    nav.innerHTML = secciones.map(([href, ico, texto, seccion]) =>
      `<a href="${href}" ${def.seccion === seccion ? 'aria-current="page"' : ''}><span class="ico" aria-hidden="true">${ico}</span>${texto}</a>`).join('');
  }
  function accionFija(html) { const a = $('#accion'); a.hidden = !html; a.innerHTML = html || ''; }

  // ---------- rutas ----------
  const RUTAS = [
    { patron: /^\/ingresar$/, vista: vIngresar, titulo: 'Ingresar', publica: true, tarea: true },
    { patron: /^\/pacientes$/, vista: vBuscar, titulo: 'Pacientes', seccion: 'Pacientes' },
    { patron: /^\/pacientes\/nuevo$/, vista: vRegistrar, titulo: 'Registrar paciente', seccion: 'Registrar', tarea: true, permiso: 'pacientes.registrar', volver: '#/pacientes' },
    { patron: /^\/pacientes\/(P-\d{6})$/, vista: vFicha, titulo: 'Ficha del paciente', seccion: 'Pacientes', volver: '#/pacientes' },
    { patron: /^\/pacientes\/(P-\d{6})\/editar$/, vista: vEditar, titulo: 'Editar paciente', tarea: true, permiso: 'pacientes.editar' },
    { patron: /^\/mas$/, vista: vMas, titulo: 'Más', seccion: 'Más' },
  ];
  async function navegar() {
    cerrarDialogo(false);
    const ruta = location.hash.slice(1) || '/pacientes';
    if (!est.sesion && ruta !== '/ingresar') { location.replace('#/ingresar'); return; }
    let def = null, params = [];
    for (const r of RUTAS) { const m = ruta.match(r.patron); if (m) { def = r; params = m.slice(1); break; } }
    if (!def) { location.replace('#/pacientes'); return; }
    if (def.permiso && !puede(def.permiso)) { def = { titulo: 'Acceso denegado', vista: vDenegado }; }
    pintarMarco(def, def.volver === undefined ? null : (def.vista === vEditar ? null : def.volver));
    accionFija('');
    document.title = `${def.titulo} · HIS`;
    await def.vista(...params);
    const h1 = $('#contenido h1');
    if (h1) { h1.tabIndex = -1; h1.focus(); }
  }
  window.addEventListener('hashchange', navegar);

  // ---------- vistas ----------
  function vIngresar() {
    $('#contenido').innerHTML = `<h1>Ingresar al prototipo</h1>
      <p class="sub">Elige con qué rol recorrerlo. Hospital Regional de Oriente. Datos ficticios.</p>
      ${Object.entries(D.personas).map(([id, p]) => `<button type="button" class="persona" data-accion="ingresar" data-id="${id}">
        <span class="avatar" aria-hidden="true" style="background:${p.color}">${esc(p.iniciales)}</span>
        <span><strong>${esc(p.nombre)}</strong><br><span class="meta">${esc(p.rol)}</span></span></button>`).join('')}`;
  }

  function vDenegado() {
    $('#contenido').innerHTML = `<h1>Acceso denegado</h1><div class="aviso error" role="alert">
      <p style="margin:0">No tienes permiso para esta acción. Si lo necesitas, pídelo a tu supervisor.</p></div>
      <a class="btn bloque" href="#/pacientes">Volver a Pacientes</a>`;
  }

  // Un solo campo: código (P-…), documento (13 dígitos) o nombre. El backend aplica la regla real (CriterioBusqueda).
  function consultaDe(texto, fechaIso) {
    const t = texto.trim(), dpi = t.replace(/[\s-]/g, '');
    const q = new URLSearchParams();
    if (/^p-\d+$/i.test(t)) q.set('codigo', t);
    else if (/^\d{13}$/.test(dpi)) { q.set('tipo_documento', 'DPI'); q.set('numero_documento', dpi); }
    else { q.set('nombre', t); if (fechaIso) q.set('nacimiento', fechaIso); }
    return q.toString();
  }
  function vBuscar() {
    const b = est.busqueda;
    $('#contenido').innerHTML = `<div class="fila"><h1>Pacientes</h1>
        <button type="button" class="btn texto icono derecha" aria-label="Ayuda: cómo buscar pacientes" aria-expanded="false" data-accion="ayuda">?</button></div>
      <p class="aviso atencion" id="ayuda-busqueda" hidden>Busca por el código de la pulsera (P-…), por el documento completo o por al menos 3 letras del nombre. Mostramos hasta 20 resultados con datos mínimos. Los pacientes con identidad protegida no aparecen por nombre.</p>
      <form id="form-buscar" role="search" aria-label="Buscar paciente" novalidate>
        <label for="buscar">Código, documento o nombre</label>
        <input id="buscar" type="search" value="${esc(b.texto)}" autocomplete="off" enterkeyhint="search" aria-describedby="buscar-ayuda ${b.error ? 'buscar-error' : ''}" ${b.error ? 'aria-invalid="true"' : ''}>
        <p class="ayuda" id="buscar-ayuda" style="margin-top:-6px">Código, documento completo o al menos 3 letras del nombre.</p>
        ${b.error ? `<p class="error" id="buscar-error">${esc(b.error)}</p>` : ''}
        <details class="mas" ${b.fecha ? 'open' : ''}><summary>Más criterios</summary>
          <label for="nacimiento">Fecha de nacimiento</label><input id="nacimiento" type="date" value="${esc(b.fecha)}"></details>
        <button class="btn primario bloque" type="submit">Buscar</button>
      </form>
      <div id="resultados">${resultadosHtml()}</div>`;
    if (puede('pacientes.registrar')) accionFija('<a class="btn primario" href="#/pacientes/nuevo">＋ Registrar paciente</a>');
  }
  function resultadosHtml() {
    const r = est.busqueda.resultado;
    if (!r) return '';
    if (r.red) return `<div class="aviso error" role="alert"><h2>No pudimos buscar</h2><p>Revisa tu conexión. Tus criterios se conservan.</p><button type="button" class="btn bloque" data-accion="buscar-otra-vez">Reintentar</button></div>`;
    if (r.total === 0) {
      return `<div class="tarjeta"><h2>No encontramos pacientes con esos datos</h2>
        <p class="meta">Revisa el documento o prueba con el código de la pulsera.${puede('pacientes.registrar') ? ' Si buscaste bien y no está, regístralo.' : ' Los pacientes protegidos solo aparecen por código o documento.'}</p>
        ${puede('pacientes.registrar') ? '<a class="btn bloque" href="#/pacientes/nuevo">Registrar paciente nuevo</a>' : ''}</div>`;
    }
    const doc = (p) => (p.identidad_protegida && p.nacimiento === null ? '<span class="reservado">Reservado</span>'
      : p.identificador ? `Documento •••• ${esc(p.identificador.slice(-4))}` : '<span class="etiqueta-provisional">Sin documento</span>');
    const etiqueta = (p) => (p.identidad_protegida ? ' <span class="etiqueta-protegida">◆ Protegida</span>' : '');
    return `<p class="meta" style="margin:16px 0 8px">${r.total} ${r.total === 1 ? 'resultado' : 'resultados'} · máximo 20</p>
      <ul class="solo-movil" style="list-style:none;padding:0;margin:0">${r.resultados.map((p) => `<li><a class="resultado" href="#/pacientes/${p.codigo}">
        <div><div class="principal">${esc(p.nombre)}${etiqueta(p)}</div><div class="meta">${p.codigo}${p.nacimiento ? ' · ' + fecha(p.nacimiento) : ''}</div><div class="meta">${doc(p)}</div></div>
        <span class="flecha" aria-hidden="true">›</span></a></li>`).join('')}</ul>
      <table class="tabla"><caption class="oculto-visual">Resultados de la búsqueda</caption>
        <thead><tr><th scope="col">Código</th><th scope="col">Nombre</th><th scope="col">Nacimiento</th><th scope="col">Documento</th></tr></thead>
        <tbody>${r.resultados.map((p) => `<tr><td><a href="#/pacientes/${p.codigo}">${p.codigo}</a></td><td>${esc(p.nombre)}${etiqueta(p)}</td>
          <td>${p.nacimiento ? fecha(p.nacimiento) : '<span class="reservado">Reservado</span>'}</td><td>${doc(p)}</td></tr>`).join('')}</tbody></table>`;
  }
  async function buscar() {
    const b = est.busqueda;
    b.texto = $('#buscar').value; b.fecha = $('#nacimiento').value; b.error = null;
    const t = b.texto.trim();
    if (!/^p-\d+$/i.test(t) && !/^\d{13}$/.test(t.replace(/[\s-]/g, '')) && (t.match(/\p{L}/gu) || []).length < 3) {
      b.error = 'Usa el código, el documento completo o al menos 3 letras del nombre.'; b.resultado = null; vBuscar(); $('#buscar').focus(); return;
    }
    $('#resultados').innerHTML = '<div aria-busy="true"><div class="tarjeta"><div class="esq" style="width:60%"></div><div class="esq" style="width:40%"></div></div><p class="meta">Buscando…</p></div>';
    const r = await llamar('GET', '/api/v1/pacientes?' + consultaDe(t, b.fecha));
    if (!$('#resultados')) return; // el usuario ya cambió de pantalla (lo detectó recorrido.cjs)
    if (r.estado === 422) { b.error = r.cuerpo.ayuda || Object.values(r.cuerpo.campos || {})[0] || 'Revisa el criterio.'; b.resultado = null; vBuscar(); $('#buscar').focus(); return; }
    b.resultado = r.red ? { red: r.red } : r.cuerpo;
    $('#resultados').innerHTML = resultadosHtml();
    anunciar(r.red ? 'No pudimos buscar: sin conexión.' : b.resultado.total === 0 ? 'No encontramos pacientes con esos datos.' : `${b.resultado.total} ${b.resultado.total === 1 ? 'resultado' : 'resultados'}.`);
  }

  // ---------- registro en 3 pasos ----------
  const CAMPOS_PASO = { nombres: 1, apellidos: 1, nacimiento: 1, sexo: 1, documento: 2, telefono: 2 };
  function vRegistrar() {
    if (!est.registro) est.registro = { paso: 1, datos: { nombres: '', apellidos: '', fecha_nacimiento: '', sexo: '', sinDocumento: false, tipo: 'DPI', numero: '', telefono: '' }, errores: {}, resultado: null, clave: null, motivo: '' };
    pintarRegistro();
  }
  function campo(id, etiqueta, valor, o = {}) {
    const e = est.registro.errores[o.error || id];
    const desc = [o.ayuda ? `${id}-ayuda` : '', e ? `${id}-error` : ''].filter(Boolean).join(' ');
    return `<label for="${id}">${etiqueta}</label>
      <input id="${id}" ${o.tipo ? `type="${o.tipo}"` : ''} ${o.modo ? `inputmode="${o.modo}"` : ''} value="${esc(valor)}" autocomplete="off" ${o.requerido ? 'aria-required="true"' : ''} ${desc ? `aria-describedby="${desc}"` : ''} ${e ? 'aria-invalid="true"' : ''}>
      ${o.ayuda ? `<p class="ayuda" id="${id}-ayuda">${o.ayuda}</p>` : ''}${e ? `<p class="error" id="${id}-error">${esc(e)}</p>` : ''}`;
  }
  function pintarRegistro() {
    const R = est.registro, d = R.datos;
    const pasos = `<ol class="pasos" aria-hidden="true">${[1, 2, 3].map((n) => `<li class="${n <= R.paso ? 'hecho' : ''}"></li>`).join('')}</ol>`;
    let cuerpo = '';
    if (R.resultado) cuerpo = resultadoRegistroHtml();
    else if (R.paso === 1) {
      cuerpo = `<p class="paso-texto">Paso 1 de 3 · ¿Quién es?</p><p class="meta">Los campos con * son obligatorios.</p>
        ${est.busqueda.texto ? `<p class="meta">Buscaste: «${esc(est.busqueda.texto)}».</p>` : ''}
        ${campo('nombres', 'Nombres *', d.nombres, { requerido: true })}${campo('apellidos', 'Apellidos *', d.apellidos, { requerido: true })}
        ${campo('fecha_nacimiento', 'Fecha de nacimiento *', d.fecha_nacimiento, { tipo: 'date', requerido: true, error: 'nacimiento' })}
        <label for="sexo">Sexo *</label><select id="sexo" aria-required="true" ${R.errores.sexo ? 'aria-invalid="true"' : ''}><option value="">Elige una opción</option>
          ${Object.entries(SEXO).map(([v, t]) => `<option value="${v}" ${d.sexo === v ? 'selected' : ''}>${t}</option>`).join('')}</select>
        ${R.errores.sexo ? `<p class="error">${esc(R.errores.sexo)}</p>` : ''}`;
    } else if (R.paso === 2) {
      cuerpo = `<p class="paso-texto">Paso 2 de 3 · Documento y contacto</p>
        <label class="casilla"><input type="checkbox" id="sin-documento" ${d.sinDocumento ? 'checked' : ''}> No tiene documento ahora (identidad provisional)</label>
        <div id="bloque-documento" ${d.sinDocumento ? 'hidden' : ''}>
          <label for="tipo">Tipo de documento</label><select id="tipo"><option value="DPI" ${d.tipo === 'DPI' ? 'selected' : ''}>DPI</option><option value="PASAPORTE" ${d.tipo === 'PASAPORTE' ? 'selected' : ''}>Pasaporte</option></select>
          ${campo('numero', 'Número', d.numero, { modo: 'numeric', ayuda: '13 dígitos. Lo usamos para no registrar dos veces a la misma persona.', error: 'documento' })}</div>
        ${campo('telefono', 'Teléfono (opcional)', d.telefono, { tipo: 'tel', modo: 'tel' })}`;
    } else {
      cuerpo = `<p class="paso-texto">Paso 3 de 3 · Revisa antes de registrar</p><div class="tarjeta"><dl class="datos">
        <dt>Nombre</dt><dd>${esc(d.nombres)} ${esc(d.apellidos)}</dd><dt>Nacimiento</dt><dd>${fecha(d.fecha_nacimiento)}</dd>
        <dt>Sexo</dt><dd>${SEXO[d.sexo] || ''}</dd><dt>Documento</dt><dd>${d.sinDocumento || !d.numero ? '<span class="etiqueta-provisional">Sin documento</span>' : `${d.tipo === 'DPI' ? 'DPI' : 'Pasaporte'} •••• ${esc(d.numero.slice(-4))}`}</dd>
        <dt>Teléfono</dt><dd>${esc(d.telefono) || '—'}</dd></dl>
        <button type="button" class="btn texto" data-accion="ir-paso" data-paso="1" style="padding:0">Corregir datos</button></div>`;
    }
    $('#contenido').innerHTML = `<h1>Registrar paciente</h1>${R.resultado ? '' : pasos}${cuerpo}`;
    if (R.resultado) accionFija('');
    else if (R.paso === 1) accionFija('<a class="btn" href="#/pacientes">Cancelar</a><button type="button" class="btn primario" data-accion="siguiente">Siguiente</button>');
    else if (R.paso === 2) accionFija('<button type="button" class="btn" data-accion="ir-paso" data-paso="1">Atrás</button><button type="button" class="btn primario" data-accion="siguiente">Siguiente</button>');
    else accionFija('<button type="button" class="btn primario" data-accion="registrar">Registrar paciente</button>');
  }
  function leerPaso() {
    const R = est.registro, d = R.datos, v = (id) => ($('#' + id) ? $('#' + id).value : undefined);
    if (R.paso === 1) Object.assign(d, { nombres: v('nombres'), apellidos: v('apellidos'), fecha_nacimiento: v('fecha_nacimiento'), sexo: v('sexo') });
    if (R.paso === 2) Object.assign(d, { sinDocumento: $('#sin-documento').checked, tipo: v('tipo'), numero: v('numero'), telefono: v('telefono') });
  }
  function siguiente() {
    leerPaso();
    const R = est.registro, d = R.datos, e = {};
    if (R.paso === 1) {
      if (!d.nombres.trim()) e.nombres = 'Este dato es obligatorio.';
      if (!d.apellidos.trim()) e.apellidos = 'Este dato es obligatorio.';
      if (!d.fecha_nacimiento) e.nacimiento = 'Este dato es obligatorio.';
      else if (d.fecha_nacimiento > D.hoy) e.nacimiento = 'No puede ser una fecha futura.';
      if (!d.sexo) e.sexo = 'Elige una opción; si no lo sabes, «Desconocido».';
    }
    R.errores = e;
    if (Object.keys(e).length === 0) R.paso += 1;
    pintarRegistro();
    if (Object.keys(e).length) { const f = $('[aria-invalid="true"]'); if (f) f.focus(); anunciar('Revisa los campos marcados.', true); } else $('#contenido h1').focus();
  }
  function cuerpoRegistro(extra = {}) {
    const d = est.registro.datos;
    return { nombres: d.nombres, apellidos: d.apellidos, fecha_nacimiento: d.fecha_nacimiento, sexo: d.sexo, telefono: d.telefono || null,
      documento: d.sinDocumento || !d.numero ? null : { tipo: d.tipo, numero: d.numero, ...(d.tipo === 'PASAPORTE' ? { pais: 'GTM' } : {}) }, ...extra };
  }
  // Una Idempotency-Key por INTENTO; «Reintentar» reutiliza la misma (medido en la semana 10: no duplica).
  async function registrar(reintento = false, extra = {}) {
    const R = est.registro;
    if (!reintento) { R.clave = claveNueva(); R.extra = extra; }
    R.resultado = { tipo: 'enviando' };
    accionFija('<span class="btn" aria-disabled="true">Registrando…</span>');
    const r = await llamar('POST', '/api/v1/pacientes', cuerpoRegistro(R.extra), { 'Idempotency-Key': R.clave });
    if (r.estado === 201) {
      est.aviso = r.cabeceras && r.cabeceras['Idempotent-Replayed'] ? `Paciente registrado · ${r.cuerpo.codigo}. Ya se había registrado: no se creó otro.` : `Paciente registrado · ${r.cuerpo.codigo}`;
      est.registro = null;
      location.hash = '#/pacientes/' + r.cuerpo.codigo;
      return;
    }
    if (r.red === 'sin-respuesta') R.resultado = { tipo: 'incierto' };
    else if (r.red) R.resultado = { tipo: 'sin-conexion' };
    else if (r.estado === 409 && r.cuerpo.error === 'PACIENTE_EXISTENTE') R.resultado = { tipo: 'existente', codigo: r.cuerpo.codigo };
    else if (r.estado === 409 && r.cuerpo.error === 'POSIBLE_DUPLICADO') { R.candidatos = r.cuerpo.candidatos; R.resultado = { tipo: 'coincidencia', candidatos: R.candidatos }; }
    else if (r.estado === 422 && r.cuerpo.error === 'DATOS_INVALIDOS') {
      R.errores = r.cuerpo.campos; R.resultado = null;
      R.paso = Math.min(...Object.keys(r.cuerpo.campos).map((k) => CAMPOS_PASO[k] || 1));
    } else if (r.estado === 422 && r.cuerpo.error === 'MOTIVO_REQUERIDO') R.resultado = { tipo: 'coincidencia', candidatos: R.candidatos || [], faltaMotivo: true };
    else R.resultado = { tipo: 'denegado' };
    pintarRegistro();
    const foco = $('[role="alert"] h2') || $('[aria-invalid="true"]');
    if (foco) { foco.tabIndex = -1; foco.focus(); }
  }
  function resultadoRegistroHtml() {
    const R = est.registro, res = R.resultado;
    if (res.tipo === 'enviando') return '<div aria-busy="true"><p class="meta" role="status">Registrando…</p></div>';
    if (res.tipo === 'incierto') return `<section class="aviso atencion" role="alert"><h2>No sabemos si se registró</h2>
      <p>Se cortó la conexión antes de recibir la respuesta. Puedes reintentar sin riesgo: si ya se registró, verás el mismo código y no se creará otro paciente.</p>
      <div class="acciones"><button type="button" class="btn primario bloque" data-accion="reintentar-registro">Reintentar</button></div><p class="meta">Tus datos siguen en esta pantalla.</p></section>`;
    if (res.tipo === 'sin-conexion') return `<section class="aviso error" role="alert"><h2>Sin conexión</h2><p>No se envió nada. Registrar requiere conexión; tus datos siguen aquí.</p>
      <div class="acciones"><button type="button" class="btn primario bloque" data-accion="reintentar-registro">Intentar de nuevo</button></div></section>`;
    if (res.tipo === 'denegado') return '<section class="aviso error" role="alert"><h2>No tienes permiso para esta acción</h2><p>Si lo necesitas, pídelo a tu supervisor.</p></section>';
    if (res.tipo === 'existente') {
      // Error crítico: si el titular es protegido, el servidor manda «RESERVADO» y la UI no revela nada más.
      if (res.codigo === 'RESERVADO') return `<section class="aviso error" role="alert"><h2>Esta persona ya está registrada</h2>
        <p>El documento pertenece a un paciente con identidad protegida. Por confidencialidad no podemos mostrar quién es.</p>
        <p><strong>No la registres de nuevo ni cambies los datos para evitar este aviso:</strong> llama a supervisión (ext. 2140).</p>
        <div class="acciones"><a class="btn primario bloque" href="#/pacientes" data-accion="cancelar-registro">Volver a Pacientes</a></div></section>`;
      return `<section class="aviso error" role="alert"><h2>Ya existe un paciente con ese documento</h2><p>Pertenece a <strong>${esc(res.codigo)}</strong>. No se puede registrar dos veces a la misma persona.</p>
        <div class="acciones"><a class="btn primario bloque" href="#/pacientes/${esc(res.codigo)}" data-accion="cancelar-registro">Abrir ficha ${esc(res.codigo)}</a>
        <button type="button" class="btn bloque" data-accion="revisar-documento">Revisar el número de documento</button></div></section>`;
    }
    // coincidencia probable (R-03)
    const lista = res.candidatos.map((c) => (c === 'RESERVADO' ? '<li class="resultado"><div><div class="principal reservado">Reservado</div><div class="meta">Paciente con identidad protegida</div></div></li>'
      : `<li><a class="resultado" href="#/pacientes/${esc(c)}"><div><div class="principal">${esc(c)}</div><div class="meta">Abrir y comparar</div></div><span class="flecha" aria-hidden="true">›</span></a></li>`)).join('');
    const confirmar = puede('pacientes.confirmar_duplicado') ? `<label for="motivo-coincidencia">Motivo * (queda en la bitácora)</label>
        <textarea id="motivo-coincidencia" ${res.faltaMotivo ? 'aria-invalid="true" aria-describedby="motivo-coincidencia-error"' : ''}>${esc(R.motivo)}</textarea>
        ${res.faltaMotivo ? '<p class="error" id="motivo-coincidencia-error">Escribe por qué es otra persona; queda en la bitácora.</p>' : ''}
        <button type="button" class="btn bloque" data-accion="confirmar-coincidencia">Registrar como otra persona</button>`
      : '<p class="meta">Si es otra persona, solo un supervisor puede confirmarlo. Supervisión de turno: ext. 2140. No cambies los datos para evitar el aviso: crearías un duplicado.</p>';
    return `<section class="aviso atencion" role="alert"><h2>Hay pacientes parecidos</h2><p>Mismo nombre y fecha de nacimiento. Ábrelos y compara antes de seguir.</p>
      <ul style="list-style:none;padding:0;margin:0">${lista}</ul>${confirmar}
      <div class="acciones"><a class="btn primario bloque" href="#/pacientes" data-accion="cancelar-registro">Es la misma: buscar su ficha</a></div></section>`;
  }

  // ---------- ficha ----------
  async function vFicha(codigo) {
    $('#contenido').innerHTML = '<div aria-busy="true"><div class="esq" style="width:70%;height:24px"></div><div class="tarjeta"><div class="esq" style="width:50%"></div><div class="esq" style="width:60%"></div></div><p class="meta" role="status">Cargando la ficha…</p></div>';
    const r = await llamar('GET', '/api/v1/pacientes/' + codigo);
    if (location.hash !== '#/pacientes/' + codigo) return; // respuesta tardía de una pantalla que ya se dejó
    if (r.red) { $('#contenido').innerHTML = `<h1>Ficha ${esc(codigo)}</h1><div class="aviso error" role="alert"><p style="margin:0">No pudimos cargar la ficha. Revisa tu conexión.</p></div><a class="btn bloque" href="#/pacientes/${esc(codigo)}" data-accion="recargar">Reintentar</a>`; return; }
    if (r.estado === 404) { $('#contenido').innerHTML = `<h1>Paciente no encontrado</h1><p>No hay un paciente con ese código en este hospital.</p><a class="btn bloque" href="#/pacientes">Volver a buscar</a>`; return; }
    if (r.estado !== 200) { vDenegado(); return; }
    const p = r.cuerpo.paciente;
    est.ficha = p;
    const reducida = p.identidad_protegida && p.nacimiento === null;
    const aviso = est.aviso ? `<div class="aviso ok" role="status" id="aviso-ok"><h2 style="margin:0">✓ ${esc(est.aviso)}</h2></div>` : '';
    est.aviso = null;
    const estado = { active: '● Activo', inactive: '◌ Inactivo', deceased: '✝ Fallecido' }[p.estado];
    const dato = (v) => (v === null ? '<span class="reservado">Reservado</span>' : esc(v));
    $('#contenido').innerHTML = `${aviso}<h1 style="margin-bottom:4px">${esc(p.nombre)}</h1>
      <div class="fila" style="margin-bottom:12px;flex-wrap:wrap"><span class="estado activo">${estado}</span>
        ${p.identidad_protegida ? '<span class="etiqueta-protegida">◆ Protegida</span>' : ''}${p.identidad_provisional && !reducida ? '<span class="etiqueta-provisional">Sin documento</span>' : ''}
        <span class="meta">${p.codigo} · versión ${p.version}</span></div>
      ${reducida ? '<div class="aviso atencion"><p style="margin:0">Puedes atender a esta paciente. Sus datos personales están protegidos.</p></div>' : ''}
      <div class="dos-columnas"><div class="tarjeta"><h2>Identidad</h2><dl class="datos">
        <dt>Nacimiento</dt><dd>${p.nacimiento ? fecha(p.nacimiento) : dato(null)}</dd><dt>Sexo</dt><dd>${SEXO[p.sexo]}</dd>
        <dt>Documento</dt><dd>${reducida ? dato(null) : p.identificador ? `${p.tipo_documento === 'DPI' ? 'DPI' : 'Pasaporte'} •••• ${esc(p.identificador.slice(-4))}` : 'Sin documento'}</dd></dl></div>
      <div class="tarjeta"><h2>Contacto</h2><dl class="datos"><dt>Teléfono</dt><dd>${reducida ? dato(null) : esc(p.telefono || '—')}</dd></dl>
        <p class="meta extra">Cada cambio queda en la bitácora del hospital.</p></div></div>
      ${reducida ? '<p class="meta">No la encontrarás por nombre: búscala por el código de la pulsera.</p>' : ''}`;
    const editable = puede('pacientes.editar') && !reducida && (p.estado === 'active' || puede('pacientes.editar_restringido'));
    if (editable) accionFija(`<a class="btn primario" href="#/pacientes/${p.codigo}/editar">Editar datos</a>`);
    if (aviso) anunciar($('#aviso-ok').textContent);
  }

  // ---------- edición ----------
  async function vEditar(codigo) {
    if (!est.ficha || est.ficha.codigo !== codigo || !est.edicion || est.edicion.codigo !== codigo) {
      const r = await llamar('GET', '/api/v1/pacientes/' + codigo);
      if (r.estado !== 200) { location.replace('#/pacientes/' + codigo); return; }
      est.ficha = r.cuerpo.paciente;
      est.edicion = null;
    }
    const p = est.ficha;
    if (!est.edicion) {
      // Contrato 1.2.0 (cambio B-4): el detalle trae nombres y apellidos por separado; el documento no hace falta.
      est.edicion = { codigo, version: p.version, valores: { nombres: p.nombres, apellidos: p.apellidos,
        fecha_nacimiento: p.nacimiento, sexo: p.sexo, telefono: p.telefono || '' }, errores: {}, resultado: null, marcados: [] };
      est.edicion.original = { ...est.edicion.valores };
    }
    pintarEdicion();
  }
  const cambiaIdentidad = (E) => normalizar(E.valores.nombres + ' ' + E.valores.apellidos) !== normalizar(E.original.nombres + ' ' + E.original.apellidos)
    || E.valores.fecha_nacimiento !== E.original.fecha_nacimiento;
  function pintarEdicion() {
    const E = est.edicion, v = E.valores, p = est.ficha;
    const marca = (k) => (E.marcados.includes(k) ? 'marcado' : '');
    const f = (id, etiqueta, o = {}) => {
      const e = E.errores[o.error || id];
      return `<label for="e-${id}">${etiqueta}</label><input id="e-${id}" class="${marca(id)}" ${o.tipo ? `type="${o.tipo}"` : ''} ${o.modo ? `inputmode="${o.modo}"` : ''} value="${esc(v[id])}" autocomplete="off"
        ${o.ayuda || e ? `aria-describedby="${o.ayuda ? `e-${id}-ayuda` : ''} ${e ? `e-${id}-error` : ''}"` : ''} ${e ? 'aria-invalid="true"' : ''} ${o.requerido ? 'aria-required="true"' : ''}>
        ${o.ayuda ? `<p class="ayuda" id="e-${id}-ayuda">${o.ayuda}</p>` : ''}${e ? `<p class="error" id="e-${id}-error">${esc(e)}</p>` : ''}`;
    };
    const res = E.resultado;
    const avisoRes = !res ? '' : res.tipo === 'conflicto' ? `<section class="aviso atencion" role="alert"><h2>Otra persona modificó esta ficha</h2>
        <p>Ahora está en la versión ${res.vigente}. Tus cambios no se guardaron.</p>
        <div class="acciones"><button type="button" class="btn primario bloque" data-accion="reaplicar">Ver la versión nueva y reaplicar mis cambios</button></div>
        <p class="meta">Tus cambios se marcarán para que los revises; no se guardan fuera de esta pantalla.</p></section>`
      : res.tipo === 'incierto' ? `<section class="aviso atencion" role="alert"><h2>No sabemos si se guardó</h2><p>Se cortó la conexión. Recarga la ficha para comprobarlo: si tu cambio no está, podrás aplicarlo otra vez.</p>
        <div class="acciones"><a class="btn primario bloque" href="#/pacientes/${E.codigo}" data-accion="recargar">Recargar la ficha</a></div></section>`
      : res.tipo === 'denegado' ? '<section class="aviso error" role="alert"><h2>No tienes permiso para esta acción</h2><p>Si lo necesitas, pídelo a tu supervisor.</p></section>'
      : res.tipo === 'sin-conexion' ? '<section class="aviso error" role="alert"><h2>Sin conexión</h2><p>No se envió nada. Guardar requiere conexión; tus cambios siguen aquí.</p></section>' : '';
    const pideMotivo = cambiaIdentidad(E);
    $('#contenido').innerHTML = `<h1>Editar · ${E.codigo}</h1><p class="sub">Versión ${E.version} · los cambios quedan en la bitácora</p>${avisoRes}
      <p class="meta">Los campos con * son obligatorios.</p>
      <div class="dos-col"><div>${f('nombres', 'Nombres *', { requerido: true })}</div><div>${f('apellidos', 'Apellidos *', { requerido: true })}</div>
      <div>${f('fecha_nacimiento', 'Fecha de nacimiento *', { tipo: 'date', requerido: true, error: 'nacimiento' })}</div>
      <div><label for="e-sexo">Sexo *</label><select id="e-sexo">${Object.entries(SEXO).map(([k, t]) => `<option value="${k}" ${v.sexo === k ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
      <div>${f('telefono', 'Teléfono', { tipo: 'tel', modo: 'tel', ayuda: 'De 8 a 15 dígitos. Cambiarlo no pide motivo.' })}</div>
      <div><span class="etiqueta">Documento</span><p style="margin:0">${p.identificador ? `${p.tipo_documento === 'DPI' ? 'DPI' : 'Pasaporte'} •••• ${esc(p.identificador.slice(-4))}` : 'Sin documento'}</p>
        <p class="ayuda">No cambia al guardar. Corregir el documento se hace con supervisión.</p></div></div>
      <div id="bloque-motivo" ${pideMotivo ? '' : 'hidden'} role="status" aria-live="polite">
        <label for="e-motivo">Motivo del cambio de identidad *</label><textarea id="e-motivo" aria-describedby="e-motivo-ayuda" ${E.errores.motivo ? 'aria-invalid="true"' : ''}>${esc(E.motivo || '')}</textarea>
        <p class="${E.errores.motivo ? 'error' : 'ayuda'}" id="e-motivo-ayuda">${E.errores.motivo ? esc(E.errores.motivo) : 'Cambiaste un dato de identidad. Escribe por qué (por ejemplo, «error de digitación»).'}</p></div>`;
    accionFija(`<a class="btn" href="#/pacientes/${E.codigo}">Cancelar</a><button type="button" class="btn primario" data-accion="revisar-cambios">Revisar cambios</button>`);
  }
  function leerEdicion() {
    const E = est.edicion, v = E.valores, g = (id) => ($('#e-' + id) ? $('#e-' + id).value : v[id]);
    Object.assign(v, { nombres: g('nombres'), apellidos: g('apellidos'), fecha_nacimiento: g('fecha_nacimiento'), sexo: g('sexo'), telefono: g('telefono') });
    E.motivo = $('#e-motivo') ? $('#e-motivo').value : E.motivo;
  }
  function revisarCambios() {
    leerEdicion();
    const E = est.edicion, v = E.valores, e = {};
    if (!v.nombres.trim()) e.nombres = 'Este dato es obligatorio.';
    if (!v.apellidos.trim()) e.apellidos = 'Este dato es obligatorio.';
    if (cambiaIdentidad(E) && !(E.motivo || '').trim()) e.motivo = 'Escribe por qué cambias un dato de identidad; queda en la bitácora.';
    E.errores = e;
    if (Object.keys(e).length) { pintarEdicion(); const f = $('[aria-invalid="true"]'); if (f) f.focus(); anunciar('Revisa los campos marcados.', true); return; }
    const etiquetas = { nombres: 'Nombres', apellidos: 'Apellidos', fecha_nacimiento: 'Nacimiento', sexo: 'Sexo', telefono: 'Teléfono' };
    const mostrar = (k, x) => (k === 'sexo' ? SEXO[x] : k === 'fecha_nacimiento' ? fecha(x) : x || '—');
    const cambios = Object.keys(etiquetas).filter((k) => (v[k] || '') !== (E.original[k] || ''));
    abrirDialogo(`<h2 id="titulo-dialogo">Confirmar cambios</h2>
      ${cambios.length ? cambios.map((k) => `<div class="cambio"><span class="meta">${etiquetas[k]}</span><span class="antes">${esc(mostrar(k, E.original[k]))}</span><span class="despues">${esc(mostrar(k, v[k]))}</span></div>`).join('') : '<p>No cambiaste ningún dato.</p>'}
      <p class="meta">${cambiaIdentidad(E) ? `Motivo: ${esc(E.motivo)}` : 'Sin cambios de identidad: no hace falta motivo.'}</p>
      <div class="acciones"><button type="button" class="btn primario" data-accion="guardar">Guardar</button><button type="button" class="btn" data-accion="cerrar-dialogo">Volver a editar</button></div>`);
  }
  async function guardar() {
    const E = est.edicion, v = E.valores, p = est.ficha;
    cerrarDialogo(false);
    accionFija('<span class="btn" aria-disabled="true">Guardando…</span>');
    const cuerpo = { nombres: v.nombres, apellidos: v.apellidos, fecha_nacimiento: v.fecha_nacimiento, sexo: v.sexo, telefono: v.telefono || null,
      motivo: (E.motivo || '').trim() || null }; // sin «documento»: ausente = sin cambio (contrato 1.2.0)
    const r = await llamar('PUT', '/api/v1/pacientes/' + E.codigo, cuerpo, { 'If-Match': `"${E.version}"` }); // el PUT no se reintenta solo
    E.resultado = null;
    if (r.estado === 200) { est.aviso = 'Datos actualizados'; est.edicion = null; location.hash = '#/pacientes/' + E.codigo; return; }
    if (r.red === 'sin-respuesta') E.resultado = { tipo: 'incierto' };
    else if (r.red) E.resultado = { tipo: 'sin-conexion' };
    else if (r.estado === 409 && r.cuerpo.error === 'VERSION_DESACTUALIZADA') E.resultado = { tipo: 'conflicto', vigente: r.cuerpo.version_vigente };
    else if (r.estado === 409 && r.cuerpo.error === 'IDENTIFICADOR_EN_USO') E.errores = { documento: 'Ese documento pertenece a otro paciente. Revisa el número.' };
    else if (r.estado === 422 && r.cuerpo.error === 'DATOS_INVALIDOS') E.errores = r.cuerpo.campos;
    else if (r.estado === 422 && r.cuerpo.error === 'MOTIVO_REQUERIDO') E.errores = { motivo: 'Escribe por qué cambias un dato de identidad; queda en la bitácora.' };
    else E.resultado = { tipo: 'denegado' };
    pintarEdicion();
    const foco = $('[role="alert"] h2') || $('[aria-invalid="true"]');
    if (foco) { foco.tabIndex = -1; foco.focus(); }
  }
  // Conflicto 409: carga la versión nueva y vuelve a poner lo que el usuario cambió, marcado (BL-02: sin portapapeles).
  async function reaplicar() {
    const E = est.edicion, mios = { ...E.valores }, original = E.original;
    const r = await llamar('GET', '/api/v1/pacientes/' + E.codigo);
    if (r.estado !== 200) return;
    est.ficha = r.cuerpo.paciente;
    est.edicion = null;
    await vEditar(E.codigo);
    const N = est.edicion;
    N.marcados = Object.keys(mios).filter((k) => (mios[k] || '') !== (original[k] || ''));
    N.marcados.forEach((k) => { N.valores[k] = mios[k]; });
    N.motivo = E.motivo;
    pintarEdicion();
    anunciar(`Cargamos la versión ${N.version}. Tus cambios quedaron marcados para revisar.`);
  }

  // ---------- más: sesión y red simulada ----------
  function vMas() {
    const p = persona();
    const opcion = (valor, texto) => `<label class="opcion"><input type="radio" name="red" value="${valor}" ${modoRed() === valor ? 'checked' : ''}> ${texto}</label>`;
    $('#contenido').innerHTML = `<h1>Más</h1><div class="tarjeta"><strong>${esc(p.nombre)}</strong><div class="meta">${esc(p.rol)} · ${esc(D.hospitales[est.sesion.hospital])}</div></div>
      <fieldset class="tarjeta"><legend>Red simulada (solo para la demostración)</legend>
        ${opcion('normal', 'Normal')}${opcion('lenta', 'Lenta (3 s por petición)')}${opcion('sin-conexion', 'Sin conexión')}${opcion('perder', 'Perder la próxima respuesta (el servidor sí procesa)')}</fieldset>
      <button type="button" class="btn bloque" data-accion="salir">Cerrar sesión</button>`;
  }
  const modoRed = () => (RED.sinConexion ? 'sin-conexion' : RED.perderRespuesta ? 'perder' : RED.latenciaMs > 1000 ? 'lenta' : 'normal');

  // ---------- diálogo (hoja inferior en móvil, centrado en escritorio) ----------
  let focoPrevio = null;
  function abrirDialogo(html) {
    focoPrevio = document.activeElement;
    $('#capa-dialogo').innerHTML = `<div class="velo" data-accion="cerrar-dialogo"></div><div class="hoja" role="dialog" aria-modal="true" aria-labelledby="titulo-dialogo"><div class="asa" aria-hidden="true"></div>${html}</div>`;
    $('#app').inert = true;
    const t = $('#titulo-dialogo'); t.tabIndex = -1; t.focus();
  }
  function cerrarDialogo(devolverFoco = true) {
    if (!$('#capa-dialogo').innerHTML) return;
    $('#capa-dialogo').innerHTML = '';
    $('#app').inert = false;
    if (devolverFoco && focoPrevio) focoPrevio.focus();
  }

  // ---------- eventos ----------
  document.addEventListener('click', (ev) => {
    const el = ev.target.closest('[data-accion]');
    if (!el) return;
    const a = el.dataset.accion;
    if (a === 'saltar') { $('#contenido').focus(); return; }
    if (a === 'ingresar') {
      const id = el.dataset.id;
      est.sesion = { actor: id, token: D.personas[id].token, hospital: D.tokens[D.personas[id].token][1] };
      location.hash = '#/pacientes';
    } else if (a === 'salir') { Object.assign(est, { sesion: null, busqueda: { texto: '', fecha: '', resultado: null, error: null }, registro: null, ficha: null, edicion: null }); location.hash = '#/ingresar'; }
    else if (a === 'ayuda') { const n = $('#ayuda-busqueda'); n.hidden = !n.hidden; el.setAttribute('aria-expanded', String(!n.hidden)); }
    else if (a === 'buscar-otra-vez') buscar();
    else if (a === 'siguiente') siguiente();
    else if (a === 'ir-paso') { leerPaso(); est.registro.paso = Number(el.dataset.paso); est.registro.errores = {}; pintarRegistro(); $('#contenido h1').focus(); }
    else if (a === 'registrar') registrar();
    else if (a === 'reintentar-registro') registrar(est.registro.resultado.tipo === 'incierto');
    else if (a === 'confirmar-coincidencia') { est.registro.motivo = $('#motivo-coincidencia').value; registrar(false, { confirmar_coincidencia: true, motivo: est.registro.motivo }); }
    else if (a === 'revisar-documento') { est.registro.resultado = null; est.registro.paso = 2; est.registro.errores = { documento: 'Ese documento ya pertenece a otro paciente. Revisa el número.' }; pintarRegistro(); $('#numero').focus(); }
    else if (a === 'cancelar-registro') est.registro = null;
    else if (a === 'revisar-cambios') revisarCambios();
    else if (a === 'guardar') guardar();
    else if (a === 'cerrar-dialogo') cerrarDialogo();
    else if (a === 'reaplicar') reaplicar();
    else if (a === 'recargar') { est.edicion = null; if (location.hash === el.getAttribute('href')) navegar(); }
    else if (a === 'reintentar-red') { RED.sinConexion = false; pintarRed(); }
  });
  document.addEventListener('submit', (ev) => { if (ev.target.id === 'form-buscar') { ev.preventDefault(); buscar(); } });
  document.addEventListener('change', (ev) => {
    if (ev.target.name === 'red') {
      const v = ev.target.value;
      Object.assign(RED, { sinConexion: v === 'sin-conexion', perderRespuesta: v === 'perder', latenciaMs: v === 'lenta' ? 3000 : 300 });
      pintarRed(); anunciar('Red simulada: ' + ev.target.parentElement.textContent.trim());
    }
    if (ev.target.id === 'sin-documento') $('#bloque-documento').hidden = ev.target.checked;
  });
  // El motivo aparece en cuanto cambia un dato de identidad (misma regla del backend), sin esperar a enviar.
  document.addEventListener('input', (ev) => {
    if (!est.edicion || !ev.target.id.startsWith('e-') || ev.target.id === 'e-motivo') return;
    leerEdicion();
    const b = $('#bloque-motivo');
    if (b) b.hidden = !cambiaIdentidad(est.edicion);
  });
  document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape' && $('#capa-dialogo').innerHTML) cerrarDialogo(); });

  navegar();
})();
