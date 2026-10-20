// Semana 11 · Servidor simulado en memoria con las reglas del kernel del Micro-HIS: sesión (401) → permiso (403 con referencia)
// → validación (422) → estado de la nota (404/409), con los mismos códigos de error y forma de JSON. Se reinicia al recargar.
(function () {
  const TOKENS = { 'token-mendez': ['dra-mendez', 'medico'], 'token-lopez': ['dr-lopez', 'medico'], 'token-ana': ['enf-ana', 'enfermeria'] };
  const PERMISOS = { medico: ['registrar', 'firmar', 'consultar'], enfermeria: ['consultar'] };
  const SECCIONES = ['subjetivo', 'objetivo', 'analisis', 'plan'];
  const notas = [], claves = {}; let perder = false;
  const error = (estado, code, message) => ({ estado, cuerpo: { error: { code, message } }, ref: estado === 403 ? crypto.randomUUID() : null });
  const ok = (estado, cuerpo) => ({ estado, cuerpo: structuredClone(cuerpo) });
  function crear(autor, c) {
    const nota = { clinical_note_id: `id-${String(notas.length + 1).padStart(3, '0')}`, medical_record_id: 'exp-carlos', care_episode_id: 'epi-100',
      author_user_ref_id: autor, status: 'draft', version_no: 1, signed_at: null, supersedes_id: null, created_at: new Date().toISOString(),
      soap: Object.fromEntries(SECCIONES.map((s) => [s, c[s].trim()])), diagnoses: [{ ...c.diagnostico }] };
    notas.push(nota); return nota;
  }
  function atender(metodo, ruta, token, c = {}, clave = null) {
    if (!TOKENS[token]) return error(401, 'token_invalido', 'La sesión no es válida.');
    const [usuario, rol] = TOKENS[token];
    const [, id, firma] = ruta.match(/^\/notas-soap\/([\w-]+)(\/firma)?$/) || [];
    const permiso = ruta === '/notas-soap' ? 'registrar' : firma ? 'firmar' : 'consultar';
    if (!PERMISOS[rol].includes(permiso)) return error(403, 'acceso_denegado', 'No tiene permiso para esta operación.');
    if (metodo === 'GET' && ruta === '/expedientes/exp-carlos/notas-soap') {
      return ok(200, { medical_record_id: 'exp-carlos', items: notas.map(({ soap, diagnoses, ...r }) => ({ ...r, diagnosis_codes: diagnoses.map((d) => d.code) })) }); // D-2
    }
    if (metodo === 'POST' && ruta === '/notas-soap') {
      if (clave && claves[clave]) return ok(201, claves[clave]); // misma Idempotency-Key: misma nota, no se duplica
      const vacia = SECCIONES.find((s) => !(c[s] || '').trim());
      if (vacia) return error(422, 'nota_incompleta', `La sección "${vacia}" no puede estar vacía.`);
      if (!/^[A-Z][0-9]{2}(\.[0-9]{1,2})?$/.test(c.diagnostico?.code || '')) return error(422, 'diagnostico_invalido', 'El diagnóstico no tiene formato CIE-10.');
      const nueva = crear(usuario, c);
      if (clave) claves[clave] = nueva;
      return ok(201, nueva);
    }
    const nota = notas.find((n) => n.clinical_note_id === id);
    if (!id || (metodo === 'POST') !== Boolean(firma)) return error(404, 'ruta_no_encontrada', 'La ruta no existe.');
    if (!nota) return error(404, 'nota_no_encontrada', 'La nota no existe.');
    if (!firma) return ok(200, nota);
    if (nota.author_user_ref_id !== usuario) return error(403, 'acceso_denegado', 'No tiene permiso para esta operación.'); // solo el autor firma
    if (nota.status === 'signed') return error(409, 'nota_firmada', 'La nota ya está firmada.');
    Object.assign(nota, { status: 'signed', signed_at: new Date().toISOString() });
    return ok(200, nota);
  }
  crear('dr-lopez', { subjetivo: 'Fiebre de 1 día', objetivo: 'T 38.1 °C', analisis: 'Fiebre sin foco claro', plan: 'Hemograma y control',
    diagnostico: { code: 'R50.9', description: 'Fiebre no especificada', type: 'primary' } });
  Object.assign(notas[0], { status: 'signed', created_at: '2026-10-12T17:40:00-06:00', signed_at: '2026-10-12T17:45:00-06:00' });
  window.API = {
    // Responde a los 400 ms. «Perder la próxima respuesta»: el servidor SÍ procesa, pero la respuesta no llega.
    async pedir(metodo, ruta, token, cuerpo, clave) {
      const r = atender(metodo, ruta, token, cuerpo, clave);
      await new Promise((listo) => setTimeout(listo, 400));
      if (perder) { perder = false; throw new Error('sin respuesta'); }
      return r;
    },
    perderProximaRespuesta: () => { perder = true; },
    totalNotas: () => notas.length,
  };
})();
