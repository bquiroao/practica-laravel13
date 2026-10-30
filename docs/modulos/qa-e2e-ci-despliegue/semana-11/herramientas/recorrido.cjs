// Recorre el camino feliz (A) y el error crítico (B) del prototipo navegable contra la API REAL, en escritorio y en móvil.
// En cada paso comprueba textos y foco, toma la captura y corre axe-core (WCAG 2.0/2.1/2.2 A y AA).
// Uso (con servidor.php levantado en BASE y la base recreable en E2E_DB_DSN):
//   node recorrido.cjs http://127.0.0.1:8092 <carpeta-capturas> <ruta-axe.min.js> [ruta-chrome]
// El pipeline no tiene pantalla: sus pasos (registrar versión, ejecutar, reportar, cerrar) van directo a la API.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { execFileSync } = require('child_process');
const crypto = require('crypto');

const [BASE, SALIDA, AXE, CHROME = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);
const API = BASE + '/api/v1/e2e';
const MICRO = path.join(__dirname, '..', '..', 'micro-his-e2e');
const digestDe = (s) => 'sha256:' + crypto.createHash('sha256').update(s).digest('hex');
const D241 = digestDe('his-2.4.1');
const D250 = digestDe('his-2.5.0');

let comprobaciones = 0;
let fallas = 0;
const ok = (cond, que) => { comprobaciones++; if (!cond) fallas++; console.log(`${cond ? 'OK   ' : 'FALLA'} ${que}`); };

async function api(metodo, ruta, token, cuerpo, clave) {
  const h = { Authorization: 'Bearer ' + token, 'X-Tenant-ID': 'hosp-norte', 'Content-Type': 'application/json' };
  if (clave) h['Idempotency-Key'] = clave;
  const r = await fetch(API + ruta, { method: metodo, headers: h, body: cuerpo ? JSON.stringify(cuerpo) : undefined });
  return { estado: r.status, cuerpo: await r.json().catch(() => ({})) };
}
// El pipeline: registra la versión, ejecuta el plan y reporta. resultados = { escenario: [resultado, conEvidencia] }
async function pipeline(version, digest, resultados, { nueva = true } = {}) {
  if (nueva) {
    await api('POST', '/versiones', 'token-pipeline', { numero: version, commit: 'a1b2c3d', digest, modulos: ['admision'] });
    await api('POST', `/versiones/${version}/plan`, 'token-ing-qa', { escenarios: Object.keys(resultados) });
  }
  const e = await api('POST', `/versiones/${version}/ejecuciones`, 'token-pipeline', { digest, entorno: 'staging' });
  for (const [esc, [res, ev]] of Object.entries(resultados)) {
    const evidencias = ev ? [{ tipo: 'traza', contenido_base64: Buffer.from(`traza ${esc} SINT-0042`).toString('base64') }] : [];
    await api('POST', `/ejecuciones/${e.cuerpo.id}/resultados`, 'token-pipeline', { escenario: esc, resultado: res, intentos: 1, evidencias }, `rec-${version}-${e.cuerpo.id}-${esc}`);
  }
  await api('POST', `/ejecuciones/${e.cuerpo.id}/cierre`, 'token-pipeline', {});
  return e.cuerpo.id;
}

