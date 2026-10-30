// Evidencia SRP: (1) paridad antes/después en 7 escenarios (CA-01..CA-07); (2) las reglas de
// estados y los textos se prueban solos, sin red ni sesión; (3) ningún texto muestra detalles técnicos.
import { cargarTarjetaAntes } from './antes.mjs';
import { cargarTarjeta, crearClienteTarjetas, estadoDeRespuesta, textoDeEstado } from './despues.mjs';

let fallos = 0;
const verificar = (ok, texto) => { if (!ok) fallos++; console.log(`${ok ? 'OK   ' : 'FALLA'} ${texto}`); };
const tarjeta = { id: 'citas-hoy', titulo: 'las citas de hoy', unidad: 'citas hoy', vacio: 'No hay citas hoy. Puedes agendar una.' };
const nuncaResponde = () => new Promise(() => {});

const escenarios = [   // [CA, descripción, respuesta simulada de la API, estado esperado]
  ['CA-01', 'responde 200 con 5 citas', () => Promise.resolve({ status: 200, total: 5 }), 'resumen'],
  ['CA-02', 'responde 200 con 0 citas', () => Promise.resolve({ status: 200, total: 0 }), 'vacio'],
  ['CA-03', 'responde 500', () => Promise.resolve({ status: 500 }), 'error'],
  ['CA-03', 'falla la red', () => Promise.reject(new Error('ECONNRESET')), 'error'],
  ['CA-04', 'no responde en el límite de tiempo', nuncaResponde, 'error'],
  ['CA-05', 'responde 401', () => Promise.resolve({ status: 401 }), 'sesion_expirada'],
  ['CA-06', 'responde 403', () => Promise.resolve({ status: 403 }), 'oculta'],
];
for (const [ca, descripcion, obtener, esperado] of escenarios) {
  const sesionA = { limpiada: false, limpiar() { this.limpiada = true; } };
  const sesionD = { limpiada: false, limpiar() { this.limpiada = true; } };
  const a = await cargarTarjetaAntes(tarjeta, { obtener, sesion: sesionA, limiteMs: 30 });
  const d = await cargarTarjeta(tarjeta, { cliente: crearClienteTarjetas(obtener, 30), sesion: sesionD });
  const iguales = JSON.stringify(a) === JSON.stringify(d) && sesionA.limpiada === sesionD.limpiada;
  verificar(iguales && d.estado === esperado, `${ca} ${descripcion}: antes=${a.estado} después=${d.estado}`);
  verificar(!/\d{3}|ECONN|Error|tiempo/.test(d.texto), `${ca} el texto no muestra detalles técnicos: «${d.texto}»`);
  if (esperado === 'sesion_expirada') verificar(sesionD.limpiada, `${ca} la sesión se limpió (R-07)`);
}

verificar(estadoDeRespuesta({ status: 200, total: 0 }) === 'vacio', 'CA-07 regla aislada: vacío no es error (R-05)');
verificar(estadoDeRespuesta({ status: 'sin_respuesta' }) === 'error', 'CA-07 regla aislada: sin respuesta es error (R-04)');
verificar(textoDeEstado('vacio', tarjeta, {}) === tarjeta.vacio, 'CA-07 texto aislado: el vacío sugiere una acción');

console.log(fallos === 0 ? 'RESULTADO: todo correcto' : `RESULTADO: ${fallos} fallos`);
process.exit(fallos === 0 ? 0 : 1);
