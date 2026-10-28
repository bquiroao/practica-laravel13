// API simulada del Micro-HIS de Prescripciones (semana 11; semana 12: «puede_justificar» en la previa, contrato 1.2.0). Reproduce, en el navegador, el kernel real:
// el mismo orden (ruta 404/405 → token 401 → tenant 400/403 → formulario 400 → caso de uso), las mismas reglas
// R-01..R-12 y los mismos códigos. Cada función dice de qué clase PHP es copia.
// No es la fuente de verdad: herramientas/paridad.cjs la compara con el kernel real paso a paso.
(function (raiz) {
  'use strict';

  function crearApi(D) {
    const BASE = '/api/v1/prescripciones';
    const MIN_JUSTIFICACION = 20; // PoliticaEmision
    const MIN_MOTIVO = 10;        // PoliticaAnulacion
    const FRECUENCIAS = [4, 6, 8, 12, 24];

    // ---------- adaptadores en memoria (FabricaAdaptadores::memoria) ----------
    const catalogo = new Map(D.medicamentos.map(([codigo, nombre, paCodigo, pa, grupo, unidad, max, vias, activo]) =>
      [codigo, { codigo, nombre, paCodigo, pa, grupo, unidad, max, vias, activo }]));
    const alergias = {};
    for (const [p, lista] of Object.entries(D.alergias)) alergias[p] = lista.map(aAlergia);
    const expediente = { caido: false };
    const prescripciones = [];   // RepositorioPrescripcionesEnMemoria
    const anulaciones = {};      // a lo sumo una por prescripción (prescription_voids)
    const trazas = [];           // RegistroAuditoriaEnMemoria
    let ultimoId = 0;
    // Los permisos son del servidor (RBAC): una copia propia, para poder retirar uno en mitad de la sesión (semana 12)
    const permisos = JSON.parse(JSON.stringify(D.permisos));

    function aAlergia([codigo, nombre, tipo, severidad, estado, reaccion]) { return { codigo, nombre, tipo, severidad, estado, reaccion }; }
    const alergeno = (a) => a.codigo ?? a.nombre;                                   // Alergia::alergeno
    const impideJustificar = (a) => a.severidad === 'life_threatening' || a.severidad === 'unknown';
    const normalizar = (t) => t.trim().toLowerCase().replace(/[áéíóúüñ]/g, (c) => ({ á: 'a', é: 'e', í: 'i', ó: 'o', ú: 'u', ü: 'u', ñ: 'n' }[c]));

    /** FuenteAlergiasEnMemoria. Una falla sale como excepción, NUNCA como lista vacía (R-07). */
    function alergiasDe(paciente) {
      if (expediente.caido) throw new Error('VERIFICACION_NO_DISPONIBLE'); // R-07
      const lista = alergias[paciente] || [];
      return { evaluado: lista.length > 0, alergias: lista };
    }
    /** CatalogoEnMemoria::grupoDelAlergeno: primero el código, después el nombre normalizado. */
    function grupoDelAlergeno(a) {
      for (const clave of [a.codigo !== null ? a.codigo.trim().toLowerCase() : null, a.nombre !== null ? normalizar(a.nombre) : null]) {
        if (clave !== null && clave in D.grupos) return D.grupos[clave];
      }
      return null;
    }

    // ---------- Dominio ----------
    /** ValidadorPrescripcion (R-01, R-02). */
    function validar(items, meds) {
      const campos = [], dosis = [];
      if (items.length === 0) campos.push('items');
      items.forEach((item, i) => {
        if (item === null || typeof item !== 'object') { campos.push(`items[${i}]`); return; }
        const m = meds.get(String(item.medicamento ?? '')) ?? null;
        if (m === null || !m.activo) { campos.push(`items[${i}].medicamento`); return; }
        const c = item.dosis, f = item.frecuencia_horas, d = item.dias;
        const esNumero = typeof c === 'number';
        if (!esNumero || c <= 0) campos.push(`items[${i}].dosis`);
        if (item.unidad !== m.unidad) campos.push(`items[${i}].unidad`);
        const frecuenciaValida = Number.isInteger(f) && FRECUENCIAS.includes(f);
        if (!frecuenciaValida) campos.push(`items[${i}].frecuencia_horas`);
        if (!m.vias.includes(item.via)) campos.push(`items[${i}].via`);
        if (!Number.isInteger(d) || d < 1 || d > 90) campos.push(`items[${i}].dias`);
        if (esNumero && c > 0 && frecuenciaValida && c * (24 / f) > m.max) dosis.push(`items[${i}]`);
      });
      return { campos, dosis };
    }
    /** VerificadorAlergias (R-04, R-05): directa por código o por nombre normalizado; cruzada por grupo. */
    function verificar(meds, exp, grupos) {
      const hallazgos = [];
      for (const m of meds) {
        for (const a of exp.alergias.filter((x) => x.estado === 'active')) {
          const directa = a.codigo !== null && a.codigo.trim() !== ''
            ? a.codigo.trim().toUpperCase() === m.paCodigo.toUpperCase()
            : normalizar(String(a.nombre)) === normalizar(m.pa);
          if (directa) hallazgos.push({ tipo: 'DIRECTA', medicamento: m.codigo, alergeno: alergeno(a), severidad: a.severidad, bloquea: true, reaccion: a.reaccion });
          else if ((grupos[alergeno(a)] ?? null) === m.grupo) {
            hallazgos.push({ tipo: 'CRUZADA', medicamento: m.codigo, alergeno: alergeno(a), severidad: a.severidad, bloquea: impideJustificar(a), reaccion: a.reaccion });
          }
        }
      }
      // ResultadoVerificacion::desde
      const estado = hallazgos.some((h) => h.bloquea) ? 'BLOQUEO' : hallazgos.length ? 'ADVERTENCIA' : 'SIN_HALLAZGOS';
      return { estado, hallazgos };
    }
    const codigoBloqueo = (v) => (v.hallazgos.some((h) => h.bloquea && h.tipo === 'DIRECTA') ? 'BLOQUEO_ALERGIA' : 'BLOQUEO_ALERGIA_CRUZADA');

    /** PoliticaEmision::decidir. El orden es parte de la regla: confirmación → bloqueo → advertencia. */
    function decidir(v, exp, s, tieneOverride) {
      if (!exp.evaluado && !s.confirma) return { tipo: 'REQUIERE_CONFIRMACION' };
      if (v.estado === 'BLOQUEO') return { tipo: 'BLOQUEADA' };
      const confirmacion = !exp.evaluado && s.confirma;
      if (v.estado !== 'ADVERTENCIA') return { tipo: 'PERMITIDA', justificacion: null, confirmacion };
      const texto = String(s.justificacion ?? '').trim();
      if ([...texto].length < MIN_JUSTIFICACION || !tieneOverride()) return { tipo: 'REQUIERE_JUSTIFICACION' };
      return { tipo: 'PERMITIDA', justificacion: texto, confirmacion };
    }

    // ---------- Aplicación ----------
    const permite = (usuario, permiso, hospital) => (permisos[`${usuario}|${hospital}`] || []).includes(permiso);
    const existeEnHospital = (p, h) => (D.pacientes[h] || []).includes(p);
    const traza = (accion, hospital, usuario, recurso, resultado) => trazas.push({ accion, hospital, usuario, recurso, resultado });
    const r = (estado, cuerpo, cabeceras = {}) => ({ estado, cuerpo, cabeceras });
    const error = (estado, codigo, extra = {}) => r(estado, { error: codigo, ...extra });

    /** EvaluacionClinica::evaluar: catálogo → completitud y dosis → alergias (siempre se consulta la fuente, R-03). */
    function evaluar(paciente, items) {
      const meds = new Map();
      for (const item of items) {
        const codigo = item !== null && typeof item === 'object' ? String(item.medicamento ?? '') : '';
        meds.set(codigo, catalogo.get(codigo) ?? null);
      }
      const errores = validar(items, meds);
      if (errores.campos.length) return error(422, 'DATOS_INVALIDOS', { campos: errores.campos });
      if (errores.dosis.length) return error(422, 'DOSIS_EXCEDE_MAXIMO', { items: errores.dosis });
      const exp = alergiasDe(paciente); // puede lanzar: fuente no disponible
      const grupos = {};
      for (const a of exp.alergias.filter((x) => x.estado === 'active')) {
        const g = grupoDelAlergeno(a);
        if (g !== null) grupos[alergeno(a)] = g;
      }
      return { exp, v: verificar([...meds.values()].filter(Boolean), exp, grupos) };
    }

    /** VerificarAlergiasPrevia (CU-02): informa; no guarda ni traza nada. */
    function previa(id, cuerpo) {
      if (!permite(id.usuario, 'prescripcion.emitir', id.hospital)) return error(403, 'PERMISO_DENEGADO');
      if (!existeEnHospital(cuerpo.paciente, id.hospital)) return error(404, 'NO_ENCONTRADO');
      let ev;
      try { ev = evaluar(cuerpo.paciente, cuerpo.items); } catch { return r(200, { codigo: 'VERIFICACION_NO_DISPONIBLE', hallazgos: [] }); }
      if (ev.estado) return ev;
      const codigo = ev.exp.evaluado ? ev.v.estado : 'CONFIRMACION_REQUERIDA';
      // Semana 12, PoliticaEmision::puedeJustificar: solo con ADVERTENCIA, y solo entonces se pregunta a RBAC
      const extra = codigo === 'ADVERTENCIA' ? { puede_justificar: permite(id.usuario, 'prescripcion.override', id.hospital) } : {};
      return r(200, { codigo, hallazgos: ev.v.hallazgos, ...extra });
    }

    /** EmitirPrescripcion (CU-01): permiso → idempotencia → tenant → evaluación → decisión → guardar con su traza. */
    function emitir(id, s) {
      if (!permite(id.usuario, 'prescripcion.emitir', id.hospital)) return error(403, 'PERMISO_DENEGADO');
      const previaClave = prescripciones.find((p) => p.hospital === id.hospital && p.clave === s.clave);
      if (previaClave) return reintento(previaClave, s);
      if (!existeEnHospital(s.paciente, id.hospital)) return error(404, 'NO_ENCONTRADO');
      let ev;
      try { ev = evaluar(s.paciente, s.items); } catch {
        traza('VERIFICACION_NO_DISPONIBLE', id.hospital, id.usuario, 'paciente:' + s.paciente, 'error');
        return error(503, 'VERIFICACION_NO_DISPONIBLE');
      }
      if (ev.estado) return ev;
      const d = decidir(ev.v, ev.exp, s, () => permite(id.usuario, 'prescripcion.override', id.hospital));
      if (d.tipo === 'BLOQUEADA') {
        traza('PRESCRIPCION_BLOQUEADA', id.hospital, id.usuario, 'paciente:' + s.paciente, 'denegado');
        return error(422, codigoBloqueo(ev.v), { hallazgos: ev.v.hallazgos });
      }
      if (d.tipo === 'REQUIERE_CONFIRMACION') return error(422, 'CONFIRMACION_REQUERIDA');
      if (d.tipo === 'REQUIERE_JUSTIFICACION') return error(409, 'JUSTIFICACION_REQUERIDA', { hallazgos: ev.v.hallazgos });
      // La prescripción, sus ítems y su traza: todo o nada (R-12). Prescripcion::__construct deja la dosis decimal.
      const p = {
        id: ++ultimoId, hospital: id.hospital, paciente: s.paciente, prescriptor: id.usuario, clave: s.clave, huella: s.huella,
        items: s.items.map((i) => ({ ...i, dosis: Number(i.dosis) })), verificacion: ev.v,
        alergenos: ev.exp.alergias.filter((a) => a.estado === 'active').map(alergeno),
        justificacion: d.justificacion, confirma: d.confirmacion, emitidaEn: ahora(),
      };
      prescripciones.push(p);
      traza('PRESCRIPCION_EMITIDA', id.hospital, id.usuario, 'prescripcion:' + p.id, 'exito');
      return r(201, { codigo: 'EMITIDA', id: p.id, verificacion: p.verificacion.estado }, { Location: `${BASE}/${p.id}` });
    }
    /** Mismo contenido → el mismo resultado (200, sin Location); otro contenido con la misma clave → 409 (R-11). */
    function reintento(p, s) {
      return p.huella === s.huella ? r(200, { codigo: 'YA_EMITIDA', id: p.id, verificacion: p.verificacion.estado }) : error(409, 'CLAVE_REUTILIZADA');
    }

    /** Prescripcion::aArreglo; «anulacion» solo si la hay (contrato 1.1.0). */
    function aArreglo(p) {
      const a = anulaciones[p.id];
      return {
        id: p.id, paciente: p.paciente, prescriptor: p.prescriptor, estado: a ? 'ANULADA' : 'EMITIDA', emitida_en: p.emitidaEn, items: p.items,
        verificacion: { resultado: p.verificacion.estado, hallazgos: p.verificacion.hallazgos, alergenos_evaluados: p.alergenos, justificacion: p.justificacion, confirma_sin_alergias: p.confirma },
        ...(a ? { anulacion: a } : {}),
      };
    }
    const porId = (hospital, n) => prescripciones.find((p) => p.id === n && p.hospital === hospital) ?? null;

    /** ConsultarPrescripcion (CU-09): otro hospital o inexistente, la misma respuesta (R-08). */
    function consultar(id, n) {
      if (!permite(id.usuario, 'prescripcion.consultar', id.hospital)) return error(403, 'PERMISO_DENEGADO');
      const p = porId(id.hospital, n);
      return p ? r(200, { codigo: 'PRESCRIPCION', ...aArreglo(p) }) : error(404, 'NO_ENCONTRADO');
    }

    /** AnularPrescripcion (CU-07): permiso → existe → PoliticaAnulacion (estado → motivo) → anulación + traza. */
    function anular(id, n, motivo) {
      if (!permite(id.usuario, 'prescripcion.anular', id.hospital)) return error(403, 'PERMISO_DENEGADO');
      const p = porId(id.hospital, n);
      if (!p) return error(404, 'NO_ENCONTRADO');
      if (anulaciones[n]) return error(409, 'ESTADO_NO_PERMITE_ANULAR');
      if ([...motivo.trim()].length < MIN_MOTIVO) return error(422, 'MOTIVO_REQUERIDO');
      const a = { motivo: motivo.trim(), anulada_por: id.usuario, anulada_en: ahora() };
      anulaciones[n] = a;
      traza('PRESCRIPCION_ANULADA', id.hospital, id.usuario, 'prescripcion:' + n, 'exito');
      return r(200, { codigo: 'ANULADA', id: n, anulacion: a });
    }

    // ---------- Presentación: FormularioPrescripcion (solo formato) ----------
    function leerCuerpo(texto) {
      let datos;
      try { datos = JSON.parse(texto); } catch { return { error: 'JSON_INVALIDO' }; }
      if (datos === null || typeof datos !== 'object' || typeof datos.paciente !== 'string' || !Array.isArray(datos.items)) return { error: 'CUERPO_INVALIDO' };
      return { datos };
    }
    const RUTAS = [
      ['POST', new RegExp(`^${BASE}/verificaciones$`), (id, sol) => {
        const c = leerCuerpo(sol.cuerpo);
        return c.error ? error(400, c.error) : previa(id, c.datos);
      }],
      ['POST', new RegExp(`^${BASE}$`), (id, sol) => {
        const clave = sol.cabeceras['idempotency-key'];
        if (clave === undefined || !/^[A-Za-z0-9._:-]{1,128}$/.test(clave)) return error(400, 'IDEMPOTENCY_KEY_REQUERIDA');
        const c = leerCuerpo(sol.cuerpo);
        if (c.error) return error(400, c.error);
        const justificacion = c.datos.justificacion ?? null, confirma = c.datos.confirma_sin_alergias ?? false;
        if ((justificacion !== null && typeof justificacion !== 'string') || typeof confirma !== 'boolean') return error(400, 'CUERPO_INVALIDO');
        // SolicitudEmision::huella: paciente, ítems, justificación y confirmación
        const huella = JSON.stringify([c.datos.paciente, c.datos.items, justificacion, confirma]);
        return emitir(id, { paciente: c.datos.paciente, items: c.datos.items, clave, justificacion, confirma, huella });
      }],
      ['GET', new RegExp(`^${BASE}/([1-9][0-9]{0,17})$`), (id, sol, n) => consultar(id, Number(n))],
      ['POST', new RegExp(`^${BASE}/([1-9][0-9]{0,17})/anulacion$`), (id, sol, n) => {
        let datos;
        try { datos = JSON.parse(sol.cuerpo); } catch { return error(400, 'JSON_INVALIDO'); }
        if (datos === null || typeof datos !== 'object' || typeof datos.motivo !== 'string') return error(400, 'CUERPO_INVALIDO');
        return anular(id, Number(n), datos.motivo);
      }],
    ];

    /** Kernel::manejar. sol = { cabeceras (en minúsculas), cuerpo (texto JSON) }. */
    function solicitar(metodo, ruta, sol) {
      let noPermitido = false;
      for (const [m, patron, manejador] of RUTAS) {
        const coincide = ruta.match(patron);
        if (!coincide) continue;
        if (m !== metodo) { noPermitido = true; continue; }
        const token = /^Bearer\s+(\S+)$/.exec(sol.cabeceras.authorization ?? '');
        const datos = token ? D.tokens[token[1]] : undefined;
        if (!datos) return error(401, 'NO_AUTENTICADO');
        const tenant = sol.cabeceras['x-tenant-id'];
        if (tenant === undefined || tenant === '') return error(400, 'TENANT_REQUERIDO');
        if (tenant !== datos[1]) return error(403, 'TENANT_NO_COINCIDE');
        return manejador({ usuario: datos[0], hospital: datos[1] }, { cabeceras: sol.cabeceras, cuerpo: sol.cuerpo ?? '' }, coincide[1]);
      }
      return noPermitido ? error(405, 'METODO_NO_PERMITIDO') : error(404, 'RUTA_NO_ENCONTRADA');
    }

    /**
     * La misma petición «por la red», con las fallas que el prototipo deja simular:
     * sin conexión (no llega), sesión vencida (401 sin procesar) y respuesta perdida (SE PROCESA y la respuesta no vuelve).
     */
    function solicitarRed(metodo, ruta, sol, red) {
      return new Promise((resolver, rechazar) => {
        if (red.sinConexion) { setTimeout(() => rechazar({ red: 'sin-conexion', procesada: false }), 150); return; }
        setTimeout(() => {
          if (red.sesionVencida) { resolver(error(401, 'NO_AUTENTICADO')); return; }
          const respuesta = solicitar(metodo, ruta, sol);
          if (red.perderRespuesta) { red.perderRespuesta = false; rechazar({ red: 'sin-conexion', procesada: true }); return; }
          resolver(respuesta);
        }, red.latenciaMs);
      });
    }

    function ahora() { return new Date().toISOString().replace(/\.\d{3}Z$/, '+00:00'); }

    return {
      solicitar, solicitarRed, expediente,
      /** Lo que haría el Expediente al registrar una alergia (FuenteAlergiasEnMemoria::agregar). */
      agregarAlergia: (paciente, a) => { (alergias[paciente] = alergias[paciente] || []).push(aAlergia(a)); },
      /** El módulo Expediente (simulado) para la franja de alergias: NO es parte de la API de Prescripciones. */
      expedienteDe: (paciente) => (alergias[paciente] || []).slice(),
      /** RBAC retira un permiso (simulación de la semana 12): la interfaz no se entera hasta que la API se lo dice. */
      retirarPermiso: (usuario, hospital, permiso) => { const k = `${usuario}|${hospital}`; permisos[k] = (permisos[k] || []).filter((p) => p !== permiso); },
      todas: () => prescripciones.map(aArreglo),
      trazas: () => trazas.slice(),
    };
  }

  raiz.crearApi = crearApi;
})(typeof window !== 'undefined' ? window : globalThis);