async function correr(navegador, nombre, vista) {
  console.log(`\n=== ${nombre} (${vista.width} px) ===`);
  execFileSync('php', [path.join(MICRO, 'bin', 'instalar-base.php')], { env: process.env, stdio: 'ignore' });
  const todosPasan = { 'E2E-101': ['PASA', false], 'E2E-102': ['PASA', false], 'E2E-103': ['PASA', false] };
  await pipeline('2.4.1', D241, todosPasan);                                     // A: lista para evaluar
  const e250 = await pipeline('2.5.0', D250, todosPasan);                        // B: la evalúa el responsable de release antes
  await api('POST', '/versiones/2.5.0/gate', 'token-release', { ejecucion: e250 });

  const pagina = await navegador.newPage();
  await pagina.setViewport(vista);
  const movil = vista.width < 768;
  const dir = path.join(SALIDA, movil ? 'movil' : 'escritorio');
  const listo = async () => {
    await pagina.waitForFunction(() => document.body.dataset.listo === location.hash && !document.querySelector('.cargando'), { timeout: 10000 });
  };
  const texto = () => pagina.evaluate(() => document.body.innerText);
  const clic = async (selOTexto) => {
    if (selOTexto.startsWith('#') || selOTexto.startsWith('[')) return pagina.click(selOTexto);
    const h = await pagina.evaluateHandle((t) => [...document.querySelectorAll('a, button')].find((b) => b.innerText.trim().startsWith(t) && b.offsetParent), selOTexto);
    if (!h.asElement()) throw new Error('No encontré «' + selOTexto + '»');
    await h.asElement().click();
  };
  const paso = async (id, que) => {
    await pagina.screenshot({ path: path.join(dir, id + '.png') });
    await pagina.addScriptTag({ path: AXE });
    const v = await pagina.evaluate(async () => (await axe.run(document, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'] })).violations
      .map((x) => `${x.id} (${x.nodes.length})`));
    ok(v.length === 0, `${id} · axe-core sin violaciones${v.length ? ': ' + v.join(', ') : ''}`);
    if (movil) {
      const m = await pagina.evaluate(() => {
        const vis = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && !el.closest('[hidden],[inert]'); };
        return { desborde: document.documentElement.scrollWidth - document.documentElement.clientWidth,
          chicos: [...document.querySelectorAll('a[href], button, textarea, input:not([type=radio]), .opcion')].filter(vis)
            .filter((el) => { const b = el.getBoundingClientRect(); return b.height < 44 || b.width < 44; }).map((el) => el.innerText.trim().slice(0, 20)) };
      });
      ok(m.desborde <= 0 && m.chicos.length === 0, `${id} · móvil: sin desborde y objetivos ≥ 44 px${m.chicos.length ? ' (chicos: ' + m.chicos.join(' | ') + ')' : ''}`);
    }
    console.log(`      ${id}: ${que}`);
  };

  // ---------------- A · camino feliz ----------------
  await pagina.goto(BASE + '/?versiones=2.4.1,2.5.0#/ingresar');
  await listo();
  ok((await texto()).includes('Ingresar (demostración)'), 'A1 · pantalla de ingreso');
  await clic('Release · resp-release');
  await listo();
  ok((await texto()).includes('Evaluar el gate de 2.4.1'), 'A1 · Release ve «Evaluar el gate de 2.4.1» (ejecución completada sin evaluar)');
  ok(!(await texto()).includes('Desplegar 2.'), 'A1 · Release no ve «Desplegar» (no tiene e2e.desplegar)');
  await paso('a01-versiones-release', 'Versiones del responsable de release');

  await clic('Ver ejecución #1');
  await listo();
  ok((await texto()).includes('3 de 3 con resultado'), 'A2 · la ejecución #1 muestra 3 de 3 con resultado');
  await paso('a02-ejecucion-completada', 'Ejecución #1 completada');

  await clic('Evaluar el gate de 2.4.1');
  await listo();
  ok((await texto()).includes('Resultado esperado: ✓ Aprobado') && (await pagina.$$('.resultado-regla.cumple')).length === 6,
    'A3 · semana 12: la vista previa del SERVIDOR anticipa Aprobado, con las 6 reglas (B-01)');
  ok((await pagina.$$('#reglas li')).length === 6, 'A3 · se listan las 6 reglas del servidor (semanas 8 y 10 mostraban 5)');
  await clic('#registrar');
  ok(await pagina.evaluate(() => document.activeElement.id === 'd-no'), 'A3 · al abrir el diálogo, el foco está en «Volver»');
  ok(await pagina.evaluate(() => document.querySelector('#principal').inert && document.querySelector('#nav').inert), 'A3 · el fondo queda inerte');
  for (let i = 0; i < 4; i++) await pagina.keyboard.press('Tab');
  ok(await pagina.evaluate(() => !!document.activeElement.closest('.hoja')), 'A3 · cuatro Tab no salen del diálogo');
  await paso('a03-confirmar-gate', 'Confirmación del gate');
  await pagina.keyboard.press('Escape');
  ok(await pagina.evaluate(() => document.activeElement.id === 'registrar' && !document.querySelector('.hoja')), 'A3 · Esc cierra y devuelve el foco a «Registrar la decisión del gate…»');
  await clic('#registrar');
  await clic('#d-si');
  await pagina.waitForSelector('#aviso');
  ok((await texto()).includes('Decisión registrada: ✓ Aprobado') && (await texto()).includes('Coincide con la vista previa'), 'A4 · la decisión la devuelve el servidor (Aprobado) y coincide con la vista previa');
  ok((await pagina.$$eval('.resultado-regla.cumple', (x) => x.length)) === 6, 'A4 · las 6 reglas, con «✓ cumple» en texto');
  ok(await pagina.evaluate(() => document.activeElement.id === 'aviso'), 'A4 · el foco va al resultado');
  await paso('a04-gate-aprobado', 'Gate registrado: Aprobado');

  await pagina.goto(BASE + '/?versiones=2.4.1,2.5.0#/ingresar');
  await listo();
  await clic('Operaciones · ops-luis');
  await listo();
  ok((await texto()).includes('Desplegar 2.4.1') && (await texto()).includes('vence el'), 'A5 · Operaciones ve «Desplegar 2.4.1» con su vencimiento');
  await paso('a05-versiones-ops', 'Versiones de Operaciones');

  await clic('Desplegar 2.4.1');
  await listo();
  await pagina.type('#digest', D241.slice(0, -1) + (D241.endsWith('0') ? '1' : '0'));
  await clic('#registrar');
  const f = await pagina.evaluate(() => {
    const c = document.querySelector('#digest');
    const e = document.querySelector('#err-digest').getBoundingClientRect();
    const abajo = document.querySelector('.accion-fija').getBoundingClientRect().top;
    return { foco: document.activeElement === c, invalido: c.getAttribute('aria-invalid') === 'true', distintos: document.querySelectorAll('#err-digest .distinto').length,
      aLaVista: e.bottom <= abajo + 1 && e.top >= document.querySelector('.contexto').getBoundingClientRect().bottom - 1, dialogo: !!document.querySelector('.hoja') };
  });
  ok(f.foco && f.invalido && !f.dialogo, 'A6 · digest distinto: no abre el diálogo, el foco va al campo inválido');
  ok(f.distintos === 1 && f.aLaVista, 'A6 · se marca 1 bloque distinto y el mensaje queda a la vista sobre la barra de acción (R-F1)');
  await paso('a06-digest-distinto', 'Digest distinto, señalado');

  await pagina.$eval('#digest', (c) => { c.value = ''; });
  await pagina.type('#digest', D241);
  await clic('#registrar');
  await clic('#d-si');
  await pagina.waitForSelector('#aviso');
  ok((await texto()).includes('✓ 2.4.1 desplegada en producción'), 'A7 · el servidor registra el despliegue de 2.4.1');
  await paso('a07-desplegado', 'Desplegada en producción');

  await clic('Ver trazabilidad de 2.4.1');
  await listo();
  const tz = await texto();
  ok(tz.includes('Gate evaluado') && tz.includes('Despliegue en produccion') && tz.includes('✓ Desplegada'), 'A8 · la trazabilidad une versión, ejecución, gate y despliegue');
  await paso('a08-trazabilidad', 'Trazabilidad de 2.4.1');

  // ---------------- B · error crítico: la aprobación se reemplaza mientras se instala ----------------
  await pagina.goto(BASE + '/?versiones=2.4.1,2.5.0#/versiones');
  await listo();
  await clic('Desplegar 2.5.0');
  await listo();
  await pagina.type('#digest', D250);
  await paso('b01-desplegar-2-5-0', 'Operaciones prepara 2.5.0, aprobada');

  // Mientras tanto (otros actores): el pipeline vuelve a ejecutar 2.5.0, E2E-102 FALLA, y el responsable de release evalúa.
  const e3 = await pipeline('2.5.0', D250, { 'E2E-101': ['PASA', false], 'E2E-102': ['FALLA', true], 'E2E-103': ['PASA', false] }, { nueva: false });
  const g = await api('POST', '/versiones/2.5.0/gate', 'token-release', { ejecucion: e3 });
  ok(g.cuerpo.decision === 'RECHAZADO', 'B2 · (otro actor) la nueva evaluación de 2.5.0 es RECHAZADO');

  await clic('#registrar');
  await clic('#d-si');
  await pagina.waitForSelector('#aviso');
  const b3 = await pagina.evaluate(() => ({ t: document.querySelector('#aviso').innerText, foco: document.activeElement.id, digest: document.querySelector('#digest').value }));
  ok(b3.t.includes('La última evaluación del gate ya no está aprobada'), 'B3 · el servidor rechaza con 409 GATE_NO_APROBADO y la UI lo explica (M-19)');
  ok(b3.foco === 'aviso' && b3.digest === D250, 'B3 · el foco va al mensaje y lo cargado se conserva (semana 9 B-09)');
  const ctx = await pagina.evaluate(() => ({ meta: document.querySelector('.contexto').innerText, registrar: !!document.querySelector('#registrar') }));
  ok(!ctx.meta.includes('Aprobada') && ctx.meta.includes('Sin aprobación vigente') && !ctx.registrar,
    'B3 · el contexto ya no dice «Aprobada» y no se ofrece registrar otra vez (revisión visual de la semana 11)');
  await paso('b03-gate-no-aprobado', '409 GATE_NO_APROBADO');

  await clic('Ver la última evaluación');
  await listo();
  const tb = await texto();
  ok(tb.indexOf('✓ Aprobado') > -1 && tb.indexOf('✕ Rechazado') > tb.indexOf('✓ Aprobado') && tb.includes('todavía no'), 'B4 · la trazabilidad muestra APROBADO y después RECHAZADO, y ningún despliegue');
  await paso('b04-trazabilidad-rechazado', 'Por qué no se desplegó');

  await pagina.goto(BASE + '/?versiones=2.4.1,2.5.0#/ingresar');
  await listo();
  await clic('Desarrollo · dev-ana');
  await listo();
  await pagina.goto(BASE + `/?versiones=2.4.1,2.5.0#/ejecucion/2.5.0/${e3}`);
  await listo();
  await pagina.waitForFunction(() => document.body.innerText.includes('con resultado'));
  const tb5 = await texto();
  ok(tb5.includes('✕ Falla') && (tb5.includes('Evidencia de E2E-102') || tb5.includes('traza de E2E-102')), 'B5 · Desarrollo ve la falla de E2E-102 con su evidencia (tarjeta en móvil, tabla en escritorio)');
  ok(!(await pagina.$('#nav a[href*="gate"]')) && !tb5.includes('Desplegar'), 'B5 · Desarrollo no ve Gate ni Desplegar');
  await paso('b05-ejecucion-dev', 'Desarrollo diagnostica');

  // Seguridad del recorrido: el token nunca viaja en la URL
  ok(!(await pagina.evaluate(() => location.href)).includes('token'), 'R-D1 · el token no aparece en la URL');
  await pagina.close();
}

(async () => {
  const navegador = await puppeteer.launch({ executablePath: CHROME, headless: true, args: ['--hide-scrollbars'] });
  await correr(navegador, 'Escritorio', { width: 1280, height: 800, deviceScaleFactor: 1 });
  await correr(navegador, 'Móvil', { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  await navegador.close();
  console.log(`\nComprobaciones: ${comprobaciones}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
