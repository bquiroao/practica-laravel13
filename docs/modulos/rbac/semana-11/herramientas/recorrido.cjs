// Semana 11 · Recorre el prototipo como lo haría una persona (clics y teclado), en escritorio y en móvil:
// camino feliz, error crítico y prueba de teclado del diálogo. En cada paso: comprueba lo esperado,
// toma la captura y corre axe-core (WCAG 2.2 A/AA). En móvil, además: sin desborde y objetivos ≥ 44 px.
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

let fallas = 0;
const log = [];
const anotar = (ok, texto) => { if (!ok) fallas++; log.push(`${ok ? 'OK   ' : 'FALLA'} ${texto}`); console.log(log[log.length - 1]); };

async function comprobar(page, vista, nombre, esperado) {
  const dir = path.join(SALIDA, vista);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${nombre}.png`) });
  if (!(await page.evaluate(() => typeof window.axe !== 'undefined'))) await page.evaluate(AXE);
  const axe = await page.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] });
    return r.violations.map((v) => `${v.id}(${v.nodes.length})`);
  });
  const medidas = await page.evaluate(() => {
    const desborde = document.documentElement.scrollWidth - window.innerWidth;
    const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]), select, textarea, summary')]
      .filter((el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && b.right > 0 && !el.closest('[inert]'); })
      .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; })
      .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 20)}」`);
    return { desborde, chicos };
  });
  const movil = vista === 'movil';
  const ok = esperado && axe.length === 0 && (!movil || (medidas.desborde <= 0 && medidas.chicos.length === 0));
  anotar(ok, `${vista.padEnd(10)} ${nombre.padEnd(34)} esperado=${esperado ? 'sí' : 'NO'}  axe=${axe.length ? axe.join(',') : 0}`
    + (movil ? `  desborde=${medidas.desborde}  toque<44=${medidas.chicos.length ? medidas.chicos.join(',') : 0}` : ''));
}

const texto = (page, sel) => page.$eval(sel, (e) => e.innerText).catch(() => '');
const esperarTexto = (page, sel, t) => page.waitForFunction((s, x) => document.querySelector(s)?.innerText.includes(x), { timeout: 5000 }, sel, t);
async function nueva(navegador, vista) {
  const page = await navegador.newPage();
  await page.setViewport(VISTAS[vista]);
  await page.goto(URL_APP + '#/ingresar', { waitUntil: 'load' });
  await page.evaluate(() => { window.__rbac.red.latenciaMs = 20; });
  return page;
}
async function ingresar(page, id) {
  await page.waitForSelector(`[data-accion="ingresar"][data-id="${id}"]`);
  await page.click(`[data-accion="ingresar"][data-id="${id}"]`);
  await esperarTexto(page, 'h1', 'Hola');
}
async function salir(page) {
  await page.click('#btn-usuario');
  await page.click('[data-accion="salir"]');
  await page.waitForSelector('[data-accion="ingresar"]');
}
async function irA(page, hash, titulo) {
  await page.evaluate((h) => { location.hash = h; }, hash);
  await esperarTexto(page, 'h1', titulo);
}

async function caminoFeliz(navegador, vista) {
  const page = await nueva(navegador, vista);
  await comprobar(page, vista, 'cf-01-ingresar', (await texto(page, 'h1')).includes('Ingresar'));
  await ingresar(page, 'admin-01');
  await comprobar(page, vista, 'cf-02-inicio-admin', (await texto(page, '#nav')).includes('Roles'));
  await page.click('#nav a[href="#/roles"]');
  await esperarTexto(page, 'h1', 'Roles del hospital');
  await page.type('#buscar', 'Luis');
  await page.waitForSelector('.resultado');
  await comprobar(page, vista, 'cf-03-buscar-persona', (await texto(page, '#resultados')).includes('Luis García'));
  await page.click('.resultado[href*="nuevo-01"]');
  await esperarTexto(page, '#contenido', 'no tiene roles');
  await comprobar(page, vista, 'cf-04-roles-de-luis', true);
  await page.click('a[href^="#/roles/asignar?usuario=nuevo-01"]');
  await page.waitForSelector('#rol');
  await page.select('#rol', 'enfermeria');
  await esperarTexto(page, '#podra', 'registrar signos vitales');
  await comprobar(page, vista, 'cf-05-paso2-rol', (await texto(page, '.paso-texto')).includes('Paso 2'));
  await page.click('button[form="form-paso2"]');
  await page.waitForSelector('#conf');
  await comprobar(page, vista, 'cf-06-paso3-confirmar', (await texto(page, '#contenido')).includes('recibirá el rol Enfermería'));
  await page.click('#btn-asignar');
  await esperarTexto(page, '#aviso', 'Rol asignado');
  const enfocado = await page.evaluate(() => document.activeElement.id);
  await comprobar(page, vista, 'cf-07-rol-asignado', enfocado === 'aviso');
  await salir(page);
  await ingresar(page, 'nuevo-01');
  await page.click('a.seccion[href^="#/pacientes"]');
  await page.waitForSelector('#btn-guardar');
  await comprobar(page, vista, 'cf-08-luis-ficha', !(await page.$('#nav:not([hidden])')));
  await page.click('#btn-guardar');
  await esperarTexto(page, '#aviso', 'Signos vitales registrados');
  await comprobar(page, vista, 'cf-09-signos-registrados', true);
  await page.close();
}

