// Semana 11 (+ semana 12: permisos efectivos y eventos en la bitácora) · Paridad entre la API simulada del prototipo y el kernel real del Micro-HIS.
// Ejecuta paridad-secuencia.json en los dos y compara la huella de cada respuesta.
// Uso: node herramientas/paridad.cjs   (desde docs/modulos/rbac/semana-11). Termina con 1 si hay diferencias.
const path = require('path');
const { execFileSync } = require('child_process');
const fs = require('fs');
const vm = require('vm');

// El repo es "type": "module"; los scripts del prototipo son de navegador. Se cargan tal cual en un contexto aislado.
const contexto = vm.createContext({ crypto: globalThis.crypto, URLSearchParams, setTimeout });
for (const f of ['datos.js', 'api-simulada.js']) vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'prototipo', f), 'utf8'), contexto);
const { DATOS, crearApi } = contexto;

const pasos = require('./paridad-secuencia.json');
const real = JSON.parse(execFileSync('php', [path.join(__dirname, 'paridad.php')], { encoding: 'utf8' }));

let n = 0;
const api = crearApi(DATOS, { generarId: () => `corr-${String(++n).padStart(4, '0')}` });
const guardadas = {};
const simulada = pasos.map((p) => {
  const ruta = p.ruta.replace(/\{(\w+)\}/g, (_, k) => guardadas[k] || 'sin-ref');
  const r = api.solicitar(p.metodo, ruta, { token: p.token, tenant: p.tenant ?? 'hosp-oriente', cuerpo: p.cuerpo ?? null });
  const c = r.cuerpo;
  if (p.guardar && typeof c.correlation_id === 'string') guardadas[p.guardar] = c.correlation_id;
  return {
    estado: r.estado, codigo: c.codigo ?? null, decision: c.decision ?? null, campos: c.campos ?? null,
    con_referencia: typeof c.correlation_id === 'string', tipo: c.tipo ?? null, actor: c.actor ?? null,
    motivo: c.datos?.motivo ?? null, permiso: c.datos?.permiso ?? null,
    permisos: c.permisos ?? null, eventos_en_bitacora: api.eventos().length,
  };
});

const resumen = (h) => [h.estado, h.codigo || h.decision || h.tipo || '', h.motivo || '', h.campos ? h.campos.join(',') : '', h.con_referencia ? 'ref' : '', h.permisos ? `sí=${Object.values(h.permisos).filter(Boolean).length}/${Object.keys(h.permisos).length}` : '', `ev=${h.eventos_en_bitacora}`]
  .filter(Boolean).join(' ');
let diferencias = 0;
pasos.forEach((p, i) => {
  const igual = JSON.stringify(real[i]) === JSON.stringify(simulada[i]);
  if (!igual) diferencias++;
  console.log(`${igual ? 'IGUAL' : 'DIFIERE'}  ${String(i + 1).padStart(2)}. ${p.paso.padEnd(52)} PHP: ${resumen(real[i]).padEnd(40)}${igual ? '' : ' JS: ' + resumen(simulada[i])}`);
});
console.log(`\nPasos: ${pasos.length}  Iguales: ${pasos.length - diferencias}  Difieren: ${diferencias}`);
process.exit(diferencias ? 1 : 0);
