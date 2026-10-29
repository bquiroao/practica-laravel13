// Prototipo navegable del módulo QA (semana 11). Rutas por hash; la API es ApiSimulada (mismos códigos que el contrato).
// El «cliente» reproduce la política de ClienteQa (semana 5): reintenta 3 veces sin red y, si no confirma, deja el resultado
// PENDIENTE con la MISMA clave. La evidencia con datos clínicos nunca va al almacenamiento del navegador (semana 9 B-01).
(function () {
  const api = window.ApiSimulada;
  const sesion = { usuario: null, rol: null };
  let vistaTraza = 'caso';               // semana 12: «Ver por» caso o requisito (Responsable de producto)
  const pendientes = [];                 // en memoria; los resultados sin evidencia clínica irían también a localStorage (fuera del prototipo)
  let borrador = null;                   // el formulario en curso: sobrevive a un 422 y a volver de la confirmación
  let ultimaSalida = null;
  const $ = (sel) => document.querySelector(sel);
  const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const NOMBRE = { PASA: 'Pasa', FALLA: 'Falla', BLOQUEADO: 'Bloqueado', NO_APLICA: 'No aplica', PENDIENTE: 'Pendiente' };
  const DECISION = { GO: '✓ Liberar (GO)', NO_GO: '⛔ No liberar (NO-GO)' };

  function anunciar(texto) { const v = $('#vivo'); v.textContent = ''; setTimeout(() => { v.textContent = texto; }, 50); }
  function barraRed() { $('#red').hidden = api.hayRed(); }
  function cargando(si) { $('#contenido').setAttribute('aria-busy', si ? 'true' : 'false'); }
  function pintar(html, foco) {
    $('#contenido').innerHTML = html;
    cargando(false);
    barraRed();
    nav();
    const destino = foco ? $(foco) : $('#contenido h1');
    if (destino) { if (!destino.hasAttribute('tabindex') && destino.tagName === 'H1') destino.setAttribute('tabindex', '-1'); destino.focus(); }
  }
  function nav() {
    const n = $('#nav');
    n.hidden = !sesion.rol;
    if (!sesion.rol) return;
    const ruta = location.hash;
    const items = sesion.rol === 'tester'
      ? [['#/campanas', '☰', 'Campañas'], ['#/pendientes', '⇅', `Pendientes${pendientes.length ? ' (' + pendientes.length + ')' : ''}`], ['#/trazabilidad/1', '⌘', 'Trazabilidad']]
      : sesion.rol === 'producto' ? [['#/trazabilidad/1', '⌘', 'Trazabilidad']]
        : [['#/campanas', '☰', 'Campañas'], ['#/trazabilidad/1', '⌘', 'Trazabilidad']];
    n.innerHTML = items.map(([h, i, t]) => `<a href="${h}" data-prueba="nav-${t.split(' ')[0].toLowerCase()}"${ruta.startsWith(h) ? ' aria-current="page"' : ''}><span class="ico" aria-hidden="true">${i}</span>${t}</a>`).join('');
    $('#rol').textContent = { tester: 'Tester', lider: 'Líder QA', producto: 'Responsable de producto' }[sesion.rol];
    $('#avatar').textContent = { tester: 'TT', lider: 'LQ', producto: 'RP' }[sesion.rol];
    $('#avatar').setAttribute('aria-label', sesion.usuario);
  }
  // Un diálogo modal deja el fondo INERTE: no se puede tocar ni tabular lo que está detrás (semana 11: axe vio los radios
  // del formulario «parcialmente tapados» pero activos detrás de la hoja; aria-modal solo no lo impide).
  function capa(html) {
    $('#capa').innerHTML = html;
    ['header.barra', '#contenido', '#nav', '#red'].forEach((sel) => { $(sel).inert = html !== ''; });
  }
  function hoja(id, titulo, cuerpo, acciones) {
    return `<div class="velo"></div><section class="hoja" role="dialog" aria-modal="true" aria-labelledby="${id}-t" id="${id}">
      <div class="asa"></div><h2 id="${id}-t">${titulo}</h2>${cuerpo}<div class="acciones">${acciones}</div></section>`;
  }

  // ---------- cliente con reintento y bandeja (ClienteQa) ----------
  async function enviarResultado(p) {
    for (let intento = 1; intento <= 3; intento++) {
      try { return await api.registrar(1, p.clave, p.datos); } catch (e) { /* sin red: reintenta con la misma clave */ }
    }
    return null;
  }

  // ---------- vistas ----------
  const vistas = {
    ingresar() {
      pintar(`<h1>Ingresar (demostración)</h1>
        <p class="sub">Elija con qué usuario entrar. En el HIS, la identidad la da el inicio de sesión.</p>
        <div class="tarjeta"><a class="btn primario bloque" href="#/campanas" data-prueba="entrar-tester" data-usuario="tito-tester" data-rol="tester">Tito · Tester</a></div>
        <div class="tarjeta"><a class="btn bloque" href="#/campanas" data-prueba="entrar-lider" data-usuario="lider-qa" data-rol="lider">Líder QA</a></div>
        <div class="tarjeta"><a class="btn bloque" href="#/campanas" data-prueba="entrar-producto" data-usuario="producto" data-rol="producto">Responsable de producto</a></div>`);
    },
    async campanas() {
      cargando(true);
      const r = await api.campanas().catch(() => null);
      if (!r) return pintar(`<h1>Campañas</h1><div class="aviso atencion"><h2>Sin conexión</h2><p>No se pudo cargar la lista.</p></div>${demo()}`);
      pintar(`<h1>${sesion.rol === 'tester' ? 'Mis campañas en curso' : 'Campañas'}</h1>
        ${r.cuerpo.map((c) => `<article class="tarjeta campana">
          <div class="fila"><span class="nombre">${esc(c.nombre)}</span>${c.estado === 'CERRADA' ? `<span class="chip ${c.decision.valor === 'GO' ? 'go' : 'nogo'} derecha">${DECISION[c.decision.valor]}</span>` : '<span class="chip en-curso derecha">● En curso</span>'}</div>
          <div class="meta">Build ${esc(c.build)} · ${c.conResultado} de ${c.total} con resultado</div>
          <div class="progreso" role="progressbar" aria-label="Avance de ${esc(c.nombre)}" aria-valuenow="${c.conResultado}" aria-valuemin="0" aria-valuemax="${c.total}"><span style="width:${100 * c.conResultado / c.total}%"></span></div>
          ${sesion.rol === 'tester' && c.estado === 'EN_CURSO' ? `<a class="btn primario bloque" data-prueba="ejecutar" href="#/ejecutar/1/${c.conResultado === 0 ? 'TC-107' : 'TC-108'}">Ejecutar el siguiente caso</a>` : ''}
          <a class="btn bloque" style="margin-top:8px" data-prueba="ver-trazabilidad" href="#/trazabilidad/1">Ver trazabilidad</a>
        </article>`).join('')}${demo()}`);
    },
    async ejecutar(id, caso) {
      cargando(true);
      const r = await api.caso(id, caso).catch(() => null);
      if (!r) return pintar(`<h1>${esc(caso)}</h1><div class="aviso atencion"><h2>Sin conexión</h2><p>No se pudo abrir el caso. [Reintentar] cuando vuelva la señal.</p></div>`);
      const c = r.cuerpo;
      if (!borrador || borrador.caso !== caso) borrador = { caso, resultado: 'PASA', evidencias: [], clasificacion: 'GENERAL', titulo: '', pasos: '', severidad: 'MAYOR', verifica: c.corregido, error: null };
      const b = borrador;
      const exige = b.resultado === 'FALLA' || b.resultado === 'BLOQUEADO';
      pintar(`<div class="caso-cabecera"><span class="id">${esc(caso)} · ${esc(c.titulo)}</span><span class="meta">Versión ${c.version} · build ${esc(c.build)} · ${c.requisitos.join(', ')}${c.critico ? ' · <strong>crítico</strong>' : ''}</span></div>
        <h1 class="oculto-visual">Ejecutar ${esc(caso)}</h1>
        <h2>Pasos</h2><ol class="pasos-caso">${c.pasos.map(([a, e], i) => `<li><div><strong>${i + 1}.</strong> ${esc(a)}</div><p class="esperado">Esperado: ${esc(e)}</p></li>`).join('')}</ol>
        <fieldset class="opciones"><legend>Resultado</legend><div class="grupo">
          ${['PASA', 'FALLA', 'BLOQUEADO', 'NO_APLICA'].map((v) => `<label class="opcion"><input type="radio" name="resultado" value="${v}" data-prueba="resultado-${v}"${b.resultado === v ? ' checked' : ''}> ${NOMBRE[v]}</label>`).join('')}
        </div></fieldset>
        ${exige ? `<div id="bloque-evidencia">
          <p class="etiqueta" id="et-evidencia">Evidencia (obligatoria si falla o está bloqueado)</p>
          ${b.evidencias.map((e) => `<div class="adjunto"><span aria-hidden="true">🖼</span><span class="nombre">${esc(e.nombre)}</span><span class="meta">182 KB</span></div>`).join('')}
          <button type="button" class="btn bloque" id="tomar-foto" data-prueba="tomar-foto" aria-describedby="${b.error === 'EVIDENCIA_REQUERIDA' ? 'error-evidencia ' : ''}ayuda-evidencia"${b.error === 'EVIDENCIA_REQUERIDA' ? ' aria-invalid="true"' : ''}>📷 Tomar foto o adjuntar</button>
          ${b.error === 'EVIDENCIA_REQUERIDA' ? '<p class="error" id="error-evidencia">Falta la evidencia: una falla necesita al menos una captura, un log o un JUnit.</p>' : ''}
          <p class="ayuda" id="ayuda-evidencia">PNG, JPEG, texto o JUnit · máximo 5 MB.</p>
          <fieldset class="opciones"><legend>¿La evidencia muestra datos de pacientes?</legend><div class="grupo dos">
            <label class="opcion"><input type="radio" name="clasif" value="GENERAL" data-prueba="clasif-GENERAL"${b.clasificacion === 'GENERAL' ? ' checked' : ''}> No</label>
            <label class="opcion"><input type="radio" name="clasif" value="DATOS_CLINICOS" data-prueba="clasif-DATOS_CLINICOS"${b.clasificacion === 'DATOS_CLINICOS' ? ' checked' : ''}> Sí, datos clínicos</label>
          </div></fieldset>
          ${b.clasificacion === 'DATOS_CLINICOS' ? '<p class="clinica">Solo quien tenga el permiso clínico verá el contenido. Sin conexión, esta evidencia <strong>no</strong> se guarda en el equipo: no cierre esta pestaña hasta confirmar.</p>' : ''}
        </div>` : ''}
        ${b.resultado === 'FALLA' ? `<h2 style="margin-top:16px">Defecto</h2>
          <label for="titulo">Título</label><input id="titulo" data-prueba="defecto-titulo" value="${esc(b.titulo)}">
          <label for="pasos-def">Pasos para reproducirlo</label><textarea id="pasos-def">${esc(b.pasos)}</textarea>
          <label for="severidad">Severidad propuesta</label><select id="severidad">${['CRITICA', 'MAYOR', 'MENOR', 'TRIVIAL'].map((s) => `<option value="${s}"${b.severidad === s ? ' selected' : ''}>${s[0] + s.slice(1).toLowerCase().replace('critica', 'rítica')}</option>`).join('')}</select>` : ''}
        ${b.resultado === 'PASA' && c.corregido ? `<label class="casilla"><input type="checkbox" id="verifica" checked> Esta ejecución verifica el defecto #${c.corregido}</label>` : ''}
        <div class="accion-fija"><button type="button" class="btn primario" data-prueba="registrar">Registrar…</button></div>`,
      b.error === 'EVIDENCIA_REQUERIDA' ? '#tomar-foto' : null);
      if (b.error === 'EVIDENCIA_REQUERIDA') $('#tomar-foto').scrollIntoView({ block: 'center' });
    },
    confirmar() {
      const b = borrador;
      const resumen = `<strong>${esc(b.caso)} · ${NOMBRE[b.resultado]}</strong>${b.evidencias.length ? ` · ${b.evidencias.length} evidencia${b.clasificacion === 'DATOS_CLINICOS' ? ' con datos clínicos' : ''}` : ''}${b.resultado === 'FALLA' && b.titulo ? ` · defecto «${esc(b.titulo)}» (${b.severidad.toLowerCase()})` : ''}`;
      capa(hoja('confirmar', '¿Registrar este resultado?', `<p>${resumen}.</p><p class="meta">Un resultado no se puede editar después: si se equivoca, registre otro intento.</p>`,
        '<button type="button" class="btn primario" data-prueba="confirmar-registro">Registrar</button><button type="button" class="btn" data-prueba="volver" id="volver">Volver</button>'));
      $('#volver').focus();
    },
    registro() {
      const s = ultimaSalida;
      let html = '<h1>Resultado del registro</h1>';
      if (s.tipo === 'exito') html += `<section class="aviso ok" role="status"><h2>✓ Registrado</h2><p>Intento #${s.cuerpo.numero} de ${esc(s.cuerpo.caso)} (${NOMBRE[s.cuerpo.resultado]})${s.cuerpo.defecto ? ` · defecto #${s.cuerpo.defecto} creado` : ''}.</p>
        <div class="acciones">${s.cuerpo.caso === 'TC-107' ? '<a class="btn primario" data-prueba="siguiente" href="#/ejecutar/1/TC-108">Siguiente caso: TC-108 →</a>' : '<a class="btn primario" data-prueba="a-campanas" href="#/campanas">Volver a las campañas</a>'}</div></section>`;
      if (s.tipo === 'pendiente') html += `<section class="aviso atencion" role="status"><h2>⏳ Pendiente de confirmar</h2><p>No se pudo confirmar con el servidor. Se reenviará con la misma clave: <strong>no lo registre de nuevo</strong>.</p></section>
        ${s.clinica ? '<section class="aviso error" role="alert"><h2>No cierre esta pestaña</h2><p>Lleva evidencia con datos clínicos: por protección <strong>no se guarda en el equipo</strong>. Si cierra antes de confirmar, tendrá que registrarlo otra vez.</p></section>' : ''}
        <a class="btn bloque" data-prueba="ir-pendientes" href="#/pendientes">Ver pendientes</a>`;
      if (s.tipo === 'rechazado') html += `<section class="aviso error" role="alert"><h2>✕ No se registró</h2><p>${esc(s.mensaje)}</p></section>`;
      pintar(html);
    },
    pendientes() {
      pintar(`<h1>Pendientes en este equipo</h1>
        ${pendientes.length ? `<ul class="bandeja">${pendientes.map((p) => `<li><strong>${esc(p.datos.caso)} · ${NOMBRE[p.datos.resultado]}</strong><span class="chip ${p.clinica ? 'bloquea' : 'nuevo'}">${p.clinica ? '🔒 Solo en esta pestaña' : '⏳ Sin confirmar'}</span><span class="cuando">${p.clinica ? 'Datos clínicos: en memoria, no en el equipo' : '3 intentos sin respuesta'}</span></li>`).join('')}</ul>
          <button type="button" class="btn primario bloque" data-prueba="reenviar">Reenviar ahora</button>` : '<p class="meta">No hay resultados sin confirmar.</p><a class="btn bloque" data-prueba="a-campanas" href="#/campanas">Volver a las campañas</a>'}
        ${demo()}`);
    },
    async trazabilidad(id) {
      cargando(true);
      const r = await api.trazabilidad(id).catch(() => null);
      if (!r) return pintar('<h1>Trazabilidad</h1><div class="aviso atencion"><h2>Sin conexión</h2><p>No se pudo cargar la trazabilidad.</p></div>');
      const t = r.cuerpo;
      const p = t.prevision;
      const prevision = t.decision ? `<section class="aviso ${t.decision.valor === 'GO' ? 'ok' : 'error'}" role="status"><h2>Cerrada: ${DECISION[t.decision.valor]}</h2><p>${t.decision.causas.length ? 'Causas: ' + esc(t.decision.causas.join('; ')) + '.' : `Tasa de aprobados ${(100 * t.decision.tasa).toFixed(0)} % y ningún defecto que bloquee.`}</p></section>`
        : p.pendientes.length ? `<section class="aviso atencion"><h2>Faltan resultados</h2><p>Sin resultado: ${p.pendientes.join(', ')}.</p></section>`
          : p.causas.length ? `<section class="aviso error"><h2>⛔ Hoy daría No liberar (NO-GO) · ${p.causas.length} ${p.causas.length === 1 ? 'causa' : 'causas'}</h2><ul>${p.causas.map((x) => `<li>${esc(x[0].toUpperCase() + x.slice(1))}</li>`).join('')}</ul></section>`
            : `<section class="aviso ok"><h2>✓ Hoy daría Liberar (GO)</h2><p>Tasa de aprobados ${(100 * p.tasa).toFixed(0)} % y ningún defecto que bloquee.</p></section>`;
      pintar(`<h1>${esc(t.nombre)}</h1><p class="sub">Build ${esc(t.build)} · ${t.estado === 'CERRADA' ? 'cerrada' : 'en curso'}</p>${sesion.rol === 'producto' ? '<p class="meta" data-prueba="solo-lectura">👁 Solo lectura · Responsable de producto</p>' : ''}${prevision}
        <fieldset class="opciones"><legend>Ver por</legend><div class="grupo dos">
          <label class="opcion"><input type="radio" name="vista" value="caso" data-prueba="vista-caso"${vistaTraza === 'caso' ? ' checked' : ''}> Caso</label>
          <label class="opcion"><input type="radio" name="vista" value="requisito" data-prueba="vista-requisito"${vistaTraza === 'requisito' ? ' checked' : ''}> Requisito</label>
        </div></fieldset>
        ${vistaTraza === 'requisito' ? porRequisito(t) : `<h2>Casos</h2>${t.casos.map((c) => `<article class="tarjeta caso"><div class="fila"><span class="titulo">${c.caso}${c.critico ? ' ★' : ''}</span><span class="chip ${c.ultimo === 'PASA' ? 'go' : c.ultimo === 'PENDIENTE' ? 'no-bloquea' : 'nogo'} derecha">${c.ultimo === 'PASA' ? '✓' : c.ultimo === 'PENDIENTE' ? '◷' : '✕'} ${NOMBRE[c.ultimo]}${c.numero > 1 ? ' (#' + c.numero + ')' : ''}</span></div>
          <div class="meta">${c.requisitos.join(', ')} · ${c.evidencias.length ? c.evidencias.length + ' evidencia' + (c.evidencias.some((e) => e.clasificacion === 'DATOS_CLINICOS') ? ' 🔒' : '') : 'sin evidencia'}</div>
          ${c.defectos.map((d) => `<div class="defecto"><div class="fila"><strong>#${d.id} · ${d.severidad[0] + d.severidad.slice(1).toLowerCase()} · ${d.estado[0] + d.estado.slice(1).toLowerCase()}</strong><span class="chip ${d.bloquea_go ? 'bloquea' : 'no-bloquea'} derecha">${d.bloquea_go ? '⛔ Bloquea' : 'No bloquea'}</span></div>
            <p class="motivo">${d.bloquea_go ? `Bloquea porque ${c.caso} es crítico para esta campaña y el defecto no está cerrado.` : 'No bloquea el GO.'}</p></div>`).join('')}</article>`).join('')}
        `}<p class="meta">★ crítico en la matriz para las funcionalidades de esta campaña.</p>
        ${sesion.rol === 'lider' && t.estado === 'EN_CURSO' ? '<div class="accion-fija"><button type="button" class="btn primario" data-prueba="cerrar">Cerrar campaña…</button></div>' : ''}`);
      vistas.ultimaTraza = t;
    },
  };

  // Semana 12 (cambio solicitado): trazabilidad por requisito, para el Responsable de producto.
  function porRequisito(t) {
    const fila = (rf) => {
      const casos = t.casos.filter((c) => c.requisitos.includes(rf));
      const fallan = casos.filter((c) => c.ultimo === 'FALLA' || c.ultimo === 'BLOQUEADO').length;
      const pendientes = casos.filter((c) => c.ultimo === 'PENDIENTE').length;
      const bloquean = casos.reduce((n, c) => n + c.defectos.filter((d) => d.bloquea_go).length, 0);
      return { rf, casos, fallan, pendientes, bloquean };
    };
    const filas = Object.keys(t.requisitos).map(fila).sort((a, b) => (b.bloquean - a.bloquean) || (b.fallan - a.fallan) || a.rf.localeCompare(b.rf));
    return `<h2>Requisitos</h2>${filas.map((f) => `<article class="tarjeta requisito">
      <div class="fila"><span class="titulo">${f.rf}</span><span class="chip ${f.bloquean ? 'bloquea' : f.fallan ? 'nogo' : f.pendientes ? 'no-bloquea' : 'go'} derecha">${f.bloquean ? '⛔ ' + f.bloquean + ' defecto bloquea' : f.fallan ? '✕ ' + f.fallan + ' falla' : f.pendientes ? '◷ Pendiente' : '✓ Cubierto'}</span></div>
      <div class="meta">${f.casos.length} ${f.casos.length === 1 ? 'caso' : 'casos'} · ${f.fallan} ${f.fallan === 1 ? 'falla' : 'fallan'} · ${f.pendientes} pendiente${f.pendientes === 1 ? '' : 's'}</div>
      <ul class="items-caso">${f.casos.map((c) => `<li>${c.caso}${c.critico ? ' ★' : ''} · ${NOMBRE[c.ultimo]}${c.defectos.some((d) => d.bloquea_go) ? ' · ⛔ bloquea' : ''}</li>`).join('')}</ul>
    </article>`).join('')}`;
  }

  function demo() {
    return `<details class="mas"><summary>Demostración: estado de la red</summary>
      <div class="acciones" style="display:flex;gap:8px;flex-wrap:wrap">
        <button type="button" class="btn" data-prueba="cortar-red">Cortar la red</button>
        <button type="button" class="btn" data-prueba="restablecer-red">Restablecer la red</button></div></details>`;
  }

  // ---------- acciones ----------
  document.addEventListener('click', async (ev) => {
    const el = ev.target.closest('[data-prueba]');
    if (!el) return;
    const accion = el.dataset.prueba;
    if (el.dataset.rol) { sesion.usuario = el.dataset.usuario; sesion.rol = el.dataset.rol; vistaTraza = 'caso'; return; }
    if (accion === 'cortar-red') { api.cortarRed(); barraRed(); anunciar('Red cortada (demostración).'); }
    if (accion === 'restablecer-red') { api.restablecerRed(); barraRed(); anunciar('Red restablecida.'); }
    if (accion === 'tomar-foto') { guardarBorrador(); borrador.evidencias.push({ tipo: 'captura', nombre: 'nota-editable.png', huella: '3fa9c2e1' }); borrador.error = null; vistas.ejecutar(1, borrador.caso); }
    if (accion === 'registrar') {
      guardarBorrador();
      const b = borrador;
      if ((b.resultado === 'FALLA' || b.resultado === 'BLOQUEADO') && !b.evidencias.length) { b.error = 'EVIDENCIA_REQUERIDA'; anunciar('Falta la evidencia.'); return vistas.ejecutar(1, b.caso); }
      vistas.confirmar();
    }
    if (accion === 'volver') { capa(''); $('[data-prueba="registrar"]').focus(); }
    if (accion === 'confirmar-registro') {
      capa('');
      const b = borrador;
      const datos = { caso: b.caso, resultado: b.resultado,
        evidencias: b.evidencias.map((e) => ({ tipo: e.tipo, clasificacion: b.clasificacion, huella: e.huella })),
        defecto: b.resultado === 'FALLA' ? { titulo: b.titulo, pasos: b.pasos.split('\n'), severidad: b.severidad } : undefined,
        verifica_defecto: b.verifica || undefined };
      const p = { clave: 'res-' + Math.random().toString(16).slice(2, 14), datos, clinica: b.clasificacion === 'DATOS_CLINICOS' && b.evidencias.length > 0 };
      cargando(true);
      const r = await enviarResultado(p);
      borrador = null;
      if (!r) { pendientes.push(p); ultimaSalida = { tipo: 'pendiente', clinica: p.clinica }; }
      else if (r.estado < 300) ultimaSalida = { tipo: 'exito', cuerpo: r.cuerpo };
      else ultimaSalida = { tipo: 'rechazado', mensaje: r.cuerpo.error === 'CAMPANA_NO_ADMITE_RESULTADOS' ? 'La campaña ya no admite resultados.' : 'Revise los datos: ' + r.cuerpo.error };
      location.hash = '#/registro';
    }
    if (accion === 'reenviar') {
      cargando(true);
      const anuncios = [];
      for (const p of [...pendientes]) {
        const r = await enviarResultado(p);
        if (r) { pendientes.splice(pendientes.indexOf(p), 1); anuncios.push(r.estado < 300 ? `${p.datos.caso} registrado` : `${p.datos.caso} rechazado: ${r.cuerpo.error}`); }
      }
      vistas.pendientes();
      anunciar(anuncios.length ? anuncios.join('. ') + `. Quedan ${pendientes.length} pendientes.` : 'Sigue sin conexión: los resultados esperan.');
    }
    if (accion === 'cerrar') {
      const t = vistas.ultimaTraza;
      const p = t.prevision;
      const consecuencia = p.pendientes.length ? `Faltan resultados de ${p.pendientes.join(', ')}: el servidor no la cerrará.` : p.causas.length ? `La decisión será <strong>No liberar (NO-GO)</strong>: ${esc(p.causas.join('; '))}.` : 'La decisión será <strong>Liberar (GO)</strong>.';
      capa(hoja('cierre', `¿Cerrar «${esc(t.nombre)}»?`, `<p>Cerrar no se puede deshacer. ${consecuencia}</p>`,
        `<button type="button" class="btn ${p.causas.length ? 'peligro' : 'primario'}" data-prueba="confirmar-cierre">${p.causas.length ? 'Cerrar con No liberar' : 'Cerrar y liberar'}</button><button type="button" class="btn" data-prueba="volver-cierre" id="volver">Volver</button>`));
      $('#volver').focus();
    }
    if (accion === 'volver-cierre') { capa(''); $('[data-prueba="cerrar"]').focus(); }
    if (accion === 'confirmar-cierre') { capa(''); cargando(true); const r = await api.cerrar(1); anunciar(r.estado === 200 ? 'Campaña cerrada.' : 'No se pudo cerrar: ' + r.cuerpo.error); vistas.trazabilidad(1); }
  });
  document.addEventListener('change', (ev) => {
    if (ev.target.name === 'vista') { vistaTraza = ev.target.value; vistas.trazabilidad(1).then(() => $(`[name="vista"][value="${vistaTraza}"]`).focus()); return; }
    if (ev.target.name === 'resultado' || ev.target.name === 'clasif') { guardarBorrador(); vistas.ejecutar(1, borrador.caso); $(`[name="${ev.target.name}"][value="${ev.target.value}"]`).focus(); }
  });
  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && $('#capa .hoja')) { ($('[data-prueba="volver"]') || $('[data-prueba="volver-cierre"]')).click(); }
  });
  function guardarBorrador() {
    if (!borrador) return;
    const v = (sel) => $(sel) ? $(sel).value : null;
    borrador.resultado = ($('[name="resultado"]:checked') || {}).value || borrador.resultado;
    borrador.clasificacion = ($('[name="clasif"]:checked') || {}).value || borrador.clasificacion;
    if (v('#titulo') !== null) borrador.titulo = v('#titulo');
    if (v('#pasos-def') !== null) borrador.pasos = v('#pasos-def');
    if (v('#severidad') !== null) borrador.severidad = v('#severidad');
  }

  // ---------- rutas ----------
  function ruta() {
    capa('');
    const h = location.hash || '#/ingresar';
    if (!sesion.rol && h !== '#/ingresar') { location.hash = '#/ingresar'; return; }
    let m;
    if (h === '#/ingresar') vistas.ingresar();
    else if (h === '#/campanas') vistas.campanas();
    else if ((m = h.match(/^#\/ejecutar\/(\d+)\/(TC-\d+)$/))) vistas.ejecutar(+m[1], m[2]);
    else if (h === '#/registro' && ultimaSalida) vistas.registro();
    else if (h === '#/pendientes') vistas.pendientes();
    else if ((m = h.match(/^#\/trazabilidad\/(\d+)$/))) vistas.trazabilidad(+m[1]);
    else location.hash = '#/campanas';
  }
  window.addEventListener('hashchange', ruta);
  ruta();
})();
