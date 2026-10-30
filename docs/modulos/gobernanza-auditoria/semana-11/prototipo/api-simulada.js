// API de Auditoría simulada en el navegador (semana 11).
// Reproduce el kernel del Micro-HIS en el mismo orden: ruta (404/405) → X-Tenant-ID (400) → token (401) → tenant (403) →
// caso de uso: permiso (403) → cursor → filtro no soportado → datos inválidos (422), y la meta-auditoría de cada lectura (R-08).
// Mismos códigos HTTP, mismos códigos de negocio y mismo formato de respuesta. La paridad con el backend real se comprueba
// con herramientas/paridad.cjs (la misma secuencia aquí y en PHP).
// Límite declarado: los sellos NO son sha256 (no hace falta para la interfaz); la verificación sí recorre la cadena igual.
(function (raiz) {
  const BASE = '/api/v1/auditoria';
  const PAGINA = 50;
  const FILTROS = ['desde', 'hasta', 'usuario', 'modulo', 'accion', 'resultado']; // orden de contenedor.php
  const RESULTADOS = ['exito', 'denegado', 'error'];
  const GT_MS = 6 * 3600000;

  const utc = (ms) => new Date(Math.floor(ms / 1000) * 1000).toISOString().replace('.000Z', 'Z');
  const b64url = (t) => btoa(t).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  const desdeB64url = (t) => { try { return atob(t.replace(/-/g, '+').replace(/_/g, '/')); } catch { return null; } };
  /** Sello de imitación: 64 hexadecimales deterministas (FNV-1a), NO sha256. */
  const sello = (texto) => {
    let out = '';
    for (let k = 0; k < 8; k++) {
      let h = 0x811c9dc5 ^ k;
      for (const c of texto) { h ^= c.codePointAt(0); h = Math.imul(h, 0x01000193) >>> 0; }
      out += h.toString(16).padStart(8, '0');
    }
    return out;
  };
  /** «AAAA-MM-DD» exacto y real → ms UTC de su medianoche en Guatemala; si no, null (FiltroRangoFechas::fecha). */
  const fechaLocal = (t) => {
    if (typeof t !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(t)) return null;
    const [a, m, d] = t.split('-').map(Number);
    const ms = Date.UTC(a, m - 1, d);
    return new Date(ms).toISOString().slice(0, 10) === t ? ms + GT_MS : null;
  };

  function crearApi(D, ahora = () => Date.now()) {
    let movs = [];
    let siguienteId = 1;
    let eventos = 0;

    const r = (estado, cuerpo, cabeceras = {}) => ({ estado, cuerpo, cabeceras });
    const err = (estado, error, extra = {}) => r(estado, { error, ...extra });
    const cadena = (hospital) => movs.filter((m) => m.hospital === hospital).sort((a, b) => a.secuencia - b.secuencia);
    const permite = (actor, tenant, permiso) => (D.permisos[`${actor}|${tenant}`] || []).includes(permiso);

    function anexar(m) {
      const c = cadena(m.hospital);
      const anterior = c.length ? c[c.length - 1] : null;
      m.secuencia = (anterior ? anterior.secuencia : 0) + 1;
      m.sello = sello((anterior ? anterior.sello : '0'.repeat(64)) + m.secuencia + JSON.stringify([m.emisor, m.event_id, m.usuario, m.accion, m.ocurrido]));
      m.id = siguienteId++;
      movs.push(m);
      return m;
    }
    function auditarLectura(hospital, actor, accion, detalle) {
      anexar({ emisor: 'auditoria', event_id: 'sis-' + (++eventos), hospital, usuario: actor, modulo: 'auditoria', accion,
        ocurrido: Math.floor(ahora() / 1000) * 1000, resultado: 'exito', recurso: null, correlation_id: null, detalle,
        corrige_a: null, motivo_correccion: null });
    }
    const resumen = (m) => ({ id: m.id, ocurrido_en: utc(m.ocurrido), hospital: m.hospital, usuario: m.usuario, modulo: m.modulo,
      accion: m.accion, resultado: m.resultado, recurso: m.recurso, corrige_a: m.corrige_a });
    const completo = (m) => ({ id: m.id, emisor: m.emisor, event_id: m.event_id, hospital: m.hospital, usuario: m.usuario, modulo: m.modulo,
      accion: m.accion, ocurrido_en: utc(m.ocurrido), resultado: m.resultado, recurso: m.recurso, correlation_id: m.correlation_id,
      detalle: m.detalle, corrige_a: m.corrige_a, motivo_correccion: m.motivo_correccion, secuencia: m.secuencia, sello: m.sello });
    const corregidoPor = (id) => { const c = movs.find((x) => x.corrige_a === id); return c ? c.id : null; };

    /** Solo para la semilla: los emisores ya enviaron estos movimientos (sin las 9 reglas, que no usa la interfaz). */
    function registrarSemilla({ token, cuerpo }) {
      const [emisor] = D.tokens[token];
      const original = cuerpo.corrige_evento ? movs.find((m) => m.emisor === emisor && m.event_id === cuerpo.corrige_evento && m.hospital === cuerpo.hospital) : null;
      const m = anexar({ emisor, event_id: cuerpo.event_id, hospital: cuerpo.hospital, usuario: cuerpo.usuario, modulo: cuerpo.modulo,
        accion: cuerpo.accion, ocurrido: Date.parse(cuerpo.ocurrido_en), resultado: cuerpo.resultado, recurso: cuerpo.recurso ?? null,
        correlation_id: null, detalle: {}, corrige_a: original ? original.id : null, motivo_correccion: cuerpo.motivo_correccion ?? null });
      return r(201, { id: m.id, secuencia: m.secuencia, sello: m.sello });
    }
    /** Lo que alguien borró directo en la base (se salta el trigger): solo para demostrar ALTERADA. */
    function borrarDirecto({ emisor, event_id }) { movs = movs.filter((m) => !(m.emisor === emisor && m.event_id === event_id)); }

    function consultar(id, tenant, q) {
      const red = tenant === '*';
      if (!permite(id.actor, tenant, red ? 'auditoria.consultar_red' : 'auditoria.consultar')) return err(403, 'ACCESO_DENEGADO');
      const p = { ...q };
      const cursor = p.cursor ?? null;
      delete p.cursor;
      let despues = null;
      if (cursor !== null) {
        const t = desdeB64url(cursor);
        const m = t && t.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\|(\d{1,18})$/);
        if (!m) return err(422, 'DATOS_INVALIDOS', { campos: { cursor: 'Cursor inválido.' } });
        despues = [Date.parse(m[1]), Number(m[2])];
      }
      for (const nombre of Object.keys(p)) if (!FILTROS.includes(nombre)) return err(422, 'FILTRO_NO_SOPORTADO', { campos: { [nombre]: 'Filtro no soportado.' } });
      const desde = fechaLocal(p.desde ?? ''), hasta = fechaLocal(p.hasta ?? '');
      if (desde === null || hasta === null) return err(422, 'DATOS_INVALIDOS', { campos: { desde: 'Indique desde y hasta (AAAA-MM-DD).' } });
      if (desde > hasta) return err(422, 'DATOS_INVALIDOS', { campos: { desde: 'Desde no puede ser posterior a hasta.' } });
      for (const campo of ['usuario', 'modulo', 'accion']) {
        if (p[campo] !== undefined && (p[campo].trim() === '' || p[campo].trim().length > 64)) return err(422, 'DATOS_INVALIDOS', { campos: { [campo]: 'De 1 a 64 caracteres.' } });
      }
      if (p.resultado !== undefined && !RESULTADOS.includes(p.resultado)) return err(422, 'DATOS_INVALIDOS', { campos: { resultado: 'Debe ser exito, denegado o error.' } });

      const hastaExcl = hasta + 86400000;
      const hospital = red ? null : tenant;
      const encontrados = movs.filter((m) => (red || m.hospital === hospital)
        && (!despues || m.ocurrido < despues[0] || (m.ocurrido === despues[0] && m.id < despues[1]))
        && m.ocurrido >= desde && m.ocurrido < hastaExcl
        && ['usuario', 'modulo', 'accion', 'resultado'].every((c) => p[c] === undefined || m[c] === p[c].trim()))
        .sort((a, b) => b.ocurrido - a.ocurrido || b.id - a.id).slice(0, PAGINA + 1);
      const pagina = encontrados.slice(0, PAGINA);
      auditarLectura(hospital, id.actor, 'AUDITORIA_CONSULTADA', { alcance: red ? 'red' : 'hospital', filtros: p, resultados: pagina.length });
      const ultimo = pagina[pagina.length - 1];
      return r(200, {
        movimientos: pagina.map((m) => ({ ...resumen(m), corregido_por: corregidoPor(m.id) })),
        cursor_siguiente: encontrados.length > PAGINA && ultimo ? b64url(`${utc(ultimo.ocurrido)}|${ultimo.id}`) : null,
      });
    }

    function detalle(id, tenant, mid) {
      const red = tenant === '*';
      if (!permite(id.actor, tenant, red ? 'auditoria.consultar_red' : 'auditoria.consultar')) return err(403, 'ACCESO_DENEGADO');
      const m = movs.find((x) => x.id === mid);
      if (!m || (!red && m.hospital !== tenant)) return err(404, 'MOVIMIENTO_NO_ENCONTRADO');
      auditarLectura(red ? null : tenant, id.actor, 'AUDITORIA_CONSULTADA', { alcance: red ? 'red' : 'hospital', detalle: mid });
      return r(200, { movimiento: { ...completo(m), corregido_por: corregidoPor(m.id) } });
    }

    function verificar(id, cuerpo) {
      if (cuerpo === null || typeof cuerpo !== 'object' || Array.isArray(cuerpo)) return err(400, 'CUERPO_INVALIDO');
      const hospital = cuerpo.hospital ?? null;
      if (hospital !== null && typeof hospital !== 'string') return err(400, 'FORMATO_INVALIDO', { campos: { hospital: 'Texto, o null para la cadena de red.' } });
      const a = cuerpo.ancla ?? null;
      if (a !== null && (typeof a !== 'object' || !Number.isInteger(a.secuencia) || a.secuencia < 1 || typeof a.sello !== 'string' || !/^[0-9a-f]{64}$/.test(a.sello))) {
        return err(400, 'FORMATO_INVALIDO', { campos: { ancla: '{"secuencia": entero ≥ 1, "sello": 64 hexadecimales}.' } });
      }
      if (!permite(id.actor, '*', 'auditoria.verificar')) return err(403, 'ACCESO_DENEGADO');
      const c = cadena(hospital);
      let informe = null;
      for (let i = 0; i < c.length; i++) {
        if (c[i].secuencia !== i + 1) { informe = { estado: 'ALTERADA', primera_secuencia: i + 1, verificados: i }; break; }
      }
      if (!informe && a) {
        if (c.length < a.secuencia) informe = { estado: 'TRUNCADA', esperada_hasta: a.secuencia, verificados: c.length };
        else if (c[a.secuencia - 1].sello !== a.sello) informe = { estado: 'ALTERADA', primera_secuencia: a.secuencia, verificados: c.length };
      }
      if (!informe) informe = { estado: 'INTEGRA', verificados: c.length, ...(a ? { ancla_confirmada: a.secuencia } : {}) };
      auditarLectura(null, id.actor, 'INTEGRIDAD_VERIFICADA', { hospital, ...informe });
      return r(200, informe);
    }

    function ancla(id, q) {
      const hospital = q.hospital ?? null;
      if (hospital !== null && hospital === '') return err(400, 'FORMATO_INVALIDO', { campos: { hospital: 'Texto; sin el parámetro, la cadena de red.' } });
      if (!permite(id.actor, '*', 'auditoria.verificar')) return err(403, 'ACCESO_DENEGADO');
      const c = cadena(hospital);
      const u = c[c.length - 1];
      return r(200, { hospital, secuencia: u ? u.secuencia : 0, sello: u ? u.sello : null, emitida_en: utc(ahora()) }, { 'Cache-Control': 'no-store' });
    }

    const RUTAS = [
      ['POST', /^\/movimientos$/, null],
      ['GET', /^\/movimientos$/, (id, t, q) => consultar(id, t, q)],
      ['GET', /^\/movimientos\/([1-9][0-9]{0,17})$/, (id, t, q, c, m) => detalle(id, t, Number(m[1]))],
      ['POST', /^\/verificaciones$/, (id, t, q, c) => verificar(id, c)],
      ['GET', /^\/anclas$/, (id, t, q) => ancla(id, q)],
    ];

    /** Una petición, sincrónica: mismo orden que Kernel::procesar(). */
    function solicitar(metodo, url, { token = '', tenant = '', cuerpo = null } = {}) {
      const [camino, qs = ''] = url.split('?');
      const q = Object.fromEntries(new URLSearchParams(qs));
      if (!camino.startsWith(BASE)) return err(404, 'RUTA_NO_ENCONTRADA');
      const ruta = camino.slice(BASE.length);
      let noPermitido = false;
      for (const [m, patron, accion] of RUTAS) {
        const coincide = ruta.match(patron);
        if (!coincide) continue;
        if (m !== metodo) { noPermitido = true; continue; }
        if (!tenant) return err(400, 'HOSPITAL_REQUERIDO');
        const ident = D.tokens[token];
        if (!ident) return err(401, 'NO_AUTENTICADO');
        const [actor, hospitalToken] = ident;
        if (!(hospitalToken === '*' || hospitalToken === tenant)) return err(403, 'TENANT_NO_COINCIDE');
        if (!accion) return err(403, 'ACCESO_DENEGADO'); // registrar: ninguna persona de la interfaz es un emisor
        return accion({ actor }, tenant, q, cuerpo, coincide);
      }
      return err(noPermitido ? 405 : 404, noPermitido ? 'METODO_NO_PERMITIDO' : 'RUTA_NO_ENCONTRADA');
    }

    /**
     * La misma petición, pero «por la red» del prototipo: latencia, sin conexión, respuesta perdida (el servidor SÍ la
     * procesó: la lectura queda registrada) y sesión vencida (el servidor ya no acepta el token: 401).
     */
    function solicitarRed(metodo, url, opciones, red) {
      return new Promise((resolver, rechazar) => {
        if (red.sinConexion) { setTimeout(() => rechazar({ red: 'sin-conexion' }), 150); return; }
        setTimeout(() => {
          if (red.sesionVencida) { resolver(err(401, 'NO_AUTENTICADO')); return; }
          const respuesta = solicitar(metodo, url, opciones);
          if (red.perderRespuesta) { red.perderRespuesta = false; rechazar({ red: 'sin-conexion', procesada: true }); return; }
          resolver(respuesta);
        }, red.latenciaMs);
      });
    }

    return { solicitar, solicitarRed, registrarSemilla, borrarDirecto, ahora };
  }

  raiz.crearApi = crearApi;
})(typeof window !== 'undefined' ? window : globalThis);
