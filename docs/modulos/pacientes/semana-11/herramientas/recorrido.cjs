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

let fallas = 0, comprobaciones = 0;
const anotar = (ok, texto) => { comprobaciones++; if (!ok) fallas++; console.log(`${ok ? 'OK   ' : 'FALLA'} ${texto}`); };
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

async function comprobar(page, vista, nombre, esperado) {
  await espera(450); // la API simulada responde en 300 ms
  await page.waitForFunction(() => !document.querySelector('[aria-busy="true"]'), { timeout: 5000 }).catch(() => {});
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
    const desborde = document.documentElement.scrollWidth - window.innerWidth;
    const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary')]
      .filter((el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && !el.closest('[inert]') && getComputedStyle(el).position !== 'absolute'; })
      .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; })
      .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 20)}」`);
    return { desborde, chicos, texto: document.body.innerText, url: location.href };
  });
  const faltan = (esperado.contiene || []).filter((t) => !medidas.texto.includes(t));
  const sobran = (esperado.noContiene || []).filter((t) => medidas.texto.includes(t));
  const movil = vista === 'movil';
  const ok = faltan.length === 0 && sobran.length === 0 && axe.length === 0 && (!movil || (medidas.desborde <= 0 && medidas.chicos.length === 0))
    && !/nombre=|documento=|telefono=/.test(medidas.url);
  anotar(ok, `${vista.padEnd(10)} ${nombre.padEnd(30)} axe=${axe.length}${movil ? ` desborde=${medidas.desborde} toque<44=${medidas.chicos.length}` : ''}`
    + (faltan.length ? ` · falta: ${faltan.join(' | ')}` : '') + (sobran.length ? ` · NO debía mostrar: ${sobran.join(' | ')}` : '')
    + (axe.length ? ` · ${axe.join(' ')}` : '') + (movil && medidas.chicos.length ? ` · ${medidas.chicos.join(' ')}` : ''));
}

const clic = async (page, selector) => { await page.waitForSelector(selector, { visible: true }); await page.click(selector); await espera(80); };
const clicTexto = async (page, texto) => {
  const el = await page.waitForFunction((t) => [...document.querySelectorAll('button, a')].find((e) => e.offsetParent !== null && e.textContent.trim().includes(t)), {}, texto);
  await el.asElement().click(); await espera(80);
};
const escribir = async (page, selector, texto) => { await page.waitForSelector(selector); await page.$eval(selector, (e) => { e.value = ''; }); await page.type(selector, texto); };
const fecha = (page, selector, iso) => page.$eval(selector, (e, v) => { e.value = v; e.dispatchEvent(new Event('input', { bubbles: true })); }, iso);
async function ingresar(page, id) { await page.goto(URL_APP + '#/ingresar'); await clic(page, `[data-id="${id}"]`); }
async function salir(page) { await page.goto(URL_APP.split('#')[0] + '#/mas'); await clicTexto(page, 'Cerrar sesión'); }
async function buscar(page, texto) { await escribir(page, '#buscar', texto); await clic(page, '#form-buscar button[type=submit]'); }
async function llenarRegistro(page, vista, d, prefijo) {
  await escribir(page, '#nombres', d.nombres); await escribir(page, '#apellidos', d.apellidos);
  await fecha(page, '#fecha_nacimiento', d.nacimiento); await page.select('#sexo', d.sexo);
  if (prefijo) await comprobar(page, vista, `${prefijo}-paso1`, { contiene: ['Paso 1 de 3'] });
  await clicTexto(page, 'Siguiente');
  await escribir(page, '#numero', d.dpi); if (d.telefono) await escribir(page, '#telefono', d.telefono);
  if (prefijo) await comprobar(page, vista, `${prefijo}-paso2`, { contiene: ['Paso 2 de 3'] });
  await clicTexto(page, 'Siguiente');
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  for (const [vista, viewport] of Object.entries(VISTAS)) {
    const page = await navegador.newPage();
    await page.setViewport(viewport);
    page.on('pageerror', (e) => anotar(false, `${vista} error de JavaScript: ${e.message}`));

    // ===== Camino feliz: admisión busca, registra (con un corte de red) y corrige un teléfono =====
    await page.goto(URL_APP);
    await comprobar(page, vista, '01-ingresar', { contiene: ['Ingresar al prototipo', 'Marta López'] });
    await ingresar(page, 'admision-01');
    await buscar(page, 'ramirez');
    await comprobar(page, vista, '02-busqueda-vacia', { contiene: ['No encontramos pacientes', 'Registrar paciente nuevo'] });
    await clicTexto(page, 'Registrar paciente nuevo');
    await llenarRegistro(page, vista, { nombres: 'Carlos', apellidos: 'Ramírez Soto', nacimiento: '1990-05-20', sexo: 'male', dpi: '4455667780105', telefono: '+502 5000 1111' }, '03');
    await comprobar(page, vista, '05-revision', { contiene: ['Paso 3 de 3', 'Carlos Ramírez Soto', '•••• 0105'] });
    await page.evaluate(() => { window.__demo.red.perderRespuesta = true; }); // el servidor registra, pero la respuesta se pierde
    await clicTexto(page, 'Registrar paciente');
    await comprobar(page, vista, '06-registro-incierto', { contiene: ['No sabemos si se registró', 'Reintentar'] });
    await clicTexto(page, 'Reintentar');                                        // misma Idempotency-Key
    await comprobar(page, vista, '07-ficha-registrada', { contiene: ['P-000006', 'Ya se había registrado: no se creó otro', 'Carlos Ramírez Soto'] });
    await clicTexto(page, 'Editar datos');
    await escribir(page, '#e-telefono', '+502 5000 2222'); await escribir(page, '#e-numero', '4455667780105');
    await comprobar(page, vista, '08-editar', { contiene: ['Editar · P-000006', 'Confirma el número de DPI'] });
    const motivoVisible = await page.$eval('#bloque-motivo', (e) => !e.hidden);
    anotar(!motivoVisible, `${vista.padEnd(10)} cambiar el teléfono no pide motivo`);
    await clicTexto(page, 'Revisar cambios');
    await comprobar(page, vista, '09-confirmar', { contiene: ['Confirmar cambios', '+502 5000 2222', 'no hace falta motivo'] });
    for (let i = 0; i < 8; i++) await page.keyboard.press('Tab');                // el diálogo retiene el foco
    anotar(await page.evaluate(() => !!document.activeElement.closest('.hoja')), `${vista.padEnd(10)} Tab ×8 se queda dentro del diálogo`);
    await clicTexto(page, 'Guardar');
    await comprobar(page, vista, '10-ficha-actualizada', { contiene: ['Datos actualizados', 'versión 2', '+502 5000 2222'] });

    // ===== Error crítico: admisión intenta registrar otra vez a una paciente protegida =====
    await page.goto(URL_APP); await page.reload();                               // estado limpio
    await ingresar(page, 'admision-01');
    await buscar(page, 'sofia morales');
    await comprobar(page, vista, '11-protegida-no-aparece', { contiene: ['No encontramos pacientes'], noContiene: ['P-000005'] });
    await clicTexto(page, 'Registrar paciente nuevo');
    await llenarRegistro(page, vista, { nombres: 'Sofía', apellidos: 'Morales Díaz', nacimiento: '1994-08-09', sexo: 'female', dpi: '2233445560101' });
    await clicTexto(page, 'Registrar paciente');
    await comprobar(page, vista, '12-ya-registrada-reservado', { contiene: ['Esta persona ya está registrada', 'Por confidencialidad', 'ext. 2140'], noContiene: ['P-000005', '+502 5123'] });
    await salir(page);
    await ingresar(page, 'supervisor-01');
    await buscar(page, '2233445560101');
    await comprobar(page, vista, '13-supervision-la-encuentra', { contiene: ['Sofía Morales Díaz', 'P-000005', 'Protegida'] });
    await page.goto(URL_APP.split('#')[0] + '#/pacientes/P-000005');
    await comprobar(page, vista, '14-ficha-supervision', { contiene: ['Sofía Morales Díaz', '+502 5123 4567', 'Protegida'] });
    await salir(page);
    await ingresar(page, 'clinico-01');
    await buscar(page, 'P-000005');
    await page.goto(URL_APP.split('#')[0] + '#/pacientes/P-000005');
    await comprobar(page, vista, '15-ficha-clinico-reducida', { contiene: ['Identidad protegida', 'Reservado', 'Puedes atender'], noContiene: ['Sofía', 'Morales', '5123', 'Editar datos'] });
    await page.close();
  }
  await navegador.close();
  console.log(`\nComprobaciones: ${comprobaciones}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
