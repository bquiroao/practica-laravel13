// Semana 11 · Recorre el prototipo como una persona (clics, escritura, teclado) en escritorio y teléfono; en cada paso
// comprueba el resultado, mide accesibilidad con axe-core y guarda la captura. Uso (desde semana-11): node herramientas/recorrido.cjs [chrome] [axe.min.js]
const { chromium } = require('playwright-core'), fs = require('fs'), path = require('path');
const [chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe', axe = 'node_modules/axe-core/axe.min.js'] = process.argv.slice(2);
const url = 'file:///' + path.resolve('prototipo/index.html').replace(/\\/g, '/');
const VISTAS = [['escritorio', 1280, 800], ['movil', 360, 740]], resultados = {};
(async () => {
  const navegador = await chromium.launch({ executablePath: chrome });
  for (const [vista, ancho, alto] of VISTAS) {
    const p = await navegador.newPage({ viewport: { width: ancho, height: alto }, deviceScaleFactor: ancho < 768 ? 2 : 1 });
    fs.mkdirSync(`capturas/${vista}`, { recursive: true });
    const texto = (s) => p.locator(s).first().innerText();
    const clic = (nombre) => p.getByRole('button', { name: nombre, exact: true }).or(p.getByRole('link', { name: nombre, exact: true })).first().click();
    const paso = async (id, nombre, comprobar) => {
      await p.waitForTimeout(1200); // la API simulada tarda 400 ms por solicitud
      const ok = await comprobar().catch(() => false);
      await p.addScriptTag({ path: axe });
      const axeFallas = (await p.evaluate(() => axe.run({ runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] }))).violations.map((v) => v.id);
      const desborde = await p.evaluate(() => document.documentElement.scrollWidth - innerWidth);
      await p.screenshot({ path: `capturas/${vista}/${id}.png` });
      (resultados[id] ??= { nombre, vistas: [] }).vistas.push({ vista, ok: ok && !axeFallas.length && desborde <= 0, detalle: `${ok ? '' : ' comprobación'}${axeFallas.length ? ` axe: ${axeFallas}` : ''}${desborde > 0 ? ` desborde ${desborde}px` : ''}` });
    };
    const escribirNota = async () => {
      await clic('+ Nueva nota');
      await p.fill('#subjetivo', 'Tos productiva y fiebre de 3 días'); await p.fill('#objetivo', 'T 38.6 °C, estertores en base derecha'); await clic('Siguiente');
      await p.fill('#analisis', 'Probable neumonía adquirida en la comunidad'); await p.fill('#plan', 'Radiografía de tórax y antibiótico'); await clic('Siguiente');
      await p.locator('#buscar').pressSequentially('neumon');
    };
    // Camino feliz: la Dra. Méndez registra una nota y la firma; enfermería la lee.
    await p.goto(url);
    await paso('cf-01-ingresar', 'Elegir persona', async () => (await p.title()) === 'Ingresar · HIS');
    await clic('Dra. Méndez · Médico');
    await paso('cf-02-notas', 'Lista de notas con «+ Nueva nota»', async () => p.getByText('+ Nueva nota').isVisible());
    await clic('+ Nueva nota');
    await paso('cf-03-paso1', 'Paso 1: paciente visible arriba', async () => (await texto('#paciente')).includes('Carlos R.'));
    await clic('Cancelar'); await escribirNota();
    await paso('cf-04-buscar-cie10', 'Paso 3: buscar «neumon» muestra J18.9', async () => (await texto('#lista')).includes('J18.9'));
    await p.locator('[data-codigo="J18.9"]').press('Enter'); await clic('Siguiente');
    await paso('cf-05-revisar', 'Paso 4: resumen con paciente y diagnóstico', async () => (await texto('.resumen')).includes('J18.9'));
    await clic('Guardar nota');
    await paso('cf-06-guardada', 'Nota guardada: el aviso recibe el foco', async () => p.evaluate(() => document.activeElement.id === 'aviso'));
    await clic('Firmar nota');
    await paso('cf-07-hoja-firma', 'Hoja de firma: nombra la nota, foco en «Cancelar», fondo inert', async () =>
      (await texto('#t-firma')).includes('versión 1 de Carlos R.') && p.evaluate(() => document.activeElement.id === 'cancelar' && document.querySelector('#fondo').inert));
    await p.locator('#firmar').click();
    await paso('cf-08-firmada', 'Nota firmada', async () => (await texto('#aviso')).includes('Nota firmada') && !(await p.locator('#abrir-firma').count()));
    await p.click('#abrir-demo'); await clic('Cambiar de persona'); await clic('Ana Pérez · Enfermería'); await p.locator('.ver:visible').first().click();
    await paso('cf-09-enfermeria-lee', 'Enfermería lee la nota: solo lectura', async () => (await texto('main')).includes('Solo lectura') && !(await p.locator('.accion-fija button').count()));
    // Error crítico: se pierde la respuesta al guardar y al firmar; reintentar no duplica y la firma no se repite sola.
    await p.goto(url + '?otra');
    await clic('Dra. Méndez · Médico'); await escribirNota(); await p.locator('[data-codigo="J18.9"]').click(); await clic('Siguiente');
    await p.click('#abrir-demo'); await clic('Perder la próxima respuesta'); await clic('Guardar nota');
    await paso('ec-01-guardado-incierto', 'Sin respuesta: «No sabemos si la nota se guardó» y «Reintentar»', async () =>
      (await texto('#aviso')).includes('No sabemos si la nota se guardó') && p.evaluate(() => document.activeElement.id === 'aviso' && API.totalNotas() === 2));
    await clic('Reintentar');
    await paso('ec-02-reintento-misma-nota', 'Reintento con la misma clave: sigue habiendo 1 nota nueva', async () => (await texto('#aviso')).includes('guardada') && p.evaluate(() => API.totalNotas() === 2));
    await p.click('#abrir-demo'); await clic('Perder la próxima respuesta'); await clic('Firmar nota'); await p.locator('#firmar').click();
    await paso('ec-03-firma-incierta', 'Firma sin respuesta: solo «Consultar estado», no «Firmar»', async () =>
      (await texto('#aviso')).includes('No sabemos si la firma llegó') && !(await p.locator('#abrir-firma').count()));
    await clic('Consultar estado de la nota');
    await paso('ec-04-firma-confirmada', 'La firma sí llegó: nota firmada', async () => (await texto('#aviso')).includes('La firma sí llegó'));
    await p.click('#abrir-demo'); await clic('Cambiar de persona'); await clic('Ana Pérez · Enfermería'); await p.evaluate(() => { location.hash = '#/notas/nueva'; });
    await paso('ec-05-ruta-protegida', 'Enfermería abre la ruta de nueva nota: acceso denegado sin datos clínicos', async () =>
      (await p.title()) === 'Acceso denegado · HIS' && !(await texto('main')).includes('neumonía'));
    await p.close();
  }
  await navegador.close();
  let fallas = 0;
  for (const [id, { nombre, vistas }] of Object.entries(resultados)) {
    fallas += vistas.filter((v) => !v.ok).length;
    console.log(`${vistas.every((v) => v.ok) ? 'OK   ' : 'FALLA'} ${id.padEnd(28)} ${nombre}${vistas.map((v) => v.ok ? '' : `  [${v.vista}:${v.detalle}]`).join('')}`);
  }
  console.log(`\nPasos: ${Object.keys(resultados).length} × ${VISTAS.length} vistas  Comprobaciones: ${Object.keys(resultados).length * VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
