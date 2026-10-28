// Semana 11 · Paridad entre la API simulada del prototipo y el kernel REAL del Micro-HIS de Prescripciones.
// 1) Los datos y permisos del prototipo deben ser los de datos-demo.php y config.php.
// 2) paridad-secuencia.json se ejecuta en los dos; se compara una huella de cada respuesta.
// 3) Al final se comparan las prescripciones guardadas y las trazas.
// Uso: node herramientas/paridad.cjs [api-alternativa.js]   (desde docs/modulos/prescripciones-alergias/semana-11)
// El argumento opcional reemplaza api-simulada.js: así se corre el control con un defecto inyectado. Termina con 1 si hay diferencias.
const path = require('path');
const fs = require('fs');
const vm = require('vm');
const { execFileSync } = require('child_process');

const proto = path.join(__dirname, '..', 'prototipo');
const archivoApi = process.argv[2] ? path.resolve(process.argv[2]) : path.join(proto, 'api-simulada.js');
// Los scripts del prototipo son de navegador: se cargan TAL CUAL en un contexto aislado.
const contexto = vm.createContext({ setTimeout, Date, Math, JSON, Promise });
vm.runInContext(fs.readFileSync(path.join(proto, 'datos.js'), 'utf8'), contexto);
vm.runInContext(fs.readFileSync(archivoApi, 'utf8'), contexto);
const { DATOS, crearApi } = contexto;
const pasos = require('./paridad-secuencia.json');

const real = JSON.parse(execFileSync('php', [path.join(__dirname, 'paridad.php')],
  { encoding: 'utf8', input: JSON.stringify(DATOS.alergiaNueva), stdio: ['pipe', 'pipe', 'inherit'] }));

// El mismo cálculo que huella() en paridad.php
function huella(estado, c, location) {
  const hallazgo = (h) => [h.tipo, h.medicamento, h.alergeno, h.severidad, h.bloquea ? 'bloquea' : 'no-bloquea', h.reaccion ?? '-'].join(' ');
  const item = (i) => [i.medicamento, String(Number(i.dosis)), i.unidad, i.frecuencia_horas, i.via, i.dias].join(' ');
  const v = c.verificacion !== null && typeof c.verificacion === 'object' ? c.verificacion : null;
  return {
    estado, codigo: c.error ?? c.codigo ?? null, location,
    campos: c.campos ?? null,
    items_dosis: Array.isArray(c.items) && typeof c.items[0] === 'string' ? c.items : null,
    hallazgos: c.hallazgos ? c.hallazgos.map(hallazgo) : null,
    id: c.id ?? null,
    verificacion: typeof c.verificacion === 'string' ? c.verificacion : null,
    detalle: v === null ? null : [c.estado, c.paciente, c.prescriptor, c.items.map(item), v.resultado, v.hallazgos.map(hallazgo),
      v.alergenos_evaluados, v.justificacion, v.confirma_sin_alergias],
    anulacion: c.anulacion ? [c.anulacion.motivo, c.anulacion.anulada_por] : null,
  };
}

const api = crearApi(DATOS);
const simulada = pasos.map((p) => {
  if (p.antes && p.antes.agregar_alergia) api.agregarAlergia(DATOS.alergiaNueva.paciente, DATOS.alergiaNueva.alergia);
  if (p.antes && p.antes.expediente) api.expediente.caido = p.antes.expediente === 'caido';
  const cabeceras = {};
  if (p.token !== '') cabeceras.authorization = 'Bearer ' + p.token;
  const tenant = p.tenant ?? 'hosp-norte';
  if (tenant !== '') cabeceras['x-tenant-id'] = tenant;
  if (p.clave) cabeceras['idempotency-key'] = p.clave;
  const cuerpo = p.cuerpoTexto ?? (p.cuerpo ? JSON.stringify(p.cuerpo) : '');
  const r = api.solicitar(p.metodo, '/api/v1/prescripciones' + p.ruta, { cabeceras, cuerpo });
  return huella(r.estado, r.cuerpo, 'Location' in r.cabeceras);
});
const finalSim = {
  prescripciones: api.todas().map((p) => [p.id, p.paciente, p.estado]),
  trazas: api.trazas().map((t) => `${t.accion} ${t.usuario} ${t.recurso} ${t.resultado}`),
};

const igual = (a, b) => JSON.stringify(a) === JSON.stringify(b);
let diferencias = 0;
const datosSim = { pacientes: DATOS.pacientes, alergias: DATOS.alergias, medicamentos: DATOS.medicamentos, grupos: DATOS.grupos, tokens: DATOS.tokens, permisos: DATOS.permisos };
for (const k of Object.keys(datosSim)) {
  const ok = igual(JSON.parse(JSON.stringify(real.datos[k])), JSON.parse(JSON.stringify(datosSim[k])));
  if (!ok) diferencias++;
  console.log(`${ok ? 'IGUAL' : 'DISTINTO'}  Datos: ${k} (datos-demo.php / config.php ↔ datos.js)`);
}

const resumen = (h) => [h.estado, h.codigo ?? '', h.location ? 'Location' : '', h.id !== null ? `#${h.id}` : '', h.verificacion ?? '',
  h.campos ? `campos=[${h.campos.join(', ')}]` : '', h.items_dosis ? `dosis=[${h.items_dosis}]` : '',
  h.hallazgos && h.hallazgos.length ? `hallazgos=[${h.hallazgos.join('; ')}]` : '',
  h.detalle ? `${h.detalle[0]} ${h.detalle[1]} por ${h.detalle[2]} · ${h.detalle[4]}${h.detalle[7] ? ' · justificada' : ''}${h.detalle[8] ? ' · confirmó sin alergias' : ''}` : '',
  h.anulacion ? `anulada por ${h.anulacion[1]}: «${h.anulacion[0]}»` : ''].filter((x) => x !== '').join(' · ');
pasos.forEach((p, i) => {
  const ok = igual(real.pasos[i], simulada[i]);
  if (!ok) diferencias++;
  console.log(`${ok ? 'IGUAL' : 'DISTINTO'}  ${String(i + 1).padStart(2)}. ${p.paso}`);
  console.log(`          ${resumen(real.pasos[i])}`);
  if (!ok) console.log(`          real=${JSON.stringify(real.pasos[i])}\n          sim =${JSON.stringify(simulada[i])}`);
});
for (const k of ['prescripciones', 'trazas']) {
  const ok = igual(real.final[k], finalSim[k]);
  if (!ok) diferencias++;
  console.log(`${ok ? 'IGUAL' : 'DISTINTO'}  Final: ${k} (${real.final[k].length} en el kernel real)`);
  (ok ? real.final[k] : []).forEach((x) => console.log(`          ${Array.isArray(x) ? `#${x[0]} ${x[1]} ${x[2]}` : x}`));
  if (!ok) console.log(`          real=${JSON.stringify(real.final[k])}\n          sim =${JSON.stringify(finalSim[k])}`);
}
console.log(`\nDatos: ${Object.keys(datosSim).length}  Pasos: ${pasos.length}  Final: 2  Diferencias: ${diferencias}`);
process.exit(diferencias ? 1 : 0);
