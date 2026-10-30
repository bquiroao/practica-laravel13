// Prototipo navegable del Catálogo de medicamentos (semana 11). Sin servidor: imita la API v1
// (semana 5) y aplica las mismas reglas: vigencia [desde, hasta), duplicados RN-04, RN-02 y 422 por campo.
(function () {
  'use strict';
  const D = window.CATALOGO_DATOS;
  const versiones = D.versiones.map((v) => ({ ...v }));
  const main = document.getElementById('contenido');
  const avisos = document.getElementById('avisos');
  const selRol = document.getElementById('rol');
  let rol = 'QF_CATALOGO';
  let avisoPendiente = false;
  let primeraCarga = true; // al abrir, el primer Tab debe llegar a «Saltar al contenido»

  // ---------- utilidades ----------
  const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const sinAcentos = (t) => String(t).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  const forma = (f) => D.etiquetas.formas[f] || f;
  const via = (v) => D.etiquetas.vias[v] || v;
  const vigenteEn = (v, f) => v.desde <= f && (v.hasta === null || f < v.hasta);
  const seCruzan = (a, b) => (b.hasta === null || a.desde < b.hasta) && (a.hasta === null || b.desde < a.hasta);
  const clave = (m) => [sinAcentos(m.principio.trim().replace(/\s+/g, ' ')), Number(m.valor), m.unidad, m.forma, m.via].join('|');
  const historial = (codigo) => versiones.filter((v) => v.codigo === codigo).sort((a, b) => a.version - b.version);
  const esQF = () => rol === 'QF_CATALOGO';
  const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

  function avisar(texto, tipo = 'exito') {
    avisos.innerHTML = `<div class="caja ${tipo}"><p>${esc(texto)}</p></div>`;
    avisoPendiente = true; // se conserva en la siguiente pantalla y se borra en la que sigue
  }
  function pintar(html, titulo) {
    main.innerHTML = html;
    main.querySelector('h1')?.setAttribute('tabindex', '-1');
    document.title = `${titulo} · Catálogo de medicamentos`;
    document.querySelectorAll('[data-solo]').forEach((e) => { e.hidden = e.dataset.solo !== rol; });
  }
  function enfocar(selector) {
    const e = main.querySelector(selector) || main;
    e.focus();
  }

  // ---------- búsqueda (CU-02) ----------
  function vistaBuscar(params) {
    const q = params.get('q') || '';
    const f = params.get('forma') || '';
    const fecha = params.get('fecha') || '';
    const opciones = Object.entries(D.etiquetas.formas).map(([k, e]) => `<option value="${k}" ${k === f ? 'selected' : ''}>${esc(e)}</option>`).join('');
    pintar(`
      <h1>Buscar medicamentos vigentes</h1>
      <form id="form-buscar" role="search" novalidate>
        <div class="fila">
          <div><label for="q">Nombre, principio activo o código
            <span class="ayuda" id="q-ayuda">Mínimo 2 caracteres. No importa si escribe acentos.</span></label>
            <input id="q" name="q" value="${esc(q)}" maxlength="60" aria-describedby="q-ayuda"></div>
          <div><label for="forma">Forma farmacéutica</label><select id="forma" name="forma"><option value="">Todas</option>${opciones}</select></div>
          <div><label for="fecha">Vigentes a la fecha <span class="ayuda">Vacío = hoy (${D.hoy})</span></label>
            <input id="fecha" name="fecha" type="date" value="${esc(fecha)}"></div>
        </div>
        <p id="q-error" class="error" hidden></p>
        <button type="submit">Buscar</button>
      </form>
      <div id="resultado" aria-live="polite"></div>`, 'Buscar');
    document.getElementById('form-buscar').addEventListener('submit', (ev) => {
      ev.preventDefault();
      const datos = new FormData(ev.target);
      const texto = String(datos.get('q')).trim();
      const err = document.getElementById('q-error');
      if (texto.length === 1 || (!texto && !datos.get('forma'))) {
        err.textContent = 'Escriba al menos 2 caracteres o elija una forma farmacéutica.';
        err.hidden = false;
        document.getElementById('q').setAttribute('aria-invalid', 'true');
        document.getElementById('q').focus();
        return;
      }
      location.hash = `#/buscar?${new URLSearchParams({ q: texto, forma: datos.get('forma'), fecha: datos.get('fecha') })}`;
    });
    if (q || f) { mostrarResultados(q, f, fecha || D.hoy); }
  }

  async function mostrarResultados(q, f, fecha) {
    const zona = document.getElementById('resultado');
    zona.innerHTML = '<p class="cargando">Buscando…</p>';
    main.querySelector('#form-buscar button').disabled = true;
    await esperar(350);
    main.querySelector('#form-buscar button').disabled = false;
    const t = sinAcentos(q.trim());
    const lista = versiones.filter((v) => vigenteEn(v, fecha) && (!f || v.forma === f)
      && (!t || [v.nombre, v.principio, v.codigo].some((c) => sinAcentos(c).includes(t))))
      .sort((a, b) => a.nombre.localeCompare(b.nombre, 'es') || a.codigo.localeCompare(b.codigo));
    if (lista.length === 0) {
      zona.innerHTML = `<div class="caja"><h2>No hay medicamentos vigentes al ${esc(fecha)} con ese criterio</h2>
        <p>Puede estar dado de baja. Pruebe con el principio activo o con otra fecha.</p>
        ${esQF() ? '<p><a href="#/nuevo">Registrar medicamento</a></p>' : ''}</div>`;
      return;
    }
    zona.innerHTML = `<h2>${lista.length} resultado(s) vigentes al ${esc(fecha)}</h2><ul class="tarjetas">${lista.map((v) => `
      <li class="tarjeta"><h3><a href="#/medicamento/${v.codigo}">${esc(v.nombre)}</a></h3>
        <p>${esc(v.valor)} ${esc(v.unidad)} · ${esc(forma(v.forma))} · ${esc(via(v.via))}</p>
        <p><span class="codigo">${v.codigo}</span> · versión ${v.version}</p></li>`).join('')}</ul>`;
  }

  // ---------- formulario de alta y nueva versión (CU-01, CU-03) ----------
  const CAMPOS = [
    ['principio', 'Principio activo', 3, 120], ['valor', 'Concentración', 0, 0], ['unidad', 'Unidad', 0, 0],
    ['forma', 'Forma farmacéutica', 0, 0], ['via', 'Vía de administración', 0, 0],
    ['nombre', 'Nombre', 3, 150], ['presentacion', 'Presentación', 3, 120],
  ];

  function vistaFormulario(codigo) {
    if (!esQF()) { return vistaError(403, 'Su rol no puede registrar ni cambiar medicamentos.'); }
    const base = codigo ? historial(codigo).slice(-1)[0] : null;
    if (codigo && (!base || base.hasta !== null)) { return vistaError(409, 'Este medicamento no tiene una versión abierta.'); }
    const v = base || {};
    const sel = (nombre, opciones) => `<select id="${nombre}" name="${nombre}" aria-required="true"><option value="">Elija…</option>${opciones}</select>`;
    const op = (obj, actual) => Object.entries(obj).map(([k, e]) => `<option value="${k}" ${k === actual ? 'selected' : ''}>${esc(e)}</option>`).join('');
    const campo = (id, etiqueta, control) => `<label for="${id}">${etiqueta} <span class="obligatorio" aria-hidden="true">*</span></label>${control}<p class="error" id="${id}-error" hidden></p>`;
    const texto = (id, max, valor, extra = '') => `<input id="${id}" name="${id}" maxlength="${max}" value="${esc(valor)}" aria-required="true" ${extra}>`;
    pintar(`
      <h1>${codigo ? `Nueva versión de ${esc(codigo)}` : 'Registrar medicamento'}</h1>
      <p>Los campos con <span class="obligatorio">*</span> son obligatorios.</p>
      <div id="resumen" tabindex="-1"></div>
      <form id="form-med" novalidate>
        <fieldset><legend>Producto (define si es un duplicado)</legend>
          ${campo('principio', 'Principio activo', texto('principio', 120, v.principio))}
          <div class="fila"><div>${campo('valor', 'Concentración', texto('valor', 10, v.valor, 'inputmode="decimal"'))}</div>
            <div>${campo('unidad', 'Unidad', sel('unidad', D.etiquetas.unidades.map((u) => `<option ${u === v.unidad ? 'selected' : ''}>${u}</option>`).join('')))}</div></div>
          ${campo('forma', 'Forma farmacéutica', sel('forma', op(D.etiquetas.formas, v.forma)))}
          ${campo('via', 'Vía de administración', sel('via', op(D.etiquetas.vias, v.via)))}
        </fieldset>
        <fieldset><legend>Presentación y vigencia</legend>
          ${campo('nombre', 'Nombre', texto('nombre', 150, v.nombre))}
          ${campo('presentacion', 'Presentación', texto('presentacion', 120, v.presentacion))}
          <label for="desde">${codigo ? 'La nueva versión empieza el' : 'Vigente desde'} <span class="ayuda">Vacío = hoy (${D.hoy})</span></label>
          <input id="desde" name="desde" type="date"><p class="error" id="desde-error" hidden></p>
        </fieldset>
        <button type="submit">${codigo ? 'Registrar nueva versión' : 'Registrar'}</button>
        <a href="${codigo ? `#/medicamento/${codigo}` : '#/buscar'}">Cancelar</a>
      </form>`, codigo ? 'Nueva versión' : 'Registrar');
    document.getElementById('form-med').addEventListener('submit', (ev) => { ev.preventDefault(); enviar(ev.target, base); });
  }

  function validar(d, base) {
    const e = {};
    for (const [id, etiqueta, min, max] of CAMPOS) {
      const valor = d[id];
      if (!valor) { e[id] = `${etiqueta}: dato obligatorio`; continue; }
      if (max && (valor.length < min || valor.length > max)) { e[id] = `${etiqueta}: use entre ${min} y ${max} caracteres (tiene ${valor.length})`; }
    }
    if (d.valor && !/^\d{1,6}([.,]\d{1,3})?$/.test(d.valor)) { e.valor = 'Concentración: número mayor que 0, hasta 3 decimales'; }
    else if (d.valor && Number(d.valor.replace(',', '.')) <= 0) { e.valor = 'Concentración: debe ser mayor que 0'; }
    if (base && d.desde <= base.desde) { e.desde = `La nueva versión debe empezar después del ${base.desde}`; }
    return e;
  }

  async function enviar(form, base) {
    const d = Object.fromEntries([...new FormData(form)].map(([k, v]) => [k, String(v).trim()]));
    d.desde = d.desde || D.hoy;
    form.querySelectorAll('.error').forEach((p) => { p.hidden = true; });
    form.querySelectorAll('[aria-invalid]').forEach((c) => c.removeAttribute('aria-invalid'));
    const resumen = document.getElementById('resumen');
    const errores = validar(d, base);
    if (Object.keys(errores).length) {
      // 422: todos los errores a la vez, resumen con enlaces y foco en el resumen (B-02, B-03)
      for (const [id, msj] of Object.entries(errores)) {
        const p = document.getElementById(`${id}-error`);
        p.textContent = msj; p.hidden = false;
        const control = document.getElementById(id);
        control.setAttribute('aria-invalid', 'true'); control.setAttribute('aria-describedby', `${id}-error`);
      }
      resumen.innerHTML = `<div class="caja alerta" role="alert"><h2>Revise ${Object.keys(errores).length} campo(s)</h2><ul>${Object.entries(errores)
        .map(([id, m]) => `<li><a href="#${id}" data-campo="${id}">${esc(m)}</a></li>`).join('')}</ul></div>`;
      resumen.querySelectorAll('[data-campo]').forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); document.getElementById(a.dataset.campo).focus(); }));
      resumen.focus();
      return;
    }
    const boton = form.querySelector('button'); boton.disabled = true; boton.textContent = 'Guardando…';
    await esperar(400);
    const nueva = { ...d, valor: d.valor.replace(',', '.'), hasta: null, motivo: null, creadoPor: 'qf.norte' };
    const otro = versiones.find((v) => v.codigo !== base?.codigo && clave(v) === clave(nueva) && seCruzan(v, nueva));
    if (otro) {
      // Error crítico 409 DUPLICADO (RN-04): recuperable, con salida clara
      boton.disabled = false; boton.textContent = base ? 'Registrar nueva versión' : 'Registrar';
      resumen.innerHTML = `<div class="caja alerta" role="alert"><h2>Ya existe un medicamento equivalente vigente</h2>
        <p><span class="codigo">${otro.codigo}</span> · ${esc(otro.nombre)} tiene el mismo principio activo, concentración, forma y vía.</p>
        <p><a href="#/medicamento/${otro.codigo}">Ver ${otro.codigo}</a> · <a href="#principio" id="corregir">Corregir datos</a></p>
        <p class="ayuda">Si es otra presentación del mismo producto, registre una nueva versión en ${otro.codigo}. Código de soporte: proto-${Date.now().toString(16)}</p></div>`;
      document.getElementById('corregir').addEventListener('click', (e) => { e.preventDefault(); document.getElementById('principio').focus(); });
      resumen.focus();
      return;
    }
    if (base) {
      base.hasta = nueva.desde; base.motivo = `Reemplazada por la versión ${base.version + 1}`;
      Object.assign(nueva, { codigo: base.codigo, version: base.version + 1 });
    } else {
      const max = Math.max(...versiones.map((v) => Number(v.codigo.slice(4))));
      Object.assign(nueva, { codigo: `MED-${String(max + 1).padStart(6, '0')}`, version: 1 });
    }
    versiones.push(nueva);
    avisar(base ? `Versión ${nueva.version} de ${nueva.codigo} registrada. La versión ${base.version} se cerró el ${nueva.desde}.` : `Registrado ${nueva.codigo}: ${nueva.nombre}.`);
    location.hash = `#/medicamento/${nueva.codigo}`;
  }

  // ---------- detalle, baja y eliminación (CU-04, CU-05, CU-06) ----------
  function vistaDetalle(codigo) {
    const lista = historial(codigo);
    if (!lista.length) { return vistaError(404, `No existe ${codigo} en este hospital.`); }
    const ultima = lista[lista.length - 1];
    const hoyV = lista.find((v) => vigenteEn(v, D.hoy));
    const verHistorial = rol !== 'CONSULTA_CLINICA';
    pintar(`
      <h1>${esc(ultima.nombre)}</h1>
      <p><span class="codigo">${codigo}</span> · ${esc(ultima.principio)} ${esc(ultima.valor)} ${esc(ultima.unidad)} · ${esc(forma(ultima.forma))} · ${esc(via(ultima.via))}</p>
      <div class="caja ${hoyV ? 'exito' : 'alerta'}"><h2>${hoyV ? `Vigente hoy: versión ${hoyV.version}` : `No vigente desde ${esc(ultima.hasta)}`}</h2>
        <p>${esc((hoyV || ultima).presentacion)}</p></div>
      ${verHistorial ? `<h2>Historial de versiones</h2><table class="tabla-historial"><thead><tr><th scope="col">Versión</th><th scope="col">Presentación</th>
        <th scope="col">Desde</th><th scope="col">Hasta</th><th scope="col">Registró</th><th scope="col">Motivo de cierre</th></tr></thead><tbody>${lista.map((v) => `
        <tr class="${v === hoyV ? 'vigente' : ''}"><td data-etiqueta="Versión">${v.version}</td><td data-etiqueta="Presentación">${esc(v.presentacion)}</td>
        <td data-etiqueta="Desde">${v.desde}</td><td data-etiqueta="Hasta">${v.hasta || '(abierta)'}</td><td data-etiqueta="Registró">${esc(v.creadoPor)}</td>
        <td data-etiqueta="Motivo">${esc(v.motivo || '')}</td></tr>`).join('')}</tbody></table>` : ''}
      ${esQF() && ultima.hasta === null ? `<h2>Acciones</h2>
        <a href="#/medicamento/${codigo}/version">Registrar nueva versión</a>
        <div class="fila"><button type="button" class="secundario" id="btn-baja">Dar de baja</button>
        <button type="button" class="peligro" id="btn-eliminar">Eliminar registro erróneo</button></div>` : ''}
      <p><a href="#/buscar">Volver a la búsqueda</a></p>
      <dialog id="dlg"></dialog>`, codigo);
    document.getElementById('btn-baja')?.addEventListener('click', () => dialogoBaja(ultima));
    document.getElementById('btn-eliminar')?.addEventListener('click', () => dialogoEliminar(codigo));
  }

  function abrir(html, alEnviar) {
    const dlg = document.getElementById('dlg');
    dlg.innerHTML = `<form method="dialog" novalidate>${html}<div class="fila"><button value="ok">Confirmar</button><button value="no" class="secundario" formnovalidate>Cancelar</button></div></form>`;
    dlg.querySelector('form').addEventListener('submit', (ev) => {
      if (ev.submitter?.value === 'ok' && !alEnviar(dlg)) { ev.preventDefault(); }
    });
    dlg.showModal();
    dlg.querySelector('input, textarea')?.focus();
  }

  function dialogoBaja(v) {
    abrir(`<h2>Dar de baja ${v.codigo}</h2><p>Dejará de aparecer en búsquedas desde la fecha indicada. El historial se conserva.</p>
      <label for="fecha-baja">Fecha de baja <span class="ayuda">Vacío = hoy (${D.hoy})</span></label><input id="fecha-baja" type="date">
      <label for="motivo">Motivo <span class="obligatorio">*</span> <span class="ayuda">5 a 200 caracteres</span></label>
      <textarea id="motivo" maxlength="200" aria-required="true" aria-describedby="motivo-error"></textarea><p class="error" id="motivo-error" hidden></p>`, (dlg) => {
      const fecha = dlg.querySelector('#fecha-baja').value || D.hoy;
      const motivo = dlg.querySelector('#motivo').value.trim();
      const error = dlg.querySelector('#motivo-error');
      const msj = motivo.length < 5 ? 'Escriba un motivo de al menos 5 caracteres.'
        : fecha <= v.desde ? `La baja debe ser posterior al ${v.desde}. Si el registro empezó hoy, use «Eliminar registro erróneo».` : '';
      if (msj) { error.textContent = msj; error.hidden = false; dlg.querySelector('#motivo').focus(); return false; }
      Object.assign(v, { hasta: fecha, motivo });
      avisar(`${v.codigo} dado de baja desde ${fecha}. Su historial se conserva.`);
      setTimeout(ruta);
      return true;
    });
  }

  function dialogoEliminar(codigo) {
    if (D.usados.includes(codigo)) {
      abrir(`<h2>No se puede eliminar ${codigo}</h2><div class="caja alerta" role="alert"><p>Ya se usó en prescripciones.
        Para que deje de usarse, dele de baja.</p></div>`, () => { setTimeout(() => dialogoBaja(historial(codigo).slice(-1)[0])); return true; });
      document.querySelector('#dlg button[value=ok]').textContent = 'Ir a dar de baja';
      return;
    }
    abrir(`<h2>Eliminar ${codigo}</h2><p>Solo para registros erróneos que nunca se usaron. No se puede deshacer.</p>
      <label for="confirmar-codigo">Escriba <span class="codigo">${codigo}</span> para confirmar</label>
      <input id="confirmar-codigo" autocomplete="off" aria-describedby="conf-error"><p class="error" id="conf-error" hidden></p>`, (dlg) => {
      if (dlg.querySelector('#confirmar-codigo').value.trim().toUpperCase() !== codigo) {
        const e = dlg.querySelector('#conf-error'); e.textContent = 'El código no coincide.'; e.hidden = false; return false;
      }
      for (let i = versiones.length - 1; i >= 0; i--) { if (versiones[i].codigo === codigo) { versiones.splice(i, 1); } }
      avisar(`${codigo} eliminado.`);
      location.hash = '#/buscar';
      return true;
    });
  }

  function vistaError(estado, mensaje) {
    pintar(`<h1>${estado === 404 ? 'No encontrado' : estado === 403 ? 'Sin permiso' : 'No se puede continuar'}</h1>
      <div class="caja alerta" role="alert"><p>${esc(mensaje)}</p></div><p><a href="#/buscar">Volver a la búsqueda</a></p>`, 'Error');
  }

  // ---------- enrutador por hash ----------
  function ruta() {
    if (!avisoPendiente) { avisos.innerHTML = ''; }
    avisoPendiente = false;
    const [camino, consulta] = (location.hash.slice(1) || '/buscar').split('?');
    const p = new URLSearchParams(consulta || '');
    const m = camino.match(/^\/medicamento\/(MED-\d{6})(\/version)?$/);
    if (camino === '/nuevo') { vistaFormulario(null); }
    else if (m && m[2]) { vistaFormulario(m[1]); }
    else if (m) { vistaDetalle(m[1]); }
    else if (camino === '/buscar') { vistaBuscar(p); }
    else { vistaError(404, 'La página no existe.'); }
    if (!primeraCarga) { enfocar('h1'); }
    primeraCarga = false;
  }
  selRol.addEventListener('change', () => { rol = selRol.value; ruta(); });
  window.addEventListener('hashchange', () => { if (location.hash === '' || location.hash.startsWith('#/')) { ruta(); } });
  document.querySelector('.saltar').addEventListener('click', (e) => { e.preventDefault(); main.focus(); });
  ruta();
})();
