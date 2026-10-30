// Consola de release · prototipo navegable (semana 11). Habla con la API REAL de micro-his-e2e (contrato 1.1.0), servida
// desde el mismo origen por servidor.php. Sin framework: rutas por hash y plantillas.
(function () {
  'use strict';

  const API = '/api/v1/e2e';
  const TENANT = 'hosp-norte';
  // Identidades de demostración: las mismas de config/config.php (tokens_demo y permisos).
  const USUARIOS = {
    'resp-release': { token: 'token-release', rol: 'Release', iniciales: 'RR', secciones: ['versiones', 'gate'], detalle: 'Evalúa el quality gate' },
    'ops-luis': { token: 'token-ops', rol: 'Operaciones', iniciales: 'OL', secciones: ['versiones', 'desplegar'], detalle: 'Despliega versiones aprobadas' },
    'dev-ana': { token: 'token-dev', rol: 'Desarrollo', iniciales: 'DA', secciones: ['versiones'], detalle: 'Consulta ejecuciones y evidencia' },
  };
  // Brecha B-1 (semana 8): el contrato 1.1.0 no lista versiones. Hasta que exista GET /versiones, la consola sigue las que
  // se le indican (?versiones=…) y lee cada una por su trazabilidad.
  const VERSIONES = (new URLSearchParams(location.search).get('versiones') || '2.5.0,2.4.1').split(',');
  // R-11: vigencia de una aprobación. El contrato no la expone: se duplica aquí (limitación documentada, como H-09).
  const VIGENCIA_H = 72;
  const REGLAS = {
    digest: 'Digest probado = digest de la versión', a_criticos: 'Todos los críticos pasan', b_tasa: 'No críticos que pasan ≥ 95 %',
    c_inestables: 'Inestables ≤ 2', d_evidencia: 'Toda falla o inestable tiene evidencia confirmada', e_antiguedad: 'Ejecución de 24 h o menos',
  };
  // Catálogo de mensajes de la semana 8 (M-xx) + M-22 de la semana 9 (B-04). El texto depende del CÓDIGO, no del servidor.
  const MENSAJES = {
    GATE_NO_APROBADO: ['La última evaluación del gate ya no está aprobada', 'Alguien evaluó la versión otra vez y el resultado no es APROBADO. No se registró nada; lo cargado se conserva. (M-19)'],
    APROBACION_VENCIDA: ['La aprobación venció mientras instalabas', 'No se registró nada; lo cargado se conserva. Hay que evaluar el gate otra vez. (M-18)'],
    DIGEST_DISTINTO: ['No es el digest aprobado', 'El servidor rechazó el digest instalado. No se registró nada. (M-15)'],
    EJECUCION_NO_COMPLETADA: ['La ejecución todavía está en curso', 'El gate se evalúa sobre una ejecución completada. (M-11)'],
    EJECUCION_DE_OTRA_VERSION: ['La ejecución es de otra versión', 'Recargá la versión. (M-12)'],
    DATOS_INVALIDOS: ['Revisá los datos', 'Campos con error: {campos}. (M-22)'],
    PERMISO_DENEGADO: ['No tenés permiso para esta acción en Hospital Norte', ''],
    ERROR_PERSISTENCIA: ['No se pudo registrar; no se guardó nada', 'Reintentá en un momento. (M-13)'],
    SIN_CONEXION: ['Sin conexión', 'No se envió nada. Reintentá cuando vuelva la señal.'],
  };

  const $ = (sel) => document.querySelector(sel);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const hora = (iso) => new Date(iso).toLocaleString('es-GT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
  const bloques = (d) => 'sha256:' + d.replace('sha256:', '').match(/.{1,8}/g).map((b) => `<span class="bloque">${b}</span>`).join('');
  const chip = { PASA: ['pasa', '✓ Pasa'], FALLA: ['falla', '✕ Falla'], INESTABLE: ['inestable', '≈ Inestable'], OMITIDO: ['omitido', '– Omitido'],
    APROBADO: ['aprobado', '✓ Aprobado'], RECHAZADO: ['rechazado', '✕ Rechazado'], DESPLEGADO: ['desplegado', '✓ Desplegada'],
    REVERTIDO: ['revertido', '✕ Revertido'], EN_CURSO: ['en-curso', '▶ En curso'], COMPLETADA: ['pasa', '■ Completada'], ABORTADA: ['falla', '■ Abortada'] };
  const ponerChip = (v) => { const [c, t] = chip[v] || ['pendiente', '… ' + v]; return `<span class="chip ${c}">${t}</span>`; };

  // ---------------- sesión (demostración) ----------------
  // El token vive en sessionStorage solo para la demostración; nunca va en la URL (R-D1).
  const sesion = () => { try { return JSON.parse(sessionStorage.getItem('sesion') || 'null'); } catch { return null; } };
  const usuario = () => { const s = sesion(); return s && USUARIOS[s] ? Object.assign({ id: s }, USUARIOS[s]) : null; };

  async function api(metodo, ruta, cuerpo) {
    const u = usuario();
    let r;
    try {
      r = await fetch(API + ruta, { method: metodo, headers: { Authorization: 'Bearer ' + u.token, 'X-Tenant-ID': TENANT, 'Content-Type': 'application/json' },
        body: cuerpo === undefined ? undefined : JSON.stringify(cuerpo) });
    } catch {
      red(false);
      return { estado: 0, cuerpo: { error: 'SIN_CONEXION' } };
    }
    red(true);
    return { estado: r.status, cuerpo: await r.json().catch(() => ({})) };
  }
  function red(hay) {
    const el = $('#red');
    el.hidden = hay;
    if (!hay) el.innerHTML = `<span>Sin conexión. Mostrando lo recibido a las ${new Date().toLocaleTimeString('es-GT', { hour: '2-digit', minute: '2-digit' })}.</span>`;
  }

  // ---------------- estado derivado de la trazabilidad ----------------
  function resumen(t) {
    const ult = (xs) => xs[xs.length - 1] || null;
    const ev = ult(t.evaluaciones);
    const ej = ult(t.ejecuciones);
    const dep = ult(t.despliegues);
    const vence = ev && ev.decision === 'APROBADO' ? new Date(new Date(ev.evaluada_en).getTime() + VIGENCIA_H * 3600e3) : null;
    const vigente = vence && vence > new Date();
    const evaluada = ej && t.evaluaciones.some((e) => e.ejecucion === ej.id);
    return { ev, ej, dep, vence, vigente, evaluada };
  }

  // ---------------- marco: barra, contexto, acción, navegación ----------------
  function marco({ atras = null, contexto = '', accion = '', seccion = '' }) {
    const u = usuario();
    $('#atras').hidden = !atras;
    if (atras) $('#atras').href = atras;
    $('#rol').textContent = u ? u.rol : '';
    $('#avatar').hidden = !u;
    if (u) { $('#avatar').textContent = u.iniciales; $('#avatar').setAttribute('aria-label', u.id); }
    $('#contexto').innerHTML = contexto ? `<div class="contexto">${contexto}</div>` : '';
    $('#accion').innerHTML = accion ? `<div class="accion-fija">${accion}</div>` : '';
    const nav = $('#nav');
    nav.hidden = !u;
    if (u) {
      const items = { versiones: ['☰', 'Versiones', '#/versiones'], gate: ['⚖', 'Gate', '#/gate/' + VERSIONES[0]], desplegar: ['⇪', 'Desplegar', '#/versiones'] };
      nav.innerHTML = u.secciones.map((s) => `<a href="${items[s][2]}"${s === seccion ? ' aria-current="page"' : ''}><span class="ico" aria-hidden="true">${items[s][0]}</span>${items[s][1]}</a>`).join('')
        + '<a href="#/ingresar"><span class="ico" aria-hidden="true">⎋</span>Salir</a>';
    }
    document.body.classList.toggle('sin-nav', !u);
  }
  const pintar = (html) => { $('#principal').innerHTML = html; };

  // ---------------- diálogo modal: fondo inerte, foco en Volver, Esc vuelve (semana 9 B-03; semana 10) ----------------
  function dialogo({ titulo, texto, confirmar, peligro = false, alConfirmar }) {
    const origen = document.activeElement;
    const fondo = ['#barra', '#red', '#contexto', '#principal', '#accion', '#nav'].map($);
    fondo.forEach((el) => el.setAttribute('inert', ''));
    $('#dialogo').innerHTML = `<div class="velo"></div><section class="hoja" role="dialog" aria-modal="true" aria-labelledby="d-t" aria-describedby="d-x">
      <div class="asa"></div><h2 id="d-t">${titulo}</h2><p id="d-x">${texto}</p>
      <div class="acciones"><button class="btn ${peligro ? 'peligro' : 'primario'}" type="button" id="d-si">${confirmar}</button>
      <button class="btn" type="button" id="d-no">Volver</button></div></section>`;
    const cerrar = () => { $('#dialogo').innerHTML = ''; fondo.forEach((el) => el.removeAttribute('inert')); document.removeEventListener('keydown', tecla); origen && origen.focus(); };
    // Trampa de foco: inert + aria-modal no alcanzan; con dos botones, Tab salía a la barra del navegador (recorrido, semana 11)
    const tecla = (e) => {
      if (e.key === 'Escape') { cerrar(); return; }
      if (e.key !== 'Tab') return;
      const f = [...document.querySelectorAll('.hoja button')];
      const i = f.indexOf(document.activeElement);
      e.preventDefault();
      f[(i + (e.shiftKey ? -1 : 1) + f.length) % f.length].focus();
    };
    document.addEventListener('keydown', tecla);
    $('#d-no').addEventListener('click', cerrar);
    $('#d-si').addEventListener('click', async () => { $('#dialogo').innerHTML = ''; fondo.forEach((el) => el.removeAttribute('inert')); document.removeEventListener('keydown', tecla); await alConfirmar(); });
    $('#d-no').focus();
  }

  function aviso(tipo, codigo, extra = {}) {
    const [t, x] = MENSAJES[codigo] || ['Error inesperado', `Código ${codigo}.`];
    return `<section class="aviso ${tipo}" id="aviso" tabindex="-1" role="${tipo === 'error' ? 'alert' : 'status'}"><h2>${esc(t)}</h2>${x ? `<p>${esc(x.replace('{campos}', (extra.campos || []).join(', ')))}</p>` : ''}${extra.acciones || ''}</section>`;
  }

  // ---------------- pantallas ----------------
  const pantallas = {
    ingresar() {
      sessionStorage.removeItem('sesion');
      marco({});
      pintar(`<h1>Ingresar (demostración)</h1><p class="sub">En el HIS la identidad la da el inicio de sesión; aquí se elige un usuario de prueba.</p>
        <div class="ingreso">${Object.entries(USUARIOS).map(([id, u]) => `<button class="btn" type="button" data-u="${id}">${u.rol} · ${id}<small>${u.detalle}</small></button>`).join('')}</div>`);
      document.querySelectorAll('[data-u]').forEach((b) => b.addEventListener('click', () => { sessionStorage.setItem('sesion', JSON.stringify(b.dataset.u)); location.hash = '#/versiones'; }));
    },

    async versiones() {
      const u = usuario();
      marco({ seccion: 'versiones' });
      pintar('<h1>Versiones</h1><p class="cargando">Cargando versiones…</p>');
      const ts = (await Promise.all(VERSIONES.map((v) => api('GET', `/versiones/${encodeURIComponent(v)}/trazabilidad`)))).filter((r) => r.estado === 200).map((r) => r.cuerpo);
      const tarjetas = ts.map((t) => {
        const r = resumen(t);
        let est = '<span class="chip pendiente">… Sin ejecución</span>';
        let meta = '';
        let accion = `<a class="btn bloque" href="#/trazabilidad/${t.version}">Ver trazabilidad de ${t.version}</a>`;
        if (r.dep) { est = ponerChip(r.dep.estado); meta = `en ${esc(r.dep.entorno)} · ${hora(r.dep.en)}${r.dep.revertido_a ? ' · volvió a ' + esc(r.dep.revertido_a) : ''}`; }
        else if (r.ej && !r.evaluada) { est = ponerChip(r.ej.estado); meta = `ejecución #${r.ej.id}${r.ej.estado === 'COMPLETADA' ? ' sin evaluar' : ''}`; }
        else if (r.ev) { est = ponerChip(r.ev.decision); meta = r.vigente ? 'vence el ' + hora(r.vence) : r.ev.decision === 'APROBADO' ? 'aprobación vencida' : esc(r.ev.causas.join('; ')); }
        if (r.ej && u.secciones.includes('gate') && r.ej.estado === 'COMPLETADA' && !r.evaluada) accion = `<a class="btn primario bloque" href="#/gate/${t.version}">Evaluar el gate de ${t.version}</a>`;
        if (u.secciones.includes('desplegar') && r.vigente && !r.dep && r.evaluada) accion = `<a class="btn primario bloque" href="#/desplegar/${t.version}">Desplegar ${t.version}</a>`;
        const ejecucion = r.ej ? `<a class="btn bloque" href="#/ejecucion/${t.version}/${r.ej.id}">Ver ejecución #${r.ej.id}</a>` : '';
        return `<article class="tarjeta"><div class="fila"><strong>${esc(t.version)}</strong><span class="derecha">${est}</span></div>
          <div class="meta">${meta}</div><div class="evidencias">${ejecucion}${accion}</div></article>`;
      });
      pintar(`<h1>Versiones</h1><p class="sub">Hospital Regional del Norte · ${ts.length} versiones seguidas</p>${tarjetas.join('') || '<section class="aviso atencion"><h2>No hay versiones para mostrar</h2></section>'}`);
    },

    async ejecucion(version, id) {
      marco({ atras: '#/versiones', contexto: `<span class="version">Ejecución #${esc(id)} · versión ${esc(version)}</span><span class="meta" id="ctx-meta">cargando…</span>` });
      let pausada = false;
      let conocidos = null;
      const cargar = async () => {
        const r = await api('GET', `/versiones/${encodeURIComponent(version)}/trazabilidad`);
        if (r.estado !== 200) return;
        const t = r.cuerpo;
        const e = t.ejecuciones.find((x) => String(x.id) === String(id));
        if (!e) { pintar(aviso('atencion', 'NO_ENCONTRADO')); return; }
        const plan = Object.keys(t.plan);
        const por = Object.fromEntries(e.resultados.map((x) => [x.escenario, x]));
        if (conocidos) {
          const nuevos = e.resultados.filter((x) => !conocidos.includes(x.escenario)).map((x) => `${x.escenario} ${chip[x.resultado][1].slice(2).toUpperCase()}`);
          if (nuevos.length) $('#vivo').textContent = 'Nuevo: ' + nuevos.join(', ') + '.';
        }
        conocidos = e.resultados.map((x) => x.escenario);
        $('#ctx-meta').textContent = `${e.entorno} · ${e.estado === 'EN_CURSO' ? 'en curso' : e.estado === 'COMPLETADA' ? 'completada' : 'abortada'}`;
        const pct = Math.round((100 * e.resultados.length) / plan.length);
        $('#res').innerHTML = `<div class="fila"><h2>Resultados</h2><span class="derecha">${ponerChip(e.estado)}</span></div>
          <div class="progreso" aria-hidden="true"><span style="width:${pct}%"></span></div><p class="meta">${e.resultados.length} de ${plan.length} con resultado</p>
          <div class="solo-movil">${plan.map((k) => { const x = por[k]; return `<article class="tarjeta escenario"><div class="fila"><span class="titulo">${k}${t.plan[k] ? ' · crítico' : ''}</span><span class="derecha">${x ? ponerChip(x.resultado) : '<span class="chip pendiente">… Pendiente</span>'}</span></div>
            <div class="meta">${x ? x.intentos + (x.intentos === 1 ? ' intento' : ' intentos') : 'sin resultado'}</div>
            ${x && x.evidencias.length ? `<p class="meta">Evidencia de ${k}: ${x.evidencias.map((v) => `${esc(v.tipo)} · huella <span class="digest">${v.sha256.slice(0, 8)}</span>`).join('; ')}</p>` : ''}</article>`; }).join('')}</div>
          <table class="tabla" aria-label="Resultados de la ejecución #${esc(id)}"><thead><tr><th>Escenario</th><th>Resultado</th><th>Intentos</th><th>Evidencia</th></tr></thead><tbody>
          ${plan.map((k) => { const x = por[k]; return `<tr><td>${k}${t.plan[k] ? ' · crítico' : ''}</td><td>${x ? chip[x.resultado][1] : '… Pendiente'}</td><td>${x ? x.intentos : '—'}</td><td>${x && x.evidencias.length ? x.evidencias.map((v) => esc(v.tipo) + ' de ' + k).join(', ') : '—'}</td></tr>`; }).join('')}</tbody></table>`;
        $('#accion').innerHTML = e.estado === 'COMPLETADA' && usuario().secciones.includes('gate') ? `<div class="accion-fija"><a class="btn primario" href="#/gate/${esc(version)}">Evaluar el gate de ${esc(version)}</a></div>` : '';
        $('#pausa').hidden = e.estado !== 'EN_CURSO';
      };
      pintar(`<button class="btn bloque" type="button" id="pausa" aria-pressed="false">⏸ Pausar actualización</button>
        <div class="vivo" id="vivo" aria-live="polite" role="status"></div><div id="res"><p class="cargando">Cargando resultados…</p></div>`);
      $('#pausa').addEventListener('click', (ev) => { pausada = !pausada; ev.currentTarget.setAttribute('aria-pressed', String(pausada)); ev.currentTarget.textContent = pausada ? '▶ Reanudar actualización' : '⏸ Pausar actualización'; });
      await cargar();
      const reloj = setInterval(() => { if (!location.hash.startsWith('#/ejecucion/')) clearInterval(reloj); else if (!pausada) cargar(); }, window.REFRESCO_MS || 15000);
    },

    async gate(version) {
      marco({ atras: '#/versiones', seccion: 'gate', contexto: `<span class="version">Quality gate · versión ${esc(version)}</span><span class="meta" id="ctx-meta">cargando…</span>` });
      const r = await api('GET', `/versiones/${encodeURIComponent(version)}/trazabilidad`);
      if (r.estado !== 200) { pintar(aviso('error', r.cuerpo.error)); return; }
      const t = r.cuerpo;
      const e = t.ejecuciones[t.ejecuciones.length - 1];
      if (!e) { pintar('<section class="aviso atencion"><h2>Todavía no hay ejecuciones</h2></section>'); return; }
      $('#ctx-meta').textContent = `Ejecución #${e.id} · ${e.estado === 'COMPLETADA' ? 'completada' : 'en curso'} · digest ${t.digest.slice(0, 15)}…`;
      const cuenta = (res) => e.resultados.filter((x) => x.resultado === res).length;
      pintar(`<h1>Registrar la decisión del gate</h1>
        <p class="sub">La calcula el servidor con la política vigente al registrar; esta pantalla no aprueba ni rechaza por su cuenta.</p>
        <section class="tarjeta"><h2>Ejecución #${e.id}</h2><p>${cuenta('PASA')} pasan · ${cuenta('FALLA')} fallan · ${cuenta('INESTABLE')} inestables · ${Object.keys(t.plan).length - e.resultados.length} sin resultado</p></section>
        <h2>Reglas que se evalúan</h2><ul class="reglas" id="reglas">${Object.entries(REGLAS).map(([k, n]) => `<li><span>${n}</span><span class="valor">se calcula al registrar</span></li>`).join('')}</ul>
        <div id="resultado"></div>`);
      marco({ atras: '#/versiones', seccion: 'gate', contexto: $('#contexto').innerHTML.replace(/^<div class="contexto">|<\/div>$/g, ''),
        accion: `<button class="btn primario" type="button" id="registrar">Registrar la decisión del gate…</button>` });
      $('#registrar').addEventListener('click', () => dialogo({
        titulo: `¿Registrar la decisión del gate de ${esc(version)}?`,
        texto: `El servidor la calcula sobre la ejecución #${e.id} con la política vigente. Una decisión registrada no se edita.`,
        confirmar: 'Registrar la decisión',
        alConfirmar: async () => {
          const s = await api('POST', `/versiones/${encodeURIComponent(version)}/gate`, { ejecucion: e.id });
          if (s.estado !== 200) { $('#resultado').innerHTML = aviso('error', s.cuerpo.error); $('#aviso').focus(); return; }
          const d = s.cuerpo;
          $('#reglas').innerHTML = Object.entries(REGLAS).map(([k, n]) => `<li><span>${n}</span><span class="valor">regla ${k.split('_')[0]}</span><span class="resultado-regla ${d.reglas[k] ? 'cumple">✓ cumple' : 'no-cumple">✕ no cumple'}</span></li>`).join('');
          $('#resultado').innerHTML = `<section class="aviso ${d.decision === 'APROBADO' ? 'ok' : 'error'}" id="aviso" tabindex="-1" role="status"><h2>Decisión registrada: ${chip[d.decision][1]}</h2>
            <p>Política ${esc(d.politica)}.${d.causas.length ? ' Causas: ' + esc(d.causas.join('; ')) + '.' : ''} ${d.decision === 'APROBADO' ? `Vence en ${VIGENCIA_H} h.` : 'Para aprobar, corrija y ejecute otra versión.'}</p></section>`;
          $('#accion').innerHTML = '';
          $('#aviso').focus();
        },
      }));
    },

    async desplegar(version) {
      marco({ atras: '#/versiones', seccion: 'desplegar', contexto: `<span class="version">Desplegar ${esc(version)} → producción</span><span class="meta" id="ctx-meta">cargando…</span>` });
      const r = await api('GET', `/versiones/${encodeURIComponent(version)}/trazabilidad`);
      if (r.estado !== 200) { pintar(aviso('error', r.cuerpo.error)); return; }
      const t = r.cuerpo;
      const s = resumen(t);
      $('#ctx-meta').textContent = s.vigente ? `Aprobada · vence el ${hora(s.vence)}` : 'Sin aprobación vigente';
      pintar(`<div id="mensaje"></div><h1>Instalar con la guía</h1>
        <a class="btn bloque" href="#/trazabilidad/${esc(version)}">Ver trazabilidad de ${esc(version)}</a>
        <span class="etiqueta">Digest aprobado</span><p class="digest">${bloques(t.digest)}</p>
        <button class="btn" type="button" id="copiar">Copiar digest aprobado</button>
        <label for="digest">Digest instalado (pegar)</label><textarea id="digest" class="digest-campo" placeholder="sha256:…" autocomplete="off" spellcheck="false"></textarea>
        <div id="err-digest"></div>
        <label for="guia">Guía usada</label><input id="guia" value="guia-2026.3" autocomplete="off" aria-describedby="pista-guia">
        <p class="ayuda" id="pista-guia">Formato guia-AAAA.N</p>
        <fieldset class="opciones"><legend>Smoke posterior</legend><div class="grupo">
          <label class="opcion"><input type="radio" name="smoke" value="PASA" checked> ✓ Pasa</label>
          <label class="opcion"><input type="radio" name="smoke" value="FALLA"> ✕ Falla</label></div></fieldset>
        <p class="clinica" id="aviso-falla" hidden>Si el smoke falla, se registra REVERTIDO y producción vuelve a la última versión desplegada.</p>`);
      marco({ atras: '#/versiones', seccion: 'desplegar', contexto: $('#contexto').innerHTML.replace(/^<div class="contexto">|<\/div>$/g, ''),
        accion: '<button class="btn primario" type="button" id="registrar">Registrar despliegue…</button>' });
      $('#copiar').addEventListener('click', () => { navigator.clipboard?.writeText(t.digest).catch(() => {}); $('#anuncio').textContent = 'Digest aprobado copiado.'; });
      document.querySelectorAll('input[name=smoke]').forEach((i) => i.addEventListener('change', () => {
        const falla = $('input[name=smoke]:checked').value === 'FALLA';
        $('#aviso-falla').hidden = !falla;
        $('#registrar').textContent = falla ? 'Registrar REVERTIDO…' : 'Registrar despliegue…';
      }));
      // Semana 9 B-03: el botón nunca se deshabilita; con el digest distinto, el foco va al campo y el bloque distinto queda a la vista (R-F1)
      $('#registrar').addEventListener('click', () => {
        const campo = $('#digest');
        const pegado = campo.value.trim();
        if (pegado !== t.digest) {
          const a = t.digest.replace('sha256:', '').match(/.{1,8}/g);
          const b = pegado.replace('sha256:', '').match(/.{1,8}/g) || [];
          campo.setAttribute('aria-invalid', 'true');
          campo.setAttribute('aria-describedby', 'err-digest');
          $('#err-digest').innerHTML = `<p class="error">✕ ${pegado ? 'No es el digest aprobado. Difieren los bloques marcados:' : 'Pegá el digest del artefacto instalado.'}</p>`
            + (pegado ? `<p class="digest">sha256:${a.map((x, i) => `<span class="bloque${b[i] === x ? '' : ' distinto'}">${esc(b[i] || '········')}</span>`).join('')}</p>` : '');
          campo.focus({ preventScroll: true });
          $('#err-digest').scrollIntoView({ block: 'end' });
          return;
        }
        campo.removeAttribute('aria-invalid');
        $('#err-digest').innerHTML = '';
        const smoke = $('input[name=smoke]:checked').value;
        dialogo({
          titulo: smoke === 'FALLA' ? `¿Registrar el smoke fallido de ${esc(version)}?` : `¿Registrar el despliegue de ${esc(version)} en producción?`,
          texto: smoke === 'FALLA' ? 'Se registra <strong>REVERTIDO</strong>: producción vuelve a la última versión desplegada. El registro no se edita.' : `Digest coincidente · ${esc($('#guia').value)} · smoke pasa. El registro no se edita.`,
          confirmar: smoke === 'FALLA' ? 'Registrar REVERTIDO' : 'Registrar despliegue', peligro: smoke === 'FALLA',
          alConfirmar: async () => {
            const res = await api('POST', `/versiones/${encodeURIComponent(version)}/despliegues`, { digest: pegado, entorno: 'produccion', guia: $('#guia').value, smoke });
            if (res.estado === 201) {
              $('#mensaje').innerHTML = `<section class="aviso ${res.cuerpo.estado === 'DESPLEGADO' ? 'ok' : 'error'}" id="aviso" tabindex="-1" role="status"><h2>${res.cuerpo.estado === 'DESPLEGADO' ? `✓ ${esc(version)} desplegada en producción` : `✕ Smoke fallido: producción quedó en ${esc(res.cuerpo.revertido_a || 'la versión anterior')}`}</h2><p>Con ${esc(res.cuerpo.guia)}.</p></section>`;
              $('#accion').innerHTML = '';
            } else {
              // Lo cargado se conserva (semana 9 B-09): solo se agrega el mensaje arriba y se le lleva el foco.
              // Revisión visual (semana 11): el contexto seguía diciendo «Aprobada · vence…» junto al rechazo, y se seguía
              // ofreciendo «Registrar». Si la aprobación ya no vale, el contexto lo dice y la acción pasa a ser ver por qué.
              if (['GATE_NO_APROBADO', 'APROBACION_VENCIDA'].includes(res.cuerpo.error)) {
                $('#ctx-meta').textContent = res.cuerpo.error === 'GATE_NO_APROBADO' ? 'Sin aprobación vigente: la última evaluación no es APROBADO' : 'Sin aprobación vigente: venció';
                $('#accion').innerHTML = `<div class="accion-fija"><a class="btn primario" href="#/trazabilidad/${esc(version)}">Ver por qué no se puede desplegar</a></div>`;
              }
              $('#mensaje').innerHTML = aviso('error', res.cuerpo.error, { campos: res.cuerpo.campos, acciones: `<div class="acciones"><a class="btn" href="#/trazabilidad/${esc(version)}">Ver la última evaluación</a></div>` });
            }
            $('#aviso').focus();
            $('#aviso').scrollIntoView({ block: 'start' });
          },
        });
      });
    },

    async trazabilidad(version) {
      marco({ atras: '#/versiones', contexto: `<span class="version">Trazabilidad · versión ${esc(version)}</span><span class="meta">de la versión al despliegue</span>` });
      const r = await api('GET', `/versiones/${encodeURIComponent(version)}/trazabilidad`);
      if (r.estado !== 200) { pintar(aviso('error', r.cuerpo.error)); return; }
      const t = r.cuerpo;
      const pasos = [`<li><span class="que">Versión registrada</span><div class="meta">commit ${esc(t.commit)}</div><p class="digest">${bloques(t.digest)}</p></li>`,
        `<li><span class="que">Plan</span><div class="meta">${Object.keys(t.plan).length} escenarios (${Object.values(t.plan).filter(Boolean).length} críticos)${t.plan_congelado ? ' · congelado' : ''}</div></li>`];
      const eventos = [
        ...t.ejecuciones.map((e) => ({ orden: e.id * 10, html: `<li><span class="que">Ejecución #${e.id}</span> ${ponerChip(e.estado)}<div class="meta">${esc(e.entorno)} · ${e.resultados.map((x) => x.escenario + ' ' + x.resultado.toLowerCase()).join(', ') || 'sin resultados'}</div></li>` })),
        ...t.evaluaciones.map((d) => ({ orden: d.ejecucion * 10 + 1, html: `<li><span class="que">Gate evaluado</span> ${ponerChip(d.decision)}<div class="meta">${esc(d.por)} · ${hora(d.evaluada_en)} · ejecución #${d.ejecucion} · ${esc(d.politica)}${d.causas.length ? ' · ' + esc(d.causas.join('; ')) : ''}</div></li>` })),
        ...t.despliegues.map((d) => ({ orden: 1e6 + d.id, html: `<li><span class="que">Despliegue en ${esc(d.entorno)}</span> ${ponerChip(d.estado)}<div class="meta">${esc(d.operador)} · ${hora(d.en)} · ${esc(d.guia)}${d.revertido_a ? ' · smoke fallido: volvió a ' + esc(d.revertido_a) : ''}</div></li>` })),
      ].sort((a, b) => a.orden - b.orden).map((x) => x.html);
      if (!t.despliegues.length) eventos.push('<li class="pendiente"><span class="que">Despliegue</span><div class="meta">todavía no</div></li>');
      pintar(`<h1>Trazabilidad de ${esc(t.version)}</h1><ol class="linea">${pasos.concat(eventos).join('')}</ol>`);
    },
  };

  async function ruta() {
    const [, nombre, ...args] = (location.hash || '#/ingresar').split('/');
    if (!usuario() && nombre !== 'ingresar') { location.hash = '#/ingresar'; return; }
    $('#dialogo').innerHTML = '';
    await (pantallas[nombre] || pantallas.versiones)(...args.map(decodeURIComponent));
    document.body.dataset.listo = location.hash;
    $('#principal').focus({ preventScroll: true });
  }
  window.addEventListener('hashchange', ruta);
  ruta();
})();
