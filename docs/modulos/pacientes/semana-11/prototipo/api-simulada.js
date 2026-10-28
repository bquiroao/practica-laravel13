// API de Pacientes simulada en el navegador (semana 11).
// Reproduce el kernel del Micro-HIS: mismo orden (ruta → hospital 400 → token 401 → tenant 403 → formulario → caso de uso),
// las mismas reglas en el mismo orden (RevisionDeFicha), los mismos códigos HTTP y de negocio y el mismo formato de respuesta.
// La paridad con el backend real se comprueba con herramientas/paridad.cjs (misma secuencia aquí y en PHP).
(function (raiz) {
  const MAPA_TILDES = { 'á': 'a', 'é': 'e', 'í': 'i', 'ó': 'o', 'ú': 'u', 'ü': 'u', 'ñ': 'n' };
  const SEXOS = ['female', 'male', 'other', 'unknown'];
  const TEXTOS = ['nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'motivo'];
  const LIMITE = 20;
  const NOMBRE_PROTEGIDO = 'Identidad protegida';
  const RESERVADO = 'RESERVADO';

  // Texto::normalizar — minúsculas, sin tildes, espacios simples
  const normalizar = (t) => String(t).trim().toLowerCase().replace(/[áéíóúüñ]/g, (c) => MAPA_TILDES[c]).replace(/\s+/g, ' ');
  const contarLetras = (t) => (String(t).match(/\p{L}/gu) || []).length;

  // FabricaIdentificador + Dpi + Pasaporte
  function crearIdentificador(tipo, numero, pais) {
    tipo = String(tipo ?? '').trim().toUpperCase();
    numero = String(numero ?? '').trim();
    if (tipo === '' && numero === '') return null;
    if (tipo === 'DPI') {
      const valor = numero.replace(/[\s-]/g, '');
      if (!/^\d{13}$/.test(valor)) throw new Error('El DPI debe tener 13 dígitos.');
      const depto = parseInt(valor.slice(9, 11), 10);
      if (depto < 1 || depto > 22) throw new Error('El departamento del DPI debe estar entre 01 y 22.');
      return { tipo: 'DPI', valor, canonico: 'GTM|DPI|' + valor };
    }
    if (tipo === 'PASAPORTE') {
      const valor = numero.replace(/[\s-]/g, '').toUpperCase();
      const p = String(pais ?? '').trim().toUpperCase();
      if (!/^[A-Z]{3}$/.test(p)) throw new Error('El país del pasaporte debe ser un código de 3 letras.');
      if (!/^[A-Z0-9]{6,20}$/.test(valor)) throw new Error('El pasaporte debe tener de 6 a 20 letras o dígitos.');
      return { tipo: 'PASAPORTE', valor, canonico: p + '|PASAPORTE|' + valor };
    }
    if (tipo === '') throw new Error('Indique el tipo de documento.');
    throw new Error(`Tipo de documento no admitido: ${tipo}.`);
  }
  const enmascarado = (id) => (id ? '*'.repeat(id.valor.length - 4) + id.valor.slice(-4) : null);

  class ErrorDominio { constructor(estado, error, datos = {}) { this.estado = estado; this.error = error; this.datos = datos; } }
  const prohibido = (codigo = 'ACCESO_DENEGADO') => new ErrorDominio(403, codigo);
  const invalido = (codigo, datos) => new ErrorDominio(422, codigo, datos);
  const conflicto = (codigo, datos) => new ErrorDominio(409, codigo, datos);

  function crearApi(DATOS, opciones = {}) {
    const hoy = DATOS.hoy;
    let pacientes = [];       // fichas guardadas (todas las del sistema, de todos los hospitales)
    let secuencia = 0;
    const idempotencia = new Map();

    const json = (estado, cuerpo, cabeceras = {}) => ({ estado, cuerpo, cabeceras });
    const puede = (actor, hospital, permiso) => (DATOS.permisos[`${actor}|${hospital}`] || []).includes(permiso);
    const capacidades = (a, h) => ({
      verProtegidos: puede(a, h, 'pacientes.leer_protegido'), proteger: puede(a, h, 'pacientes.proteger'),
      confirmarDuplicado: puede(a, h, 'pacientes.confirmar_duplicado'), editarRestringido: puede(a, h, 'pacientes.editar_restringido'),
    });
    const exigir = (a, h, permiso) => { if (!puede(a, h, permiso)) throw prohibido(); };

    // ---------- Paciente ----------
    const nombreNormalizado = (p) => normalizar(p.nombres + ' ' + p.apellidos);
    const reducidoPara = (p, ver) => p.protegida && !ver;
    function resumen(p, ver) {
      if (reducidoPara(p, ver)) return { codigo: p.codigo, nombre: NOMBRE_PROTEGIDO, nacimiento: null, identificador: null, identidad_protegida: true };
      return { codigo: p.codigo, nombre: p.nombres + ' ' + p.apellidos, nacimiento: p.nacimiento, identificador: enmascarado(p.id), identidad_protegida: p.protegida };
    }
    function detalle(p, ver) {
      const r = reducidoPara(p, ver);
      return { ...resumen(p, ver), sexo: p.sexo, telefono: r ? null : p.telefono, tipo_documento: r ? null : (p.id ? p.id.tipo : null),
        estado: p.estado, identidad_provisional: p.id === null, version: p.version };
    }
    function fechaValida(f) {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(f)) return false;
      const d = new Date(f + 'T00:00:00Z');
      return !Number.isNaN(d.getTime()) && d.toISOString().slice(0, 10) === f;
    }
    // Paciente::desdeDatos (R-01)
    function desdeDatos(hospital, d) {
      const e = {};
      const nombres = d.nombres.trim(), apellidos = d.apellidos.trim();
      if (nombres === '' || [...nombres].length > 100) e.nombres = 'Obligatorio, hasta 100 caracteres.';
      if (apellidos === '' || [...apellidos].length > 100) e.apellidos = 'Obligatorio, hasta 100 caracteres.';
      if (!fechaValida(d.nacimiento)) e.nacimiento = 'Fecha válida con formato AAAA-MM-DD.';
      else if (d.nacimiento > hoy) e.nacimiento = 'No puede ser una fecha futura.';
      if (!SEXOS.includes(d.sexo)) e.sexo = 'Debe ser female, male, other o unknown.';
      const telefono = String(d.telefono ?? '').trim();
      if (telefono !== '' && !/^\+?[0-9 ]{8,15}$/.test(telefono)) e.telefono = 'De 8 a 15 dígitos.';
      let id = null;
      try { id = crearIdentificador(d.tipoDocumento, d.numeroDocumento, d.paisDocumento); } catch (x) { e.documento = x.message; }
      if (Object.keys(e).length) throw invalido('DATOS_INVALIDOS', { campos: e });
      return { codigo: null, hospital, nombres, apellidos, nacimiento: d.nacimiento, sexo: d.sexo, telefono: telefono === '' ? null : telefono,
        id, estado: 'active', version: 0, protegida: d.identidadProtegida ?? false };
    }
    const cambiaIdentidad = (a, b) => nombreNormalizado(a) !== nombreNormalizado(b) || a.nacimiento !== b.nacimiento
      || (a.id ? a.id.canonico : null) !== (b.id ? b.id.canonico : null);

    // ---------- índice de duplicados ----------
    const titularDe = (id) => (pacientes.find((p) => p.id && p.id.canonico === id.canonico) || {}).codigo ?? null;
    const coincidencias = (p) => pacientes.filter((q) => nombreNormalizado(q) === nombreNormalizado(p) && q.nacimiento === p.nacimiento)
      .map((q) => q.codigo).sort();
    const visibles = (codigos, ver) => (ver ? codigos
      : codigos.map((c) => (pacientes.some((p) => p.codigo === c && p.protegida) ? RESERVADO : c)));

    // ---------- RevisionDeFicha ----------
    function paraAlta(hospital, d, puede, confirma, motivo) {
      const paciente = desdeDatos(hospital, d);
      if (paciente.protegida) {                                 // PoliticaConfidencialidad
        if (!puede.proteger) throw prohibido();
        if (motivo.trim() === '') throw invalido('MOTIVO_REQUERIDO');
      }
      if (paciente.id) {                                        // PoliticaDuplicados (R-02)
        const titular = titularDe(paciente.id);
        if (titular !== null) {
          const codigo = visibles([titular], puede.verProtegidos)[0];
          throw conflicto('PACIENTE_EXISTENTE', codigo === RESERVADO ? { codigo } : { codigo, identificador: enmascarado(paciente.id) });
        }
      }
      const parecidos = coincidencias(paciente);                 // R-03
      if (parecidos.length) {
        if (!confirma) throw conflicto('POSIBLE_DUPLICADO', { candidatos: visibles(parecidos, puede.verProtegidos) });
        if (!puede.confirmarDuplicado) throw prohibido();
        if (motivo.trim() === '') throw invalido('MOTIVO_REQUERIDO');
      }
      return paciente;
    }
    function paraEdicion(actual, d, puede, motivo) {
      if (reducidoPara(actual, puede.verProtegidos)) throw prohibido();   // no se edita lo que no se ve
      const nuevo = { ...desdeDatos(actual.hospital, d), codigo: actual.codigo, estado: actual.estado, version: actual.version };
      if (d.identidadProtegida === null) nuevo.protegida = actual.protegida;  // campo ausente = sin cambio
      if (actual.estado !== 'active' && !puede.editarRestringido) throw prohibido('REQUIERE_SUPERVISOR');
      const identidad = cambiaIdentidad(nuevo, actual);
      if (identidad && motivo === '') throw invalido('MOTIVO_REQUERIDO');
      if (actual.protegida !== nuevo.protegida) {
        if (!puede.proteger) throw prohibido();
        if (motivo === '') throw invalido('MOTIVO_REQUERIDO');
      }
      if (identidad && nuevo.id) {
        const titular = titularDe(nuevo.id);
        if (titular !== null && titular !== actual.codigo) throw conflicto('IDENTIFICADOR_EN_USO', { identificador: enmascarado(nuevo.id) });
      }
      return nuevo;
    }

    // ---------- Presentación: formulario ----------
    function cuerpoValido(cuerpo) {
      if (cuerpo === null || typeof cuerpo !== 'object') return json(400, { error: 'CUERPO_INVALIDO' });
      const e = {};
      for (const k of TEXTOS) if (k in cuerpo && cuerpo[k] !== null && typeof cuerpo[k] !== 'string') e[k] = 'Debe ser texto.';
      const prot = cuerpo.identidad_protegida;
      if (prot !== undefined && prot !== null && typeof prot !== 'boolean') e.identidad_protegida = 'Debe ser true o false.';
      const doc = cuerpo.documento;
      if (doc !== undefined && doc !== null && typeof doc !== 'object') e.documento = 'Debe ser un objeto {tipo, numero, pais} o null.';
      else if (doc) for (const k of ['tipo', 'numero', 'pais']) if (doc[k] !== undefined && doc[k] !== null && typeof doc[k] !== 'string') e['documento.' + k] = 'Debe ser texto.';
      return Object.keys(e).length ? json(400, { error: 'FORMATO_INVALIDO', campos: e }) : null;
    }
    // FormularioPaciente::datos (Presentación) + FormularioPaciente::aDatos (Aplicación)
    function aDatos(c) {
      const doc = c.documento && typeof c.documento === 'object' ? c.documento : {};
      const texto = (v) => (typeof v === 'string' || typeof v === 'number' ? String(v) : '');
      const opc = (v) => (typeof v === 'string' || typeof v === 'number' ? String(v) : null);
      return { nombres: texto(c.nombres), apellidos: texto(c.apellidos), nacimiento: texto(c.fecha_nacimiento), sexo: texto(c.sexo),
        telefono: opc(c.telefono), tipoDocumento: opc(doc.tipo), numeroDocumento: opc(doc.numero), paisDocumento: opc(doc.pais),
        identidadProtegida: typeof c.identidad_protegida === 'boolean' ? c.identidad_protegida : null };
    }
    const enError = (fn) => { try { return fn(); } catch (x) { if (x instanceof ErrorDominio) return json(x.estado, { error: x.error, ...x.datos }); throw x; } };

    // ---------- casos de uso ----------
    function registrar(s) {
      const malo = cuerpoValido(s.cuerpo);
      if (malo) return malo;
      const c = s.cuerpo;
      const confirma = c.confirmar_coincidencia ?? false;
      if (typeof confirma !== 'boolean') return json(400, { error: 'FORMATO_INVALIDO', campos: { confirmar_coincidencia: 'Debe ser true o false.' } });
      const clave = s.cabeceras['Idempotency-Key'] || '';
      if (clave !== '' && !/^[A-Za-z0-9-]{8,64}$/.test(clave)) return json(400, { error: 'FORMATO_INVALIDO', campos: { 'Idempotency-Key': 'De 8 a 64 letras, dígitos o guiones.' } });
      return enError(() => {
        exigir(s.actor, s.hospital, 'pacientes.registrar');
        const motivo = typeof c.motivo === 'string' ? c.motivo : '';
        const huella = JSON.stringify([aDatos(c), confirma, c.motivo ?? null]);
        const llave = `${s.hospital}|${s.actor}|${clave}`;
        if (clave !== '' && idempotencia.has(llave)) {
          const previa = idempotencia.get(llave);
          if (previa.huella !== huella) throw invalido('IDEMPOTENCY_KEY_REUTILIZADA');
          return json(201, previa.datos, { ETag: `"${previa.datos.version}"`, 'Idempotent-Replayed': 'true' });
        }
        const p = paraAlta(s.hospital, aDatos(c), capacidades(s.actor, s.hospital), confirma, motivo);
        p.codigo = 'P-' + String(++secuencia).padStart(6, '0');
        p.version = 1;
        pacientes.push(p);
        const datos = { codigo: p.codigo, version: 1, identidad_provisional: p.id === null };
        if (clave !== '') idempotencia.set(llave, { huella, datos });
        return json(201, datos, { Location: '/api/v1/pacientes/' + p.codigo, ETag: '"1"' });
      });
    }
    function buscar(s) {
      return enError(() => {
        exigir(s.actor, s.hospital, 'pacientes.buscar');
        const q = s.consulta;
        const ver = capacidades(s.actor, s.hospital).verProtegidos;
        let coincide;
        const codigo = String(q.codigo ?? '').trim().toUpperCase();
        if (codigo !== '') coincide = (p) => p.codigo === codigo;
        else {
          let doc;
          try { doc = crearIdentificador(q.tipo_documento, q.numero_documento, q.pais_documento); } catch (x) { throw invalido('DATOS_INVALIDOS', { campos: { documento: x.message } }); }
          if (doc) coincide = (p) => p.id !== null && p.id.canonico === doc.canonico;
          else {
            const nombre = normalizar(q.nombre ?? ''), nac = String(q.nacimiento ?? '').trim();
            if (contarLetras(nombre) < 3) throw invalido('CRITERIO_INSUFICIENTE', { ayuda: 'Use el código, el documento completo o al menos 3 letras del nombre.' });
            if (nac !== '' && !/^\d{4}(-\d{2}(-\d{2})?)?$/.test(nac)) throw invalido('DATOS_INVALIDOS', { campos: { nacimiento: 'Use AAAA, AAAA-MM o AAAA-MM-DD.' } });
            coincide = (p) => (ver || !p.protegida) && nombreNormalizado(p).includes(nombre) && (nac === '' || p.nacimiento.startsWith(nac));
          }
        }
        const clave = (p) => [normalizar(p.apellidos), nombreNormalizado(p), p.codigo];
        const orden = (a, b) => { const x = clave(a), y = clave(b); for (let i = 0; i < 3; i++) if (x[i] !== y[i]) return x[i] < y[i] ? -1 : 1; return 0; };
        const encontrados = pacientes.filter((p) => p.hospital === s.hospital && coincide(p)).sort(orden).slice(0, LIMITE);
        return json(200, { resultados: encontrados.map((p) => resumen(p, ver)), total: encontrados.length });
      });
    }
    function consultar(s, codigo) {
      return enError(() => {
        exigir(s.actor, s.hospital, 'pacientes.leer');
        const p = pacientes.find((x) => x.hospital === s.hospital && x.codigo === codigo.toUpperCase());
        if (!p) return json(404, { error: 'PACIENTE_NO_ENCONTRADO' });
        const etag = `"${p.version}"`;
        if ((s.cabeceras['If-None-Match'] || '') === etag) return json(304, {}, { ETag: etag });
        return json(200, { paciente: detalle(p, capacidades(s.actor, s.hospital).verProtegidos) }, { ETag: etag });
      });
    }
    function actualizar(s, codigo) {
      const ifMatch = s.cabeceras['If-Match'] || '';
      if (ifMatch === '') return json(428, { error: 'VERSION_REQUERIDA' });
      const m = ifMatch.match(/^(?:W\/)?"?(\d{1,9})"?$/);
      if (!m) return json(400, { error: 'IF_MATCH_INVALIDO' });
      const malo = cuerpoValido(s.cuerpo);
      if (malo) return malo;
      return enError(() => {
        exigir(s.actor, s.hospital, 'pacientes.editar');
        const i = pacientes.findIndex((x) => x.hospital === s.hospital && x.codigo === codigo.toUpperCase());
        if (i < 0) return json(404, { error: 'PACIENTE_NO_ENCONTRADO' });
        const actual = pacientes[i];
        const motivo = typeof s.cuerpo.motivo === 'string' ? s.cuerpo.motivo.trim() : '';
        const nuevo = paraEdicion(actual, aDatos(s.cuerpo), capacidades(s.actor, s.hospital), motivo);
        if (actual.version !== parseInt(m[1], 10)) throw conflicto('VERSION_DESACTUALIZADA', { version_vigente: actual.version });
        nuevo.version = actual.version + 1;
        pacientes[i] = nuevo;
        return json(200, { codigo: nuevo.codigo, version: nuevo.version }, { ETag: `"${nuevo.version}"` });
      });
    }

    // ---------- Kernel ----------
    const RUTAS = [
      ['POST', /^\/api\/v1\/pacientes$/, (s) => registrar(s)],
      ['GET', /^\/api\/v1\/pacientes$/, (s) => buscar(s)],
      ['GET', /^\/api\/v1\/pacientes\/([A-Za-z0-9-]{1,20})$/, (s, c) => consultar(s, c)],
      ['PUT', /^\/api\/v1\/pacientes\/([A-Za-z0-9-]{1,20})$/, (s, c) => actualizar(s, c)],
    ];
    // solicitar(metodo, url, {token, tenant, cuerpo, cabeceras}) → {estado, cuerpo, cabeceras}
    function solicitar(metodo, url, o = {}) {
      const [ruta, qs = ''] = url.split('?');
      const consulta = Object.fromEntries(new URLSearchParams(qs));
      let destino = null, otroMetodo = false;
      for (const [m, patron, fn] of RUTAS) {
        const r = ruta.match(patron);
        if (!r) continue;
        if (m === metodo) { destino = [fn, r.slice(1)]; break; }
        otroMetodo = true;
      }
      if (!destino) return otroMetodo ? json(405, { error: 'METODO_NO_PERMITIDO' }) : json(404, { error: 'RUTA_NO_ENCONTRADA' });
      if (!o.tenant) return json(400, { error: 'HOSPITAL_REQUERIDO' });
      const sesion = DATOS.tokens[o.token];
      if (!sesion) return json(401, { error: 'NO_AUTENTICADO' });
      if (sesion[1] !== o.tenant) return json(403, { error: 'TENANT_NO_COINCIDE' });
      const s = { actor: sesion[0], hospital: sesion[1], cuerpo: o.cuerpo ?? null, consulta, cabeceras: o.cabeceras || {} };
      return destino[0](s, ...destino[1]);
    }

    // Red simulada para el prototipo: latencia, sin conexión y «respuesta perdida» (el servidor SÍ procesa).
    function solicitarRed(metodo, url, o, red) {
      return new Promise((resolver, rechazar) => {
        if (red.sinConexion) { setTimeout(() => rechazar({ red: 'sin-conexion' }), 150); return; }
        setTimeout(() => {
          const r = solicitar(metodo, url, o);
          if (red.perderRespuesta) { red.perderRespuesta = false; rechazar({ red: 'sin-respuesta' }); return; }
          resolver(r);
        }, red.latenciaMs);
      });
    }

    for (const [token, cuerpo] of DATOS.semilla || []) solicitar('POST', '/api/v1/pacientes', { token, tenant: DATOS.tokens[token][1], cuerpo });
    return { solicitar, solicitarRed };
  }

  raiz.crearApi = crearApi;
})(typeof window !== 'undefined' ? window : globalThis);
