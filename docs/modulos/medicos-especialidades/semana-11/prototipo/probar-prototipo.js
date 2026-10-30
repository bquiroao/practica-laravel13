// Semana 11: recorre el prototipo en escritorio y en celular (camino feliz y error crítico) y guarda las capturas.  Uso: node probar-prototipo.js   (necesita playwright)
const { chromium } = require('playwright'); const sal = __dirname + '/../capturas/', sal12 = __dirname + '/../../semana-12/capturas/';
(async () => {
  const b = await chromium.launch(); let total = 0, fallas = 0;
  for (const [tipo, ancho, alto] of [['escritorio', 1280, 800], ['movil', 390, 844]]) {
    const p = await b.newPage({ viewport: { width: ancho, height: alto } }); await p.goto('file://' + __dirname + '/index.html');
    const ver = async (id, ok, d) => { total++; fallas += ok ? 0 : 1; console.log(`${id.padEnd(7)} ${ok ? 'PASA ' : 'FALLA'} [${tipo}] ${d}`); };
    const txt = (s) => p.locator(s).innerText(); const rol = (r) => p.selectOption('#rol', r); const ir = async (h, titulo) => { await p.evaluate((x) => (location.hash = x), h); await p.waitForSelector(`h1:has-text("${titulo}")`); }; const foto12 = (n) => p.screenshot({ path: `${sal12}${tipo}-${n}.png`, fullPage: true }); const foto = (n) => p.screenshot({ path: `${sal}${tipo}-${n}.png`, fullPage: true });
    // Error de colegiado repetido (con el foco en el campo)
    await ir('#/medicos/nuevo', 'Registrar médico'); await p.selectOption('#usuario', 'u-sofia'); await p.fill('#col', '11111'); await p.click('#ok');
    await p.waitForSelector('#col[aria-invalid=true]');
    await ver('E2E-01', (await txt('#col-e')).includes('ya tiene perfil') && await p.evaluate(() => document.activeElement.id) === 'col', 'colegiado repetido: mensaje junto al campo y foco en el campo');
    // Camino feliz: registrar → asociar → consultar
    await p.fill('#col', '33333'); await p.selectOption('#esp', 'esp-mi'); await p.click('#ok'); await p.waitForSelector('.ok');
    await ver('E2E-02', (await txt('.ok')).includes('Dra. Sofía Ruiz quedó registrada'), 'registrar médico: confirmación con lo guardado'); await foto('feliz-1-registro');
    await p.click('.ok a'); await p.waitForSelector('h1:has-text("Especialidades de")'); await p.selectOption('#esp', 'esp-ped'); await p.click('#ok'); await p.waitForSelector('.ok');
    await ver('E2E-03', (await txt('#vista')).includes('Pediatría') && (await txt('.ok')).includes('asociada'), 'asociar especialidad: aparece en la lista del médico');
    await p.click('#f button'); await p.waitForSelector('#desde-e');   // repetir la misma asociación
    await ver('E2E-04', (await txt('#desde-e')).includes('ya está vigente'), 'asociar de nuevo la misma especialidad: cruce de vigencia (409)');
    await rol('recepcion'); await ir('#/disponibilidad', 'Disponibilidad'); await p.selectOption('#esp', 'PED'); await p.click('#buscar'); await p.waitForSelector('#res .h');
    const t = await p.locator('#res').innerText();
    await ver('E2E-05', t.includes('Dra. Sofía Ruiz') && t.includes('08:00'), 'recepción ve las franjas de la médica recién registrada'); await foto('feliz-2-disponibilidad');
    // Error crítico: ausencia con citas en conflicto
    await rol('admin'); await ir('#/ausencias', 'Registrar ausencia'); await p.selectOption('#med', 'med-ana'); await p.click('#rev'); await p.waitForSelector('#conf');
    await ver('E2E-06', (await txt('#conf')).includes('2 citas') && await p.locator('#reg').isDisabled(), 'ausencia: lista 2 citas en conflicto y el botón final está inactivo'); await foto('error-1-conflicto');
    await p.check('#ent'); await p.click('#reg'); await p.waitForSelector('.ok'); await ver('E2E-07', (await txt('.ok')).includes('2 citas'), 'con la casilla marcada se registra y avisa cuántas citas reprogramar');
    await rol('recepcion'); await ir('#/disponibilidad', 'Disponibilidad'); await p.click('#buscar'); await p.waitForSelector('#res .h');
    const u = await p.locator('#res').innerText(); await ver('E2E-08', !u.includes('Ana Pérez') && u.includes('Luis Gómez'), 'tras la ausencia, la Dra. Pérez ya no aparece y el Dr. Gómez sí');
    // Errores de sistema y de permisos
    await p.check('#citasCaida'); await p.click('#buscar'); await p.waitForSelector('#aviso .al');
    await ver('E2E-09', (await txt('#aviso')).includes('No podemos ofrecer franjas') && (await p.locator('#res table, #res .card').count()) === 0, 'Citas no responde: error recuperable y ninguna franja'); await foto('error-2-citas-caida');
    await p.uncheck('#citasCaida'); await p.check('#sinConexion'); await p.click('#buscar'); await p.waitForSelector('#aviso .al');
    await ver('E2E-10', (await txt('#aviso')).includes('Sin conexión'), 'sin conexión: no muestra franjas guardadas'); await p.uncheck('#sinConexion');
    await ir('#/ausencias', 'Sin permiso');
    await ver('E2E-11', (await txt('#vista')).includes('Sin permiso') && !(await txt('#tabs')).includes('Ausencias'), 'recepción no ve la pestaña ni entra a Ausencias');
    // Cambio de la semana 12: el médico registra sus propias ausencias (y solo las suyas)
    await rol('medico'); await ir('#/ausencias', 'Registrar ausencia');
    await ver('E2E-12', (await p.locator('#med').count()) === 0 && (await txt('#vista')).includes('solo puede registrar sus propias ausencias') && (await txt('#tabs')).includes('Ausencias'), 'médico: ve la pestaña Ausencias y su nombre fijo, sin elegir a otro médico');
    await p.click('#rev'); await p.waitForSelector('#conf'); await p.check('#ent'); await foto12('cambio-medico-conflicto'); await p.click('#reg'); await p.waitForSelector('.ok');
    const a = await p.evaluate(() => ApiSimulada.db.ausencias.at(-1)); await ver('E2E-13', (await txt('.ok')).includes('2 citas') && a.medico === 'med-ana' && a.desde === '2026-11-02' && a.hasta === '2026-11-09', 'médico: registra su ausencia con las 2 citas en conflicto; «último día 08» se envía como hasta 09'); await foto12('cambio-medico-exito');
    await ver('E2E-14', await p.evaluate(() => ApiSimulada.registrarAusencia('medico', 'med-luis', { desde: '2026-11-02', hasta: '2026-11-09' }).then((r) => r.estado)) === 403, 'médico: la API rechaza con 403 una ausencia de otro médico');
    await p.close();
  }
  await b.close(); console.log(`\nTotal: ${total}  Pasan: ${total - fallas}  Fallan: ${fallas}`); process.exit(fallas ? 1 : 0);
})();
