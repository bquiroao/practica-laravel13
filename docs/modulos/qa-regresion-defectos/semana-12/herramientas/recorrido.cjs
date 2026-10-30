// Semana 12 · Recorre el prototipo (más el flujo C, el cambio solicitado de esta semana) como una persona (clics y teclado) en escritorio (1280) y móvil (360):
//   A · camino feliz: el tester registra TC-107 y TC-108 (Pasa); el Líder QA ve «Hoy daría Liberar» y cierra con GO.
//   B · error crítico: FALLA sin evidencia (422, foco al campo) → con foto clínica y SIN RED → PENDIENTE, sin guardarse en el
//       equipo → vuelve la red → Reenviar (región viva) → el Líder QA ve «⛔ Bloquea», prueba Esc en el diálogo y cierra con NO-GO.
// En cada paso: comprueba lo esperado, toma la captura y corre axe-core (WCAG 2.0/2.1/2.2 A y AA). En móvil, además:
// sin desborde horizontal y objetivos táctiles ≥ 44 px.
// Uso: node herramientas/recorrido.cjs [carpeta-capturas] [ruta-chrome]   (requiere puppeteer-core y axe-core)
const fs = require('fs');
const path = require('path');
const { pathToFileURL } = require('url');
const puppeteer = require('puppeteer-core');

