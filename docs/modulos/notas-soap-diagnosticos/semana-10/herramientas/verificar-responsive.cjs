// Semana 10 · Captura el prototipo en cada ancho y verifica las reglas de breakpoint medibles.
// Uso: node herramientas/verificar-responsive.cjs prototipo capturas [ruta-de-chrome]
// Requiere playwright-core (npm install --no-save playwright-core). Termina con código 1 si alguna regla falla.
const { chromium } = require('playwright-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-notas', 'base', 320, 568], ['m1-notas', 'base', 360, 740], ['m1-notas', 'base', 430, 860],
  ['m1-notas', 'base', 768, 1024], ['m1-notas', 'base', 1024, 768], ['m1-notas', 'carga', 360, 740],
  ['m1-notas', 'sin-conexion', 360, 740], ['m1-notas', 'enfermeria', 360, 740],
  ['m1-notas', 'enf-carga', 360, 740], ['m1-notas', 'enf-sin-conexion', 360, 740],
  ['m2-nueva-nota', 'paso1', 360, 740], ['m2-nueva-nota', 'paso2', 360, 740], ['m2-nueva-nota', 'paso3', 360, 740],
  ['m2-nueva-nota', 'paso4', 360, 740], ['m2-nueva-nota', 'errores', 360, 740], ['m2-nueva-nota', 'guardando', 320, 568],
  ['m2-nueva-nota', 'incierto', 320, 568], ['m2-nueva-nota', 'guardada', 320, 568], ['m2-nueva-nota', 'paso3', 320, 568],
  ['m3-detalle', 'base', 360, 740], ['m3-detalle', 'hoja-firma', 360, 740], ['m3-detalle', 'hoja-firma', 768, 1024],
  ['m3-detalle', 'firmada', 360, 740], ['m3-detalle', 'incierto-firma', 360, 740], ['m3-detalle', 'reemplazada', 360, 740],
  ['m3-detalle', 'enfermeria', 320, 568],
  ['m4-corregir', 'base', 360, 740], ['m4-corregir', 'resumen', 360, 740], ['m4-corregir', 'ya-corregida', 360, 740],
  ['m5-avisos', 'denegado', 360, 740], ['m5-avisos', 'denegado', 320, 568], ['m5-avisos', 'sesion', 360, 740],
  ['m5-avisos', 'sin-conexion', 360, 740],
];

const MIN_TOQUE = 44;        // px CSS: R-B2 (WCAG 2.5.8 pide 24; se usan 44 por uso de pie, con guantes y en movimiento)
const MIN_FUENTE_CAMPO = 16; // px: R-B3 (evita el zoom automático de iOS al enfocar un campo)

(async () => {
  const navegador = await chromium.launch({ executablePath: chrome, headless: true });
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: alto }, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const pagina = await contexto.newPage();
    await pagina.goto(pathToFileURL(path.join(proto, archivo + '.html')).href + '?estado=' + estado);
    const r = await pagina.evaluate(([minToque, minFuente]) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); return b.width > 0 && b.height > 0 && getComputedStyle(el).visibility !== 'hidden' && !el.closest('[inert]'); };
      const desborde = document.documentElement.scrollWidth - window.innerWidth;
      const chicos = [...document.querySelectorAll('button, a[href], input, select, textarea, summary, [role=option], [role=button]')]
        .filter(visible).filter((el) => !el.closest('p, dd, td'))
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()} «${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}» ${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input, select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const visibleBox = (sel) => { const el = document.querySelector(sel); return !!el && el.getBoundingClientRect().height > 0; };
      return { desborde, chicos, fuentes, hospital: visibleBox('.barra .hospital'), paciente: document.querySelector('.paciente') ? visibleBox('.paciente') : null };
    }, [MIN_TOQUE, MIN_FUENTE_CAMPO]);
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    await contexto.close();
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.hospital && r.paciente !== false;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(36)} desborde=${r.desborde}px  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  hospital=${r.hospital ? 'sí' : 'NO'}  paciente=${r.paciente === null ? '—' : (r.paciente ? 'sí' : 'NO')}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
