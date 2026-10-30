// Captura el prototipo en cada ancho y verifica reglas de breakpoint medibles (semana 10, Pacientes).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-buscar', 'base', 320, 568], ['m1-buscar', 'base', 360, 740], ['m1-buscar', 'base', 430, 860],
  ['m1-buscar', 'base', 768, 1024], ['m1-buscar', 'base', 1024, 768], ['m1-buscar', 'vacio', 360, 740],
  ['m1-buscar', 'carga', 360, 740], ['m1-buscar', 'sin-conexion', 360, 740],
  // escenario 1: registro con red lenta (360 px)
  ['m2-registrar', 'paso1', 360, 740], ['m2-registrar', 'paso2', 360, 740], ['m2-registrar', 'base', 360, 740],
  ['m2-registrar', 'enviando', 360, 740], ['m2-registrar', 'incierto', 360, 740], ['m2-registrar', 'exito', 360, 740],
  ['m2-registrar', 'paso1', 320, 568], ['m2-registrar', 'paso2', 320, 568], ['m2-registrar', 'incierto', 320, 568],
  ['m2-registrar', 'duplicado', 360, 740], ['m2-registrar', 'coincidencia', 360, 740], ['m2-registrar', 'base', 1024, 768],
  ['m3-ficha', 'base', 360, 740], ['m3-ficha', 'base', 430, 860], ['m3-ficha', 'base', 1024, 768],
  ['m3-ficha', 'reducida', 360, 740], ['m3-ficha', 'reducida', 320, 568], ['m3-ficha', 'carga', 360, 740],
  ['m3-ficha', 'sin-conexion', 360, 740],
  // escenario 2: edición con red inestable (320 px)
  ['m3-ficha', 'base', 320, 568], ['m4-editar', 'base', 320, 568], ['m4-editar', 'confirmar', 320, 568],
  ['m4-editar', 'incierto', 320, 568], ['m4-editar', 'conflicto', 320, 568],
  ['m4-editar', 'motivo', 360, 740], ['m4-editar', 'confirmar', 768, 1024],
  ['m5-proteger', 'base', 360, 740], ['m5-proteger', 'base', 320, 568], ['m5-proteger', 'base', 768, 1024],
  ['m5-proteger', 'sin-conexion', 360, 740], ['m5-proteger', 'exito', 360, 740],
];

const MIN_TOQUE = 44;        // px CSS: regla R-T1 (WCAG 2.5.8 pide 24; usamos 44 por uso con guantes y en movimiento)
const MIN_FUENTE_CAMPO = 16; // px: regla R-T2 (evita el zoom automático de iOS al enfocar)
const DATOS_EN_URL = /[?&](nombre|nombres|apellidos|documento|numero_documento|nacimiento|telefono)=/i; // regla R-D1

(async () => {
  const navegador = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--hide-scrollbars'] });
  const pagina = await navegador.newPage();
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    await pagina.setViewport({ width: ancho, height: alto, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const url = pathToFileURL(path.join(proto, archivo + '.html')).href + (estado === 'base' ? '' : '?estado=' + estado);
    await pagina.goto(url, { waitUntil: 'load' });
    const r = await pagina.evaluate((minToque, minFuente, patron) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[inert]'); };
      const desborde = document.documentElement.scrollWidth - window.innerWidth;
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]), select, textarea, summary')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input, select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const hospitalVisible = [...document.querySelectorAll('.barra .hospital')].some((el) => el.getBoundingClientRect().height > 0);
      const datosEnUrl = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => new RegExp(patron).test(h));
      return { desborde, chicos, fuentes, hospitalVisible, datosEnUrl };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO, DATOS_EN_URL.source);
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.hospitalVisible && r.datosEnUrl.length === 0;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(36)} desborde=${r.desborde}px  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  hospital=${r.hospitalVisible ? 'sí' : 'NO'}  datos-en-url=${r.datosEnUrl.length}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
    r.datosEnUrl.forEach((c) => console.log('        dato personal en la URL: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