const SALIDA = process.argv[2] || path.join(__dirname, '..', 'capturas');
const CHROME = process.argv[3] || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const URL_APP = pathToFileURL(path.join(__dirname, '..', 'prototipo', 'index.html')).href;
const AXE = fs.readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');
const VISTAS = {
  escritorio: { width: 1280, height: 800, deviceScaleFactor: 1, isMobile: false, hasTouch: false },
  movil: { width: 360, height: 740, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
};

let fallas = 0, comprobaciones = 0, violacionesAxe = 0;
const anotar = (ok, texto) => { comprobaciones++; if (!ok) fallas++; console.log(`${ok ? 'OK   ' : 'FALLA'} ${texto}`); };
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function paso(page, vista, nombre, esperado) {
  await espera(400);
  await page.waitForFunction(() => document.querySelector('#contenido').getAttribute('aria-busy') !== 'true', { timeout: 8000 }).catch(() => {});
  await espera(150);
  const dir = path.join(SALIDA, vista);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${nombre}.png`) });
  if (!(await page.evaluate(() => typeof window.axe !== 'undefined'))) await page.evaluate(AXE);
  const axe = await page.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] });
    return r.violations.map((v) => `${v.id}(${v.nodes.length})`);
  });
  violacionesAxe += axe.length;
  const movil = vista === 'movil' ? await page.evaluate(() => {
    const visible = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && !el.closest('[hidden]') && !el.closest('details:not([open]) > :not(summary)'); };
    const desborde = document.documentElement.scrollWidth - document.documentElement.clientWidth;
    const chicos = [...document.querySelectorAll('button, a[href], input:not([type=radio]):not([type=checkbox]), select, textarea, summary')]
      .filter(visible).filter((el) => !el.classList.contains('oculto-visual'))
      .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; }).map((el) => (el.innerText || el.id).trim().slice(0, 20));
    return { desborde, chicos };
  }) : { desborde: 0, chicos: [] };
  const r = await page.evaluate((e) => {
    const texto = document.body.innerText;
    return { faltan: e.textos.filter((t) => !texto.includes(t)), foco: e.foco ? (document.activeElement && document.activeElement.matches(e.foco)) : true };
  }, esperado);
  const ok = r.faltan.length === 0 && r.foco && axe.length === 0 && movil.desborde <= 0 && movil.chicos.length === 0;
  anotar(ok, `${vista.padEnd(10)} ${nombre.padEnd(30)} texto=${r.faltan.length ? 'FALTA «' + r.faltan.join('», «') + '»' : 'sí'}  foco=${r.foco ? 'sí' : 'NO'}  axe=${axe.length ? axe.join(' ') : 0}${vista === 'movil' ? `  desborde=${movil.desborde}  toque<44=${movil.chicos.length ? movil.chicos.join('|') : 0}` : ''}`);
}

async function clic(page, prueba) { await page.click(`[data-prueba="${prueba}"]`); }

async function caminoFeliz(page, v) {
  await page.goto(URL_APP);
  await paso(page, v, 'a01-ingresar', { textos: ['Ingresar (demostración)'] });
  await clic(page, 'entrar-tester');
  await paso(page, v, 'a02-campanas-tester', { textos: ['Mis campañas en curso', 'Regresión H4', '0 de 2 con resultado'] });
  await clic(page, 'ejecutar');
  await paso(page, v, 'a03-ejecutar-tc107', { textos: ['TC-107', 'Versión 1', 'build 1.4.0+abc1234', 'crítico'] });
  await clic(page, 'registrar');
  await paso(page, v, 'a04-confirmar', { textos: ['¿Registrar este resultado?', 'TC-107 · Pasa'], foco: '[data-prueba="volver"]' });
  for (let i = 0; i < 4; i++) await page.keyboard.press('Tab');
  const dentro = await page.evaluate(() => !!document.activeElement.closest('#capa') || document.activeElement === document.body);
  anotar(dentro, `${v.padEnd(10)} a04 · con el diálogo abierto, Tab no llega al fondo (fondo inerte)`);
  await page.focus('[data-prueba="volver"]');
  await clic(page, 'confirmar-registro');
  await paso(page, v, 'a05-registrado-tc107', { textos: ['✓ Registrado', 'Intento #1 de TC-107 (Pasa)'] });
  await clic(page, 'siguiente');
  await paso(page, v, 'a06-ejecutar-tc108', { textos: ['TC-108'] });
  await clic(page, 'registrar');
  await clic(page, 'confirmar-registro');
  await paso(page, v, 'a07-registrado-tc108', { textos: ['✓ Registrado', 'Intento #1 de TC-108 (Pasa)'] });
  await page.evaluate(() => { location.hash = '#/ingresar'; });
  await espera(100);
  await clic(page, 'entrar-lider');
  await espera(500);
  await clic(page, 'ver-trazabilidad');
  await paso(page, v, 'a08-trazabilidad-lider', { textos: ['Hoy daría Liberar (GO)', 'TC-107 ★', '✓ Pasa'] });
  await clic(page, 'cerrar');
  await paso(page, v, 'a09-confirmar-cierre', { textos: ['Cerrar no se puede deshacer', 'Cerrar y liberar'], foco: '#volver' });
  await clic(page, 'confirmar-cierre');
  await paso(page, v, 'a10-cerrada-go', { textos: ['Cerrada: ✓ Liberar (GO)'] });
}

async function errorCritico(page, v) {
  await page.goto(URL_APP);
  await espera(200);
  await clic(page, 'entrar-tester');
  await espera(500);
  await clic(page, 'ejecutar');
  await espera(500);
  await clic(page, 'resultado-FALLA');
  await paso(page, v, 'b01-falla-formulario', { textos: ['Evidencia (obligatoria si falla o está bloqueado)', 'Defecto'] });
  await clic(page, 'registrar');
  await paso(page, v, 'b02-falta-evidencia-422', { textos: ['Falta la evidencia'], foco: '#tomar-foto' });
  await clic(page, 'tomar-foto');
  await espera(500);
  await clic(page, 'clasif-DATOS_CLINICOS');
  await espera(500);
  await page.type('#titulo', 'Se puede editar una nota firmada');
  await page.evaluate(() => window.ApiSimulada.cortarRed());   // la señal se cae justo antes de registrar
  await clic(page, 'registrar');
  await paso(page, v, 'b03-confirmar-clinica', { textos: ['1 evidencia con datos clínicos', 'Se puede editar una nota firmada'], foco: '[data-prueba="volver"]' });
  await clic(page, 'confirmar-registro');
  await paso(page, v, 'b04-pendiente-sin-red', { textos: ['⏳ Pendiente de confirmar', 'No cierre esta pestaña', 'Sin conexión'] });
  const enNavegador = await page.evaluate(() => JSON.stringify(localStorage) + JSON.stringify(sessionStorage));
  anotar(!/nota|3fa9c2e1|DATOS_CLINICOS/.test(enNavegador), `${v.padEnd(10)} b04 · la evidencia clínica NO está en localStorage ni sessionStorage (B-01)`);
  await page.evaluate(() => window.ApiSimulada.restablecerRed());
  await clic(page, 'ir-pendientes');
  await paso(page, v, 'b05-pendientes', { textos: ['TC-107 · Falla', 'Solo en esta pestaña'] });
  await clic(page, 'reenviar');
  await paso(page, v, 'b06-reenviado', { textos: ['No hay resultados sin confirmar'] });
  const vivo = await page.$eval('#vivo', (e) => e.textContent);
  anotar(vivo.includes('TC-107 registrado'), `${v.padEnd(10)} b06 · la región viva anunció «${vivo}» (B-07)`);
  await clic(page, 'a-campanas');
  await espera(500);
  await clic(page, 'ejecutar');
  await espera(500);
  await clic(page, 'registrar');
  await clic(page, 'confirmar-registro');
  await espera(500);
  await page.evaluate(() => { location.hash = '#/ingresar'; });
  await espera(100);
  await clic(page, 'entrar-lider');
  await espera(500);
  await clic(page, 'ver-trazabilidad');
  await paso(page, v, 'b07-trazabilidad-bloquea', { textos: ['Hoy daría No liberar (NO-GO) · 2 causas', '1 defecto MAYOR abierto en un caso crítico', 'Tasa de aprobados 50.0 %', '⛔ Bloquea', 'Bloquea porque TC-107 es crítico'] });
  await clic(page, 'cerrar');
  await page.keyboard.press('Escape');
  await espera(200);
  const tras = await page.evaluate(() => ({ dialogo: !!document.querySelector('#capa .hoja'), foco: document.activeElement.matches('[data-prueba="cerrar"]') }));
  anotar(!tras.dialogo && tras.foco, `${v.padEnd(10)} b07 · Esc cierra el diálogo y el foco vuelve a «Cerrar campaña…» (B-08)`);
  await clic(page, 'cerrar');
  await paso(page, v, 'b08-confirmar-nogo', { textos: ['No liberar (NO-GO)', 'Cerrar con No liberar'], foco: '#volver' });
  await clic(page, 'confirmar-cierre');
  await paso(page, v, 'b09-cerrada-nogo', { textos: ['Cerrada: ⛔ No liberar (NO-GO)', '1 defecto MAYOR abierto en un caso crítico de la matriz'] });
}

// C · cambio solicitado (semana 12): el Responsable de producto ve la trazabilidad POR REQUISITO, en solo lectura.
async function porRequisito(page, v) {
  await page.goto(URL_APP);
  await espera(200);
  await clic(page, 'entrar-tester');
  await espera(500);
  await clic(page, 'ejecutar');
  await espera(500);
  await clic(page, 'resultado-FALLA');
  await espera(400);
  await clic(page, 'tomar-foto');
  await espera(400);
  await page.type('#titulo', 'Se puede editar una nota firmada');
  await clic(page, 'registrar');
  await clic(page, 'confirmar-registro');
  await espera(600);
  await page.evaluate(() => { location.hash = '#/ingresar'; });
  await espera(100);
  await clic(page, 'entrar-producto');
  await espera(500);
  await clic(page, 'ver-trazabilidad');
  await paso(page, v, 'c01-producto-por-caso', { textos: ['Responsable de producto', 'Ver por', 'TC-107 ★'] });
  const sinCerrar = await page.evaluate(() => !document.querySelector('[data-prueba="cerrar"]'));
  anotar(sinCerrar, `${v.padEnd(10)} c01 · el Responsable de producto no ve «Cerrar campaña…» (solo lectura)`);
  await clic(page, 'vista-requisito');
  await paso(page, v, 'c02-por-requisito', { textos: ['RF-07', 'RF-08', '2 casos', '1 falla', '⛔ 1 defecto bloquea'], foco: '[data-prueba="vista-requisito"]' });
  const orden = await page.evaluate(() => [...document.querySelectorAll('.requisito .titulo')].map((e) => e.textContent.trim()));
  anotar(orden[0] && orden[0].startsWith('RF-07'), `${v.padEnd(10)} c02 · el requisito con un defecto que bloquea va primero (${orden.join(', ')})`);
  await clic(page, 'vista-caso');
  await paso(page, v, 'c03-de-vuelta-por-caso', { textos: ['TC-107 ★', 'TC-108 ★'], foco: '[data-prueba="vista-caso"]' });
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  for (const [v, viewport] of Object.entries(VISTAS)) {
    const page = await navegador.newPage();
    await page.setViewport(viewport);
    console.log(`\n== ${v} (${viewport.width} px) · A · camino feliz ==`);
    await caminoFeliz(page, v);
    console.log(`\n== ${v} (${viewport.width} px) · B · error crítico y recuperación ==`);
    await errorCritico(page, v);
    console.log(`\n== ${v} (${viewport.width} px) · C · cambio solicitado: trazabilidad por requisito ==`);
    await porRequisito(page, v);
    await page.close();
  }
  await navegador.close();
  console.log(`\nComprobaciones: ${comprobaciones}  Fallan: ${fallas}  Violaciones axe-core: ${violacionesAxe}`);
  process.exit(fallas ? 1 : 0);
})();
