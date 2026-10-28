// Semana 11 · Recorre el prototipo como lo haría una persona (clics y teclado), en escritorio y en móvil:
// camino feliz del auditor, error crítico del super administrador (cadena ALTERADA) y las correcciones B-01 y B-02.
// En cada paso: comprueba lo esperado, toma la captura y corre axe-core (WCAG 2.2 A/AA).
// En móvil, además: sin desborde (contra clientWidth, ver semana 10) y objetivos táctiles ≥ 44 px.
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

let fallas = 0, comprobaciones = 0;
const anotar = (ok, texto) => { comprobaciones++; if (!ok) fallas++; console.log(`${ok ? 'OK   ' : 'FALLA'} ${texto}`); };
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function comprobar(page, vista, nombre, esperado) {
  await espera(450); // la API simulada responde en 300 ms
  await page.waitForFunction(() => !document.querySelector('[aria-busy="true"]'), { timeout: 6000 }).catch(() => {});
  await espera(150);
  const dir = path.join(SALIDA, vista);
  fs.mkdirSync(dir, { recursive: true });
  await page.screenshot({ path: path.join(dir, `${nombre}.png`) });
  if (!(await page.evaluate(() => typeof window.axe !== 'undefined'))) await page.evaluate(AXE);
  const axe = await page.evaluate(async () => {
    const r = await window.axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] });
    return r.violations.map((v) => `${v.id}(${v.nodes.length})`);
  });
  const medidas = await page.evaluate(() => {
    const ancho = document.documentElement.clientWidth;
    const visible = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && !el.closest('[inert]') && getComputedStyle(el).visibility !== 'hidden'; };
    const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea')].filter(visible).filter((el) => el.getBoundingClientRect().right > ancho + 0.5).length;
    const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary')]
      .filter(visible).filter((el) => !el.classList.contains('saltar'))
      .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; })
      .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 20)}」`);
    return { desborde: Math.max(document.documentElement.scrollWidth - ancho, fuera), chicos, texto: document.body.innerText, url: location.href };
  });
  const faltan = (esperado.contiene || []).filter((t) => !medidas.texto.includes(t));
  const sobran = (esperado.noContiene || []).filter((t) => medidas.texto.includes(t));
  const movil = vista === 'movil';
  const datosEnUrl = /[?&](desde|hasta|usuario|modulo|accion|resultado|recurso|cursor|sello)=/.test(medidas.url);
  const ok = faltan.length === 0 && sobran.length === 0 && axe.length === 0 && !datosEnUrl && (!movil || (medidas.desborde <= 0 && medidas.chicos.length === 0));
  anotar(ok, `${vista.padEnd(10)} ${nombre.padEnd(30)} axe=${axe.length}${movil ? ` desborde=${medidas.desborde} toque<44=${medidas.chicos.length}` : ''}`
    + (faltan.length ? ` · falta: ${faltan.join(' | ')}` : '') + (sobran.length ? ` · NO debía mostrar: ${sobran.join(' | ')}` : '')
    + (axe.length ? ` · ${axe.join(' ')}` : '') + (movil && medidas.chicos.length ? ` · ${medidas.chicos.join(' ')}` : '') + (datosEnUrl ? ' · datos en la URL' : ''));
}

/** Clic en el primer elemento VISIBLE que coincide (en móvil hay tarjetas; en escritorio, la tabla). */
const clic = async (page, selector) => {
  await page.waitForFunction((s) => [...document.querySelectorAll(s)].some((e) => e.offsetParent !== null || e.getClientRects().length), {}, selector);
  const el = await page.evaluateHandle((s) => [...document.querySelectorAll(s)].find((e) => e.getBoundingClientRect().height > 0), selector);
  await el.asElement().click(); await espera(80);
};
const clicTexto = async (page, texto, dentro = '') => {
  const el = await page.waitForFunction((t, d) => [...document.querySelectorAll(`${d} button, ${d} a, ${d} summary`)].find((e) => e.getBoundingClientRect().height > 0 && e.textContent.trim().includes(t)), {}, texto, dentro);
  await el.asElement().click(); await espera(80);
};
const hayDialogo = (page) => page.evaluate(() => !!document.querySelector('[role="dialog"]'));
const foco = (page) => page.evaluate(() => (document.activeElement.innerText || document.activeElement.getAttribute('aria-label') || document.activeElement.id || '').trim());

async function recorrer(navegador, vista) {
  const page = await navegador.newPage();
  await page.setViewport(VISTAS[vista]);
  await page.goto(URL_APP + '#/ingresar');

  // ===== Camino feliz: el auditor revisa la corrección de hoy =====
  await comprobar(page, vista, '01-ingresar', { contiene: ['Ingresar al prototipo', 'Auditor de Oriente'] });
  await clic(page, '[data-id="auditor-oriente"]');
  await comprobar(page, vista, '02-filtros', { contiene: ['Su consulta queda registrada', 'Período', 'Hoy'], noContiene: ['Toda la red'] });
  await clicTexto(page, 'Más filtros');
  await page.select('#modulo', 'pacientes');
  await comprobar(page, vista, '03-filtro-modulo', { contiene: ['Módulo'] });
  await clic(page, '[data-accion="buscar"]');
  await comprobar(page, vista, '04-resultados', { contiene: ['Movimientos', '50 movimientos', 'hay más', 'Corregido por #56', 'Corrige a #55'], noContiene: ['Editar', 'Borrar'] });
  await clicTexto(page, 'Cargar 50 más');
  await comprobar(page, vista, '05-cargar-mas', { contiene: ['58 movimientos', 'fin de la lista'] });
  await page.evaluate(() => window.scrollTo(0, 0));
  await clic(page, 'a[aria-label="Ver detalle del movimiento #55"]');
  await comprobar(page, vista, '06-detalle-original', { contiene: ['Movimiento #55', 'Corregido por #56', 'u-equivocado', 'Abrir este detalle quedó registrado'] });
  await clicTexto(page, 'Ver #56');
  await comprobar(page, vista, '07-detalle-correccion', { contiene: ['Movimiento #56', 'Corrige a #55', 'El integrador atribuyó la consulta al usuario equivocado', 'u-correcto'] });
  await clic(page, '.barra .atras');
  await comprobar(page, vista, '08-vuelve-a-la-lista', { contiene: ['58 movimientos', 'fin de la lista'] }); // misma lista: sin repetir la consulta

  // ===== Semana 12 · cambio solicitado: «¿Quién vio la ficha de este paciente?» =====
  await page.evaluate(() => window.scrollTo(0, 0));
  await clic(page, 'a[aria-label="Ver detalle del movimiento #55"]');
  await comprobar(page, vista, '08a-detalle-con-recurso', { contiene: ['¿Quién más accedió a esta ficha?'] });
  await clicTexto(page, '¿Quién más accedió');
  await comprobar(page, vista, '08b-filtros-con-recurso', { contiene: ['Recurso (¿quién vio esta ficha?)', 'Su consulta queda registrada'] });
  anotar((await page.$eval('#recurso', (e) => e.value)) === 'paciente:P-000001' && (await page.$eval('#modulo', (e) => e.value)) === '',
    `${vista.padEnd(10)} 12 el recurso llega precargado y el resto de los filtros se limpia; todavía no se consultó nada`);
  anotar((await page.evaluate(() => document.activeElement.id)) === 'recurso', `${vista.padEnd(10)} 12 el foco va al campo «Recurso» (se ve qué se va a buscar)`);
  await clic(page, '[data-accion="buscar"]');
  await comprobar(page, vista, '08c-quien-vio-la-ficha', { contiene: ['3 movimientos', 'recurso: paciente:P-000001', 'u-luis', 'u-equivocado', 'u-correcto', 'Corregido por #56'], noContiene: ['u-pedro'] });

  // ===== B-02: la sesión vence a mitad de la tarea; se renueva sin perder nada =====
  await page.evaluate(() => { window.__demo.red.sesionVencida = true; });
  await clicTexto(page, 'Filtros');
  await clic(page, '[data-accion="buscar"]');
  await comprobar(page, vista, '09-sesion-vencida', { contiene: ['Su sesión venció', 'Lo que tiene en pantalla se conserva'] });
  anotar((await foco(page)) === 'Renovar sesión', `${vista.padEnd(10)} B-02 el foco inicial del diálogo está en «Renovar sesión»`);
  await clicTexto(page, 'Renovar sesión', '#capa-dialogo');
  await comprobar(page, vista, '10-sesion-renovada', { contiene: ['3 movimientos', 'recurso: paciente:P-000001'] }); // la búsqueda vigente (semana 12: la del recurso)
  const almacenado = await page.evaluate(() => localStorage.length + sessionStorage.length);
  anotar(almacenado === 0, `${vista.padEnd(10)} B-02 nada de la bitácora en localStorage ni sessionStorage (${almacenado} claves)`);

  // ===== Error crítico: el super administrador verifica Norte y la cadena está ALTERADA =====
  await clicTexto(page, 'Más');
  await clicTexto(page, 'Cerrar sesión');
  await clic(page, '[data-id="superadmin"]');
  await comprobar(page, vista, '11-superadmin-filtros', { contiene: ['Alcance', 'Toda la red'] });
  // B-01: marcar «Toda la red» (también con flechas) NO abre nada
  await page.focus('input[name="alcance"][value="hospital"]');
  await page.keyboard.press('ArrowDown');
  await espera(200);
  anotar(!(await hayDialogo(page)), `${vista.padEnd(10)} B-01 recorrer el grupo de alcance con ↓ NO abre ningún diálogo`);
  await clicTexto(page, 'Buscar en toda la red');
  await comprobar(page, vista, '12-confirmar-red', { contiene: ['¿Consultar toda la red?', 'todos los hospitales'] });
  anotar((await foco(page)) === 'Cancelar', `${vista.padEnd(10)} B-01 el foco inicial está en «Cancelar»`);
  await page.keyboard.press('Escape');
  await espera(150);
  anotar(!(await hayDialogo(page)) && (await foco(page)) === 'Buscar en toda la red', `${vista.padEnd(10)} Esc cierra el diálogo y el foco vuelve al botón que lo abrió`);

  await clicTexto(page, 'Integridad', '#nav');
  await comprobar(page, vista, '13-integridad', { contiene: ['Verificar integridad', 'Ancla publicada'] });
  await clicTexto(page, 'Ancla publicada');
  await page.type('#secuencia', '3');
  await page.type('#sello', '9c0e…71ab');
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '14-ancla-invalida', { contiene: ['Revise un campo', 'Deben ser 64 caracteres hexadecimales'] });
  anotar((await page.evaluate(() => document.activeElement.id)) === 'resumen-errores', `${vista.padEnd(10)} B-07 «Verificar» no se deshabilita: el foco va al resumen de errores`);
  await page.$eval('#secuencia', (e) => { e.value = ''; });
  await page.$eval('#sello', (e) => { e.value = ''; });
  await page.select('#cadena', 'hosp-norte');
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '15-confirmar-verificacion', { contiene: ['¿Verificar la cadena de Hospital Regional del Norte?', 'queda registrado'] });
  await clicTexto(page, 'Verificar', '#capa-dialogo');
  await comprobar(page, vista, '16-cadena-alterada', { contiene: ['Cadena alterada desde la secuencia 2', 'No modifique nada', 'Copiar informe', 'INFORME DE VERIFICACIÓN', 'Estado: ALTERADA', 'seguridad@'], noContiene: ['Reparar'] });
  anotar((await page.evaluate(() => document.activeElement.id)) === 'veredicto', `${vista.padEnd(10)} el foco va al veredicto (se anuncia como alerta)`);
  await page.select('#cadena', 'hosp-oriente');
  await clic(page, '[data-accion="verificar"]');
  await clicTexto(page, 'Verificar', '#capa-dialogo');
  await comprobar(page, vista, '17-oriente-integra', { contiene: ['Cadena íntegra', 'ninguno alterado'] });
  await page.close();
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  for (const vista of Object.keys(VISTAS)) await recorrer(navegador, vista);
  await navegador.close();
  console.log(`\nComprobaciones: ${comprobaciones}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
