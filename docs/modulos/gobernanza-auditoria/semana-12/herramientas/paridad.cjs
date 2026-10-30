// Semana 11 (semana 12: + filtro por recurso) · Paridad entre la API simulada del prototipo y el kernel REAL del Micro-HIS.
// Registra la misma semilla, «borra» la misma fila y ejecuta paridad-secuencia.json en los dos; compara la huella de cada respuesta.
// Uso: node herramientas/paridad.cjs   (desde docs/modulos/gobernanza-auditoria/semana-11). Termina con 1 si hay diferencias.
const path = require('path');
const fs = require('fs');
const vm = require('vm');
const { execFileSync } = require('child_process');

// Los scripts del prototipo son de navegador: se cargan TAL CUAL en un contexto aislado.
const contexto = vm.createContext({ URLSearchParams, setTimeout, btoa, atob, Date, Math, JSON });
for (const f of ['datos.js', 'api-simulada.js']) vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'prototipo', f), 'utf8'), contexto);
const { DATOS, crearApi } = contexto;

const ahora = Date.now();
const hoy = DATOS.hoyLocal(ahora);
const manana = new Date(Date.parse(hoy + 'T00:00:00Z') + 86400000).toISOString().slice(0, 10);
const semilla = DATOS.semilla(ahora);
const pasos = require('./paridad-secuencia.json');

const real = JSON.parse(execFileSync('php', [path.join(__dirname, 'paridad.php')],
  { encoding: 'utf8', input: JSON.stringify({ semilla, borrado: DATOS.borrado, hoy }), stdio: ['pipe', 'pipe', 'ignore'] }));

// El mismo cálculo que huella() en paridad.php
const huella = (estado, c) => {
  const movs = c.movimientos ?? null, m = c.movimiento ?? null;
  return {
    estado, error: c.error ?? null, campos: c.campos ? Object.keys(c.campos) : null,
    ids: movs ? movs.map((x) => x.id) : null,
    acciones: movs ? [...new Set(movs.map((x) => x.accion))] : null,
    corregidos: movs ? movs.filter((x) => x.corregido_por !== null).map((x) => `${x.id}→${x.corregido_por}`) : null,
    cursor: 'cursor_siguiente' in c ? c.cursor_siguiente !== null : null,
    detalle: m ? [m.id, m.usuario, m.corrige_a, m.motivo_correccion, m.corregido_por, m.secuencia] : null,
    verificacion: c.estado !== undefined ? [c.estado, c.primera_secuencia ?? c.esperada_hasta ?? null, c.verificados] : null,
  };
};

const api = crearApi(DATOS);
const semillaSim = semilla.map((s) => { const r = api.registrarSemilla(s); return [r.estado, r.cuerpo.id, r.cuerpo.secuencia, null, null]; });
api.borrarDirecto(DATOS.borrado);
let cursor = '';
const simulada = pasos.map((p) => {
  const ruta = p.ruta.replace('{HOY}', hoy).replace('{HOY}', hoy).replace('{MANANA}', manana).replace('{CURSOR}', encodeURIComponent(cursor));
  const r = api.solicitar(p.metodo, ruta, { token: p.token, tenant: p.tenant ?? 'hosp-oriente', cuerpo: p.cuerpo ?? null });
  if (r.cuerpo.cursor_siguiente) cursor = r.cuerpo.cursor_siguiente;
  return huella(r.estado, r.cuerpo);
});

let diferencias = 0;
const igualSemilla = JSON.stringify(real.semilla) === JSON.stringify(semillaSim);
if (!igualSemilla) diferencias++;
console.log(`${igualSemilla ? 'IGUAL' : 'DISTINTO'}  Semilla: ${semilla.length} registros por POST en el kernel real (estado, id y secuencia por registro)`);
if (!igualSemilla) real.semilla.forEach((h, i) => { if (JSON.stringify(h) !== JSON.stringify(semillaSim[i])) console.log(`         #${i + 1} real=${JSON.stringify(h)} sim=${JSON.stringify(semillaSim[i])}`); });

const resumen = (h) => [h.estado, h.error ?? '', h.campos ? `campos=[${h.campos}]` : '', h.ids ? `${h.ids.length} filas` : '',
  h.corregidos && h.corregidos.length ? `corregidos=${h.corregidos.join(',')}` : '', h.cursor ? 'hay más' : '',
  h.detalle ? `#${h.detalle[0]} ${h.detalle[1]}${h.detalle[2] ? ' corrige a #' + h.detalle[2] : ''}${h.detalle[4] ? ' corregido por #' + h.detalle[4] : ''}` : '',
  h.verificacion ? h.verificacion.join(' ') : ''].filter((x) => x !== '').join(' · ');
pasos.forEach((p, i) => {
  const igual = JSON.stringify(real.pasos[i]) === JSON.stringify(simulada[i]);
  if (!igual) diferencias++;
  console.log(`${igual ? 'IGUAL' : 'DISTINTO'}  ${String(i + 1).padStart(2)}. ${p.paso}`);
  console.log(`          ${resumen(real.pasos[i])}`);
  if (!igual) console.log(`          real=${JSON.stringify(real.pasos[i])}\n          sim =${JSON.stringify(simulada[i])}`);
});
console.log(`\nPasos: ${pasos.length} + semilla  Diferencias: ${diferencias}`);
process.exit(diferencias ? 1 : 0);
