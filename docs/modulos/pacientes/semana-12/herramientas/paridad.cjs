// Semana 12 (contrato 1.2.0) · Paridad entre la API simulada del prototipo y el kernel real del Micro-HIS.
// Ejecuta paridad-secuencia.json en los dos y compara la huella de cada respuesta.
// Uso: node herramientas/paridad.cjs   (desde docs/modulos/pacientes/semana-11). Termina con 1 si hay diferencias.
const path = require('path');
const { execFileSync } = require('child_process');
const fs = require('fs');
const vm = require('vm');

// Los scripts del prototipo son de navegador: se cargan tal cual en un contexto aislado.
const contexto = vm.createContext({ URLSearchParams, setTimeout });
for (const f of ['datos.js', 'api-simulada.js']) vm.runInContext(fs.readFileSync(path.join(__dirname, '..', 'prototipo', f), 'utf8'), contexto);
const { DATOS, crearApi } = contexto;

const pasos = require('./paridad-secuencia.json');
const real = JSON.parse(execFileSync('php', [path.join(__dirname, 'paridad.php')], { encoding: 'utf8', input: JSON.stringify(DATOS.semilla) }));

// El mismo cálculo que huella() en paridad.php
const huella = (r) => {
  const c = r.cuerpo, p = c.paciente ?? (c.resultados ? c.resultados[0] ?? null : null);
  return {
    estado: r.estado, error: c.error ?? null, codigo: c.codigo ?? null,
    version: c.version ?? (c.paciente ? c.paciente.version : null), total: c.total ?? null,
    campos: c.campos ? Object.keys(c.campos).sort() : null, candidatos: c.candidatos ?? null,
    identificador: c.identificador ?? (p ? p.identificador ?? null : null), nombre: p ? p.nombre ?? null : null,
    telefono: c.paciente ? c.paciente.telefono : null,
    nombres: c.paciente && 'nombres' in c.paciente ? [c.paciente.nombres, c.paciente.apellidos] : 'ausente', repetida: r.cabeceras['Idempotent-Replayed'] === 'true',
  };
};

const api = crearApi(DATOS);
const hospitalDe = { 'token-admision-norte': 'hosp-norte' };
const simulada = pasos.map((p) => huella(api.solicitar(p.metodo, p.ruta, {
  token: p.token, tenant: p.tenant ?? hospitalDe[p.token] ?? 'hosp-oriente', cuerpo: p.cuerpo ?? null, cabeceras: p.cabeceras ?? {},
})));

const resumen = (h) => [h.estado, h.error || h.codigo || '', h.total !== null ? `total=${h.total}` : '', h.version ? `v${h.version}` : '',
  h.candidatos ? `[${h.candidatos.join(',')}]` : '', h.repetida ? 'repetida' : ''].filter(Boolean).join(' ');
let diferencias = 0;
pasos.forEach((p, i) => {
  const igual = JSON.stringify(real[i]) === JSON.stringify(simulada[i]);
  if (!igual) diferencias++;
  console.log(`${igual ? 'IGUAL  ' : 'DIFIERE'} ${String(i + 1).padStart(2)}. ${p.paso.padEnd(52)} PHP: ${resumen(real[i])}${igual ? '' : '\n           JS:  ' + resumen(simulada[i]) + '\n           PHP: ' + JSON.stringify(real[i]) + '\n           JS:  ' + JSON.stringify(simulada[i])}`);
});
console.log(`\nPasos: ${pasos.length}  Iguales: ${pasos.length - diferencias}  Difieren: ${diferencias}`);
process.exit(diferencias ? 1 : 0);
