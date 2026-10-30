// Semana 11, ampliado en la semana 12 (paso 26: RBAC retira el permiso a mitad de la sesión) · Recorre el prototipo como lo haría una persona (clics y teclado), en escritorio y en móvil:
// camino feliz (emitir con advertencia justificada; farmacia la consulta), error crítico (alergia nueva entre la verificación
// y la emisión: el servidor bloquea) y la recuperación después (respuesta perdida, reintentos seguros), más los roles.
// En cada paso: comprueba lo esperado, toma la captura y corre axe-core (WCAG 2.2 A/AA).
// En móvil, además: sin desborde (contra clientWidth, semana 10), objetivos táctiles ≥ 44 px y ningún control del contenido
// tapado por la barra de acción fija o la navegación cuando se lo desplaza al centro (regla nueva de esta semana).
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
// R-D1 (semana 8): ningún dato clínico viaja en la URL
const DATOS_EN_URL = /[?&](paciente|medicamento|dosis|items|alergia|justificacion|motivo|via|dias)=/i;

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
  const tapados = vista === 'movil' ? await page.evaluate((fn) => {
    const tapado = new Function('return ' + fn)(), y = window.scrollY;
    const lista = [...document.querySelectorAll('#contenido button, #contenido a[href], #contenido input, #contenido select, #contenido textarea, #contenido summary')]
      .filter((e) => e.getBoundingClientRect().height > 0 && !e.closest('[inert]'))
      .map((e) => [e, tapado(e)]).filter(([, t]) => t).map(([e, t]) => `${e.id || e.innerText.trim().slice(0, 24)} bajo ${t}`);
    window.scrollTo(0, y);
    return lista;
  }, TAPADO.toString()) : [];
  const medidas = await page.evaluate(() => {
    const ancho = document.documentElement.clientWidth;
    const visible = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && !el.closest('[inert]') && getComputedStyle(el).visibility !== 'hidden'; };
    const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea, .hoja, .toast')].filter(visible).filter((el) => el.getBoundingClientRect().right > ancho + 0.5).length;
    const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary')]
      .filter(visible).filter((el) => !el.classList.contains('saltar'))
      .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; })
      .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 20)}」`);
    return { desborde: Math.max(document.documentElement.scrollWidth - ancho, fuera), chicos, texto: document.body.innerText, url: location.href };
  });
  const faltan = (esperado.contiene || []).filter((t) => !medidas.texto.includes(t));
  const sobran = (esperado.noContiene || []).filter((t) => medidas.texto.includes(t));
  const movil = vista === 'movil';
  const datosEnUrl = DATOS_EN_URL.test(medidas.url);
  const ok = faltan.length === 0 && sobran.length === 0 && axe.length === 0 && !datosEnUrl && (!movil || (medidas.desborde <= 0 && medidas.chicos.length === 0 && tapados.length === 0));
  anotar(ok, `${vista.padEnd(10)} ${nombre.padEnd(30)} axe=${axe.length}${movil ? ` desborde=${medidas.desborde} toque<44=${medidas.chicos.length} tapados=${tapados.length}` : ''}`
    + (faltan.length ? ` · falta: ${faltan.join(' | ')}` : '') + (sobran.length ? ` · NO debía mostrar: ${sobran.join(' | ')}` : '')
    + (axe.length ? ` · ${axe.join(' ')}` : '') + (movil && medidas.chicos.length ? ` · ${medidas.chicos.join(' ')}` : '') + (datosEnUrl ? ' · datos en la URL' : '') + (tapados.length ? ` · tapado: ${tapados.join(' | ')}` : ''));
}

// Un control que, desplazado al centro, sigue bajo otra capa (la barra de acción fija, la navegación) no se puede tocar.
const TAPADO = (e) => {
  e.scrollIntoView({ block: 'center' });
  const r = e.getBoundingClientRect(), t = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
  return t === e || e.contains(t) || (t && t.closest('label') && t.closest('label').contains(e)) ? '' : (t ? (t.id || t.className || t.tagName) : 'fuera de la pantalla');
};
let vistaActual = '';
async function pulsar(el, nombre) {
  const tapado = await el.evaluate(TAPADO);
  if (tapado) { anotar(false, `${vistaActual.padEnd(10)} «${nombre}» queda bajo «${tapado}»: no se puede tocar (se activa con el teclado para seguir)`); await el.evaluate((e) => e.click()); }
  else await el.click();
  await espera(80);
}
const clic = async (page, selector) => {
  await page.waitForFunction((s) => [...document.querySelectorAll(s)].some((e) => e.getBoundingClientRect().height > 0), { timeout: 6000 }, selector);
  const el = await page.evaluateHandle((s) => [...document.querySelectorAll(s)].find((e) => e.getBoundingClientRect().height > 0), selector);
  await pulsar(el.asElement(), selector);
};
const clicTexto = async (page, texto, dentro = '') => {
  const el = await page.waitForFunction((t, d) => [...document.querySelectorAll(`${d} button, ${d} a, ${d} summary`)].find((e) => e.getBoundingClientRect().height > 0 && e.textContent.trim().includes(t)), { timeout: 6000 }, texto, dentro);
  await pulsar(el.asElement(), texto);
};
const escribir = async (page, selector, texto) => { await page.$eval(selector, (e) => { e.value = ''; }); await page.type(selector, texto); };
const elegir = async (page, selector, valor) => { await page.select(selector, valor); await espera(60); };
const foco = (page) => page.evaluate(() => (document.activeElement.id === 'veredicto' ? '#veredicto' : document.activeElement.id === 'resumen-errores' ? '#resumen-errores'
  : (document.activeElement.innerText || document.activeElement.getAttribute('aria-label') || document.activeElement.id || '').trim()));
const demo = (page, fn) => page.evaluate(fn);
const contar = (page) => page.evaluate(() => ({ presc: window.__demo.api.todas().length, trazas: window.__demo.api.trazas().map((t) => t.accion) }));
const ingresar = async (page, id) => {
  if (await page.evaluate(() => !!document.querySelector('#barra:not([hidden])'))) {
    // En una pantalla de tarea (sin navegación) se vuelve con «‹», como lo haría una persona
    if (await page.evaluate(() => document.getElementById('nav').hidden)) await clic(page, '.barra .atras');
    await clic(page, '#nav a[href="#/mas"]'); await clic(page, '[data-accion="salir"]');
  }
  await clic(page, `[data-id="${id}"]`);
};
const item = async (page, n, medicamento, dosis, cada, via, dias) => {
  await elegir(page, `#med-${n}`, medicamento);
  await escribir(page, `#dosis-${n}`, dosis);
  await elegir(page, `#cada-${n}`, cada);
  if (via) await elegir(page, `#via-${n}`, via);
  await escribir(page, `#dias-${n}`, dias);
};

