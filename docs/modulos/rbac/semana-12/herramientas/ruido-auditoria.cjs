// Semana 12 · Mide el efecto del cambio B-6 en el prototipo: cada persona entra y SOLO navega por sus
// secciones (no intenta nada prohibido). Cuenta los ACCESO_DENEGADO que quedan en la bitácora simulada.
// Uso: node herramientas/ruido-auditoria.cjs <carpeta-prototipo> [ruta-chrome]
const path = require('path');
const { pathToFileURL } = require('url');
const puppeteer = require('puppeteer-core');

const PROTO = path.resolve(process.argv[2] || path.join(__dirname, '..', 'prototipo'));
const CHROME = process.argv[3] || 'C:/Program Files/Google/Chrome/Application/chrome.exe';

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true });
  const page = await navegador.newPage();
  await page.setViewport({ width: 1280, height: 800 });
  await page.goto(pathToFileURL(path.join(PROTO, 'index.html')).href + '#/ingresar', { waitUntil: 'load' });
  await page.evaluate(() => { window.__rbac.red.latenciaMs = 5; });
  const contar = () => page.evaluate(() => {
    // Semana 12 expone eventos(); en la semana 11 no existe y se reconstruye pidiendo cada referencia no es posible,
    // así que se cuenta interceptando las respuestas del PDP (cada DENEGADO del PDP = un evento ACCESO_DENEGADO).
    return window.__rbac.eventos ? window.__rbac.eventos().filter((e) => e.tipo === 'ACCESO_DENEGADO').length : window.__denegados || 0;
  });
  // Para la semana 11 (sin eventos()), se envuelve la API y se cuenta cada respuesta con correlation_id de un DENEGADO/403.
  await page.evaluate(() => {
    if (window.__rbac.eventos) return;
    window.__denegados = 0;
    const api = window.__rbac.api();
    const original = api.solicitarRed.bind(api);
    api.solicitarRed = async (...args) => {
      const r = await original(...args);
      if ((r.cuerpo.decision === 'DENEGADO' || r.estado === 403) && r.cuerpo.correlation_id) window.__denegados++;
      return r;
    };
  });
  let total = 0;
  for (const id of ['admin-01', 'nuevo-01', 'ana', 'marta']) {
    const antes = await contar();
    await page.evaluate(() => { location.hash = '#/ingresar'; });
    await page.waitForSelector(`[data-accion="ingresar"][data-id="${id}"]`);
    await page.click(`[data-accion="ingresar"][data-id="${id}"]`);
    await page.waitForFunction(() => document.querySelector('h1')?.innerText.includes('Hola'));
    // Solo se abren las secciones que la propia interfaz ofrece (tarjetas de Inicio).
    const enlaces = await page.$$eval('a.seccion', (as) => as.map((a) => a.getAttribute('href')));
    for (const href of enlaces) {
      await page.evaluate((h) => { location.hash = h; }, href);
      await page.waitForFunction(() => !document.querySelector('#contenido .cargando'));
      await new Promise((r) => setTimeout(r, 60));
    }
    const d = (await contar()) - antes;
    total += d;
    console.log(`${id.padEnd(9)} secciones abiertas: ${String(enlaces.length).padEnd(2)} ACCESO_DENEGADO generados sin intentar nada: ${d}`);
    await page.click('#btn-usuario'); await page.click('[data-accion="salir"]');
  }
  console.log(`\nPrototipo: ${path.basename(path.dirname(PROTO))}/${path.basename(PROTO)}  Total de denegaciones «fantasma»: ${total}`);
  await navegador.close();
})().catch((e) => { console.error(e); process.exit(2); });
