// Prototipo navegable de Auditoría (semana 11): una sola aplicación para escritorio y móvil.
// La interfaz NO decide reglas: muestra lo que responde la API (simulada, con paridad probada contra el Micro-HIS) y traduce
// sus códigos a los mensajes del catálogo de la semana 8 (M-xx). Los permisos locales solo muestran u ocultan secciones.
(function () {
  'use strict';
  const D = window.DATOS;
  const api = window.crearApi(D);
  const RED = { latenciaMs: 300, sinConexion: false, perderRespuesta: false, sesionVencida: false };
  window.__demo = { red: RED }; // para el recorrido automático (herramientas/recorrido.cjs)
  D.semilla(api.ahora()).forEach((s) => api.registrarSemilla(s));
  api.borrarDirecto(D.borrado);

  // Todo vive en memoria: nada de la bitácora en la URL ni en el almacenamiento del navegador (semana 8, R-D1).
  const hoy = () => D.hoyLocal(api.ahora());
  const sumarDias = (iso, n) => new Date(Date.parse(iso + 'T00:00:00Z') + n * 86400000).toISOString().slice(0, 10);
  const filtrosIniciales = () => ({ rango: 'hoy', desde: hoy(), hasta: hoy(), usuario: '', modulo: '', accion: '', resultado: '', recurso: '', alcance: 'hospital' });
  const est = { sesion: null, filtros: null, res: null, error: null, scroll: 0, verif: { cadena: 'hosp-norte', secuencia: '', sello: '', resultado: null, errores: {} } };

  // ---------- utilidades ----------
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const persona = () => D.personas[est.sesion.actor];
  const puede = (permiso, tenant = est.sesion.hospital) => (D.permisos[`${est.sesion.actor}|${tenant}`] || []).includes(permiso);
  const anunciar = (txt, urgente = false) => { const n = $(urgente ? '#alerta' : '#vivo'); n.textContent = ''; setTimeout(() => { n.textContent = txt; }, 30); };
  const local = (isoUtc) => { const d = new Date(Date.parse(isoUtc) - 6 * 3600000); return { fecha: d.toISOString().slice(0, 10).split('-').reverse().join('/'), hora: d.toISOString().slice(11, 16) }; };
  const fechaCorta = (iso) => iso.split('-').reverse().join('/');
  const etiqueta = (accion) => D.acciones[accion] || accion;
  const RES = { exito: ['exito', '✓', 'Éxito'], denegado: ['denegado', '✕', 'Denegado'], error: ['error', '!', 'Error'] };
  const resultadoHtml = (r) => { const [c, i, t] = RES[r]; return `<span class="res ${c}"><span aria-hidden="true">${i}</span> ${t}</span>`; };
  const tenantActual = () => (est.filtros && est.filtros.alcance === 'red' ? '*' : est.sesion.hospital);

  /** Petición «por la red». Un 401 abre la renovación de sesión SOBRE la página y reintenta (semana 9, B-02). */
  async function llamar(metodo, url, cuerpo = null, tenant = tenantActual()) {
    const t = setTimeout(() => pintarRed('lenta'), 2000);
    try {
      let r = await api.solicitarRed(metodo, url, { token: persona().token, tenant, cuerpo }, RED);
      pintarRed();
      if (r.estado === 401) {
        clearTimeout(t); pintarRed(); // mientras la persona decide, la red no está «lenta»
        const renovada = await renovarSesion();
        if (!renovada) return { estado: 401, cuerpo: r.cuerpo };
        r = await api.solicitarRed(metodo, url, { token: persona().token, tenant, cuerpo }, RED);
      }
      return r;
    } catch (e) {
      pintarRed('sin-conexion');
      return { estado: 0, red: true, procesada: !!e.procesada, cuerpo: {} };
    } finally { clearTimeout(t); }
  }

  // ---------- marco ----------
  function pintarRed(estado) {
    const n = $('#red');
    if (RED.sinConexion || estado === 'sin-conexion') {
      n.innerHTML = '<div class="red" role="status"><span aria-hidden="true">⦸</span> Sin conexión. Lo que ve se cargó antes; para consultar hace falta conexión.</div>';
    } else if (estado === 'lenta') {
      n.innerHTML = '<div class="red lenta" role="status"><span aria-hidden="true">◔</span> La conexión está lenta. Seguimos esperando la respuesta.</div>';
    } else n.innerHTML = '';
  }
  function pintarMarco(def) {
    const barra = $('#barra'), nav = $('#nav');
    if (!est.sesion) { barra.hidden = true; nav.hidden = true; document.body.classList.add('sin-nav'); return; }
    const p = persona();
    const red = def.seccion === 'Integridad' || (est.filtros && est.filtros.alcance === 'red');
    barra.hidden = false;
    barra.classList.toggle('red-activa', red);
    barra.innerHTML = `${def.volver ? `<a class="atras btn texto" href="${def.volver}" aria-label="Volver a la lista" style="color:#fff">‹</a>` : ''}
      <span class="hospital" aria-label="${red ? 'Alcance: red hospitalaria' : 'Hospital activo: ' + esc(D.hospitales[est.sesion.hospital])}">
        ${red ? '<span>Red hospitalaria</span>' : `<span class="corto">${esc(D.cortos[est.sesion.hospital])}</span><span class="largo">${esc(D.hospitales[est.sesion.hospital])}</span>`}</span>
      ${red ? '<span class="alcance-red">RED</span>' : ''}
      <span class="rol-actual">${esc(p.rol)}</span>
      <span class="avatar" role="img" aria-label="${esc(p.nombre)}">${esc(p.iniciales)}</span>`;
    const conNav = !def.tarea;
    document.body.classList.toggle('sin-nav', !conNav);
    nav.hidden = !conNav;
    const secciones = [['#/bitacora', '📋', 'Bitácora'], ...(puede('auditoria.verificar', '*') ? [['#/integridad', '🛡', 'Integridad']] : []), ['#/mas', '☰', 'Más']];
    nav.innerHTML = secciones.map(([href, ico, texto]) =>
      `<a href="${href}" ${def.seccion === texto ? 'aria-current="page"' : ''}><span class="ico" aria-hidden="true">${ico}</span>${texto}</a>`).join('');
  }
  const accionFija = (html) => { const a = $('#barra-accion'); a.hidden = !html; a.innerHTML = html || ''; };
  const pintar = (html) => { $('#contenido').innerHTML = html; };

  // ---------- diálogos: el resto queda inerte; Esc cancela; el foco vuelve a quien lo abrió ----------
  let dialogo = null;
  function abrirDialogo({ titulo, texto, botones }) {
    return new Promise((resolver) => {
      const previo = document.activeElement;
      $('#app').inert = true;
      $('#capa-dialogo').innerHTML = `<div class="velo"></div><div class="hoja" role="dialog" aria-modal="true" aria-labelledby="d-titulo" aria-describedby="d-texto">
        <div class="asa" aria-hidden="true"></div><h2 id="d-titulo">${titulo}</h2><p id="d-texto">${texto}</p>
        <div class="acciones">${botones.map((b) => `<button type="button" class="btn ${b.primario ? 'primario' : ''}" data-valor="${b.valor}">${b.texto}</button>`).join('')}</div></div>`;
      const cerrar = (valor) => {
        $('#capa-dialogo').innerHTML = ''; $('#app').inert = false; dialogo = null;
        if (previo && previo.isConnected) previo.focus();
        resolver(valor);
      };
      dialogo = { cerrar };
      $('#capa-dialogo').addEventListener('click', (e) => { const b = e.target.closest('[data-valor]'); if (b) cerrar(b.dataset.valor); }, { once: false });
      $(`#capa-dialogo [data-valor="${botones.find((b) => b.foco).valor}"]`).focus();
    });
  }
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && dialogo) dialogo.cerrar('cancelar'); });

  async function renovarSesion() {
    const v = await abrirDialogo({ titulo: 'Su sesión venció',
      texto: 'Renueve la sesión para continuar. Lo que tiene en pantalla se conserva: no hace falta repetir la búsqueda. (M-09)',
      botones: [{ texto: 'Renovar sesión', valor: 'renovar', primario: true, foco: true }, { texto: 'Salir', valor: 'salir' }] });
    if (v !== 'renovar') { salir(); return false; }
    RED.sesionVencida = false; // en el HIS: el módulo de autenticación renueva el token sin navegar
    anunciar('Sesión renovada.');
    return true;
  }
  function salir() { est.sesion = null; est.filtros = null; est.res = null; location.hash = '#/ingresar'; }

  // ---------- rutas: la URL solo lleva la pantalla y, en el detalle, el id (nunca filtros) ----------
  const RUTAS = [
    { patron: /^\/ingresar$/, vista: vIngresar, titulo: 'Ingresar', publica: true, tarea: true },
    { patron: /^\/bitacora$/, vista: vFiltros, titulo: 'Bitácora', seccion: 'Bitácora' },
    { patron: /^\/bitacora\/resultados$/, vista: vResultados, titulo: 'Movimientos', seccion: 'Bitácora' },
    { patron: /^\/bitacora\/(\d{1,18})$/, vista: vDetalle, titulo: 'Movimiento', tarea: true, volver: '#/bitacora/resultados' },
    { patron: /^\/integridad$/, vista: vIntegridad, titulo: 'Verificar integridad', seccion: 'Integridad', permisoRed: 'auditoria.verificar' },
    { patron: /^\/mas$/, vista: vMas, titulo: 'Más', seccion: 'Más' },
  ];
  async function navegar() {
    if (dialogo) dialogo.cerrar('cancelar');
    const ruta = location.hash.slice(1) || '/bitacora';
    if (!est.sesion && ruta !== '/ingresar') { location.replace('#/ingresar'); return; }
    let def = null, params = [];
    for (const r of RUTAS) { const m = ruta.match(r.patron); if (m) { def = r; params = m.slice(1); break; } }
    if (!def) { location.replace('#/bitacora'); return; }
    if (def.permisoRed && !puede(def.permisoRed, '*')) def = { titulo: 'Acceso denegado', vista: vDenegado };
    pintarMarco(def);
    accionFija('');
    document.title = `${def.titulo} · HIS`;
    await def.vista(...params);
    const h1 = $('#contenido h1');
    if (est.foco && $('#' + est.foco)) { $('#' + est.foco).focus(); $('#' + est.foco).scrollIntoView({ block: 'center' }); est.foco = null; } // se ve lo que se va a buscar
    else if (h1 && def.vista !== vResultados) { h1.tabIndex = -1; h1.focus(); }
  }
  window.addEventListener('hashchange', navegar);

  // ---------- vistas ----------
  function vIngresar() {
    pintar(`<h1>Ingresar al prototipo</h1><p class="sub">Elija una persona de demostración. Los datos son ficticios.</p>
      ${Object.entries(D.personas).map(([id, p]) => `<button type="button" class="tarjeta btn bloque" style="justify-content:flex-start;min-height:72px" data-accion="ingresar" data-id="${id}">
        <span class="avatar" aria-hidden="true">${p.iniciales}</span><span style="text-align:left"><strong>${esc(p.nombre)}</strong><br><span class="meta">${esc(p.rol)} · ${esc(D.hospitales[p.hospital])}</span></span></button>`).join('')}`);
  }

  function vDenegado() {
    pintar(`<h1>Acceso denegado</h1><div class="aviso error"><p>No tiene permiso para esta sección. Pídalo a su administrador. (M-08)</p></div>`);
  }

  function vFiltros() {
    const f = est.filtros;
    const red = puede('auditoria.consultar_red', '*');
    const err = est.error || {};
    const campo = (id, texto, tipo, valor, extra = '') => `<label for="${id}">${texto}</label><input id="${id}" type="${tipo}" value="${esc(valor)}" ${extra}>`;
    const opciones = (id, texto, lista, valor) => `<label for="${id}">${texto}</label><select id="${id}"><option value="">${id === 'accion' ? 'Todas' : 'Todos'}</option>${lista.map(([v, t]) => `<option value="${v}" ${v === valor ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select>`;
    pintar(`<div class="fila"><h1>Bitácora</h1>
        <button type="button" class="btn texto icono derecha" aria-label="Ayuda sobre la bitácora" aria-expanded="false" aria-controls="ayuda" data-accion="ayuda">?</button></div>
      <div id="ayuda" class="aviso atencion" hidden><p>Cada movimiento es un hecho registrado por un módulo del HIS. <strong>No se puede editar ni borrar.</strong> Si un sistema registró un dato mal, envía una corrección que queda enlazada al original. (M-21)</p></div>
      <p class="aviso-registro"><span aria-hidden="true">ⓘ</span> Su consulta queda registrada: quién, cuándo y con qué filtros. (M-06)</p>
      <form id="form-filtros" aria-label="Filtros de la bitácora" novalidate>
        <p class="obligatorio">* obligatorio</p>
        <fieldset class="rangos"><legend>Período *</legend>
          ${[['hoy', 'Hoy'], ['ayer', 'Ayer'], ['7dias', '7 días'], ['otro', 'Otro']].map(([v, t]) => `<label><input type="radio" name="rango" value="${v}" ${f.rango === v ? 'checked' : ''}> ${t}</label>`).join('')}
        </fieldset>
        <div class="dos">
          <div>${campo('desde', 'Desde *', 'date', f.desde, `aria-required="true" aria-describedby="${err.desde ? 'desde-error ' : ''}fechas-ayuda" ${err.desde ? 'aria-invalid="true"' : ''}`)}</div>
          <div>${campo('hasta', 'Hasta *', 'date', f.hasta, 'aria-required="true" aria-describedby="fechas-ayuda"')}</div>
        </div>
        ${err.desde ? `<p class="error" id="desde-error">${esc(err.desde)}</p>` : ''}
        <p class="ayuda" id="fechas-ayuda">Día/mes/año, hora de Guatemala. «Hasta» incluye el día completo.</p>
        ${red ? `<fieldset class="alcance"><legend>Alcance</legend>
          <label class="opcion"><input type="radio" name="alcance" value="hospital" ${f.alcance === 'hospital' ? 'checked' : ''}> ${esc(D.hospitales[est.sesion.hospital])}</label>
          <label class="opcion"><input type="radio" name="alcance" value="red" ${f.alcance === 'red' ? 'checked' : ''}> Toda la red</label></fieldset>` : ''}
        <details class="mas" ${f.usuario || f.modulo || f.accion || f.resultado || f.recurso ? 'open' : ''}><summary>Más filtros (usuario, módulo, acción, resultado, recurso)</summary>
          ${campo('usuario', 'Usuario', 'text', f.usuario, 'autocomplete="off" placeholder="Ej. u-ana"')}
          ${opciones('modulo', 'Módulo', [['pacientes', 'pacientes'], ['rbac', 'rbac'], ['auditoria', 'auditoría']], f.modulo)}
          ${opciones('accion', 'Acción', Object.entries(D.acciones), f.accion)}
          ${opciones('resultado', 'Resultado', [['exito', 'Éxito'], ['denegado', 'Denegado'], ['error', 'Error']], f.resultado)}
          ${campo('recurso', 'Recurso (¿quién vio esta ficha?)', 'text', f.recurso, 'autocomplete="off" placeholder="Ej. paciente:P-000001" maxlength="128" aria-describedby="recurso-ayuda"')}
          <p class="ayuda" id="recurso-ayuda">Escriba el recurso completo, como aparece en el detalle de un movimiento.</p>
        </details>
        <button type="submit" hidden></button>
      </form>`);
    accionFija(`<button type="button" class="btn" data-accion="restablecer">Restablecer</button>
      <button type="button" class="btn primario" data-accion="buscar">${f.alcance === 'red' ? 'Buscar en toda la red' : 'Buscar'}</button>`);
    if (err.desde) $('#desde').focus();
  }

  function leerFiltros() {
    const f = est.filtros;
    f.desde = $('#desde').value; f.hasta = $('#hasta').value;
    for (const c of ['usuario', 'modulo', 'accion', 'resultado', 'recurso']) f[c] = $('#' + c).value.trim();
  }
  const parametros = (f) => {
    const p = new URLSearchParams({ desde: f.desde, hasta: f.hasta });
    for (const c of ['usuario', 'modulo', 'accion', 'resultado', 'recurso']) if (f[c]) p.set(c, f[c]);
    return p;
  };

  async function buscar() {
    if ($('#form-filtros')) leerFiltros(); // «Reintentar» desde la lista usa los filtros guardados
    const f = est.filtros;
    est.error = null;
    if (!f.desde || !f.hasta) { est.error = { desde: 'Indique la fecha desde y hasta.' }; vFiltros(); return; } // M-01 de la UI (semana 9, B-09)
    if (f.desde > f.hasta) { est.error = { desde: 'Desde no puede ser posterior a hasta.' }; vFiltros(); return; } // M-02
    if (f.alcance === 'red') {
      const v = await abrirDialogo({ titulo: '¿Consultar toda la red?',
        texto: `Va a ver los movimientos de <strong>todos los hospitales</strong> entre ${fechaCorta(f.desde)} y ${fechaCorta(f.hasta)}. La consulta queda registrada en la cadena de red con su usuario. (M-15)`,
        botones: [{ texto: 'Consultar la red', valor: 'si', primario: true }, { texto: 'Cancelar', valor: 'cancelar', foco: true }] });
      if (v !== 'si') return;
    }
    est.res = { filtros: { ...f }, movimientos: [], cursor: null, cargando: true, error: null, errorMas: null };
    if (location.hash === '#/bitacora/resultados') vResultados(); else location.hash = '#/bitacora/resultados';
    const r = await llamar('GET', '/api/v1/auditoria/movimientos?' + parametros(f));
    est.res.cargando = false;
    if (r.estado === 200) {
      est.res.movimientos = r.cuerpo.movimientos; est.res.cursor = r.cuerpo.cursor_siguiente;
      anunciar(r.cuerpo.movimientos.length ? `${r.cuerpo.movimientos.length} movimientos encontrados${r.cuerpo.cursor_siguiente ? '; hay más' : ''}.` : 'No hay movimientos con esos filtros.');
    } else if (r.estado === 422) {
      est.error = { desde: (r.cuerpo.campos && Object.values(r.cuerpo.campos)[0]) || 'Revise los filtros.' };
      location.hash = '#/bitacora'; return;
    } else est.res.error = r;
    if (location.hash === '#/bitacora/resultados') vResultados();
  }

  async function cargarMas() {
    const res = est.res;
    res.cargandoMas = true; res.errorMas = null; vResultados();
    const antes = res.movimientos.length;
    const r = await llamar('GET', '/api/v1/auditoria/movimientos?' + parametros(res.filtros) + '&cursor=' + encodeURIComponent(res.cursor), null,
      res.filtros.alcance === 'red' ? '*' : est.sesion.hospital);
    res.cargandoMas = false;
    if (r.estado === 200) {
      res.movimientos = res.movimientos.concat(r.cuerpo.movimientos); res.cursor = r.cuerpo.cursor_siguiente;
      anunciar(`Se cargaron ${r.cuerpo.movimientos.length} más; ${res.movimientos.length} en total.${res.cursor ? '' : ' Fin de la lista.'}`); // B-11
    } else res.errorMas = r; // las filas ya cargadas se conservan (semana 10, medido)
    vResultados();
    const nueva = document.querySelectorAll('.movs .mov')[antes] || document.querySelectorAll('.tabla tbody tr')[antes];
    if (nueva) { const ver = nueva.querySelector('a.ver, a[data-ver]'); if (ver) ver.focus(); }
  }

  function textoError(r) {
    if (r.red) return { titulo: 'Sin conexión', texto: 'No se recibió respuesta. Revise su red y reintente. (M-11)' };
    if (r.estado === 403) return { titulo: 'Sin permiso', texto: 'No tiene permiso para consultar la bitácora de este alcance. Pídalo a su administrador. (M-08)' };
    if (r.estado === 401) return { titulo: 'Sesión cerrada', texto: 'Inicie sesión de nuevo. Deberá repetir la búsqueda.' };
    return { titulo: 'La bitácora no está disponible', texto: 'Intente en unos minutos. Código de soporte: 3f9a…c21 (M-10)' };
  }

  function vResultados() {
    const res = est.res;
    if (!res) { location.replace('#/bitacora'); return; }
    const f = res.filtros, red = f.alcance === 'red';
    const resumenFiltros = [`${fechaCorta(f.desde)}${f.hasta !== f.desde ? ' – ' + fechaCorta(f.hasta) : ''}`, red ? 'toda la red' : null,
      f.usuario && 'usuario: ' + f.usuario, f.modulo && 'módulo: ' + f.modulo, f.accion && 'acción: ' + etiqueta(f.accion), f.resultado && 'resultado: ' + RES[f.resultado][2].toLowerCase(), f.recurso && 'recurso: ' + f.recurso].filter(Boolean).join(' · ');
    const variosDias = f.desde !== f.hasta;
    const cuando = (m) => { const l = local(m.ocurrido_en); return variosDias ? `${l.fecha.slice(0, 5)} ${l.hora}` : l.hora; };
    const insignias = (m) => `${m.corrige_a ? `<a class="insignia corrige" href="#/bitacora/${m.corrige_a}">Corrige a #${m.corrige_a}</a>` : ''}
      ${m.corregido_por ? `<a class="insignia corregido" href="#/bitacora/${m.corregido_por}">Corregido por #${m.corregido_por}</a>` : ''}`;
    let cuerpo;
    if (res.cargando) {
      cuerpo = `<div aria-busy="true"><p class="meta">Buscando movimientos… (M-04)</p>${'<div class="tarjeta"><div class="esq" style="width:70%"></div><div class="esq" style="width:45%"></div></div>'.repeat(3)}</div>`;
    } else if (res.error) {
      const e = textoError(res.error);
      cuerpo = `<div class="aviso error" role="alert"><h2>${e.titulo}</h2><p>${e.texto}</p><div class="acciones"><button type="button" class="btn primario" data-accion="reintentar-busqueda">Reintentar</button></div></div>`;
    } else if (!res.movimientos.length) {
      cuerpo = `<div class="tarjeta"><h2>No hay movimientos con esos filtros</h2><p class="meta">Pruebe con «7 días» o quite un filtro. (M-03)</p><a class="btn bloque" href="#/bitacora">Cambiar filtros</a></div>`;
    } else {
      const ms = res.movimientos;
      cuerpo = `<p class="meta">${ms.length} movimientos · ${res.cursor ? 'hay más' : 'fin de la lista'}</p>
      <ul class="movs solo-movil">${ms.map((m) => `<li class="mov">
        <div class="cab"><span class="num">#${m.id}</span><span class="que">${esc(etiqueta(m.accion))}</span></div>
        <div class="codigo">${esc(m.accion)}</div>
        <div class="quien">${esc(m.usuario)} <span class="meta">· ${cuando(m)}${red ? ' · ' + esc(D.cortos[m.hospital] || 'Red') : ''}</span></div>
        <div class="extra meta">${esc(m.recurso || '—')} · ${esc(m.modulo)}</div>
        <div class="pie">${resultadoHtml(m.resultado)}${insignias(m)}<a class="btn ver" href="#/bitacora/${m.id}" aria-label="Ver detalle del movimiento #${m.id}">Ver</a></div></li>`).join('')}</ul>
      <table class="tabla"><caption class="oculto-visual">Movimientos encontrados</caption>
        <thead><tr><th scope="col">#</th><th scope="col">${variosDias ? 'Fecha y hora' : 'Hora'}</th>${red ? '<th scope="col">Hospital</th>' : ''}<th scope="col">Usuario</th><th scope="col">Acción</th><th scope="col">Resultado</th><th scope="col">Recurso</th><th scope="col"><span class="oculto-visual">Detalle</span></th></tr></thead>
        <tbody>${ms.map((m) => `<tr><td>${m.id}</td><td>${cuando(m)}</td>${red ? `<td>${esc(D.cortos[m.hospital] || 'Red')}</td>` : ''}<td>${esc(m.usuario)}</td>
          <td>${esc(etiqueta(m.accion))}<span class="codigo">${esc(m.accion)}</span>${insignias(m)}</td><td>${resultadoHtml(m.resultado)}</td><td>${esc(m.recurso || '—')}</td>
          <td><a data-ver href="#/bitacora/${m.id}" aria-label="Ver detalle del movimiento #${m.id}">Ver</a></td></tr>`).join('')}</tbody></table>
      <div style="margin-top:12px">
        ${res.cargandoMas ? '<p class="carga-mas" aria-busy="true">Cargando 50 más…</p>' : ''}
        ${res.errorMas ? `<div class="aviso error" role="alert"><h2>No se pudieron cargar más movimientos</h2><p>Las ${ms.length} filas de arriba siguen siendo válidas.</p><div class="acciones"><button type="button" class="btn primario" data-accion="mas">Reintentar</button></div></div>` : ''}
        ${res.cursor && !res.cargandoMas && !res.errorMas ? '<button type="button" class="btn bloque" data-accion="mas">Cargar 50 más</button>' : ''}
        ${!res.cursor ? '<p class="meta">No hay más movimientos para estos filtros.</p>' : ''}
      </div>`;
    }
    pintar(`<a class="btn texto" href="#/bitacora" style="margin-left:-8px">‹ Filtros</a><h1>Movimientos</h1><p class="sub">${esc(resumenFiltros)} · más recientes primero</p>${cuerpo}`);
    if (est.scroll) { window.scrollTo(0, est.scroll); est.scroll = 0; }
  }

  async function vDetalle(id) {
    est.scroll = est.scroll || 0;
    const red = est.res && est.res.filtros.alcance === 'red';
    pintar(`<div aria-busy="true"><p class="meta">Abriendo el movimiento…</p><div class="tarjeta"><div class="esq" style="width:50%"></div><div class="esq" style="width:80%"></div></div></div>`);
    const r = await llamar('GET', `/api/v1/auditoria/movimientos/${id}`, null, red ? '*' : est.sesion.hospital);
    if (location.hash !== `#/bitacora/${id}`) return;
    if (r.estado !== 200) {
      const e = r.estado === 404 ? { titulo: 'Este movimiento no existe o no pertenece a su hospital', texto: '(M-12)' } : textoError(r);
      pintar(`<h1>Movimiento #${esc(id)}</h1><div class="aviso error" role="alert"><h2>${e.titulo}</h2><p>${e.texto}</p><div class="acciones"><a class="btn primario" href="#/bitacora/resultados">Volver a la lista</a></div></div>`);
      return;
    }
    const m = r.cuerpo.movimiento, l = local(m.ocurrido_en);
    pintar(`<h1>Movimiento #${m.id}</h1>
      ${m.corregido_por ? `<div class="aviso atencion"><strong>Corregido por #${m.corregido_por}.</strong> Este movimiento se conserva sin cambios; el motivo está en la corrección. (M-13)<div class="acciones"><a class="btn" href="#/bitacora/${m.corregido_por}">Ver #${m.corregido_por}</a></div></div>` : ''}
      ${m.corrige_a ? `<div class="aviso atencion"><strong>Corrige a #${m.corrige_a}.</strong> Motivo: «${esc(m.motivo_correccion)}». (M-14)<div class="acciones"><a class="btn" href="#/bitacora/${m.corrige_a}">Ver #${m.corrige_a}</a></div></div>` : ''}
      <div class="tarjeta"><dl class="datos">
        <dt>Acción</dt><dd>${esc(etiqueta(m.accion))} <span class="codigo meta">(${esc(m.accion)})</span></dd>
        <dt>Usuario</dt><dd>${esc(m.usuario)}</dd>
        <dt>Fecha y hora</dt><dd>${l.fecha} ${l.hora} (hora de Guatemala)</dd>
        <dt>Hospital</dt><dd>${esc(D.hospitales[m.hospital] || 'Red hospitalaria')}</dd>
        <dt>Resultado</dt><dd>${resultadoHtml(m.resultado)}</dd>
        <dt>Recurso</dt><dd>${esc(m.recurso || '—')}</dd></dl>
        ${m.recurso ? `<button type="button" class="btn bloque" style="margin-top:12px" data-accion="por-recurso" data-recurso="${esc(m.recurso)}" aria-label="¿Quién más accedió a ${esc(m.recurso)}?">¿Quién más accedió a esta ficha?</button>` : ''}</div>
      <details class="mas"><summary>Origen e integridad</summary><dl class="datos">
        <dt>Módulo · emisor</dt><dd>${esc(m.modulo)} · ${esc(m.emisor)}</dd>
        <dt>Evento</dt><dd>${esc(m.event_id)}</dd>
        <dt>Secuencia</dt><dd>${m.secuencia}</dd>
        <dt>Sello</dt><dd class="sello">${esc(m.sello)}</dd></dl>
        <button type="button" class="btn" style="margin-top:8px" data-accion="copiar" data-texto="${esc(m.sello)}">Copiar sello</button></details>
      <p class="meta">Abrir este detalle quedó registrado en la bitácora.</p>`);
  }

  function vIntegridad() {
    const v = est.verif, e = v.errores;
    const informe = v.resultado;
    let veredicto = '';
    if (informe && informe.verificando) veredicto = `<div aria-busy="true" role="status"><p class="meta">Verificando… no cierre esta pantalla.</p><div class="esq"></div></div>`;
    else if (informe && informe.red) veredicto = `<div class="veredicto incierto" role="alert"><h2>No sabemos si la verificación terminó</h2><p>Se cortó la conexión antes de recibir el resultado. Verificar no cambia la bitácora: puede repetirla sin riesgo. Si el servidor alcanzó a verificar, quedará registrada dos veces.</p></div>`;
    else if (informe && informe.estado === 'INTEGRA') veredicto = `<div class="veredicto integra" role="status"><h2>✓ Cadena íntegra</h2><p>${informe.verificados} movimientos de ${esc(nombreCadena(informe.cadena))} verificados, ninguno alterado.${informe.ancla_confirmada ? ` Ancla ${informe.ancla_confirmada} confirmada.` : ''} (M-17)</p></div>`;
    else if (informe && (informe.estado === 'ALTERADA' || informe.estado === 'TRUNCADA')) {
      const alterada = informe.estado === 'ALTERADA';
      veredicto = `<div class="veredicto ${alterada ? 'alterada' : 'truncada'}" role="alert" tabindex="-1" id="veredicto">
        <h2>${alterada ? `✕ Cadena alterada desde la secuencia ${informe.primera_secuencia}` : '⚠ Faltan movimientos al final'}</h2>
        <p>${alterada ? `${informe.verificados} ${informe.verificados === 1 ? 'movimiento coincide' : 'movimientos coinciden'}; a partir de la secuencia ${informe.primera_secuencia}, no. No modifique nada. (M-18)` : `El ancla dice ${informe.esperada_hasta} y hay ${informe.verificados}. (M-19)`}</p>
        <p>Envíe el informe a seguridad: <strong>${esc(D.contactoSeguridad)}</strong></p>
        <button type="button" class="btn primario" data-accion="copiar-informe">Copiar informe</button>
        <pre class="referencia informe" id="informe">${esc(informe.texto)}</pre></div>`;
    } else if (informe && informe.error) veredicto = `<div class="aviso error" role="alert"><h2>${informe.error.titulo}</h2><p>${informe.error.texto}</p></div>`;
    pintar(`<h1>Verificar integridad</h1>
      ${Object.keys(e).length ? `<div class="aviso error" role="alert" tabindex="-1" id="resumen-errores"><h2>Revise ${Object.keys(e).length === 1 ? 'un campo' : 'los campos'}</h2><ul>${Object.entries(e).map(([c, t]) => `<li><a href="#${c}" data-accion="ir-campo" data-campo="${c}">${esc(t)}</a></li>`).join('')}</ul></div>` : ''}
      <form id="form-verif" novalidate aria-label="Verificación"><p class="obligatorio">* obligatorio</p>
        <label for="cadena">Cadena *</label><select id="cadena" aria-required="true">
          ${[['hosp-oriente', D.hospitales['hosp-oriente']], ['hosp-norte', D.hospitales['hosp-norte']], ['', 'Red (consultas de red)']].map(([val, t]) => `<option value="${val}" ${v.cadena === val ? 'selected' : ''}>${esc(t)}</option>`).join('')}</select>
        <details class="mas" ${v.secuencia || v.sello || Object.keys(e).length ? 'open' : ''}><summary>Ancla publicada (recomendado)</summary>
          <p class="ayuda" style="margin-top:0">Sin el ancla no se detecta que falten las últimas filas. Péguela tal como se publicó.</p>
          <label for="secuencia">Secuencia</label><input id="secuencia" type="text" inputmode="numeric" value="${esc(v.secuencia)}" ${e.secuencia ? 'aria-invalid="true" aria-describedby="secuencia-error"' : ''}>
          ${e.secuencia ? `<p class="error" id="secuencia-error">${esc(e.secuencia)}</p>` : ''}
          <label for="sello">Sello</label><textarea id="sello" rows="3" spellcheck="false" autocapitalize="off" ${e.sello ? 'aria-invalid="true" aria-describedby="sello-error"' : ''}>${esc(v.sello)}</textarea>
          ${e.sello ? `<p class="error" id="sello-error">${esc(e.sello)}</p>` : ''}
        </details></form>${veredicto}`);
    accionFija('<button type="button" class="btn primario" data-accion="verificar">Verificar</button>');
  }
  const nombreCadena = (c) => (c ? D.hospitales[c] : 'la red');

  async function verificar() {
    const v = est.verif;
    v.cadena = $('#cadena').value; v.secuencia = $('#secuencia').value.trim(); v.sello = $('#sello').value.trim();
    v.errores = {};
    if (v.secuencia || v.sello) { // misma regla del servidor (FormularioMovimientos::verificacion)
      if (!/^[1-9]\d*$/.test(v.secuencia)) v.errores.secuencia = 'La secuencia es un número entero desde 1.';
      if (!/^[0-9a-f]{64}$/.test(v.sello)) v.errores.sello = 'Deben ser 64 caracteres hexadecimales. Copie el sello completo.';
    }
    v.resultado = null;
    if (Object.keys(v.errores).length) { vIntegridad(); $('#resumen-errores').focus(); return; } // B-07: el botón nunca se deshabilita
    const ok = await abrirDialogo({ titulo: `¿Verificar la cadena de ${nombreCadena(v.cadena || null)}?`,
      texto: 'Se recalcularán los sellos de toda la cadena. Puede tardar unos segundos y queda registrado. (M-16)',
      botones: [{ texto: 'Verificar', valor: 'si', primario: true }, { texto: 'Cancelar', valor: 'cancelar', foco: true }] });
    if (ok !== 'si') return;
    v.resultado = { verificando: true }; vIntegridad();
    const cuerpo = { hospital: v.cadena || null, ...(v.secuencia ? { ancla: { secuencia: Number(v.secuencia), sello: v.sello } } : {}) };
    const r = await llamar('POST', '/api/v1/auditoria/verificaciones', cuerpo, '*');
    if (r.red) v.resultado = { red: true };
    else if (r.estado === 200) {
      const cuando = new Date(api.ahora() - 6 * 3600000).toISOString().slice(0, 16).replace('T', ' ');
      v.resultado = { ...r.cuerpo, cadena: v.cadena || null, texto: [
        'INFORME DE VERIFICACIÓN DE INTEGRIDAD', `Cadena: ${nombreCadena(v.cadena || null)}`, `Estado: ${r.cuerpo.estado}`,
        r.cuerpo.primera_secuencia ? `Primera secuencia que no coincide: ${r.cuerpo.primera_secuencia}` : `Esperado hasta: ${r.cuerpo.esperada_hasta ?? '—'}`,
        `Movimientos verificados: ${r.cuerpo.verificados}`, `Fecha y hora (Guatemala): ${cuando}`, `Usuario: ${est.sesion.actor}`,
        `X-Request-ID: ${r.cabeceras && r.cabeceras['X-Request-ID'] || '(prototipo)'}`].join('\n') };
    } else v.resultado = { error: textoError(r) };
    vIntegridad();
    const destino = $('#veredicto') || $('.veredicto');
    if (destino) { destino.tabIndex = -1; destino.focus(); }
    anunciar(r.estado === 200 ? `Resultado: ${r.cuerpo.estado === 'INTEGRA' ? 'cadena íntegra' : r.cuerpo.estado === 'ALTERADA' ? 'cadena alterada' : 'faltan movimientos'}.` : 'No se pudo verificar.', r.estado === 200 && r.cuerpo.estado !== 'INTEGRA');
  }

  function vMas() {
    pintar(`<h1>Más</h1>
      <div class="tarjeta"><p><strong>${esc(persona().nombre)}</strong><br><span class="meta">${esc(persona().rol)}</span></p>
        <button type="button" class="btn bloque" data-accion="salir">Cerrar sesión</button></div>
      <div class="tarjeta"><h2>Simular la red (demostración)</h2>
        ${[['sinConexion', 'Sin conexión'], ['lenta', 'Conexión lenta (3 s)'], ['perderRespuesta', 'Perder la próxima respuesta'], ['sesionVencida', 'La sesión vence en la próxima petición']]
          .map(([k, t]) => `<label class="casilla"><input type="checkbox" data-demo="${k}" ${(k === 'lenta' ? RED.latenciaMs > 1000 : RED[k]) ? 'checked' : ''}> ${t}</label>`).join('')}</div>`);
  }

  // ---------- eventos ----------
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-accion]');
    if (!b) return;
    const accion = b.dataset.accion;
    if (accion === 'ingresar') { est.sesion = { actor: b.dataset.id, hospital: D.personas[b.dataset.id].hospital }; est.filtros = filtrosIniciales(); location.hash = '#/bitacora'; }
    else if (accion === 'buscar') buscar();
    else if (accion === 'reintentar-busqueda') { est.filtros = { ...est.res.filtros }; buscar(); }
    else if (accion === 'restablecer') { est.filtros = { ...filtrosIniciales(), alcance: est.filtros.alcance }; est.error = null; vFiltros(); anunciar('Filtros restablecidos: hoy.'); }
    else if (accion === 'mas') cargarMas();
    else if (accion === 'por-recurso') {
      // Semana 12: mismos filtros de período y alcance; solo el recurso. Antes de consultar se ven los filtros (cada consulta queda registrada)
      est.filtros = { ...(est.res ? est.res.filtros : est.filtros), usuario: '', modulo: '', accion: '', resultado: '', recurso: b.dataset.recurso };
      est.error = null; est.foco = 'recurso'; location.hash = '#/bitacora';
    }
    else if (accion === 'ayuda') { const a = $('#ayuda'); a.hidden = !a.hidden; b.setAttribute('aria-expanded', String(!a.hidden)); }
    else if (accion === 'verificar') verificar();
    else if (accion === 'salir') salir();
    else if (accion === 'ir-campo') { e.preventDefault(); $('#' + b.dataset.campo).focus(); }
    else if (accion === 'copiar' || accion === 'copiar-informe') {
      const texto = accion === 'copiar' ? b.dataset.texto : $('#informe').textContent;
      try { await navigator.clipboard.writeText(texto); anunciar('Copiado.'); b.textContent = accion === 'copiar' ? 'Sello copiado' : 'Informe copiado'; }
      catch { anunciar('No se pudo copiar automáticamente: seleccione el texto y cópielo.'); }
    } else if (accion === 'saltar') { e.preventDefault(); $('#contenido').focus(); }
  });
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.name === 'rango') {
      // Elegir un período NO consulta nada (cada consulta queda registrada): solo llena las fechas
      leerFiltros();
      const f = est.filtros, h = hoy();
      f.rango = t.value;
      if (t.value === 'hoy') { f.desde = h; f.hasta = h; } else if (t.value === 'ayer') { f.desde = sumarDias(h, -1); f.hasta = f.desde; } else if (t.value === '7dias') { f.desde = sumarDias(h, -6); f.hasta = h; }
      $('#desde').value = f.desde; $('#hasta').value = f.hasta;
      if (t.value === 'otro') $('#desde').focus();
    } else if (t.name === 'alcance') {
      // Semana 9, B-01: marcar «Toda la red» NO abre nada; solo cambia el texto del botón
      leerFiltros(); est.filtros.alcance = t.value;
      $('[data-accion="buscar"]').textContent = t.value === 'red' ? 'Buscar en toda la red' : 'Buscar';
    } else if (t.id === 'desde' || t.id === 'hasta') {
      est.filtros.rango = 'otro'; document.querySelectorAll('input[name=rango]').forEach((r) => { r.checked = r.value === 'otro'; });
    } else if (t.dataset.demo) {
      if (t.dataset.demo === 'lenta') RED.latenciaMs = t.checked ? 3000 : 300; else RED[t.dataset.demo] = t.checked;
      pintarRed();
    }
  });
  document.addEventListener('submit', (e) => { e.preventDefault(); if (e.target.id === 'form-filtros') buscar(); if (e.target.id === 'form-verif') verificar(); });
  // «‹» y los enlaces al detalle: se recuerda la posición de la lista para volver sin repetir la consulta
  document.addEventListener('click', (e) => { const a = e.target.closest('a[href^="#/bitacora/"]'); if (a && location.hash === '#/bitacora/resultados') est.scroll = window.scrollY; }, true);

  navegar();
})();
