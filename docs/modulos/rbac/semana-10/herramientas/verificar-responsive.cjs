// Captura el prototipo en cada ancho y verifica reglas de breakpoint medibles (semana 10).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-roles', 'base', 320, 568], ['m1-roles', 'base', 360, 740], ['m1-roles', 'base', 430, 860],
  ['m1-roles', 'base', 768, 1024], ['m1-roles', 'base', 1024, 768], ['m1-roles', 'sin-conexion', 360, 740],
  ['m2-asignar', 'paso1', 360, 740], ['m2-asignar', 'paso2', 360, 740], ['m2-asignar', 'base', 360, 740],
  ['m2-asignar', 'base', 320, 568], ['m2-asignar', 'enviando', 360, 740], ['m2-asignar', 'incierto', 360, 740],
  ['m2-asignar', 'ya-asignado', 360, 740],
  // escenario 2: teléfono pequeño (320 px)
  ['m2-asignar', 'paso1', 320, 568], ['m2-asignar', 'paso2', 320, 568], ['m2-asignar', 'enviando', 320, 568],
  ['m2-asignar', 'incierto', 320, 568], ['m2-asignar', 'ya-asignado', 320, 568],
  ['m3-revocar', 'base', 360, 740], ['m3-revocar', 'base', 320, 568], ['m3-revocar', 'sin-conexion', 360, 740],
  ['m3-revocar', 'base', 768, 1024],
  ['m4-operacion', 'carga', 360, 740], ['m4-operacion', 'base', 360, 740], ['m4-operacion', 'base', 430, 860], ['m4-operacion', 'sin-conexion', 360, 740],
  ['m4-operacion', 'denegado', 360, 740], ['m4-operacion', 'denegado', 320, 568],
  ['m5-auditoria', 'base', 360, 740], ['m5-auditoria', '404', 320, 568], ['m5-auditoria', 'base', 1024, 768],
];

const MIN_TOQUE = 44;   // px CSS: regla R-T1 (WCAG 2.5.8 pide 24; usamos 44 por uso con guantes y en movimiento)
const MIN_FUENTE_CAMPO = 16; // px: regla R-T2 (evita el zoom automático de iOS al enfocar)

(async () => {
  const navegador = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--hide-scrollbars'] });
  const pagina = await navegador.newPage();
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    await pagina.setViewport({ width: ancho, height: alto, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const url = pathToFileURL(path.join(proto, archivo + '.html')).href + '?estado=' + estado;
    await pagina.goto(url, { waitUntil: 'load' });
    const r = await pagina.evaluate((minToque, minFuente) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[inert]'); };
      const desborde = document.documentElement.scrollWidth - window.innerWidth;
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]), select, textarea, summary, [role=option]')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input, select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const hospital = document.querySelector('.barra .hospital');
      const hospitalVisible = !!hospital && hospital.getBoundingClientRect().height > 0;
      return { desborde, chicos, fuentes, hospitalVisible };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO);
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.hospitalVisible;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(34)} desborde=${r.desborde}px  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  hospital=${r.hospitalVisible ? 'sí' : 'NO'}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
