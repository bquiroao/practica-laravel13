// API RBAC simulada en el navegador (semana 11).
// Reproduce el kernel del Micro-HIS: mismo orden (ruta → tenant 400 → token 401 → permiso 403 → controlador),
// mismas reglas en el mismo orden, mismos códigos HTTP y de negocio, y el mismo formato de respuesta.
// La paridad con el backend real se comprueba con herramientas/paridad.cjs (misma secuencia en PHP y aquí).
(function (raiz) {
  function crearApi(DATOS, opciones = {}) {
    const ahora = new Date(DATOS.ahora);
    const fecha = (iso) => (iso === null ? null : new Date(iso));
    const usuarios = { ...DATOS.semilla.usuarios };
    const permisosPorRol = DATOS.semilla.permisosPorRol;
    const tokens = DATOS.semilla.tokens;
    let membresias = DATOS.semilla.membresias.map(([usuario, hospital, rol, estado, desde, hasta]) =>
      ({ usuario, hospital, rol, estado, desde: fecha(desde), hasta: fecha(hasta) }));
    const auditoria = [];
    let contador = 0;
    const nuevoId = opciones.generarId || (() => (typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `corr-${String(++contador).padStart(4, '0')}`));

    const json = (estado, cuerpo) => ({ estado, cuerpo });
    const vigente = (m) => m.estado === 'active' && m.desde <= ahora && (m.hasta === null || ahora < m.hasta);
    const activaAbierta = (m) => m.estado === 'active' && m.hasta === null;
    const de = (usuario, hospital) => membresias.filter((m) => m.usuario === usuario && m.hospital === hospital);

    function registrar(tipo, actor, hospital, datos) {
      const evento = { correlation_id: nuevoId(), tipo, actor, hospital, datos, ocurrido_en: DATOS.ahora };
      auditoria.push(evento);
      return evento.correlation_id;
    }

    // CU-03: PoliticaAutorizacion con ReglaTenantCoincide → ReglaMembresiaVigente → ReglaRolConcedePermiso.
    function autorizar(usuario, tenantToken, tenantSolicitud, permiso, recurso, ip) {
      let motivo = null;
      if (tenantToken !== tenantSolicitud) motivo = 'TENANT_NO_COINCIDE';
      else {
        const vigentes = de(usuario, tenantSolicitud).filter(vigente);
        if (vigentes.length === 0) motivo = 'MEMBRESIA_NO_VIGENTE';
        else if (!vigentes.some((m) => (permisosPorRol[m.rol] || []).includes(permiso))) motivo = 'PERMISO_NO_ASIGNADO';
      }
      if (motivo === null) return { permitido: true };
      const id = registrar('ACCESO_DENEGADO', usuario, tenantSolicitud, { permiso, recurso, motivo, ip });
      return { permitido: false, correlationId: id };
    }

    const isoFecha = (v) => (typeof v === 'string' && /^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2})?([+-]\d{2}:\d{2}|Z)?)?$/.test(v.trim())
      ? new Date(v.trim().length === 10 ? v.trim() + 'T00:00:00-06:00' : v.trim()) : null);
    const texto = (v) => (typeof v === 'string' ? v.trim() : '');

    // ---------- controladores ----------
    function asignar(p) {
      const c = p.cuerpo || {};
      const usuario = texto(c.usuario_id), rol = texto(c.rol), desde = isoFecha(c.valido_desde);
      const tieneHasta = c.valido_hasta !== undefined && c.valido_hasta !== null;
      const hasta = tieneHasta ? isoFecha(c.valido_hasta) : null;
      const invalidos = [];
      if (!usuario) invalidos.push('usuario_id');
      if (!rol) invalidos.push('rol');
      if (!desde) invalidos.push('valido_desde');
      if (tieneHasta && !hasta) invalidos.push('valido_hasta');
      if (invalidos.length) return json(422, { codigo: 'DATOS_INVALIDOS', campos: invalidos });
      // PoliticaAsignacion: usuario activo → rol en catálogo → no autoasignación → vigencia → sin duplicada.
      if (!(usuario in usuarios)) return json(404, { codigo: 'USUARIO_NO_ENCONTRADO' });
      if (usuarios[usuario] !== 'active') return json(422, { codigo: 'USUARIO_INACTIVO' });
      if (!(rol in permisosPorRol)) return json(422, { codigo: 'ROL_INVALIDO' });
      if (usuario === p.usuario) return json(422, { codigo: 'AUTOASIGNACION' });
      if (hasta && hasta <= desde) return json(422, { codigo: 'VIGENCIA_INVALIDA' });
      if (de(usuario, p.tenant).some((m) => m.rol === rol && activaAbierta(m))) return json(409, { codigo: 'ASIGNACION_DUPLICADA' });
      membresias.push({ usuario, hospital: p.tenant, rol, estado: 'active', desde, hasta });
      const id = registrar('ROL_ASIGNADO', p.usuario, p.tenant, {
        usuario_destino: usuario, rol, valido_desde: c.valido_desde, valido_hasta: tieneHasta ? c.valido_hasta : null, ip: p.ip,
      });
      return json(201, { codigo: 'ROL_ASIGNADO', correlation_id: id });
    }

    function revocar(p) {
      const c = p.cuerpo || {};
      const campos = { usuario_id: texto(c.usuario_id), rol: texto(c.rol), motivo: texto(c.motivo) };
      const invalidos = Object.keys(campos).filter((k) => campos[k] === '' || [...campos[k]].length > 500);
      if (invalidos.length) return json(422, { codigo: 'DATOS_INVALIDOS', campos: invalidos });
      const { usuario_id: usuario, rol, motivo } = campos;
      // PoliticaRevocacion: no autorrevocación → motivo suficiente → membresía revocable.
      if (usuario === p.usuario) return json(422, { codigo: 'AUTORREVOCACION' });
      if ([...motivo].length < 10) return json(422, { codigo: 'MOTIVO_INSUFICIENTE' });
      const revocable = (m) => m.rol === rol && m.estado === 'active' && (m.hasta === null || m.hasta > ahora);
      const objetivo = de(usuario, p.tenant).filter(revocable);
      if (objetivo.length === 0) return json(404, { codigo: 'MEMBRESIA_NO_ENCONTRADA' });
      objetivo.forEach((m) => { m.estado = 'revoked'; });
      const id = registrar('ROL_REVOCADO', p.usuario, p.tenant, {
        usuario_destino: usuario, rol, motivo, revocadas: String(objetivo.length), ip: p.ip,
      });
      return json(200, { codigo: 'ROL_REVOCADO', correlation_id: id });
    }

    function decidir(p) {
      const c = p.cuerpo || {};
      const permiso = texto(c.permiso), recurso = texto(c.recurso);
      const invalidos = [];
      if (!/^[a-z_]+\.[a-z_]+$/.test(permiso)) invalidos.push('permiso');
      if (!recurso || recurso.length > 200) invalidos.push('recurso');
      if (invalidos.length) return json(422, { codigo: 'DATOS_INVALIDOS', campos: invalidos });
      const r = autorizar(p.usuario, p.tenantToken, p.tenant, permiso, recurso, p.ip);
      return r.permitido ? json(200, { decision: 'PERMITIDO' }) : json(200, { decision: 'DENEGADO', correlation_id: r.correlationId });
    }

    function evento(p) {
      const e = auditoria.find((x) => x.correlation_id === p.params.id);
      return e && e.hospital === p.tenant ? json(200, { ...e, datos: { ...e.datos } })
        : json(404, { mensaje: 'No existe un evento con esa referencia en este hospital.' });
    }

    // ---------- endpoints PROPUESTOS (no están en el contrato v1.1.0: brechas B-1 y B-4) ----------
    function membresiasDe(p) {
      const usuario = p.query.get('usuario') || '';
      return json(200, { usuario, membresias: de(usuario, p.tenant).map((m) => ({
        rol: m.rol, estado: m.estado, valido_desde: m.desde.toISOString(), valido_hasta: m.hasta ? m.hasta.toISOString() : null,
      })) });
    }
    function buscarUsuarios(p) {
      const q = (p.query.get('q') || '').trim().toLowerCase();
      if (q.length < 2) return json(200, { usuarios: [] });
      const lista = Object.keys(usuarios).filter((id) => {
        const nombre = (DATOS.personas[id] || {}).nombre || '';
        return id.toLowerCase().includes(q) || nombre.toLowerCase().includes(q);
      }).map((id) => ({ id, estado: usuarios[id] }));
      return json(200, { usuarios: lista });
    }

    const RUTAS = [
      ['POST', /^\/api\/v1\/roles\/asignaciones$/, 'roles.asignar', asignar],
      ['POST', /^\/api\/v1\/roles\/revocaciones$/, 'roles.revocar', revocar],
      ['GET', /^\/api\/v1\/auditoria\/eventos\/(?<id>[A-Za-z0-9-]{1,64})$/, 'auditoria.consultar', evento],
      ['POST', /^\/api\/v1\/autorizaciones\/decisiones$/, null, decidir],
      ['POST', /^\/api\/v1\/prescripciones$/, 'prescripciones.crear', () => json(201, { mensaje: 'Prescripción registrada (demo).' })],
      ['POST', /^\/api\/v1\/signos-vitales$/, 'signos_vitales.registrar', () => json(201, { mensaje: 'Signos vitales registrados (demo).' })],
      ['GET', /^\/api\/v1\/roles\/membresias$/, 'roles.asignar', membresiasDe, 'B-1'],
      ['GET', /^\/api\/v1\/usuarios$/, 'roles.asignar', buscarUsuarios, 'B-4'],
    ];

    // ---------- kernel ----------
    function manejar(metodo, url, { token = null, tenant = null, cuerpo = null, ip = '10.20.5.14' } = {}) {
      const [ruta, consulta = ''] = url.split('?');
      let encontrada = null, metodoNoPermitido = false;
      for (const r of RUTAS) {
        const m = ruta.match(r[1]);
        if (!m) continue;
        if (r[0] === metodo) { encontrada = [r, m.groups || {}]; break; }
        metodoNoPermitido = true;
      }
      if (!encontrada) return metodoNoPermitido ? json(405, { mensaje: 'Método no permitido.' }) : json(404, { mensaje: 'Ruta no encontrada.' });
      const [[, , permiso, controlador], params] = encontrada;
      if (!tenant) return json(400, { mensaje: 'La cabecera X-Tenant-ID es obligatoria.' });
      const identidad = tokens[token];
      if (!identidad) return json(401, { mensaje: 'Token inválido o expirado.' });
      const [usuario, tenantToken] = identidad;
      if (permiso) {
        const r = autorizar(usuario, tenantToken, tenant, permiso, `${metodo} ${ruta}`, ip);
        if (!r.permitido) return json(403, { mensaje: 'Acceso denegado.', correlation_id: r.correlationId });
      }
      return controlador({ usuario, tenantToken, tenant, cuerpo, ip, params, query: new URLSearchParams(consulta) });
    }

    // Copia profunda: la UI nunca toca el estado interno.
    const responder = (r) => JSON.parse(JSON.stringify(r));
    return {
      solicitar: (metodo, url, op) => responder(manejar(metodo, url, op)),
      // Simula la red: latencia, modo sin conexión y «respuesta perdida» (el servidor SÍ procesa,
      // pero la respuesta no llega: es el caso de resultado incierto de la semana 10).
      async solicitarRed(metodo, url, op, red = { latenciaMs: 250, sinConexion: false, perderRespuesta: false }) {
        await new Promise((ok) => setTimeout(ok, red.latenciaMs));
        if (red.sinConexion) throw new TypeError('Failed to fetch');
        const r = responder(manejar(metodo, url, op));
        if (red.perderRespuesta && metodo === 'POST' && !url.includes('/autorizaciones/')) { red.perderRespuesta = false; throw new TypeError('Respuesta perdida'); }
        return r;
      },
      rutas: RUTAS.map(([m, re, permiso, , brecha]) => ({ metodo: m, ruta: re.source, permiso, brecha: brecha || null })),
    };
  }

  if (typeof module !== 'undefined' && module.exports) module.exports = { crearApi };
  else raiz.crearApi = crearApi;
})(typeof window !== 'undefined' ? window : globalThis);
