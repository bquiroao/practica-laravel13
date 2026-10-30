// Prototipo navegable de Prescripciones (semana 11): una sola aplicación para escritorio y móvil.
// La interfaz NO decide reglas clínicas: muestra lo que responde la API (simulada, con paridad probada contra el Micro-HIS)
// y traduce sus códigos a los mensajes del catálogo de la semana 8 (M-xx). Los permisos locales solo muestran u ocultan.
(function () {
  'use strict';
  const D = window.DATOS;
  const api = window.crearApi(D);
  const BASE = '/api/v1/prescripciones';
  const RED = { latenciaMs: 300, sinConexion: false, perderRespuesta: false, sesionVencida: false };
  window.__demo = { red: RED, api }; // para el recorrido automático (herramientas/recorrido.cjs)

  // Todo vive en memoria: ningún dato clínico en la URL ni en el almacenamiento del navegador (semana 8, R-D1).
  const est = { sesion: null, borrador: null, verif: null, emision: null, detalle: null, anulacion: null, quitado: null, alergiaNueva: null };
  const itemVacio = () => ({ medicamento: '', dosis: '', frecuencia: '', via: '', dias: '' });

  // ---------- utilidades ----------
  const $ = (s, r = document) => r.querySelector(s);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const persona = () => D.personas[est.sesion.actor];
  const puede = (permiso) => (D.permisos[`${est.sesion.actor}|${est.sesion.hospital}`] || []).includes(permiso);
  const anunciar = (txt, urgente = false) => { const n = $(urgente ? '#alerta' : '#vivo'); n.textContent = ''; setTimeout(() => { n.textContent = txt; }, 30); };
  const med = (codigo) => { const m = D.medicamentos.find((x) => x[0] === codigo); return m && { codigo: m[0], nombre: m[1], pa: m[3], unidad: m[5], max: m[6], vias: m[7], activo: m[8] }; };
  const nombreMed = (codigo) => (med(codigo) || { nombre: codigo }).nombre;
  const sev = (s) => D.severidades[s] || s;
  const hora = (iso) => { const d = new Date(Date.parse(iso) - 6 * 3600000); return `${d.toISOString().slice(8, 10)}/${d.toISOString().slice(5, 7)} a las ${d.toISOString().slice(11, 16)}`; };
  const nombrePaciente = (p) => `${D.nombresPacientes[p] || ''} · ${p}`;
  const nombreUsuario = (u) => (D.personas[u] ? D.personas[u].nombre : u);
  const uuid = () => (window.crypto && crypto.randomUUID ? crypto.randomUUID() : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => { const r = Math.random() * 16 | 0; return (c === 'x' ? r : (r & 3 | 8)).toString(16); }));
  const pintar = (html) => { $('#contenido').innerHTML = html; };
  /** El resultado recibe el foco: el lector de pantalla lo lee de inmediato (W4-1). */
  const enfocarVeredicto = () => { const v = $('#veredicto'); if (v) { v.tabIndex = -1; v.focus(); } };
  const accionFija = (html) => { const a = $('#barra-accion'); a.hidden = !html; a.innerHTML = html || ''; medirFijos(); };
  /** El espacio que se reserva abajo es el alto REAL de lo fijo (ver estilos.css, «Teléfono»): nada del contenido queda tapado. */
  function medirFijos() {
    const a = $('#barra-accion'), t = $('#toast .toast');
    document.documentElement.style.setProperty('--accion', (a.hidden ? 0 : a.offsetHeight) + 'px');
    document.documentElement.style.setProperty('--toast', (t ? t.offsetHeight + 8 : 0) + 'px');
  }
  window.addEventListener('resize', medirFijos);

  /** Petición «por la red». Un 401 abre la renovación de sesión SOBRE la página y repite la MISMA petición (B-02, B-10). */
  async function llamar(metodo, ruta, cuerpo = null, clave = null) {
    const sol = () => ({
      cabeceras: { authorization: 'Bearer ' + persona().token, 'x-tenant-id': est.sesion.hospital, ...(clave ? { 'idempotency-key': clave } : {}) },
      cuerpo: cuerpo === null ? '' : cuerpo,
    });
    const lenta = setTimeout(() => pintarRed('lenta'), 2000);
    try {
      let r = await api.solicitarRed(metodo, BASE + ruta, sol(), RED);
      if (r.estado === 401) {
        clearTimeout(lenta); pintarRed(); // mientras la persona decide, la red no está «lenta»
        if (!(await renovarSesion())) return { estado: 401, cuerpo: r.cuerpo };
        r = await api.solicitarRed(metodo, BASE + ruta, sol(), RED);
      }
      pintarRed();
      return r;
    } catch (e) {
      pintarRed('sin-conexion');
      return { estado: 0, red: true, cuerpo: {} };
    } finally { clearTimeout(lenta); }
  }

  // ---------- marco ----------
  function pintarRed(estado) {
    const n = $('#red');
    if (RED.sinConexion || estado === 'sin-conexion') {
      n.innerHTML = '<div class="red" role="status"><span aria-hidden="true">⦸</span> Sin conexión. Lo que escribió sigue aquí; verificar, emitir y anular necesitan conexión.</div>';
    } else if (estado === 'lenta') {
      n.innerHTML = '<div class="red lenta" role="status"><span aria-hidden="true">◔</span> La conexión está lenta. Seguimos esperando la respuesta.</div>';
    } else n.innerHTML = '';
  }
  /** P-01: las alergias activas se ven SIEMPRE al prescribir y verificar. Es lo que el Expediente dijo al abrir el paciente. */
  function pintarFranja(visible) {
    const n = $('#franja');
    const b = est.borrador;
    if (!visible || !b || !b.paciente) { n.innerHTML = ''; return; }
    const activas = b.alergias.filter((a) => a[4] === 'active');
    if (b.alergias.length === 0) {
      n.innerHTML = `<div class="alergias sin-registros" role="region" aria-label="Alergias"><span aria-hidden="true">?</span><div>Sin alergias registradas <span class="detalle">· no significa que no tenga</span></div></div>`;
    } else if (activas.length === 0) {
      n.innerHTML = `<div class="alergias sin-activas" role="region" aria-label="Alergias"><span aria-hidden="true">ⓘ</span><div>Sin alergias activas <span class="detalle">· ${b.alergias.length} registrada${b.alergias.length > 1 ? 's' : ''} como resuelta${b.alergias.length > 1 ? 's' : ''}</span></div></div>`;
    } else {
      n.innerHTML = `<div class="alergias" role="region" aria-label="Alergias activas"><span aria-hidden="true">⚠</span><div>Alergias activas: ${activas.map((a) =>
        `${esc((a[1] || a[0]).trim())} <span class="detalle">· ${sev(a[3])}${a[5] ? ' · ' + esc(a[5]) : ''}</span>`).join('; ')}</div></div>`;
    }
  }
  function pintarMarco(def) {
    const barra = $('#barra'), nav = $('#nav');
    if (!est.sesion) { barra.hidden = true; nav.hidden = true; document.body.classList.add('sin-nav'); return; }
    const p = persona();
    barra.hidden = false;
    barra.innerHTML = `${def.volver ? `<a class="atras" href="${def.volver}" aria-label="${def.volverTexto || 'Volver'}">‹</a>` : ''}
      <span class="hospital" aria-label="Hospital activo: ${esc(D.hospitales[est.sesion.hospital])}"><span class="corto">${esc(D.cortos[est.sesion.hospital])}</span><span class="largo">${esc(D.hospitales[est.sesion.hospital])}</span></span>
      <span class="rol-actual">${esc(p.rol)}</span>
      <span class="avatar" role="img" aria-label="${esc(p.nombre)}, ${esc(p.rol.toLowerCase())}">${esc(p.iniciales)}</span>`;
    const conNav = !def.tarea;
    document.body.classList.toggle('sin-nav', !conNav);
    nav.hidden = !conNav;
    const secciones = [...(puede('prescripcion.emitir') ? [['#/prescribir', '℞', 'Prescribir']] : []), ['#/consultar', '🔎', 'Consultar'], ['#/mas', '☰', 'Más']];
    nav.innerHTML = secciones.map(([href, ico, texto]) =>
      `<a href="${href}" ${def.seccion === texto ? 'aria-current="page"' : ''}><span class="ico" aria-hidden="true">${ico}</span>${texto}</a>`).join('');
  }

  // ---------- hojas y diálogos: el resto queda inerte; Esc cierra; el foco vuelve a quien la abrió ----------
  let hoja = null;
  function abrirHoja(html, foco, alCerrar = null) {
    const previo = hoja ? hoja.previo : document.activeElement;
    $('#app').inert = true;
    $('#capa-dialogo').innerHTML = `<div class="velo"></div><div class="hoja" role="dialog" aria-modal="true" aria-labelledby="h-titulo"><div class="asa" aria-hidden="true"></div>${html}</div>`;
    hoja = { previo, alCerrar };
    const f = $(`#capa-dialogo ${foco}`);
    if (f) f.focus();
  }
  function cerrarHoja(valor = 'cancelar') {
    if (!hoja) return;
    const { previo, alCerrar } = hoja;
    hoja = null;
    $('#capa-dialogo').innerHTML = ''; $('#app').inert = false;
    if (previo && previo.isConnected) previo.focus();
    if (alCerrar) alCerrar(valor);
  }
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && hoja) cerrarHoja('cancelar'); });

  function renovarSesion() {
    return new Promise((resolver) => {
      abrirHoja(`<h2 id="h-titulo">Su sesión venció</h2>
        <p>Renueve la sesión para continuar. El borrador, la justificación y el motivo se conservan: no hace falta escribirlos otra vez. (M-18)</p>
        <div class="acciones"><button type="button" class="btn primario" data-accion="cerrar-hoja" data-valor="renovar">Renovar sesión</button>
        <button type="button" class="btn" data-accion="cerrar-hoja" data-valor="salir">Salir</button></div>`, '[data-valor="renovar"]', (v) => {
        if (v !== 'renovar') { salir(); resolver(false); return; }
        RED.sesionVencida = false; // en el HIS: el módulo de autenticación renueva el token sin navegar
        anunciar('Sesión renovada. Continuamos donde estaba.');
        resolver(true);
      });
    });
  }
  function salir() { Object.assign(est, { sesion: null, borrador: null, verif: null, emision: null, detalle: null, anulacion: null }); ir('#/ingresar'); }

  // ---------- rutas: la URL solo lleva la pantalla y, en el detalle, el número ----------
  const RUTAS = [
    { patron: /^\/ingresar$/, vista: vIngresar, titulo: 'Ingresar', publica: true, tarea: true },
    { patron: /^\/prescribir$/, vista: vPrescribir, titulo: 'Nueva prescripción', seccion: 'Prescribir', permiso: 'prescripcion.emitir', franja: true },
    { patron: /^\/verificacion$/, vista: vVerificacion, titulo: 'Verificación de alergias', seccion: 'Prescribir', permiso: 'prescripcion.emitir', franja: true, volver: '#/prescribir', volverTexto: 'Volver a los ítems' },
    { patron: /^\/emision$/, vista: vEmision, titulo: 'Emisión', tarea: true, permiso: 'prescripcion.emitir' },
    { patron: /^\/consultar$/, vista: vConsultar, titulo: 'Consultar prescripción', seccion: 'Consultar' },
    { patron: /^\/prescripcion\/([1-9]\d{0,17})$/, vista: vPrescripcion, titulo: 'Prescripción', tarea: true, volver: '#/consultar', volverTexto: 'Volver a consultar' },
    { patron: /^\/mas$/, vista: vMas, titulo: 'Más', seccion: 'Más' },
  ];
  let pintada = null;
  async function navegar() {
    pintada = location.hash;
    if (hoja) cerrarHoja('cancelar');
    const ruta = location.hash.slice(1) || '/prescribir';
    if (!est.sesion && ruta !== '/ingresar') { location.replace('#/ingresar'); return; }
    let def = null, params = [];
    for (const r of RUTAS) { const m = ruta.match(r.patron); if (m) { def = r; params = m.slice(1); break; } }
    if (!def) { location.replace(puede('prescripcion.emitir') ? '#/prescribir' : '#/consultar'); return; }
    if (def.permiso && !puede(def.permiso)) def = { titulo: 'Acceso denegado', vista: vDenegado, seccion: def.seccion };
    pintarMarco(def);
    pintarFranja(!!def.franja);
    accionFija('');
    document.title = `${def.titulo} · HIS`;
    await def.vista(...params);
    if (pintada !== location.hash) return; // otra navegación ya ganó
    const destino = $('#veredicto') || $('#contenido h1');
    if (destino) { destino.tabIndex = -1; destino.focus(); }
  }
  const alCambiar = () => { if (location.hash !== pintada) navegar(); };
  window.addEventListener('hashchange', alCambiar);
  window.addEventListener('popstate', alCambiar);
  /** reemplazar: la pantalla intermedia (p. ej. «Verificando…» que terminó en un 422) no queda en el historial. */
  async function ir(hash, reemplazar = false) {
    if (location.hash !== hash) history[reemplazar ? 'replaceState' : 'pushState'](null, '', hash);
    await navegar();
  }

  // ---------- vistas ----------
  function vIngresar() {
    pintar(`<h1>Ingresar al prototipo</h1><p class="sub">Elija una persona de demostración. Los datos son ficticios.</p>
      ${Object.entries(D.personas).map(([id, p]) => `<button type="button" class="tarjeta btn bloque persona" data-accion="ingresar" data-id="${id}">
        <span class="avatar" aria-hidden="true">${p.iniciales}</span><span><strong>${esc(p.nombre)}</strong><br><span class="meta">${esc(p.rol)} · ${esc(p.detalle)}</span></span></button>`).join('')}`);
  }
  function vDenegado() {
    pintar(`<h1>Acceso denegado</h1><div class="aviso error"><p>No tiene permiso para esta acción en este hospital. (M-16)</p></div>`);
  }

  // ----- W1: preparar -----
  function vPrescribir() {
    if (est.emision && est.emision.estado === 'incierto') {
      // Medido en la semana 10: editar y reenviar duplica o choca (409). El borrador queda bloqueado hasta resolverlo.
      pintar(`<h1>Nueva prescripción</h1><div class="veredicto incierto" role="alert"><h2>? Hay una emisión sin confirmar</h2>
        <p>No sabemos si la última prescripción quedó emitida. Mientras no se resuelva, el borrador no se puede editar. (M-13)</p></div>`);
      accionFija('<a class="btn primario" href="#/emision">Resolver la emisión</a>');
      return;
    }
    const b = est.borrador || (est.borrador = { paciente: '', alergias: [], items: [itemVacio()], errores: {}, resumen: [], marcados: {} });
    const err = b.errores;
    const campoErr = (id) => (err[id] ? `aria-invalid="true" aria-describedby="${id}-error"` : '');
    const msgErr = (id) => (err[id] ? `<p class="error" id="${id}-error">${esc(err[id])}</p>` : '');
    const opciones = (lista, valor, vacio) => `<option value="">${vacio}</option>${lista.map(([v, t]) => `<option value="${v}" ${String(v) === String(valor) ? 'selected' : ''}>${esc(t)}</option>`).join('')}`;
    const activos = D.medicamentos.filter((m) => m[8]).map((m) => [m[0], m[1]]);
    pintar(`
      ${b.paciente ? `<div class="paciente"><div class="nombre">${esc(nombrePaciente(b.paciente))}</div><div class="meta">Episodio ambulatorio</div></div>` : ''}
      <div class="fila"><h1>Nueva prescripción</h1>
        <button type="button" class="btn icono derecha" aria-label="Ayuda: cómo se verifican las alergias" aria-expanded="false" aria-controls="ayuda" data-accion="ayuda">?</button></div>
      <p id="ayuda" class="ayuda" hidden>Antes de emitir se comparan los medicamentos con las alergias activas del paciente: el mismo principio activo bloquea; el mismo grupo pide justificación. (M-01)</p>
      <p class="obligatorio">* obligatorio</p>
      ${b.resumen.length ? `<div class="aviso error" id="resumen-errores" role="alert" tabindex="-1"><h2>Revise ${b.resumen.length === 1 ? 'un dato' : b.resumen.length + ' datos'}</h2>
        <ul>${b.resumen.map(([id, t]) => `<li><a href="#${id}" data-accion="ir-campo" data-campo="${id}">${esc(t)}</a></li>`).join('')}</ul></div>` : ''}
      <form id="form-borrador" aria-label="Borrador de la prescripción" novalidate>
        <label for="paciente">Paciente *</label>
        <select id="paciente" aria-required="true" ${campoErr('paciente')}>${opciones(D.pacientes[est.sesion.hospital].map((p) => [p, nombrePaciente(p)]), b.paciente, 'Elija el paciente')}</select>
        ${msgErr('paciente')}
        ${b.items.map((it, i) => {
          const n = i + 1, m = med(it.medicamento), marcado = b.marcados[it.medicamento];
          return `<fieldset class="item ${marcado || Object.keys(err).some((k) => k.endsWith('-' + n)) ? 'con-error' : ''}"><legend>Ítem ${n}${m ? ': ' + esc(m.nombre) : ''}</legend>
            ${marcado ? `<p class="error" id="marcado-${n}"><span aria-hidden="true">⛔</span> ${esc(marcado)}</p>` : ''}
            <div class="campos">
              <div class="ancho"><label for="med-${n}">Medicamento *</label>
                <select id="med-${n}" data-i="${i}" data-campo="medicamento" aria-required="true" ${campoErr('med-' + n) || (marcado ? `aria-invalid="true" aria-describedby="marcado-${n}"` : '')}>${opciones(activos, it.medicamento, 'Elija')}</select>${msgErr('med-' + n)}</div>
              <div><label for="dosis-${n}">Dosis${m ? ' (' + m.unidad + ')' : ''} *</label>
                <input id="dosis-${n}" data-i="${i}" data-campo="dosis" inputmode="decimal" autocomplete="off" value="${esc(it.dosis)}" aria-required="true" ${campoErr('dosis-' + n)}>${msgErr('dosis-' + n)}</div>
              <div><label for="cada-${n}">Cada *</label>
                <select id="cada-${n}" data-i="${i}" data-campo="frecuencia" aria-required="true" ${campoErr('cada-' + n)}>${opciones([4, 6, 8, 12, 24].map((h) => [h, h + ' h']), it.frecuencia, 'Elija')}</select>${msgErr('cada-' + n)}</div>
              <div><label for="via-${n}">Vía *</label>
                <select id="via-${n}" data-i="${i}" data-campo="via" aria-required="true" ${campoErr('via-' + n)}>${opciones((m ? m.vias : []).map((v) => [v, v]), it.via, m ? 'Elija' : 'Elija primero el medicamento')}</select>${msgErr('via-' + n)}</div>
              <div><label for="dias-${n}">Días *</label>
                <input id="dias-${n}" data-i="${i}" data-campo="dias" type="number" inputmode="numeric" min="1" max="90" value="${esc(it.dias)}" aria-required="true" ${campoErr('dias-' + n)}>${msgErr('dias-' + n)}</div>
            </div>
            ${b.items.length > 1 ? `<button type="button" class="btn texto quitar" data-accion="quitar" data-i="${i}">Quitar ítem ${n}${m ? ': ' + esc(m.nombre) : ''}</button>` : ''}
          </fieldset>`;
        }).join('')}
        <button type="button" class="btn bloque" data-accion="agregar">+ Agregar medicamento</button>
        <button type="submit" hidden></button>
      </form>`);
    accionFija(`<p class="por-que" id="por-que-emitir">Para emitir, primero verifique las alergias de este borrador. (M-09)</p>
      <button type="button" class="btn primario" data-accion="verificar" aria-describedby="por-que-emitir">Verificar alergias</button>`);
    if (b.resumen.length) setTimeout(() => $('#resumen-errores') && $('#resumen-errores').focus(), 0);
  }

  /** Lo que se envía a la API: el formato que pide el contrato; las reglas las decide el servidor. */
  function cuerpoBorrador(extra = {}) {
    const b = est.borrador;
    const numero = (v) => (String(v).trim() === '' ? null : Number(String(v).replace(',', '.')));
    return JSON.stringify({
      paciente: b.paciente,
      items: b.items.map((it) => ({ medicamento: it.medicamento, dosis: numero(it.dosis), unidad: med(it.medicamento) ? med(it.medicamento).unidad : '', frecuencia_horas: numero(it.frecuencia), via: it.via, dias: numero(it.dias) })),
      ...extra,
    });
  }
  const MSG_CAMPO = { medicamento: 'Elija un medicamento del catálogo', dosis: 'Indique la dosis (mayor que 0)', unidad: 'La unidad no corresponde al medicamento',
    frecuencia_horas: 'Elija cada cuántas horas', via: 'Elija una vía válida para este medicamento', dias: 'Días entre 1 y 90' };
  const ID_CAMPO = { medicamento: 'med', dosis: 'dosis', unidad: 'dosis', frecuencia_horas: 'cada', via: 'via', dias: 'dias' };

  async function verificarBorrador() {
    const b = est.borrador;
    b.errores = {}; b.resumen = []; b.marcados = {};
    if (!b.paciente) { b.errores.paciente = 'Elija el paciente'; b.resumen.push(['paciente', 'Elija el paciente']); vPrescribir(); return; }
    const cuerpo = cuerpoBorrador();
    est.verif = { cargando: true, cuerpo };
    await ir('#/verificacion');
    const r = await llamar('POST', '/verificaciones', cuerpo);
    if (r.estado === 422 && r.cuerpo.error === 'DATOS_INVALIDOS') {
      for (const c of r.cuerpo.campos) {
        const m = /^items\[(\d+)\]\.(\w+)$/.exec(c);
        const [id, t] = m ? [`${ID_CAMPO[m[2]]}-${Number(m[1]) + 1}`, `Ítem ${Number(m[1]) + 1}: ${MSG_CAMPO[m[2]]}`] : ['med-1', 'Agregue al menos un medicamento'];
        if (!b.errores[id]) { b.errores[id] = t.replace(/^Ítem \d+: /, ''); b.resumen.push([id, t]); }
      }
      est.verif = null; await ir('#/prescribir', true); return;
    }
    if (r.estado === 422 && r.cuerpo.error === 'DOSIS_EXCEDE_MAXIMO') {
      for (const c of r.cuerpo.items) {
        const i = Number(/\d+/.exec(c)[0]), it = b.items[i], m = med(it.medicamento);
        const diaria = Number(String(it.dosis).replace(',', '.')) * (24 / Number(it.frecuencia));
        const t = `La dosis diaria de ${m.pa} (${diaria} ${m.unidad}) supera la máxima (${m.max} ${m.unidad}). Reduzca la dosis o la frecuencia. (M-03)`;
        b.errores[`dosis-${i + 1}`] = t; b.resumen.push([`dosis-${i + 1}`, `Ítem ${i + 1}: ${t}`]);
      }
      est.verif = null; await ir('#/prescribir', true); return;
    }
    est.verif = { cuerpo, r };
    vVerificacion();
    enfocarVeredicto();
    const c = r.cuerpo.codigo;
    // B-12: el resultado se anuncia; el bloqueo, con urgencia
    anunciar(r.estado !== 200 ? 'No se pudo verificar.' : c === 'SIN_HALLAZGOS' ? 'Sin conflictos. Puede emitir.' : c === 'ADVERTENCIA' ? `Advertencia: ${r.cuerpo.hallazgos.length} hallazgo.`
      : c === 'BLOQUEO' ? 'Bloqueo: no se puede prescribir.' : c === 'CONFIRMACION_REQUERIDA' ? 'Sin alergias registradas: confirme con el paciente.' : 'No se pudo consultar las alergias.', c === 'BLOQUEO');
  }

  // ----- W2: verificación previa -----
  /**
   * El hallazgo trae el ALÉRGENO como lo guarda el Expediente: el código si lo tiene («J01CA04»), si no el nombre.
   * La primera versión lo mostraba tal cual («Amoxicilina 500 mg ↔ J01CA04»); el nombre se busca en el Expediente.
   */
  const nombreAlergeno = (paciente, alergeno) => {
    const a = api.expedienteDe(paciente).find((x) => (x.codigo ?? x.nombre) === alergeno);
    return (a && a.nombre ? a.nombre : alergeno).trim();
  };
  const hallazgoHtml = (h) => `<div class="hallazgo"><div class="med">${esc(nombreMed(h.medicamento))} ↔ ${esc(nombreAlergeno(est.borrador.paciente, h.alergeno))}</div>
    <div>${h.tipo === 'DIRECTA' ? 'Alergia activa al mismo principio activo' : 'Reactividad cruzada (mismo grupo farmacológico)'} · severidad <b>${sev(h.severidad)}</b>${h.reaccion ? ' · ' + esc(h.reaccion) : ''}</div></div>`;
  function cabeceraPaciente() {
    const b = est.borrador;
    return `<div class="paciente"><div class="nombre">${esc(nombrePaciente(b.paciente))}</div><div class="meta">${b.items.length} medicamento${b.items.length > 1 ? 's' : ''}: ${b.items.map((i) => esc(nombreMed(i.medicamento))).join(', ')}</div></div>`;
  }
  function vVerificacion() {
    const v = est.verif;
    if (!v || !est.borrador) { location.replace('#/prescribir'); return; }
    if (v.cargando) {
      pintar(`${cabeceraPaciente()}<h1>Verificación de alergias</h1><div aria-busy="true" role="status"><p class="meta">Verificando alergias… (M-04)</p>
        <div class="esq" style="width:70%"></div><div class="esq"></div><div class="esq" style="width:85%"></div></div>`);
      return;
    }
    const r = v.r, c = r.cuerpo.codigo, activas = est.borrador.alergias.filter((a) => a[4] === 'active').length;
    let html = '', acciones = '';
    if (r.estado === 0) {
      html = `<div class="veredicto no-disponible" id="veredicto" role="alert"><h2>✕ Sin conexión</h2><p>No se pudo verificar. Su borrador sigue aquí. (M-22)</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="verificar">Reintentar</button>`;
    } else if (r.estado !== 200) {
      html = `<div class="veredicto no-disponible" id="veredicto" role="alert"><h2>✕ No se pudo verificar</h2><p>${esc(textoError(r))}</p></div>`;
    } else if (c === 'SIN_HALLAZGOS') {
      html = `<div class="veredicto ok" id="veredicto" role="status"><h2>✓ Sin conflictos</h2><p>Se compararon los ${est.borrador.items.length} medicamentos con ${activas === 1 ? 'la alergia activa' : `las ${activas} alergias activas`}. Puede emitir. (M-05)</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="emitir">Emitir</button>`;
    } else if (c === 'ADVERTENCIA') {
      html = `<div class="veredicto advertencia" id="veredicto" role="status"><h2>⚠ Advertencia: ${r.cuerpo.hallazgos.length} hallazgo${r.cuerpo.hallazgos.length > 1 ? 's' : ''}</h2>
        ${r.cuerpo.hallazgos.map(hallazgoHtml).join('')}<p style="margin-bottom:0">Para emitir, justifique por qué la necesita, o cambie el medicamento. (M-06)</p></div>`;
      acciones = `<button type="button" class="btn" data-accion="cambiar">Cambiar medicamento</button><button type="button" class="btn primario" data-accion="justificar">Justificar y emitir</button>`;
    } else if (c === 'BLOQUEO') {
      html = `<div class="veredicto bloqueo" id="veredicto" role="alert"><h2>⛔ Bloqueo: no se puede prescribir</h2>${r.cuerpo.hallazgos.filter((h) => h.bloquea).map(hallazgoHtml).join('')}
        <p style="margin-bottom:0">No hay forma de forzarlo. Cambie el medicamento. (M-08)</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="cambiar">Cambiar medicamento</button>`;
    } else if (c === 'CONFIRMACION_REQUERIDA') {
      html = `<div class="veredicto sin-registros" id="veredicto" role="status"><h2>? Sin alergias registradas</h2>
        <p>El expediente de ${esc(est.borrador.paciente)} no tiene alergias registradas. <b>Eso no significa que no tenga alergias.</b> Confírmelo con el paciente. (M-10)</p></div>`;
      acciones = `<button type="button" class="btn" data-accion="cambiar">Volver al borrador</button><button type="button" class="btn primario" data-accion="confirmar">Confirmar con el paciente</button>`;
    } else {
      html = `<div class="veredicto no-disponible" id="veredicto" role="alert"><h2>✕ No se pudo consultar las alergias</h2>
        <p>El expediente no respondió. No podrá emitir hasta que responda. Su borrador sigue aquí. (M-11)</p></div>`;
      acciones = `<button type="button" class="btn" data-accion="cambiar">Volver al borrador</button><button type="button" class="btn primario" data-accion="verificar">Reintentar</button>`;
    }
    pintar(`${cabeceraPaciente()}<h1>Verificación de alergias</h1>${html}`);
    accionFija(acciones);
  }

  function hojaJustificar(texto = '', errorTexto = '') {
    const h = est.verif.r.cuerpo.hallazgos[0];
    const n = [...texto.trim()].length;
    abrirHoja(`<h2 id="h-titulo">Emitir con advertencia</h2>
      <p class="meta">${esc(nombreMed(h.medicamento))} ↔ ${esc(nombreAlergeno(est.borrador.paciente, h.alergeno))} · ${sev(h.severidad)}</p>
      <p class="obligatorio">* obligatorio</p>
      <label for="just">Justificación clínica *</label>
      <textarea id="just" aria-required="true" aria-describedby="${errorTexto ? 'just-error ' : ''}just-cont just-quien" ${errorTexto ? 'aria-invalid="true"' : ''}>${esc(texto)}</textarea>
      ${errorTexto ? `<p class="error" id="just-error">${esc(errorTexto)}</p>` : ''}
      <p class="contador ${n >= 20 ? 'ok' : ''}" id="just-cont">${n} / mínimo 20${n >= 20 ? ' ✓' : ''}</p>
      <p class="ayuda" id="just-quien">Queda guardada con la prescripción y la ven farmacia y enfermería.</p>
      <div class="acciones"><button type="button" class="btn primario" data-accion="emitir-justificado">Emitir con justificación</button>
        <button type="button" class="btn" data-accion="cerrar-hoja">Volver</button></div>`, '#just');
  }
  /** Semana 9 B-11: sin el permiso, la hoja dice solo M-07; no se escribe en vano. (La previa no informa el permiso: brecha M-07.) */
  function hojaSinOverride() {
    abrirHoja(`<h2 id="h-titulo">No puede autorizar esta advertencia</h2>
      <p>Su usuario no puede autorizar advertencias: pida a un médico con ese permiso que la emita, o cambie el medicamento. (M-07)</p>
      <div class="acciones"><button type="button" class="btn primario" data-accion="cambiar">Cambiar medicamento</button>
        <button type="button" class="btn" data-accion="cerrar-hoja">Volver</button></div>`, '[data-accion="cerrar-hoja"]');
  }
  function hojaConfirmar(error = false) {
    abrirHoja(`<h2 id="h-titulo">Confirmar con el paciente</h2>
      <p>«Sin registros» no es «sin alergias». Pregúntele al paciente o a su responsable.</p>
      <label class="casilla"><input type="checkbox" id="conf" ${error ? 'aria-invalid="true" aria-describedby="conf-error"' : ''}> Confirmé con el paciente (o su responsable) que no tiene alergias conocidas.</label>
      ${error ? '<p class="error" id="conf-error">Marque la confirmación para poder emitir.</p>' : ''}
      <p class="ayuda">Se registra quién confirmó y cuándo.</p>
      <div class="acciones"><button type="button" class="btn primario" data-accion="emitir-confirmado" aria-describedby="conf-falta">Emitir</button>
        <p class="por-que" id="conf-falta">Marque la confirmación para poder emitir.</p>
        <button type="button" class="btn" data-accion="cerrar-hoja">Volver</button></div>`, error ? '#conf' : '[data-accion="cerrar-hoja"]');
  }

  // ----- W4: emisión (el servidor vuelve a verificar, R-03) -----
  async function emitir(extra = {}) {
    const cuerpo = cuerpoBorrador(extra);
    // B-10: la misma intención usa SIEMPRE la misma Idempotency-Key; otro contenido, otra clave
    if (!est.emision || est.emision.cuerpo !== cuerpo) est.emision = { clave: 'cli-' + uuid(), cuerpo };
    est.emision.estado = 'enviando';
    if (hoja) { hoja.previo = null; cerrarHoja('emitir'); }
    await ir('#/emision');
    resultadoEmision(await llamar('POST', '', cuerpo, est.emision.clave));
  }
  /** El primer intento y cada reintento (la MISMA clave) se tratan igual. */
  function resultadoEmision(r) {
    const e = est.emision;
    e.r = r;
    if (r.estado === 201 || r.estado === 200) {
      e.estado = 'emitida';
      est.borrador = null; est.verif = null;
    } else if (r.estado === 422 && /^BLOQUEO_/.test(r.cuerpo.error)) {
      e.estado = 'rechazada';
      // La franja vuelve a pedir el Expediente: ahora muestra la alergia nueva. Los ítems afectados quedan marcados.
      est.borrador.alergias = api.expedienteDe(est.borrador.paciente).map((a) => [a.codigo, a.nombre, a.tipo, a.severidad, a.estado, a.reaccion]);
      est.borrador.marcados = {};
      for (const h of r.cuerpo.hallazgos.filter((x) => x.bloquea)) {
        est.borrador.marcados[h.medicamento] = `Bloqueado al emitir: alergia ${h.tipo === 'DIRECTA' ? '' : 'cruzada '}a ${nombreAlergeno(est.borrador.paciente, h.alergeno)} (${sev(h.severidad)}). Cambie el medicamento.`;
      }
      est.verif = null;
    } else if (r.estado === 503) e.estado = 'no-emitida';
    else if (r.estado === 0) e.estado = 'incierto';
    else e.estado = 'error';
    vEmision();
    enfocarVeredicto();
    anunciar(e.estado === 'emitida' ? `Prescripción ${r.cuerpo.id} emitida.` : e.estado === 'rechazada' ? 'No se emitió: alergia nueva.' : e.estado === 'incierto' ? 'No sabemos si quedó emitida.' : 'No se emitió.', e.estado !== 'emitida');
  }
  function vEmision() {
    const e = est.emision;
    if (!e) { location.replace('#/prescribir'); return; }
    const r = e.r;
    const paciente = est.borrador ? `<div class="paciente"><div class="nombre">${esc(nombrePaciente(est.borrador.paciente))}</div></div>` : '';
    let html = '', acciones = '';
    if (e.estado === 'enviando') {
      html = `<div aria-busy="true" role="status"><p class="meta">Emitiendo… el servidor vuelve a verificar las alergias.</p><div class="esq" style="width:80%"></div><div class="esq"></div></div>`;
    } else if (e.estado === 'emitida') {
      const ya = r.cuerpo.codigo === 'YA_EMITIDA';
      html = `<div class="veredicto ok" id="veredicto" role="status"><h2>✓ Prescripción #${r.cuerpo.id} emitida</h2>
        <p>${ya ? 'Ya había quedado emitida antes del corte. Hay una sola prescripción: el reintento no la duplicó.' : 'Farmacia ya puede verla.'} (M-12)</p></div>`;
      acciones = `<a class="btn" href="#/prescribir" data-accion="nueva">Nueva prescripción</a><a class="btn primario" href="#/prescripcion/${r.cuerpo.id}" data-accion="ver">Ver prescripción</a>`;
    } else if (e.estado === 'rechazada') {
      html = `<div class="veredicto rechazada" id="veredicto" role="alert"><h2>⛔ No se emitió: alergia nueva</h2>
        <p>Al emitir, el servidor volvió a consultar el Expediente y encontró una alergia que no estaba cuando usted verificó. (M-08)</p>
        ${r.cuerpo.hallazgos.filter((h) => h.bloquea).map(hallazgoHtml).join('')}
        <p style="margin-bottom:0">No se guardó nada. Su borrador sigue intacto: cambie el medicamento.</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="cambiar">Cambiar medicamento</button>`;
    } else if (e.estado === 'no-emitida') {
      html = `<div class="veredicto no-disponible" id="veredicto" role="alert"><h2>✕ Por seguridad, no se emitió</h2>
        <p>El expediente de alergias no respondió y por seguridad no se emitió. Intente en unos minutos: su prescripción está intacta. (M-14)</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="reintentar-emision">Reintentar</button>`;
    } else if (e.estado === 'incierto') {
      html = `<div class="veredicto incierto" id="veredicto" role="alert"><h2>? No sabemos si quedó emitida</h2>
        <p>La red falló antes de la respuesta. <b>Reintentar es seguro: nunca se emite dos veces.</b> (M-13)</p>
        <p class="meta">Mientras no se resuelva, el borrador no se puede editar.</p>
        <p class="meta" id="soporte-t">Código de soporte</p><p class="referencia" aria-labelledby="soporte-t">${esc(e.clave)}</p>
        <button type="button" class="btn texto" data-accion="copiar" data-texto="${esc(e.clave)}">Copiar código</button></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="reintentar-emision">Reintentar</button>`;
    } else {
      html = `<div class="veredicto no-disponible" id="veredicto" role="alert"><h2>✕ No se emitió</h2><p>${esc(textoError(r))}</p></div>`;
      acciones = `<button type="button" class="btn primario" data-accion="cambiar">Volver al borrador</button>`;
    }
    pintar(`${paciente}<h1>Emisión</h1>${html}`);
    accionFija(acciones);
  }
  function textoError(r) {
    const c = r.cuerpo.error;
    if (r.estado === 403) return 'No tiene permiso para esta acción en este hospital. (M-16)';
    if (r.estado === 404) return 'Esta prescripción no existe o no es de su hospital. (M-17)';
    if (c === 'JUSTIFICACION_REQUERIDA') return 'Esta advertencia necesita justificación y el permiso override. (M-15)';
    if (c === 'CONFIRMACION_REQUERIDA') return 'El expediente no tiene alergias registradas: confirme con el paciente antes de emitir. (M-10)';
    if (r.estado === 401) return 'Su sesión expiró. Vuelva a ingresar. (M-18)';
    return `El servicio no está disponible. Intente en unos minutos. Código de soporte: ${c || 'SIN_CONEXION'} (M-22)`;
  }

  // ----- W5: consultar -----
  function vConsultar(error = '') {
    pintar(`<h1>Consultar prescripción</h1>
      <p class="sub">Escriba el número que figura en la receta. (La API no ofrece una lista: se abre por número.)</p>
      <form id="form-consultar" novalidate>
        <label for="numero">Número de prescripción *</label>
        <input id="numero" inputmode="numeric" autocomplete="off" aria-required="true" ${error ? 'aria-invalid="true" aria-describedby="numero-error"' : ''}>
        ${error ? `<p class="error" id="numero-error">${esc(error)}</p>` : ''}
        <button type="submit" hidden></button>
      </form>`);
    accionFija('<button type="button" class="btn primario" data-accion="abrir">Abrir</button>');
  }

  // ----- W5/W6: la prescripción, y anular -----
  async function vPrescripcion(id) {
    pintar(`<div aria-busy="true" role="status"><p class="meta">Cargando la prescripción…</p><div class="esq" style="width:60%"></div><div class="esq"></div><div class="esq" style="width:80%"></div></div>`);
    const r = await llamar('GET', '/' + id);
    if (location.hash !== '#/prescripcion/' + id) return;
    est.detalle = r;
    pintarDetalle(id);
  }
  function pintarDetalle(id, aviso = '') {
    const r = est.detalle;
    if (r.estado !== 200) {
      pintar(`<h1>Prescripción #${esc(id)}</h1><div class="veredicto bloqueo" id="veredicto" role="alert"><h2>${r.estado === 404 ? 'No encontrada' : 'No se pudo abrir'}</h2><p>${esc(r.estado === 0 ? 'Sin conexión. Intente de nuevo. (M-22)' : textoError(r))}</p></div>`);
      accionFija('<a class="btn" href="#/consultar">Volver a consultar</a>');
      return;
    }
    const p = r.cuerpo, v = p.verificacion, anulada = p.estado === 'ANULADA';
    pintar(`${aviso}
      ${anulada ? `<div class="veredicto bloqueo" role="status"><h2>ANULADA · no dispensar</h2><p>Anulada el ${hora(p.anulacion.anulada_en)} por ${esc(nombreUsuario(p.anulacion.anulada_por))}. Motivo: «${esc(p.anulacion.motivo)}». (M-19)</p></div>` : ''}
      <div class="fila" style="flex-wrap:wrap"><h1 style="margin:0">Prescripción #${p.id}</h1>
        ${anulada ? '<span class="chip anulada">✕ ANULADA</span>' : '<span class="chip emitida">✓ EMITIDA</span>'}</div>
      <p class="meta">${esc(nombrePaciente(p.paciente))} · ${esc(nombreUsuario(p.prescriptor))} · ${hora(p.emitida_en)}</p>
      ${v.resultado === 'ADVERTENCIA' ? '<span class="chip advertencia">⚠ Con advertencia justificada</span>' : ''}
      ${v.confirma_sin_alergias ? '<span class="chip advertencia">? Sin alergias conocidas, confirmado con el paciente</span>' : ''}
      <h2 style="margin-top:16px">Medicamentos</h2>
      <ul class="items-leidos">${p.items.map((i) => `<li><div class="med"><b>${esc(nombreMed(i.medicamento))}</b></div>
        <div class="pauta ${anulada ? 'tachada' : ''}">${i.dosis} ${esc(i.unidad)} · cada ${i.frecuencia_horas} h · ${esc(i.via)} · ${i.dias} día${i.dias > 1 ? 's' : ''}</div></li>`).join('')}</ul>
      <details class="mas" open><summary>Verificación al emitir</summary>
        <dl class="datos">
          <dt>Alergias comparadas</dt><dd>${v.alergenos_evaluados.length ? v.alergenos_evaluados.map((a) => esc(nombreAlergeno(p.paciente, a))).join(', ') : 'Ninguna activa'}</dd>
          <dt>Resultado</dt><dd>${{ SIN_HALLAZGOS: '✓ Sin conflictos', ADVERTENCIA: '⚠ Advertencia', BLOQUEO: '⛔ Bloqueo' }[v.resultado]}</dd>
          ${v.hallazgos.map((h) => `<dt>Hallazgo</dt><dd>${esc(nombreMed(h.medicamento))} ↔ ${esc(nombreAlergeno(p.paciente, h.alergeno))} · ${h.tipo === 'DIRECTA' ? 'directa' : 'cruzada'} · ${sev(h.severidad)}</dd>`).join('')}
          ${v.justificacion ? `<dt>Justificación del médico</dt><dd>«${esc(v.justificacion)}»</dd>` : ''}
        </dl></details>`);
    accionFija(!anulada && puede('prescripcion.anular') ? '<button type="button" class="btn peligro" data-accion="anular">Anular</button>' : '');
  }
  function hojaAnular(texto = '', errorTexto = '') {
    const n = [...texto.trim()].length;
    abrirHoja(`<h2 id="h-titulo">Anular prescripción #${est.detalle.cuerpo.id}</h2>
      <p>No se borra: queda visible como ANULADA, con su motivo. Farmacia lo verá. No se puede deshacer.</p>
      <p class="obligatorio">* obligatorio</p>
      <label for="motivo">Motivo *</label>
      <textarea id="motivo" aria-required="true" aria-describedby="${errorTexto ? 'motivo-error ' : ''}motivo-cont" ${errorTexto ? 'aria-invalid="true"' : ''}>${esc(texto)}</textarea>
      ${errorTexto ? `<p class="error" id="motivo-error">${esc(errorTexto)}</p>` : ''}
      <p class="contador" id="motivo-cont">${n} / mínimo 10</p>
      <div class="acciones"><button type="button" class="btn peligro" data-accion="confirmar-anular">Anular</button>
        <button type="button" class="btn" data-accion="cerrar-hoja">Cancelar</button></div>`, '#motivo');
  }
  async function anular(motivo) {
    const id = est.detalle.cuerpo.id;
    est.anulacion = { motivo };
    const r = await llamar('POST', `/${id}/anulacion`, JSON.stringify({ motivo }));
    if (r.estado === 422) { hojaAnular(motivo, `Escriba al menos 10 caracteres (lleva ${[...motivo.trim()].length}). Describa por qué se anula. (M-20)`); return; }
    if (r.estado === 0) {
      // La anulación pudo haber llegado. Reintentar es seguro porque la interfaz reconcilia el 409 con una consulta.
      abrirHoja(`<h2 id="h-titulo">No sabemos si se anuló</h2>
        <p>La red falló antes de la respuesta. Reintentar es seguro: si ya estaba anulada, se lo diremos.</p>
        <div class="acciones"><button type="button" class="btn primario" data-accion="reintentar-anular">Reintentar</button>
          <button type="button" class="btn" data-accion="cerrar-hoja">Cerrar</button></div>`, '[data-accion="reintentar-anular"]');
      return;
    }
    if (hoja) { hoja.previo = null; cerrarHoja('hecho'); }
    let aviso = '';
    if (r.estado === 200) aviso = `<div class="aviso ok" id="veredicto" role="status"><h2>✓ Prescripción anulada</h2><p>Farmacia la verá como ANULADA, con su motivo.</p></div>`;
    else if (r.estado === 409) {
      // Medido en la semana 10: anular NO es idempotente; el reintento responde 409. Se consulta quién la anuló.
      const g = await llamar('GET', '/' + id);
      if (g.estado === 200 && g.cuerpo.anulacion) {
        const mia = g.cuerpo.anulacion.anulada_por === est.sesion.actor;
        aviso = mia ? `<div class="aviso ok" id="veredicto" role="status"><h2>✓ Su anulación quedó registrada</h2><p>La conexión se cortó, pero la primera solicitud llegó. Al reintentar, el servidor respondió que ya estaba anulada: por usted, el ${hora(g.cuerpo.anulacion.anulada_en)}.</p></div>`
          : `<div class="aviso atencion" id="veredicto" role="status"><h2>Ya estaba anulada</h2><p>Esta prescripción ya fue anulada por ${esc(nombreUsuario(g.cuerpo.anulacion.anulada_por))} el ${hora(g.cuerpo.anulacion.anulada_en)}. (M-21)</p></div>`;
        est.detalle = g;
      }
    } else aviso = `<div class="aviso error" id="veredicto" role="alert"><p>${esc(textoError(r))}</p></div>`;
    if (r.estado === 200) est.detalle = await llamar('GET', '/' + id);
    est.anulacion = null;
    pintarDetalle(id, aviso);
    enfocarVeredicto();
    anunciar(r.estado === 200 ? 'Prescripción anulada.' : 'Su anulación quedó registrada.');
  }

  // ----- Más: sesión y simulación -----
  function vMas() {
    const nueva = D.alergiaNueva;
    pintar(`<h1>Más</h1>
      <div class="tarjeta"><p><strong>${esc(persona().nombre)}</strong><br><span class="meta">${esc(persona().rol)} · ${esc(persona().detalle)}</span></p>
        <button type="button" class="btn bloque" data-accion="salir">Cerrar sesión</button></div>
      <div class="tarjeta"><h2>Simular (demostración)</h2>
        ${[['sinConexion', 'Sin conexión'], ['lenta', 'Conexión lenta (3 s)'], ['perderRespuesta', 'Perder la próxima respuesta (el servidor sí la procesa)'], ['sesionVencida', 'La sesión vence en la próxima petición'], ['expedienteCaido', 'El Expediente de alergias no responde']]
          .map(([k, t]) => `<label class="casilla"><input type="checkbox" data-demo="${k}" ${(k === 'lenta' ? RED.latenciaMs > 1000 : k === 'expedienteCaido' ? api.expediente.caido : RED[k]) ? 'checked' : ''}> ${t}</label>`).join('')}
        <h3>Otra persona registra una alergia</h3>
        <p class="ayuda" id="nueva-ayuda">Como si enfermería la registrara en el Expediente ahora: ${esc(nueva.alergia[1])} (${sev(nueva.alergia[3])}) para ${esc(nombrePaciente(nueva.paciente))}. Una pantalla ya abierta no se entera sola.</p>
        ${est.alergiaNueva ? `<p class="aviso ok" role="status">✓ Registrada en el Expediente a las ${est.alergiaNueva}.</p>`
          : `<button type="button" class="btn bloque" data-accion="alergia-nueva" aria-describedby="nueva-ayuda">Registrar la alergia en el Expediente</button>`}
      </div>`);
  }

  // ---------- eventos ----------
  function leerCampo(t) {
    const b = est.borrador; if (!b) return;
    const i = Number(t.dataset.i);
    if (t.id === 'paciente') {
      b.paciente = t.value;
      // El Expediente (otro módulo) se consulta al elegir el paciente; la franja muestra esa foto.
      b.alergias = b.paciente ? api.expedienteDe(b.paciente).map((a) => [a.codigo, a.nombre, a.tipo, a.severidad, a.estado, a.reaccion]) : [];
      pintarFranja(true);
      return 'repintar';
    }
    if (t.dataset.campo) {
      b.items[i][t.dataset.campo] = t.value;
      if (t.dataset.campo === 'medicamento') {
        const m = med(t.value);
        b.items[i].via = m && m.vias.length === 1 ? m.vias[0] : '';
        delete b.marcados[Object.keys(b.marcados).find((k) => !b.items.some((it) => it.medicamento === k)) || ''];
        return 'repintar';
      }
    }
  }
  document.addEventListener('input', (e) => {
    const t = e.target;
    if (t.id === 'just' || t.id === 'motivo') {
      // B-14: el contador es neutro mientras se escribe; el error aparece al pulsar
      const n = [...t.value.trim()].length, min = t.id === 'just' ? 20 : 10, c = $('#' + t.id + '-cont');
      c.textContent = `${n} / mínimo ${min}${t.id === 'just' && n >= min ? ' ✓' : ''}`;
      c.classList.toggle('ok', t.id === 'just' && n >= min);
    } else if (t.dataset.campo === 'dosis' || t.dataset.campo === 'dias') leerCampo(t);
  });
  document.addEventListener('change', (e) => {
    const t = e.target;
    if (t.dataset.demo) {
      if (t.dataset.demo === 'lenta') RED.latenciaMs = t.checked ? 3000 : 300;
      else if (t.dataset.demo === 'expedienteCaido') api.expediente.caido = t.checked;
      else RED[t.dataset.demo] = t.checked;
      pintarRed();
      return;
    }
    if (t.closest('#form-borrador') && leerCampo(t) === 'repintar') {
      const id = t.id; vPrescribir(); const n = $('#' + id); if (n) n.focus();
    }
  });
  document.addEventListener('submit', (e) => {
    e.preventDefault();
    if (e.target.id === 'form-borrador') verificarBorrador();
    if (e.target.id === 'form-consultar') abrirNumero();
  });
  function abrirNumero() {
    const v = $('#numero').value.trim().replace(/^#/, '');
    if (!/^[1-9]\d{0,17}$/.test(v)) { vConsultar('Escriba un número de prescripción, por ejemplo 12.'); $('#numero').focus(); return; }
    ir('#/prescripcion/' + v);
  }
  let temporizadorDeshacer = null;
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-accion]');
    if (!b) return;
    const accion = b.dataset.accion;
    if (accion === 'ingresar') {
      est.sesion = { actor: b.dataset.id, hospital: D.personas[b.dataset.id].hospital };
      ir(D.permisos[`${b.dataset.id}|${est.sesion.hospital}`].includes('prescripcion.emitir') ? '#/prescribir' : '#/consultar');
    } else if (accion === 'ayuda') { const a = $('#ayuda'); a.hidden = !a.hidden; b.setAttribute('aria-expanded', String(!a.hidden)); }
    else if (accion === 'agregar') { est.borrador.items.push(itemVacio()); vPrescribir(); $(`#med-${est.borrador.items.length}`).focus(); }
    else if (accion === 'quitar') {
      // B-08: quitar con nombre; «Deshacer» 10 s, anunciado
      const i = Number(b.dataset.i), [it] = est.borrador.items.splice(i, 1);
      est.quitado = { it, i };
      vPrescribir();
      $('#toast').innerHTML = `<div class="toast" role="status"><span>Se quitó ${esc(it.medicamento ? nombreMed(it.medicamento) : 'el ítem ' + (i + 1))}.</span><button type="button" class="btn" data-accion="deshacer">Deshacer</button></div>`;
      medirFijos();
      clearTimeout(temporizadorDeshacer);
      temporizadorDeshacer = setTimeout(() => { $('#toast').innerHTML = ''; est.quitado = null; medirFijos(); }, 10000);
      $('[data-accion="agregar"]').focus();
    } else if (accion === 'deshacer') {
      if (est.quitado && est.borrador) { est.borrador.items.splice(est.quitado.i, 0, est.quitado.it); est.quitado = null; $('#toast').innerHTML = ''; vPrescribir(); medirFijos(); anunciar('Ítem restaurado.'); }
    } else if (accion === 'verificar') verificarBorrador();
    else if (accion === 'cambiar') { if (hoja) { hoja.previo = null; cerrarHoja(); } est.verif = null; if (est.emision && est.emision.estado !== 'emitida') est.emision = null; ir('#/prescribir'); }
    else if (accion === 'emitir') emitir();
    else if (accion === 'justificar') { if (puede('prescripcion.override')) hojaJustificar(); else hojaSinOverride(); }
    else if (accion === 'emitir-justificado') {
      const texto = $('#just').value;
      if ([...texto.trim()].length < 20) { hojaJustificar(texto, `Escriba al menos 20 caracteres (lleva ${[...texto.trim()].length}). Explique por qué la necesita.`); return; }
      emitir({ justificacion: texto });
    } else if (accion === 'confirmar') hojaConfirmar();
    else if (accion === 'emitir-confirmado') { if (!$('#conf').checked) { hojaConfirmar(true); return; } emitir({ confirma_sin_alergias: true }); }
    else if (accion === 'reintentar-emision') { const e2 = est.emision; e2.estado = 'enviando'; vEmision(); resultadoEmision(await llamar('POST', '', e2.cuerpo, e2.clave)); }
    else if (accion === 'abrir') abrirNumero();
    else if (accion === 'anular') hojaAnular();
    else if (accion === 'confirmar-anular') anular($('#motivo').value);
    else if (accion === 'reintentar-anular') anular(est.anulacion.motivo);
    else if (accion === 'cerrar-hoja') cerrarHoja(b.dataset.valor || 'cancelar');
    else if (accion === 'alergia-nueva') {
      api.agregarAlergia(D.alergiaNueva.paciente, D.alergiaNueva.alergia);
      est.alergiaNueva = new Date(Date.now() - 6 * 3600000).toISOString().slice(11, 16);
      vMas(); anunciar('Alergia registrada en el Expediente.');
    } else if (accion === 'salir') salir();
    else if (accion === 'ir-campo') { e.preventDefault(); $('#' + b.dataset.campo).focus(); }
    else if (accion === 'copiar') {
      try { await navigator.clipboard.writeText(b.dataset.texto); b.textContent = 'Código copiado'; anunciar('Copiado.'); }
      catch { anunciar('No se pudo copiar automáticamente: seleccione el código y cópielo.'); }
    } else if (accion === 'saltar') { e.preventDefault(); $('#contenido').focus(); }
  });
  navegar();
})();