async function errorCritico(navegador, vista) {
  const page = await nueva(navegador, vista);
  await ingresar(page, 'ana');
  await irA(page, '#/roles', 'No tienes acceso');
  const refRuta = await page.$eval('[data-accion="copiar"]', (b) => b.dataset.ref);
  await comprobar(page, vista, 'ec-01-ruta-protegida', !!refRuta);
  await irA(page, '#/inicio', 'Hola');
  await page.click('a.seccion[href^="#/pacientes"]');
  await page.waitForSelector('#btn-guardar');
  await comprobar(page, vista, 'ec-02-ana-ficha', !(await texto(page, '#contenido')).includes('Nueva prescripción'));
  await page.click('#btn-usuario');
  await page.waitForSelector('#menu-usuario');
  await comprobar(page, vista, 'ec-03-menu-demostracion', true);
  await page.click('[data-accion="demo-revocar"]');
  await page.click('#btn-guardar'); // la pantalla aún muestra la acción (decisión en caché), el servidor decide
  await page.waitForSelector('#aviso[role="alert"]');
  const ref = await page.$eval('#aviso [data-accion="copiar"]', (b) => b.dataset.ref);
  const foco = await page.evaluate(() => document.activeElement.id);
  const sinGuardar = !(await page.$('#btn-guardar'));
  const sinMotivo = !(await texto(page, '#aviso')).match(/MEMBRESIA|revoc|Enfermería/i);
  await comprobar(page, vista, 'ec-04-operacion-denegada', !!ref && foco === 'aviso' && sinGuardar && sinMotivo);
  await salir(page);
  await ingresar(page, 'admin-01');
  await page.click('#nav a[href="#/auditoria"]');
  await page.waitForSelector('#ref');
  const agrupada = await page.evaluate((r) => r.split('-').join(' · '), ref); // como la copiaría una persona desde la pantalla
  await page.type('#ref', agrupada);
  await page.click('form[data-accion="buscar-ref"] button[type="submit"]');
  await page.waitForSelector('article.tarjeta');
  await page.click('article details summary');
  const evento = await texto(page, 'article.tarjeta');
  await comprobar(page, vista, 'ec-05-auditor-localiza', evento.includes('MEMBRESIA_NO_VIGENTE') && evento.includes('Ana Pérez') && evento.includes(ref));
  await irA(page, `#/auditoria?ref=${refRuta}`, 'Consulta de auditoría');
  await page.waitForSelector('article.tarjeta');
  anotar((await texto(page, 'article.tarjeta')).includes('PERMISO_NO_ASIGNADO'), `${vista.padEnd(10)} ec-01 auditada como PERMISO_NO_ASIGNADO (sin captura)`);
  await page.close();
}

async function teclado(navegador, vista) {
  const page = await nueva(navegador, vista);
  await ingresar(page, 'admin-01');
  await irA(page, '#/roles?usuario=marta', 'Roles del hospital');
  const visible = vista === 'escritorio' ? 'table.tabla [data-accion="abrir-revocar"]' : 'ul.solo-movil [data-accion="abrir-revocar"]';
  await page.focus(visible);
  await page.keyboard.press('Enter');
  await page.waitForSelector('.hoja');
  const inicial = await page.evaluate(() => document.activeElement.innerText);
  await comprobar(page, vista, 'tec-01-dialogo-revocar', inicial === 'Cancelar');
  let dentro = true;
  for (let i = 0; i < 12; i++) { await page.keyboard.press('Tab'); dentro = dentro && await page.evaluate(() => !!document.activeElement.closest('.hoja')); }
  anotar(dentro, `${vista.padEnd(10)} tec: 12 Tab sin salir del diálogo (barra inerte)`);
  await page.keyboard.press('Escape');
  const volvio = await page.evaluate(() => !document.querySelector('.hoja') && document.activeElement.dataset.accion === 'abrir-revocar');
  anotar(volvio, `${vista.padEnd(10)} tec: Esc cierra y el foco vuelve a «Revocar rol»`);
  await page.keyboard.press('Enter');
  await page.waitForSelector('.hoja');
  for (let i = 0; i < 3; i++) await page.keyboard.down('Shift'), await page.keyboard.press('Tab'), await page.keyboard.up('Shift');
  await page.keyboard.type('Cambio de servicio a consulta externa');
  await page.keyboard.press('Tab'); await page.keyboard.press('Space');
  await page.keyboard.press('Tab'); await page.keyboard.press('Enter');
  await esperarTexto(page, '#aviso', 'Rol revocado');
  const foco = await page.evaluate(() => document.activeElement.id);
  await comprobar(page, vista, 'tec-02-revocado-con-teclado', foco === 'aviso');
  await page.close();
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  for (const vista of Object.keys(VISTAS)) {
    await caminoFeliz(navegador, vista);
    await errorCritico(navegador, vista);
    await teclado(navegador, vista);
  }
  await navegador.close();
  console.log(`\nComprobaciones: ${log.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
