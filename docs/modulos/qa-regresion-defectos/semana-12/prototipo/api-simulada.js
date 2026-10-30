// API simulada del módulo QA (semana 11). Responde con los MISMOS estados y códigos del contrato api/openapi.yaml 1.1.0 y
// aplica las reglas del servidor que la interfaz necesita ver: R-03 (críticos), R-05 (evidencia), R-06 (defecto),
// R-09/R-15 (GO / NO-GO, bloquea_go), R-12 (idempotencia). Datos ficticios. Vive en memoria: recargar la página la reinicia.
window.ApiSimulada = (function () {
  const LATENCIA = 300;
  const estado = {
    red: true,
    campanas: {
      1: { id: 1, nombre: 'Regresión H4', build: '1.4.0+abc1234', estado: 'EN_CURSO', funcionalidades: ['expediente'], umbral: 0.95,
        casos: {
          'TC-107': { titulo: 'Una nota firmada no se edita', version: 1, critico: true, requisitos: ['RF-07'],
            pasos: [['Actualizar el texto de la nota firmada', 'Se rechaza; se agrega una corrección']] },
          'TC-108': { titulo: 'Una alergia activa equivalente no se duplica', version: 1, critico: true, requisitos: ['RF-07', 'RF-08'],
            pasos: [['Registrar otra alergia activa a penicilina', 'Se rechaza por equivalencia']] },
        },
        intentos: [], decision: null },
    },
    defectos: [],
    claves: {},
  };
  const espera = () => new Promise((r) => setTimeout(r, LATENCIA));
  const respuesta = (codigoHttp, cuerpo) => ({ estado: codigoHttp, cuerpo });
  const ultimo = (c, caso) => [...c.intentos].reverse().find((i) => i.caso === caso) || null;
  const bloquea = (c, d) => d.estado !== 'CERRADO' && d.clasificado !== false &&
    (d.severidad === 'CRITICA' || (d.severidad === 'MAYOR' && c.casos[d.caso] && c.casos[d.caso].critico));

  // R-09 y R-15: las causas de un NO-GO. La previsión de la trazabilidad y el cierre usan ESTA función (semana 11: la
  // primera versión de la previsión solo contaba defectos y decía «1 causa» cuando el cierre daba 2).
  function causasDe(c) {
    const ultimos = Object.keys(c.casos).map((k) => ultimo(c, k)).filter((i) => i && i.resultado !== 'NO_APLICA');
    const tasa = ultimos.length ? ultimos.filter((i) => i.resultado === 'PASA').length / ultimos.length : 1;
    const causas = [];
    const criticos = estado.defectos.filter((d) => c.casos[d.caso] && d.severidad === 'CRITICA' && d.estado !== 'CERRADO').length;
    const mayores = estado.defectos.filter((d) => c.casos[d.caso] && c.casos[d.caso].critico && d.severidad === 'MAYOR' && d.estado !== 'CERRADO').length;
    if (criticos) causas.push(criticos === 1 ? '1 defecto crítico abierto' : criticos + ' defectos críticos abiertos');
    if (mayores) causas.push(mayores === 1 ? '1 defecto MAYOR abierto en un caso crítico de la matriz' : mayores + ' defectos MAYOR abiertos en casos críticos de la matriz');
    if (tasa < c.umbral) causas.push(`tasa de aprobados ${(100 * tasa).toFixed(1)} % menor que el umbral ${(100 * c.umbral).toFixed(1)} %`);
    return { tasa, causas };
  }

  async function llamar(fn) {
    await espera();
    if (!estado.red) throw new Error('SIN_CONEXION');
    return fn();
  }

  return {
    cortarRed() { estado.red = false; },
    restablecerRed() { estado.red = true; },
    hayRed() { return estado.red; },

    campanas: () => llamar(() => respuesta(200, Object.values(estado.campanas).map((c) => ({
      id: c.id, nombre: c.nombre, build: c.build, estado: c.estado, total: Object.keys(c.casos).length,
      conResultado: Object.keys(c.casos).filter((k) => ultimo(c, k)).length, decision: c.decision })))),

    caso: (id, caso) => llamar(() => {
      const c = estado.campanas[id];
      if (!c || !c.casos[caso]) return respuesta(404, { error: 'NO_ENCONTRADO' });
      const corregido = estado.defectos.find((d) => d.caso === caso && d.estado === 'CORREGIDO');
      return respuesta(200, Object.assign({ id: caso, build: c.build, campana: c.nombre, corregido: corregido ? corregido.id : null }, c.casos[caso]));
    }),

    // POST /campanas/{id}/resultados con Idempotency-Key
    registrar: (id, clave, datos) => llamar(() => {
      const c = estado.campanas[id];
      const huella = JSON.stringify(datos);
      if (estado.claves[clave]) {
        return estado.claves[clave].huella === huella ? respuesta(200, Object.assign({ codigo: 'RESULTADO_YA_REGISTRADO' }, estado.claves[clave].datos))
          : respuesta(409, { error: 'CLAVE_REUTILIZADA' });
      }
      if (!c) return respuesta(404, { error: 'NO_ENCONTRADO' });
      if (c.estado !== 'EN_CURSO' || !c.casos[datos.caso]) return respuesta(409, { error: 'CAMPANA_NO_ADMITE_RESULTADOS' });
      const exige = datos.resultado === 'FALLA' || datos.resultado === 'BLOQUEADO';
      if (exige && !(datos.evidencias || []).length) return respuesta(422, { error: 'EVIDENCIA_REQUERIDA' });
      if (datos.resultado === 'NO_APLICA' && !(datos.motivo || '').trim()) return respuesta(422, { error: 'MOTIVO_REQUERIDO' });
      const verificado = datos.verifica_defecto ? estado.defectos.find((d) => d.id === datos.verifica_defecto) : null;
      let defecto = null;
      if (datos.resultado === 'FALLA' && !verificado) {
        if (!datos.defecto || !(datos.defecto.titulo || '').trim()) return respuesta(422, { error: 'DATOS_INVALIDOS', detalle: ['defecto.titulo'] });
        defecto = { id: estado.defectos.length + 12, caso: datos.caso, titulo: datos.defecto.titulo, severidad: datos.defecto.severidad, estado: 'ABIERTO', clasificado: true };
        estado.defectos.push(defecto);
      }
      if (verificado) verificado.estado = datos.resultado === 'PASA' ? 'CERRADO' : 'REABIERTO';
      const numero = c.intentos.filter((i) => i.caso === datos.caso).length + 1;
      const intento = { id: Object.keys(estado.claves).length + 1, caso: datos.caso, numero, resultado: datos.resultado, evidencias: (datos.evidencias || []).map((e) => ({ tipo: e.tipo, clasificacion: e.clasificacion, huella: e.huella })), defecto: defecto ? defecto.id : null };
      c.intentos.push(intento);
      const cuerpo = { intento: intento.id, caso: datos.caso, numero, resultado: datos.resultado, defecto: intento.defecto };
      estado.claves[clave] = { huella, datos: cuerpo };
      return respuesta(201, Object.assign({ codigo: 'RESULTADO_REGISTRADO' }, cuerpo));
    }),

    trazabilidad: (id) => llamar(() => {
      const c = estado.campanas[id];
      if (!c) return respuesta(404, { error: 'NO_ENCONTRADO' });
      const casos = Object.entries(c.casos).map(([k, v]) => {
        const u = ultimo(c, k);
        return { caso: k, critico: v.critico, requisitos: v.requisitos, ultimo: u ? u.resultado : 'PENDIENTE', numero: u ? u.numero : 0,
          evidencias: u ? u.evidencias : [], defectos: estado.defectos.filter((d) => d.caso === k).map((d) => Object.assign({ bloquea_go: bloquea(c, d) }, d)) };
      });
      const aplicables = casos.filter((x) => x.ultimo !== 'PENDIENTE' && x.ultimo !== 'NO_APLICA');
      const tasa = aplicables.length ? aplicables.filter((x) => x.ultimo === 'PASA').length / aplicables.length : 1;
      const bloqueantes = estado.defectos.filter((d) => c.casos[d.caso] && bloquea(c, d));
      const requisitos = {};
      casos.forEach((x) => x.requisitos.forEach((rf) => { (requisitos[rf] = requisitos[rf] || []).push(x.caso); }));
      return respuesta(200, { codigo: 'TRAZABILIDAD', campana: id, nombre: c.nombre, build: c.build, estado: c.estado, decision: c.decision, casos, requisitos,
        prevision: Object.assign(causasDe(c), { bloqueantes: bloqueantes.length, pendientes: casos.filter((x) => x.ultimo === 'PENDIENTE').map((x) => x.caso) }) });
    }),

    // POST /campanas/{id}/cierre (R-09, R-15)
    cerrar: (id) => llamar(() => {
      const c = estado.campanas[id];
      if (c.estado !== 'EN_CURSO') return respuesta(409, { error: 'ESTADO_NO_PERMITE_CERRAR' });
      const pendientes = Object.keys(c.casos).filter((k) => !ultimo(c, k));
      if (pendientes.length) return respuesta(409, { error: 'CASOS_PENDIENTES', detalle: pendientes });
      const { tasa, causas } = causasDe(c);
      c.estado = 'CERRADA';
      c.decision = { valor: causas.length ? 'NO_GO' : 'GO', tasa, causas };
      return respuesta(200, { codigo: 'CAMPANA_CERRADA', id, decision: c.decision.valor, tasa_aprobados: tasa, causas });
    }),
  };
})();
