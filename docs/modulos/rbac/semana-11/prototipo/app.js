// Prototipo navegable RBAC (semana 11): una sola aplicación para escritorio y móvil.
// La interfaz NUNCA decide: pregunta al PDP para mostrar u ocultar, y el servidor vuelve a autorizar cada operación.
(function () {
  'use strict';
  const D = window.DATOS;
  let api = window.crearApi(D);
  const RED = { latenciaMs: 300, sinConexion: false, perderRespuesta: false };
  const CACHE_MS = 30000; // decisiones del PDP: 30 s, solo en memoria (D-5)
  const PACIENTE = 'P-00123';
  const SECCIONES = [
    { permiso: 'expediente.leer', href: `#/pacientes/${PACIENTE}`, texto: 'Pacientes', ico: '🩺', detalle: 'Ficha y registro clínico' },
    { permiso: 'roles.asignar', href: '#/roles', texto: 'Roles', ico: '👥', detalle: 'Asignar y revocar roles' },
    { permiso: 'auditoria.consultar', href: '#/auditoria', texto: 'Auditoría', ico: '🔎', detalle: 'Buscar una referencia' },
  ];
  const est = { sesion: null, cache: new Map(), secciones: [], denegacion: null, aviso: null, asignar: null, paciente: null };

  // ---------- utilidades ----------
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const persona = (id) => D.personas[id] || { nombre: id, servicio: '', iniciales: '?', color: '#5B7083' };
  const hosp = () => D.hospitales[est.sesion.hospital];
  const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleDateString('es-GT', { timeZone: 'America/Guatemala', day: '2-digit', month: '2-digit', year: 'numeric' }) : null);
  const agrupar = (ref) => (ref || '').split('-').join(' · ');
  const normalizarRef = (txt) => txt.replace(/[\s·]+/g, (m) => (m.includes('·') ? '-' : '')).replace(/--+/g, '-').trim();
  const anunciar = (txt, urgente = false) => { const n = $(urgente ? '#alerta' : '#vivo'); n.textContent = ''; setTimeout(() => { n.textContent = txt; }, 30); };
  const avatar = (id, extra = '') => { const p = persona(id); return `<span class="avatar" ${extra} style="background:${p.color}">${esc(p.iniciales)}</span>`; };

  async function llamar(metodo, url, cuerpo = null) {
    return api.solicitarRed(metodo, url, { token: est.sesion.token, tenant: est.sesion.hospital, cuerpo }, RED);
  }
  // PDP con caché: solo sirve para mostrar u ocultar. Toda denegación del PDP también queda auditada (comportamiento real).
  async function puede(permiso, recurso) {
    const c = est.cache.get(permiso);
    if (c && Date.now() - c.t < CACHE_MS) return c.r;
    const r = await llamar('POST', '/api/v1/autorizaciones/decisiones', { permiso, recurso });
    const res = { permitido: r.cuerpo.decision === 'PERMITIDO', ref: r.cuerpo.correlation_id || null };
    est.cache.set(permiso, { r: res, t: Date.now() });
    return res;
  }

  // ---------- rutas ----------
  const RUTAS = [
    { patron: /^\/ingresar$/, vista: vIngresar, publica: true, titulo: 'Ingresar' },
    { patron: /^\/inicio$/, vista: vInicio, titulo: 'Inicio' },
    { patron: /^\/pacientes\/(P-\d+)$/, vista: vPaciente, permiso: 'expediente.leer', titulo: 'Ficha del paciente', seccion: 'Pacientes' },
    { patron: /^\/roles$/, vista: vRoles, permiso: 'roles.asignar', titulo: 'Roles del hospital', seccion: 'Roles' },
    { patron: /^\/roles\/asignar$/, vista: vAsignar, permiso: 'roles.asignar', titulo: 'Asignar rol', seccion: 'Roles' },
    { patron: /^\/auditoria$/, vista: vAuditoria, permiso: 'auditoria.consultar', titulo: 'Consulta de auditoría', seccion: 'Auditoría' },
    { patron: /^\/acceso-denegado$/, vista: vDenegado, titulo: 'Acceso denegado' },
  ];

  async function navegar() {
    const [ruta, consulta = ''] = (location.hash.slice(1) || '/inicio').split('?');
    const q = new URLSearchParams(consulta);
    if (!est.sesion && ruta !== '/ingresar') { location.replace('#/ingresar'); return; }
    let def = null, params = [];
    for (const r of RUTAS) { const m = ruta.match(r.patron); if (m) { def = r; params = m.slice(1); break; } }
    if (!def) { location.replace(est.sesion ? '#/inicio' : '#/ingresar'); return; }
    cerrarMenu();
    pintarMarco(def);
    const main = $('#contenido');
    // Ruta protegida: el guard pregunta al PDP; sin permiso, a la página de acceso denegado con la referencia.
    if (def.permiso) {
      main.innerHTML = `<p class="cargando" role="status">Verificando permisos…</p>`;
      let d;
      try { d = await puede(def.permiso, `ruta:#${ruta}`); } catch { d = null; }
      if (d === null) { main.innerHTML = vSinVerificar(); enfocarTitulo('No pudimos verificar tus permisos'); return; }
      if (!d.permitido) { est.denegacion = { ref: d.ref, donde: def.titulo }; location.replace('#/acceso-denegado'); return; }
    }
    main.innerHTML = await def.vista(params, q);
    document.title = `${def.titulo} · HIS`;
    enfocarTitulo();
    if (def.alMontar) def.alMontar();
  }
  function enfocarTitulo() {
    const h1 = $('#contenido h1');
    if (h1) { h1.setAttribute('tabindex', '-1'); h1.focus({ preventScroll: false }); }
  }

  // ---------- marco: barra, red y navegación ----------
  function pintarMarco(def) {
    const barra = $('#barra'), nav = $('#nav');
    if (!est.sesion) { barra.hidden = true; nav.hidden = true; document.body.classList.add('sin-nav'); $('#red').innerHTML = ''; return; }
    const p = persona(est.sesion.usuario);
    barra.hidden = false;
    barra.innerHTML = `
      <a class="marca" href="#/inicio" aria-label="Inicio">HIS</a>
      <span class="hospital" aria-label="Hospital activo: ${esc(hosp().nombre)}"><span class="corto">${esc(hosp().corto)}</span><span class="largo">${esc(hosp().nombre)}</span></span>
      <span class="rol-actual">${esc(p.servicio)}</span>
      <button class="avatar-btn" id="btn-usuario" aria-expanded="false" aria-controls="menu-usuario" aria-label="Menú de ${esc(p.nombre)}">${avatar(est.sesion.usuario, 'aria-hidden="true"')}</button>`;
    $('#red').innerHTML = RED.sinConexion
      ? `<div class="red" role="status"><span aria-hidden="true">⦸</span> Sin conexión <button type="button" data-accion="reconectar">Reintentar</button></div>` : '';
    const visibles = est.secciones;
    nav.hidden = visibles.length < 2; // con una sola sección no hay barra de navegación (R-B8)
    document.body.classList.toggle('sin-nav', visibles.length < 2);
    nav.innerHTML = visibles.map((s) => `<a href="${s.href}" ${def.seccion === s.texto ? 'aria-current="page"' : ''}><span class="ico" aria-hidden="true">${s.ico}</span>${s.texto}</a>`).join('');
  }

  // ---------- sesión ----------
  async function iniciarSesion(id) {
    const token = persona(id).token;
    est.sesion = { usuario: id, token, hospital: D.semilla.tokens[token][1] };
    est.cache.clear(); est.aviso = null; est.asignar = null; est.paciente = null; est.denegacion = null;
    // La navegación solo muestra secciones que el PDP permite (las denegaciones de esta consulta quedan auditadas: ver B-6).
    est.secciones = [];
    for (const s of SECCIONES) { if ((await puede(s.permiso, `seccion:${s.texto}`)).permitido) est.secciones.push(s); }
    anunciar(`Sesión iniciada como ${persona(id).nombre}`);
    location.hash = '#/inicio';
  }
  function cerrarSesion() {
    est.sesion = null; est.cache.clear(); est.secciones = []; // al cerrar sesión se borra la caché (D-5)
    location.hash = '#/ingresar';
  }

  // ---------- vistas ----------
  function vIngresar() {
    const demo = ['admin-01', 'nuevo-01', 'ana', 'marta'];
    return `
      <h1>Ingresar al HIS</h1>
      <p class="demo-nota">Prototipo de demostración: elige con quién entrar. No hay contraseñas ni datos reales.</p>
      <div class="lista-personas" style="margin-top:16px">
        ${demo.map((id) => { const p = persona(id); return `
          <button type="button" class="tarjeta persona-opcion" data-accion="ingresar" data-id="${id}">
            ${avatar(id, 'aria-hidden="true"')}
            <span><strong>${esc(p.nombre)}</strong><span class="meta" style="display:block">${esc(p.servicio)} · ${esc(id)}</span></span>
          </button>`; }).join('')}
      </div>
      <p class="ayuda" style="margin-top:16px">Todas las sesiones comparten el mismo servidor simulado: lo que hace una persona lo ve la siguiente.
        <button type="button" class="btn texto" data-accion="reiniciar">Reiniciar demostración</button></p>`;
  }

  function vInicio() {
    const p = persona(est.sesion.usuario);
    const tarjetas = est.secciones.map((s) => `
      <a class="tarjeta seccion" href="${s.href}"><span class="ico" aria-hidden="true">${s.ico}</span>
        <span><strong>${s.texto}</strong><span class="meta">${s.detalle}</span></span></a>`).join('');
    return `
      <h1>Hola, ${esc(p.nombre.split(' ')[0])}</h1>
      <p class="sub">${esc(p.servicio)} · ${esc(hosp().nombre)}</p>
      ${tarjetas ? `<div class="secciones">${tarjetas}</div>`
        : `<section class="aviso atencion"><h2>No tienes secciones disponibles en este hospital</h2>
             <p style="margin:0">Si crees que es un error, contacta a tu administrador.</p></section>`}`;
  }

  function vSinVerificar() {
    return `<h1>No pudimos verificar tus permisos</h1>
      <section class="aviso error"><p style="margin:0">${D.mensajes['M-13']}</p>
      <div class="acciones-fila"><button class="btn primario" type="button" data-accion="reconectar">Reintentar</button></div></section>`;
  }

  function avisoHtml(a) {
    if (!a) return '';
    if (a.tipo === 'ok') return `
      <section class="aviso ok" role="status" id="aviso" tabindex="-1">
        <button type="button" class="btn texto icono cerrar" data-accion="cerrar-aviso" aria-label="Cerrar aviso">×</button>
        <h2>✓ ${esc(a.titulo)}</h2><p style="margin:0 0 8px">${a.texto}</p>
        ${a.ref ? `<div class="referencia" aria-label="Referencia ${esc(a.ref)}">${esc(agrupar(a.ref))}</div>
        <div class="acciones-fila"><button type="button" class="btn" data-accion="copiar" data-ref="${esc(a.ref)}">Copiar referencia</button></div>` : ''}
      </section>`;
    if (a.tipo === 'denegado') return `
      <section class="aviso error" role="alert" id="aviso" tabindex="-1">
        <h2 style="color:var(--peligro)">${esc(a.titulo || 'No se guardó: acceso denegado')}</h2>
        <p style="margin:0 0 8px">${D.mensajes['M-11']}</p>
        <div class="referencia" aria-label="Referencia ${esc(a.ref)}">${esc(agrupar(a.ref))}</div>
        <div class="acciones-fila">
          ${navigator.share ? `<button type="button" class="btn primario" data-accion="compartir" data-ref="${esc(a.ref)}">Compartir referencia</button>` : ''}
          <button type="button" class="btn ${navigator.share ? '' : 'primario'}" data-accion="copiar" data-ref="${esc(a.ref)}">Copiar referencia</button>
        </div>
        <p class="ayuda" style="margin-bottom:0">Se comparte solo la referencia y el hospital, nunca datos del paciente. Este intento quedó registrado.</p>
      </section>`;
    return `<section class="aviso ${a.tipo === 'atencion' ? 'atencion' : 'error'}" role="alert" id="aviso" tabindex="-1">
        <h2>${esc(a.titulo)}</h2><p style="margin:0">${a.texto}</p>${a.extra || ''}</section>`;
  }

  // ----- paciente (operación protegida) -----
  async function vPaciente([id]) {
    est.paciente = est.paciente && est.paciente.id === id ? est.paciente : { id, valores: { pa: '120/80', fc: '78', t: '36.8', sat: '97' }, aviso: null };
    let signos, prescribir;
    try {
      signos = await puede('signos_vitales.registrar', `paciente/${id}`);
      prescribir = await puede('prescripciones.crear', `paciente/${id}`);
    } catch { return vSinVerificar(); }
    const v = est.paciente.valores, denegado = est.paciente.aviso && est.paciente.aviso.tipo === 'denegado';
    const campo = (k, etiqueta, modo) => `<div style="flex:1"><label for="sv-${k}">${etiqueta}</label><input id="sv-${k}" name="${k}" inputmode="${modo}" value="${esc(v[k])}" ${denegado ? 'readonly' : ''}></div>`;
    const form = signos.permitido || denegado ? `
      <form class="tarjeta" id="form-signos" novalidate>
        <h2>Registrar signos vitales</h2>
        <div class="fila" style="gap:12px">${campo('pa', 'Presión', 'numeric')}${campo('fc', 'Pulso', 'numeric')}</div>
        <div class="fila" style="gap:12px">${campo('t', 'Temp. °C', 'decimal')}${campo('sat', 'SpO₂ %', 'numeric')}</div>
      </form>` : '';
    const ninguna = !signos.permitido && !prescribir.permitido && !denegado;
    const acciones = denegado
      ? `<a class="btn" href="#/inicio">Volver al inicio</a>`
      : `${signos.permitido ? `<button class="btn primario" type="submit" form="form-signos" id="btn-guardar">Guardar signos vitales</button>` : ''}
         ${prescribir.permitido ? `<button class="btn" type="button">Nueva prescripción</button>` : ''}`;
    return `
      <p class="meta" style="margin:0">Paciente de prueba (ficticio)</p>
      <h1 style="margin-top:0">${esc(id)} · Signos vitales</h1>
      ${avisoHtml(est.paciente.aviso)}
      ${ninguna ? `<section class="aviso atencion"><h2>No tienes acciones disponibles para este paciente</h2><p style="margin:0">Puedes leer la ficha, pero no registrar datos.</p></section>` : ''}
      ${form}
      <p class="meta extra">Última toma: 08:00</p>
      ${acciones.trim() ? `<div class="accion-fija">${acciones}</div>` : ''}`;
  }

  async function guardarSignos(form) {
    const btn = $('#btn-guardar');
    const valores = Object.fromEntries(new FormData(form));
    est.paciente.valores = valores;
    btn.setAttribute('aria-disabled', 'true'); btn.textContent = 'Guardando…';
    let r;
    try { r = await llamar('POST', '/api/v1/signos-vitales', valores); } catch {
      est.paciente.aviso = { tipo: 'error', titulo: 'Sin conexión: no se guardó', texto: 'Lo escrito se conserva en esta pantalla. No se guarda en el teléfono.' };
      await refrescar(); $('#aviso')?.focus(); return;
    }
    if (r.estado === 201) est.paciente.aviso = { tipo: 'ok', titulo: 'Signos vitales registrados', texto: `Paciente ${esc(est.paciente.id)} · ${esc(persona(est.sesion.usuario).nombre)}` };
    else if (r.estado === 403) {
      // El servidor re-autorizó y negó: esa decisión en caché quedó vieja. Se borra y la acción deja de mostrarse.
      // (Solo esa: si se borrara todo, el guard de la ruta sacaría a la persona antes de que lea la referencia.)
      est.cache.delete('signos_vitales.registrar');
      est.paciente.aviso = { tipo: 'denegado', ref: r.cuerpo.correlation_id };
      anunciar('Acceso denegado. No se guardó. Hay una referencia para compartir con tu administrador.', true);
    } else est.paciente.aviso = { tipo: 'error', titulo: 'No se guardó', texto: D.mensajes['M-12'] };
    await refrescar();
    $('#aviso')?.focus(); // el aviso recibe el foco: se lee y la referencia queda a un Tab (BL-03)
  }

  // ----- roles del hospital -----
  async function vRoles(_, q) {
    const usuario = q.get('usuario');
    let cuerpoPersona = '';
    if (usuario) {
      let r, puedeRevocar;
      try {
        r = await llamar('GET', `/api/v1/roles/membresias?usuario=${encodeURIComponent(usuario)}`);
        puedeRevocar = (await puede('roles.revocar', 'membresias')).permitido;
      } catch { return vSinVerificar(); }
      const p = persona(usuario);
      const filas = r.cuerpo.membresias.map((m) => {
        const ahora = new Date(D.ahora);
        const e = m.estado === 'revoked' ? 'revocado'
          : new Date(m.valido_desde) > ahora ? 'futuro'
          : m.valido_hasta && new Date(m.valido_hasta) <= ahora ? 'vencido' : 'activo';
        const vig = `${fechaCorta(m.valido_desde)} – ${m.valido_hasta ? fechaCorta(m.valido_hasta) : 'sin fin'}`;
        const propia = usuario === est.sesion.usuario;
        const accion = e !== 'activo' ? 'Sin acciones: el rol no está activo.'
          : propia ? 'Tu propio rol: otro administrador puede revocarlo.'
          : !puedeRevocar ? 'Sin acciones disponibles.' : null;
        return { m, e, vig, accion };
      });
      const st = (e) => `<span class="estado ${D.estados[e].clase}">${D.estados[e].icono} ${D.estados[e].texto}</span>`;
      const boton = (f) => `<button type="button" class="btn" data-accion="abrir-revocar" data-usuario="${esc(usuario)}" data-rol="${esc(f.m.rol)}">Revocar rol</button>`;
      cuerpoPersona = `
        <section class="tarjeta" aria-labelledby="persona"><div class="fila">${avatar(usuario, 'aria-hidden="true"')}
          <div><h2 id="persona" style="margin:0">${esc(p.nombre)}</h2><div class="meta">${esc(usuario)} · ${esc(p.servicio)}</div></div></div></section>
        <h2 class="solo-movil">Roles en este hospital (${filas.length}) <span class="badge-brecha" title="Endpoint propuesto, aún no está en el contrato">B-1</span></h2>
        ${filas.length === 0 ? `<p class="aviso atencion">Esta persona no tiene roles en este hospital.</p>` : ''}
        <ul class="solo-movil" style="list-style:none;padding:0;margin:0">
          ${filas.map((f) => `<li class="tarjeta"><div class="fila"><strong>${esc(D.roles[f.m.rol] || f.m.rol)}</strong><span class="derecha">${st(f.e)}</span></div>
            <div class="meta">${f.vig}</div>
            ${f.accion ? `<div class="meta">${f.accion}</div>` : `<div class="fila" style="margin-top:10px">${boton(f).replace('class="btn"', 'class="btn bloque"')}</div>`}</li>`).join('')}
        </ul>
        ${filas.length ? `<table class="tabla"><caption class="oculto-visual">Roles de ${esc(p.nombre)} en ${esc(hosp().nombre)}</caption>
          <thead><tr><th scope="col">Rol</th><th scope="col">Estado</th><th scope="col">Vigencia</th><th scope="col">Acción</th></tr></thead>
          <tbody>${filas.map((f) => `<tr><td>${esc(D.roles[f.m.rol] || f.m.rol)}</td><td>${st(f.e)}</td><td>${f.vig}</td><td>${f.accion ? `<span class="meta">${f.accion}</span>` : boton(f)}</td></tr>`).join('')}</tbody></table>` : ''}`;
    }
    return `
      <h1>Roles del hospital</h1>
      ${avisoHtml(est.aviso)}
      <form role="search" data-accion="buscar-persona" data-destino="#/roles">
        <label for="buscar">Buscar persona por nombre o usuario <span class="badge-brecha" title="Endpoint propuesto, aún no está en el contrato">B-4</span></label>
        <input id="buscar" name="q" type="search" autocomplete="off" value="${esc(usuario ? persona(usuario).nombre : '')}" aria-describedby="buscar-ayuda">
        <p class="ayuda" id="buscar-ayuda">Solo personas de ${esc(hosp().nombre)}. Escribe al menos 2 letras.</p>
        <div id="resultados"></div>
      </form>
      ${cuerpoPersona}
      ${usuario ? `<div class="accion-fija"><a class="btn primario" href="#/roles/asignar?usuario=${encodeURIComponent(usuario)}">＋ Asignar rol a ${esc(persona(usuario).nombre.split(' ')[0])}</a></div>` : ''}`;
  }

  async function buscarPersonas(input, destino) {
    const cont = input.form.querySelector('#resultados');
    const q = input.value.trim();
    if (q.length < 2) { cont.innerHTML = ''; return; }
    let r;
    try { r = await llamar('GET', `/api/v1/usuarios?q=${encodeURIComponent(q)}`); } catch { cont.innerHTML = `<p class="error">${D.mensajes['M-12']}</p>`; return; }
    if (input.value.trim() !== q) return; // llegó una respuesta vieja
    const lista = r.cuerpo.usuarios;
    cont.innerHTML = lista.length === 0
      ? `<p class="meta" role="status">No hay personas con «${esc(q)}» en ${esc(hosp().corto)}.</p>`
      : `<div role="list">` + lista.map((u) => { const p = persona(u.id); return `
        <div role="listitem"><a class="resultado" href="${destino}${destino.includes('?') ? '&' : '?'}usuario=${encodeURIComponent(u.id)}" style="text-decoration:none;color:inherit">
          ${avatar(u.id, 'aria-hidden="true"')}<span><strong>${esc(p.nombre)}</strong><span class="meta" style="display:block">${esc(u.id)} · ${esc(p.servicio)}</span></span></a></div>`; }).join('')
        + `</div><p class="oculto-visual" role="status">${lista.length} resultado(s)</p>`;
  }

  // ----- revocar (diálogo) -----
  let opener = null;
  function abrirRevocar(boton) {
    opener = boton;
    const usuario = boton.dataset.usuario, rol = boton.dataset.rol, p = persona(usuario);
    const lista = D.semilla.permisosPorRol[rol].map((x) => `<strong>${D.permisos[x]}</strong>`);
    const pierde = lista.length > 1 ? `${lista.slice(0, -1).join(', ')} ni ${lista[lista.length - 1]}` : lista[0];
    $('#capa-dialogo').innerHTML = `
      <div class="velo"></div>
      <section class="hoja" role="dialog" aria-modal="true" aria-labelledby="dlg-titulo" aria-describedby="dlg-consecuencia">
        <div class="asa" aria-hidden="true"></div>
        <h2 id="dlg-titulo" style="font-size:1.25rem">Revocar ${esc(D.roles[rol])} a ${esc(p.nombre)}</h2>
        <p class="meta" style="margin:-4px 0 12px">en ${esc(hosp().nombre)}</p>
        <div class="aviso atencion" id="dlg-consecuencia">Desde ahora no podrá ${pierde}. Sus intentos quedarán registrados.</div>
        <form id="form-revocar" novalidate data-usuario="${esc(usuario)}" data-rol="${esc(rol)}">
          <div id="c-motivo"><label for="motivo">Motivo <span style="font-weight:400">(queda en la bitácora)</span></label>
          <textarea id="motivo" name="motivo" maxlength="500" aria-describedby="contador err-motivo"></textarea>
          <p class="ayuda" id="contador">0 / 10 mínimo</p><p class="error" id="err-motivo" hidden></p></div>
          <label class="casilla"><input type="checkbox" id="entiendo" aria-describedby="err-entiendo"> Entiendo que el cambio es inmediato</label>
          <p class="error" id="err-entiendo" hidden>Marca la casilla para confirmar.</p>
          <p class="error" id="err-dlg" role="alert" hidden></p>
          <p class="ayuda" id="req-revocar">Para revocar: motivo de al menos 10 caracteres y la casilla marcada.</p>
          <div class="acciones">
            <button type="submit" class="btn bloque peligro" id="btn-revocar" aria-describedby="req-revocar">Revocar rol</button>
            <button type="button" class="btn bloque" data-accion="cerrar-dialogo">Cancelar</button>
          </div>
        </form>
      </section>`;
    $('#app').inert = true; // el resto de la página, barra incluida, queda inerte (BL-05)
    $('#capa-dialogo [data-accion="cerrar-dialogo"]').focus(); // foco inicial en el camino seguro
  }
  function cerrarDialogo() {
    $('#capa-dialogo').innerHTML = ''; $('#app').inert = false;
    if (opener && document.contains(opener)) opener.focus();
  }
  async function enviarRevocacion(form) {
    const motivo = form.motivo.value.trim(), entiendo = $('#entiendo').checked;
    const errMotivo = $('#err-motivo'), errEntiendo = $('#err-entiendo');
    errMotivo.hidden = [...motivo].length >= 10; errMotivo.textContent = D.mensajes['M-06'];
    form.motivo.setAttribute('aria-invalid', String(!errMotivo.hidden));
    $('#c-motivo').classList.toggle('campo-error', !errMotivo.hidden);
    errEntiendo.hidden = entiendo;
    if (!errMotivo.hidden) { form.motivo.focus(); return; }
    if (!entiendo) { $('#entiendo').focus(); return; }
    const btn = $('#btn-revocar'); btn.setAttribute('aria-disabled', 'true'); btn.textContent = 'Revocando…';
    const usuario = form.dataset.usuario, rol = form.dataset.rol;
    let r;
    try { r = await llamar('POST', '/api/v1/roles/revocaciones', { usuario_id: usuario, rol, motivo }); } catch {
      // Revocar es seguro de reintentar (semana 10, R-C1): un segundo envío da 404 si el primero llegó.
      const e = $('#err-dlg'); e.hidden = false; e.textContent = 'No sabemos si se revocó: la respuesta no llegó. Puedes reintentar sin riesgo.';
      btn.removeAttribute('aria-disabled'); btn.textContent = 'Reintentar'; return;
    }
    if (r.estado === 200) {
      est.aviso = { tipo: 'ok', titulo: D.mensajes['M-21'], texto: `${esc(persona(usuario).nombre)} ya no tiene ${esc(D.roles[rol])} en este hospital.`, ref: r.cuerpo.correlation_id };
      cerrarDialogo(); await refrescar(); $('#aviso')?.focus(); anunciar(`${D.mensajes['M-21']}.`); return;
    }
    const mapa = { MOTIVO_INSUFICIENTE: 'M-06', AUTORREVOCACION: 'M-08', MEMBRESIA_NO_ENCONTRADA: 'M-07' };
    if (r.estado === 404) { est.aviso = { tipo: 'atencion', titulo: D.mensajes['M-07'], texto: '' }; cerrarDialogo(); await refrescar(); $('#aviso')?.focus(); return; }
    if (r.estado === 403) { est.aviso = { tipo: 'denegado', titulo: 'No se revocó: acceso denegado', ref: r.cuerpo.correlation_id }; cerrarDialogo(); await refrescar(); $('#aviso')?.focus(); return; }
    const e = $('#err-dlg'); e.hidden = false; e.textContent = D.mensajes[mapa[r.cuerpo.codigo]] || D.mensajes['M-12'];
    btn.removeAttribute('aria-disabled'); btn.textContent = 'Revocar rol';
  }

  // ----- asignar (3 pasos) -----
  async function vAsignar(_, q) {
    if (!est.asignar || (q.get('usuario') && est.asignar.usuario !== q.get('usuario'))) {
      est.asignar = { paso: q.get('usuario') ? 2 : 1, usuario: q.get('usuario'), rol: '', desde: '2026-11-15', hasta: '', errores: {}, resultado: null };
    }
    const a = est.asignar, p = a.usuario ? persona(a.usuario) : null;
    const pasos = `<ol class="pasos" aria-hidden="true">${[1, 2, 3].map((n) => `<li class="${n <= a.paso ? 'hecho' : ''}"></li>`).join('')}</ol>`;
    const err = (k) => (a.errores[k] ? `<p class="error" id="err-${k}">${esc(a.errores[k])}</p>` : '');
    const inv = (k) => (a.errores[k] ? `aria-invalid="true" aria-describedby="err-${k}"` : '');
    if (a.resultado) return vResultadoAsignacion(a);
    if (a.paso === 1) return `
      <h1>Asignar rol</h1>${pasos}<p class="paso-texto">Paso 1 de 3 · Persona</p>
      <form role="search" data-accion="buscar-persona" data-destino="#/roles/asignar">
        <div class="${a.errores.usuario ? 'campo-error' : ''}">
          <label for="buscar">Buscar por nombre o usuario <span class="badge-brecha" title="Endpoint propuesto">B-4</span></label>
          <input id="buscar" name="q" type="search" autocomplete="off" ${inv('usuario')}>${err('usuario')}
        </div>
        <p class="ayuda">Solo personas de ${esc(hosp().nombre)}.</p>
        <div id="resultados"></div>
      </form>
      <div class="accion-fija"><a class="btn" href="#/roles">Cancelar</a></div>`;
    const rolesOpc = Object.keys(D.roles).map((k) => `<option value="${k}" ${a.rol === k ? 'selected' : ''}>${D.roles[k]}</option>`).join('');
    const podra = a.rol ? D.semilla.permisosPorRol[a.rol].map((x) => D.permisos[x]).join(', ') : '';
    if (a.paso === 2) return `
      <h1>Asignar rol</h1>${pasos}<p class="paso-texto">Paso 2 de 3 · Rol y vigencia para <strong>${esc(p.nombre)}</strong>
        <a class="btn texto" href="#/roles/asignar" data-accion="cambiar-persona">Cambiar persona</a></p>
      <form id="form-paso2" novalidate>
        <div class="${a.errores.rol ? 'campo-error' : ''}"><label for="rol">Rol</label>
          <select id="rol" name="rol" ${inv('rol')}><option value="">Elige un rol</option>${rolesOpc}</select>${err('rol')}
          <p class="ayuda" id="podra" aria-live="polite">${podra ? `Podrá: ${esc(podra)}.` : ''}</p></div>
        <div class="${a.errores.desde ? 'campo-error' : ''}"><label for="desde">Válido desde (dd/mm/aaaa)</label>
          <input id="desde" name="desde" type="date" value="${esc(a.desde)}" ${inv('desde')}>${err('desde')}</div>
        <div class="${a.errores.hasta ? 'campo-error' : ''}"><label for="hasta">Válido hasta (opcional)</label>
          <input id="hasta" name="hasta" type="date" value="${esc(a.hasta)}" ${a.errores.hasta ? inv('hasta') : 'aria-describedby="hasta-ayuda"'}>${err('hasta')}
          <p class="ayuda" id="hasta-ayuda">Déjalo vacío si el rol no vence.</p></div>
      </form>
      <div class="accion-fija"><button type="button" class="btn" data-accion="paso" data-paso="1">Atrás</button>
        <button type="submit" form="form-paso2" class="btn primario">Revisar</button></div>`;
    return `
      <h1>Asignar rol</h1>${pasos}<p class="paso-texto">Paso 3 de 3 · Confirmar</p>
      ${avisoHtml(a.aviso)}
      <section class="tarjeta" aria-labelledby="conf">
        <h2 id="conf">${esc(p.nombre)} <span class="meta">(${esc(a.usuario)})</span></h2>
        <p style="margin:0 0 8px">recibirá el rol <strong>${esc(D.roles[a.rol])}</strong> en <strong>${esc(hosp().nombre)}</strong>
          desde el <strong>${fechaCorta(a.desde + 'T12:00:00-06:00')}</strong>${a.hasta ? ` hasta el <strong>${fechaCorta(a.hasta + 'T12:00:00-06:00')}</strong>` : ', sin fecha de fin'}.</p>
        <p class="etiqueta" style="margin-top:8px">Podrá</p>
        <ul style="margin:0;padding-left:20px">${D.semilla.permisosPorRol[a.rol].map((x) => `<li>${esc(D.permisos[x])}</li>`).join('')}</ul>
        <p class="ayuda">El cambio queda registrado en la bitácora con tu nombre.</p>
      </section>
      <div class="accion-fija"><button type="button" class="btn" data-accion="paso" data-paso="2">Atrás</button>
        <button type="button" class="btn primario" data-accion="confirmar-asignacion" id="btn-asignar">Asignar rol</button></div>`;
  }
  function vResultadoAsignacion(a) {
    const p = persona(a.usuario), r = a.resultado;
    const cuerpo = r.tipo === 'ok'
      ? avisoHtml({ tipo: 'ok', titulo: r.yaEstaba ? 'El rol ya estaba asignado' : D.mensajes['M-20'],
          texto: r.yaEstaba ? `Tu solicitud anterior sí llegó. ${esc(p.nombre)} tiene ${esc(D.roles[a.rol])} activo.` : `${esc(p.nombre)} ya puede ${esc(D.semilla.permisosPorRol[a.rol].map((x) => D.permisos[x]).join(' y '))}.`, ref: r.ref })
      : r.tipo === 'incierto'
        ? avisoHtml({ tipo: 'atencion', titulo: 'No sabemos si se asignó', texto: `La respuesta no llegó. Puede que ${esc(D.roles[a.rol])} para ${esc(p.nombre)} ya esté asignado.`,
            extra: `<p class="ayuda">${a.hasta ? 'Como tiene fecha de fin, revisa sus roles antes de reintentar: un reintento crearía otra asignación.' : 'Puedes reintentar sin riesgo: como no tiene fecha de fin, el sistema no crea una segunda asignación.'}</p>` })
        : avisoHtml({ tipo: 'denegado', titulo: 'No se asignó: acceso denegado', ref: r.ref });
    const acciones = r.tipo === 'incierto'
      ? `<a class="btn ${a.hasta ? 'primario' : ''}" href="#/roles?usuario=${encodeURIComponent(a.usuario)}">Ver sus roles</a>${a.hasta ? '' : `<button type="button" class="btn primario" data-accion="confirmar-asignacion">Reintentar</button>`}`
      : `<a class="btn primario" href="#/roles?usuario=${encodeURIComponent(a.usuario)}" data-accion="terminar-asignacion">Ver roles de ${esc(p.nombre.split(' ')[0])}</a>`;
    return `<h1>Asignar rol</h1>${cuerpo}<div class="accion-fija">${acciones}</div>`;
  }
  function validarPaso2(form) {
    const a = est.asignar; a.errores = {};
    a.rol = form.rol.value; a.desde = form.desde.value; a.hasta = form.hasta.value;
    if (!a.rol) a.errores.rol = 'Elige un rol.';
    if (!a.desde) a.errores.desde = D.mensajes['M-01'];
    if (a.hasta && a.desde && a.hasta <= a.desde) a.errores.hasta = D.mensajes['M-04'];
    return Object.keys(a.errores).length === 0;
  }
  async function confirmarAsignacion() {
    const a = est.asignar, btn = $('[data-accion="confirmar-asignacion"]');
    if (btn) { btn.setAttribute('aria-disabled', 'true'); btn.textContent = 'Asignando…'; }
    const cuerpo = { usuario_id: a.usuario, rol: a.rol, valido_desde: a.desde };
    if (a.hasta) cuerpo.valido_hasta = a.hasta;
    let r;
    try { r = await llamar('POST', '/api/v1/roles/asignaciones', cuerpo); } catch { a.resultado = { tipo: 'incierto' }; a.intentos = (a.intentos || 0) + 1; return refrescar(); }
    const reintento = a.resultado && a.resultado.tipo === 'incierto';
    a.aviso = null;
    if (r.estado === 201) a.resultado = { tipo: 'ok', ref: r.cuerpo.correlation_id };
    else if (r.estado === 409 && reintento) a.resultado = { tipo: 'ok', yaEstaba: true }; // 409 tras un reintento = la primera sí llegó
    else if (r.estado === 403) a.resultado = { tipo: 'denegado', ref: r.cuerpo.correlation_id };
    else {
      a.resultado = null;
      const c = r.cuerpo.codigo;
      if (c === 'ASIGNACION_DUPLICADA') { a.paso = 2; a.errores = { rol: D.mensajes['M-03'] }; }
      else if (c === 'VIGENCIA_INVALIDA') { a.paso = 2; a.errores = { hasta: D.mensajes['M-04'] }; }
      else if (c === 'USUARIO_NO_ENCONTRADO') { a.paso = 1; a.errores = { usuario: D.mensajes['M-02'] }; }
      else if (c === 'USUARIO_INACTIVO') { a.paso = 1; a.errores = { usuario: D.mensajes['M-05'] }; }
      else if (c === 'AUTOASIGNACION') { a.paso = 1; a.errores = { usuario: D.mensajes['M-09'] }; }
      else a.aviso = { tipo: 'error', titulo: 'No se asignó', texto: D.mensajes['M-12'] };
    }
    await refrescar();
    if (a.resultado) { $('#aviso')?.focus(); anunciar(a.resultado.tipo === 'ok' ? 'Rol asignado.' : '', a.resultado.tipo === 'denegado'); }
  }

  // ----- auditoría -----
  async function vAuditoria(_, q) {
    const ref = q.get('ref') || '';
    let resultado = '';
    if (ref) {
      if (!/^[A-Za-z0-9-]{1,64}$/.test(ref)) resultado = `<p class="error" id="err-ref">${D.mensajes['M-30']}</p>`;
      else {
        let r;
        try { r = await llamar('GET', `/api/v1/auditoria/eventos/${ref}`); } catch { return vSinVerificar(); }
        resultado = r.estado === 200 ? tarjetaEvento(r.cuerpo)
          : r.estado === 404 ? `<section class="aviso atencion" role="status"><h2>No hay un evento con esa referencia en ${esc(hosp().nombre)}</h2>
              <p style="margin:0">Revisa que esté completa: 32 letras y números, sin contar espacios ni guiones. Si la persona trabaja en otro hospital, cambia de hospital y vuelve a buscar.</p></section>`
          : `<p class="error">${D.mensajes['M-12']}</p>`;
      }
    }
    return `
      <h1>Consulta de auditoría</h1>
      <form role="search" data-accion="buscar-ref">
        <label for="ref">Referencia</label>
        <div class="fila"><input id="ref" name="ref" value="${esc(ref)}" autocomplete="off" autocapitalize="off" spellcheck="false" aria-describedby="ref-ayuda${resultado.includes('err-ref') ? ' err-ref' : ''}">
          <button class="btn primario" type="submit">Buscar</button></div>
        <p class="ayuda" id="ref-ayuda">Pégala o escríbela: los espacios no importan.</p>
      </form>
      <div id="resultado-auditoria">${resultado}</div>`;
  }
  function tarjetaEvento(e) {
    const tipos = { ACCESO_DENEGADO: ['Acceso denegado', 'revocado'], ROL_ASIGNADO: ['Rol asignado', 'activo'], ROL_REVOCADO: ['Rol revocado', 'vencido'] };
    const [titulo, clase] = tipos[e.tipo] || [e.tipo, 'vencido'];
    const d = e.datos;
    const fila = (k, v) => `<dt>${k}</dt><dd>${v}</dd>`;
    let principal = '', explicacion = '';
    if (e.tipo === 'ACCESO_DENEGADO') {
      principal = fila('Persona', `${esc(persona(e.actor).nombre)} (${esc(e.actor)})`) + fila('Intentó', esc(D.permisos[d.permiso] || d.permiso));
      const [mt, mx] = D.motivos[d.motivo] || [d.motivo, ''];
      explicacion = `<div class="aviso atencion" style="margin:12px 0 0"><strong>Motivo: ${esc(mt.toLowerCase())}</strong>
        <p style="margin:4px 0 0">${esc(mx)}</p><p class="meta" style="margin:4px 0 0">Código: ${esc(d.motivo)}</p></div>`;
    } else {
      principal = fila('Hecho por', `${esc(persona(e.actor).nombre)} (${esc(e.actor)})`) + fila('Persona', esc(persona(d.usuario_destino).nombre))
        + fila('Rol', esc(D.roles[d.rol] || d.rol)) + (d.motivo ? fila('Motivo', esc(d.motivo)) : '');
    }
    const tecnico = (d.permiso ? fila('Permiso', esc(d.permiso)) : '') + (d.recurso ? fila('Recurso', esc(d.recurso)) : '')
      + fila('Origen (IP)', esc(d.ip)) + `<dt>Referencia</dt><dd class="referencia">${esc(e.correlation_id)}</dd>`;
    return `<article class="tarjeta" aria-labelledby="ev" tabindex="-1" id="aviso">
      <div class="fila"><span class="estado ${clase}" id="ev">${titulo.toUpperCase()}</span><span class="meta derecha">${fechaCorta(e.ocurrido_en)} 10:00</span></div>
      <dl class="datos">${principal}</dl>${explicacion}
      <details style="margin-top:12px"><summary style="min-height:48px;display:flex;align-items:center;font-weight:700;color:var(--primario);cursor:pointer">Detalles técnicos</summary>
      <dl class="datos">${tecnico}</dl></details></article>`;
  }

  // ----- acceso denegado (destino del guard) -----
  function vDenegado() {
    const d = est.denegacion || { ref: null, donde: 'esta sección' };
    return `
      <div class="tarjeta" style="text-align:center;padding:24px 16px">
        <div aria-hidden="true" style="font-size:2rem;width:64px;height:64px;border-radius:50%;background:var(--fondo);border:1px solid var(--borde);display:grid;place-items:center;margin:0 auto 12px">!</div>
        <h1>No tienes acceso a esta sección</h1>
        <p>Si crees que es un error, comparte esta referencia con tu administrador:</p>
        ${d.ref ? `<div class="referencia" style="text-align:left">${esc(agrupar(d.ref))}</div>
        <div class="acciones-fila" style="justify-content:center"><button type="button" class="btn" data-accion="copiar" data-ref="${esc(d.ref)}">Copiar referencia</button></div>` : ''}
        <p class="meta"><em>Este intento quedó registrado.</em></p>
        <div class="acciones-fila" style="justify-content:center"><a class="btn primario" href="#/inicio">Volver al inicio</a></div>
        <details style="text-align:left;margin-top:16px"><summary style="min-height:48px;display:flex;align-items:center;font-weight:700;color:var(--primario);cursor:pointer">¿Cómo solicito acceso?</summary>
          <p>Pídeselo al administrador de ${esc(hosp().nombre)} y dale la referencia. Con ella puede ver qué pasó sin que tengas que explicarlo.</p></details>
      </div>`;
  }

  // ---------- menú de usuario y herramientas de demostración ----------
  function abrirMenu() {
    const p = persona(est.sesion.usuario);
    const menu = document.createElement('div');
    menu.className = 'menu-usuario'; menu.id = 'menu-usuario';
    menu.innerHTML = `
      <div class="quien"><strong>${esc(p.nombre)}</strong><div class="meta">${esc(p.servicio)} · ${esc(hosp().nombre)}</div></div>
      <button class="btn" data-accion="salir">Cambiar de usuario</button>
      <div class="grupo" aria-hidden="true">Demostración</div>
      <button class="btn" data-accion="demo-revocar">Otra sesión: Carlos revoca Enfermería de Ana</button>
      <button class="btn" data-accion="demo-red" aria-pressed="${RED.sinConexion}">${RED.sinConexion ? 'Volver a tener conexión' : 'Simular sin conexión'}</button>
      <button class="btn" data-accion="demo-perder">Perder la próxima respuesta</button>`;
    $('#barra').after(menu);
    $('#btn-usuario').setAttribute('aria-expanded', 'true');
    menu.querySelector('button').focus();
  }
  function cerrarMenu(devolverFoco = false) {
    const m = $('#menu-usuario'); if (!m) return;
    m.remove(); const b = $('#btn-usuario'); if (b) { b.setAttribute('aria-expanded', 'false'); if (devolverFoco) b.focus(); }
  }
  async function refrescar() { const y = window.scrollY; await navegar(); window.scrollTo(0, y); }

  // ---------- eventos (delegados) ----------
  document.addEventListener('click', async (ev) => {
    const el = ev.target.closest('[data-accion]');
    if (!el) { if (!ev.target.closest('#menu-usuario, #btn-usuario')) cerrarMenu(); if (ev.target.closest('#btn-usuario')) ($('#menu-usuario') ? cerrarMenu(true) : abrirMenu()); return; }
    const acc = el.dataset.accion;
    if (el.getAttribute('aria-disabled') === 'true') { ev.preventDefault(); return; }
    switch (acc) {
      case 'ingresar': await iniciarSesion(el.dataset.id); break;
      case 'reiniciar': api = window.crearApi(D); anunciar('Demostración reiniciada.'); break;
      case 'salir': cerrarMenu(); cerrarSesion(); break;
      case 'copiar': {
        try { await navigator.clipboard.writeText(el.dataset.ref); } catch { /* file:// sin portapapeles: igual se confirma */ }
        el.textContent = 'Copiado ✓'; anunciar('Referencia copiada.'); setTimeout(() => { el.textContent = 'Copiar referencia'; }, 2000); break;
      }
      case 'compartir': try { await navigator.share({ title: 'Referencia de acceso denegado', text: `Referencia ${el.dataset.ref} · ${hosp().nombre}` }); } catch { /* cancelado */ } break;
      case 'cerrar-aviso': est.aviso = null; if (est.paciente) est.paciente.aviso = null; if (est.asignar) est.asignar.aviso = null; await refrescar(); break;
      case 'abrir-revocar': abrirRevocar(el); break;
      case 'cerrar-dialogo': cerrarDialogo(); break;
      case 'paso': est.asignar.paso = Number(el.dataset.paso); est.asignar.errores = {}; await refrescar(); break;
      case 'cambiar-persona': ev.preventDefault(); est.asignar.paso = 1; est.asignar.usuario = null; if (location.hash === '#/roles/asignar') await refrescar(); else location.hash = '#/roles/asignar'; break;
      case 'confirmar-asignacion': await confirmarAsignacion(); break;
      case 'terminar-asignacion': est.asignar = null; break;
      case 'saltar': enfocarTitulo(); break;
      case 'reconectar': RED.sinConexion = false; await refrescar(); break;
      case 'demo-revocar': {
        cerrarMenu(true);
        const r = api.solicitar('POST', '/api/v1/roles/revocaciones', { token: 'token-admin-oriente', tenant: 'hosp-oriente', cuerpo: { usuario_id: 'ana', rol: 'enfermeria', motivo: 'Traslado a consulta externa' } });
        anunciar(r.estado === 200 ? 'Demostración: Carlos revocó Enfermería de Ana en otra sesión. Esta pantalla no se entera hasta volver a preguntar.' : 'Demostración: Ana ya no tenía Enfermería activa.');
        break;
      }
      case 'demo-red': RED.sinConexion = !RED.sinConexion; cerrarMenu(true); await refrescar(); anunciar(RED.sinConexion ? 'Sin conexión (simulado).' : 'Conexión restablecida.'); break;
      case 'demo-perder': RED.perderRespuesta = true; cerrarMenu(true); anunciar('La próxima respuesta de una operación se perderá (simulado).'); break;
      default:
    }
  });
  document.addEventListener('submit', async (ev) => {
    const f = ev.target; ev.preventDefault();
    if (f.id === 'form-signos') await guardarSignos(f);
    else if (f.id === 'form-revocar') await enviarRevocacion(f);
    else if (f.id === 'form-paso2') { if (validarPaso2(f)) est.asignar.paso = 3; await refrescar(); if (Object.keys(est.asignar.errores).length) $('[aria-invalid="true"]')?.focus(); }
    else if (f.dataset.accion === 'buscar-ref') { location.hash = `#/auditoria?ref=${encodeURIComponent(normalizarRef(f.ref.value))}`; }
    else if (f.dataset.accion === 'buscar-persona') await buscarPersonas(f.q, f.dataset.destino);
  });
  let espera;
  document.addEventListener('input', (ev) => {
    const t = ev.target;
    if (t.id === 'buscar') { clearTimeout(espera); espera = setTimeout(() => buscarPersonas(t, t.form.dataset.destino), 250); }
    if (t.id === 'motivo') { const n = [...t.value.trim()].length; $('#contador').textContent = `${n} / 10 mínimo${n >= 10 ? ' ✓' : ''}`; } // contador neutro (BL-12)
    if (t.id === 'rol') { const r = t.value; $('#podra').textContent = r ? `Podrá: ${D.semilla.permisosPorRol[r].map((x) => D.permisos[x]).join(', ')}.` : ''; }
  });
  document.addEventListener('focusout', (ev) => {
    if (ev.target.id === 'motivo') { // el error aparece al salir del campo, no mientras se escribe (I-03)
      const corto = [...ev.target.value.trim()].length < 10 && ev.target.value.trim() !== '';
      const e = $('#err-motivo'); e.hidden = !corto; e.textContent = D.mensajes['M-06'];
      ev.target.setAttribute('aria-invalid', String(corto)); $('#c-motivo').classList.toggle('campo-error', corto);
    }
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') { if ($('#capa-dialogo .hoja')) cerrarDialogo(); else cerrarMenu(true); }
    if (ev.key === 'Tab' && $('#capa-dialogo .hoja')) { // el foco no sale del diálogo
      const f = [...$('#capa-dialogo .hoja').querySelectorAll('button, textarea, input, a[href]')].filter((x) => !x.disabled);
      if (ev.shiftKey && document.activeElement === f[0]) { ev.preventDefault(); f[f.length - 1].focus(); }
      else if (!ev.shiftKey && document.activeElement === f[f.length - 1]) { ev.preventDefault(); f[0].focus(); }
    }
  });
  window.addEventListener('hashchange', navegar);

  // Punto de entrada para las pruebas automáticas (herramientas/recorrido.cjs). No cambia el comportamiento.
  window.__rbac = { red: RED, reiniciar: () => { api = window.crearApi(D); }, api: () => api };
  navegar();
})();