async function recorrer(navegador, vista) {
  vistaActual = vista;
  const page = await navegador.newPage();
  await page.setViewport(VISTAS[vista]);
  await page.goto(URL_APP + '#/ingresar');
  const v = vista.padEnd(10);

  // ===== Camino feliz: el Dr. López emite con una advertencia justificada =====
  await comprobar(page, vista, '01-ingresar', { contiene: ['Ingresar al prototipo', 'Dr. López', 'Dra. Ruiz', 'Farmacia Norte'] });
  await clic(page, '[data-id="dr-lopez"]');
  await elegir(page, '#paciente', 'P-002');
  await comprobar(page, vista, '02-paciente', { contiene: ['Alergias activas: Penicilina', 'moderada · exantema', 'Para emitir, primero verifique'] });
  await item(page, 1, 'CEFA500', '500', '6', null, '7');
  await clic(page, '[data-accion="agregar"]');
  await item(page, 2, 'PARA500', '1500', '6', 'oral', '3');
  await comprobar(page, vista, '03-borrador', { contiene: ['Ítem 1: Cefalexina 500 mg', 'Ítem 2: Paracetamol 500 mg', 'Quitar ítem 2: Paracetamol 500 mg'] });
  // B-08: quitar con nombre y «Deshacer»
  await clic(page, '[data-accion="quitar"][data-i="1"]');
  await comprobar(page, vista, '04-quitar-deshacer', { contiene: ['Se quitó Paracetamol 500 mg', 'Deshacer'], noContiene: ['Ítem 2'] });
  const focoOculto = await page.evaluate(() => { const e = document.activeElement, r = e.getBoundingClientRect(), t = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2); return t === e || e.contains(t) ? '' : (t ? t.className || t.tagName : 'fuera'); });
  anotar(focoOculto === '', `${v} WCAG 2.4.11 tras quitar, el foco («+ Agregar medicamento») no queda oculto${focoOculto ? ': está bajo ' + focoOculto : ''}`);
  await clic(page, '[data-accion="deshacer"]');
  anotar((await page.$eval('#dosis-2', (e) => e.value)) === '1500', `${v} B-08 «Deshacer» restaura el ítem con sus valores (dosis 1500)`);
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '05-dosis-excedida', { contiene: ['Revise un dato', 'La dosis diaria de Paracetamol (6000 mg) supera la máxima (4000 mg)'] });
  anotar((await foco(page)) === '#resumen-errores', `${v} el foco va al resumen de errores, que enlaza al campo`);
  await escribir(page, '#dosis-2', '500');
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '06-advertencia', { contiene: ['Advertencia: 1 hallazgo', 'Cefalexina 500 mg ↔ Penicilina', 'Reactividad cruzada', 'severidad moderada', 'Justificar y emitir'] });
  anotar((await foco(page)) === '#veredicto', `${v} B-12 el resultado recibe el foco y se anuncia`);
  await clic(page, '[data-accion="justificar"]');
  await escribir(page, '#just', 'Sin alternativa: cultivo sensible solo a cefalosporinas');
  await comprobar(page, vista, '07-justificar', { contiene: ['Emitir con advertencia', '55 / mínimo 20 ✓', 'la ven farmacia y enfermería'] });
  // B-02: la sesión vence justo al emitir
  await demo(page, () => { window.__demo.red.sesionVencida = true; });
  await clic(page, '[data-accion="emitir-justificado"]');
  await comprobar(page, vista, '08-sesion-vencida', { contiene: ['Su sesión venció', 'se conservan'] });
  anotar((await foco(page)) === 'Renovar sesión', `${v} B-02 el foco inicial del diálogo está en «Renovar sesión»`);
  await clicTexto(page, 'Renovar sesión', '#capa-dialogo');
  await comprobar(page, vista, '09-emitida', { contiene: ['Prescripción #1 emitida', 'Farmacia ya puede verla'] });
  const c1 = await contar(page);
  anotar(c1.presc === 1, `${v} B-02/B-10 tras renovar se repite la MISMA petición: ${c1.presc} prescripción`);
  const almacenado = await page.evaluate(() => localStorage.length + sessionStorage.length);
  anotar(almacenado === 0, `${v} nada clínico en localStorage ni sessionStorage (${almacenado} claves)`);
  await clic(page, '[data-accion="ver"]');
  await comprobar(page, vista, '10-detalle-medico', { contiene: ['Prescripción #1', 'EMITIDA', 'Con advertencia justificada', '«Sin alternativa: cultivo sensible solo a cefalosporinas»', 'Anular'] });

  // Farmacia consulta la misma prescripción
  await ingresar(page, 'farm-norte');
  await comprobar(page, vista, '11-farmacia-consultar', { contiene: ['Consultar prescripción', 'Número de prescripción'], noContiene: ['Prescribir'] });
  await page.type('#numero', '1');
  await clic(page, '[data-accion="abrir"]');
  await comprobar(page, vista, '12-farmacia-detalle', { contiene: ['Prescripción #1', 'Con advertencia justificada', 'Justificación del médico', 'Penicilina'], noContiene: ['Anular'] });
  await page.evaluate(() => { location.hash = '#/prescribir'; });
  await comprobar(page, vista, '13-farmacia-sin-permiso', { contiene: ['Acceso denegado', 'M-16'] });

  // ===== Error crítico: una alergia nueva aparece entre la verificación y la emisión =====
  await ingresar(page, 'dr-lopez');
  await elegir(page, '#paciente', 'P-005');
  await item(page, 1, 'AMOX500', '500', '8', null, '7');
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '14-sin-conflictos', { contiene: ['Sin alergias activas', '1 registrada como resuelta', 'Sin conflictos', 'Emitir'] });
  await clic(page, '#nav a[href="#/mas"]');
  await clic(page, '[data-accion="alergia-nueva"]');
  await comprobar(page, vista, '15-alergia-registrada', { contiene: ['Registrada en el Expediente', 'Una pantalla ya abierta no se entera sola'] });
  await page.goBack(); await espera(150);
  const franja = await page.$eval('#franja', (e) => e.innerText);
  anotar(franja.includes('Sin alergias activas'), `${v} la pantalla abierta sigue con la foto anterior del Expediente (la carrera es real)`);
  await clic(page, '[data-accion="emitir"]');
  await comprobar(page, vista, '16-bloqueo-al-emitir', { contiene: ['No se emitió: alergia nueva', 'volvió a consultar el Expediente', 'Amoxicilina 500 mg ↔ Amoxicilina', 'severidad grave', 'No se guardó nada'] });
  anotar((await foco(page)) === '#veredicto', `${v} el foco va al veredicto (se anuncia como alerta)`);
  const c2 = await contar(page);
  anotar(c2.presc === 1 && c2.trazas.includes('PRESCRIPCION_BLOQUEADA'), `${v} no se guardó ninguna prescripción (${c2.presc}) y el bloqueo quedó trazado`);
  await clic(page, '[data-accion="cambiar"]');
  await comprobar(page, vista, '17-borrador-intacto', { contiene: ['Alergias activas: Amoxicilina', 'grave', 'Bloqueado al emitir: alergia a Amoxicilina (grave)', 'Ítem 1: Amoxicilina 500 mg'] });
  const valores = await page.evaluate(() => ['dosis-1', 'cada-1', 'dias-1'].map((id) => document.getElementById(id).value).join(' '));
  anotar(valores === '500 8 7', `${v} el borrador conserva lo escrito (dosis, frecuencia y días: ${valores})`);

  // Recuperación: la respuesta de «Emitir» se pierde; reintentar con la misma clave no duplica
  await elegir(page, '#med-1', 'PARA500');
  await elegir(page, '#via-1', 'oral');
  await clic(page, '[data-accion="verificar"]');
  await page.waitForFunction(() => !!document.querySelector('[data-accion="emitir"]'), { timeout: 6000 });
  await demo(page, () => { window.__demo.red.perderRespuesta = true; });
  await clic(page, '[data-accion="emitir"]');
  await comprobar(page, vista, '18-incierto', { contiene: ['No sabemos si quedó emitida', 'Reintentar es seguro: nunca se emite dos veces', 'Código de soporte', 'cli-'] });
  await page.evaluate(() => { location.hash = '#/prescribir'; });
  await comprobar(page, vista, '19-borrador-bloqueado', { contiene: ['Hay una emisión sin confirmar', 'el borrador no se puede editar'] });
  await clicTexto(page, 'Resolver la emisión');
  await clic(page, '[data-accion="reintentar-emision"]');
  await comprobar(page, vista, '20-ya-emitida', { contiene: ['Prescripción #2 emitida', 'el reintento no la duplicó'] });
  const c3 = await contar(page);
  anotar(c3.presc === 2, `${v} B-10 el reintento con la misma Idempotency-Key no duplicó: ${c3.presc} prescripciones en total`);

  // Anular con la red intermitente
  await clic(page, '[data-accion="ver"]');
  await clic(page, '[data-accion="anular"]');
  await page.type('#motivo', 'error');
  await clic(page, '[data-accion="confirmar-anular"]');
  await comprobar(page, vista, '21-motivo-corto', { contiene: ['Escriba al menos 10 caracteres (lleva 5)', 'M-20'] });
  await escribir(page, '#motivo', 'Pauta duplicada con la de urgencias');
  await demo(page, () => { window.__demo.red.perderRespuesta = true; });
  await clic(page, '[data-accion="confirmar-anular"]');
  await comprobar(page, vista, '22-anular-incierto', { contiene: ['No sabemos si se anuló', 'Reintentar es seguro'] });
  anotar((await foco(page)) === 'Reintentar', `${v} el foco inicial está en «Reintentar»`);
  await clic(page, '[data-accion="reintentar-anular"]');
  await comprobar(page, vista, '23-anulacion-reconciliada', { contiene: ['Su anulación quedó registrada', 'por usted', 'ANULADA · no dispensar', '«Pauta duplicada con la de urgencias»'], noContiene: ['Anular'] });
  const c4 = await contar(page);
  anotar(c4.trazas.filter((t) => t === 'PRESCRIPCION_ANULADA').length === 1, `${v} una sola anulación trazada aunque se envió dos veces`);

  // ===== Roles: sin permiso override (B-11) y Expediente caído =====
  await ingresar(page, 'dr-ruiz');
  await elegir(page, '#paciente', 'P-002');
  await item(page, 1, 'CEFA500', '500', '6', null, '7');
  await clic(page, '[data-accion="verificar"]');
  await clic(page, '[data-accion="justificar"]');
  await comprobar(page, vista, '24-sin-override', { contiene: ['No puede autorizar esta advertencia', 'M-07'], noContiene: ['Justificación clínica', 'Emitir con justificación'] });
  anotar((await foco(page)) === 'Volver', `${v} B-11 la hoja no tiene área de texto; el foco inicial está en «Volver»`);
  await page.keyboard.press('Escape'); await espera(150);
  anotar(!(await page.evaluate(() => !!document.querySelector('[role="dialog"]'))) && (await foco(page)) === 'Justificar y emitir', `${v} Esc cierra la hoja y el foco vuelve al botón que la abrió`);
  await clic(page, '#nav a[href="#/mas"]');
  await clic(page, '[data-demo="expedienteCaido"]');
  await clic(page, '#nav a[href="#/prescribir"]');
  await clic(page, '[data-accion="verificar"]');
  await comprobar(page, vista, '25-expediente-caido', { contiene: ['No se pudo consultar las alergias', 'No podrá emitir hasta que responda', 'Reintentar'], noContiene: ['Sin conflictos'] });

  // ===== Semana 12 (cambio solicitado): RBAC retira el permiso override a mitad de la sesión del Dr. López =====
  // La copia de permisos de la sesión dice que lo tiene; la API dice puede_justificar=false. Manda la API.
  await clic(page, '#nav a[href="#/mas"]');
  await clic(page, '[data-demo="expedienteCaido"]'); // vuelve el Expediente
  await ingresar(page, 'dr-lopez');
  await clic(page, '#nav a[href="#/mas"]');
  await clic(page, '[data-accion="retirar-override"]');
  await clic(page, '#nav a[href="#/prescribir"]');
  await elegir(page, '#paciente', 'P-002');
  await item(page, 1, 'CEFA500', '500', '6', null, '7');
  await clic(page, '[data-accion="verificar"]');
  await clic(page, '[data-accion="justificar"]');
  await comprobar(page, vista, '26-rbac-retira-override', { contiene: ['No puede autorizar esta advertencia', 'M-07'], noContiene: ['Justificación clínica', 'Emitir con justificación'] });
  const copia = await page.evaluate(() => window.DATOS.permisos['dr-lopez|hosp-norte'].includes('prescripcion.override'));
  anotar(copia, `${v} RF-20 la copia de la sesión todavía dice «override», pero la hoja la decidió la API (puede_justificar=false)`);
  await page.close();
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  for (const vista of Object.keys(VISTAS)) await recorrer(navegador, vista);
  await navegador.close();
  console.log(`\nComprobaciones: ${comprobaciones}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
