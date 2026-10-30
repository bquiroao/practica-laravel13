// Captura el prototipo del módulo QA en cada ancho y verifica reglas de breakpoint medibles (semana 10).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-campanas', 'base', 320, 568], ['m1-campanas', 'base', 360, 740], ['m1-campanas', 'base', 390, 844], ['m1-campanas', 'base', 430, 932],
  ['m1-campanas', 'base', 768, 1024], ['m1-campanas', 'base', 1024, 768], ['m1-campanas', 'carga', 360, 740], ['m1-campanas', 'vacio', 360, 740],
  ['m1-campanas', 'sin-conexion', 320, 568], ['m1-campanas', 'lider', 390, 844],
  // escenario 1: el tester registra una FALLA en la sala de pruebas con señal débil (360 px)
  ['m2-ejecutar', 'base', 320, 568], ['m2-ejecutar', 'base', 360, 740], ['m2-ejecutar', 'base', 430, 932], ['m2-ejecutar', 'base', 1024, 768],
  ['m2-ejecutar', 'error', 360, 740], ['m2-ejecutar', 'confirmar', 360, 740], ['m2-ejecutar', 'confirmar', 768, 1024], ['m2-ejecutar', 'verifica', 390, 844],
  ['m3-registro', 'exito', 360, 740], ['m3-registro', 'pendiente', 360, 740], ['m3-registro', 'pendiente-clinica', 320, 568],
  ['m3-registro', 'reenviado', 360, 740], ['m3-registro', 'rechazado', 360, 740], ['m3-registro', 'pendiente', 1024, 768],
  // escenario 2: el Líder QA revisa qué bloquea el GO camino a la reunión de liberación (390 px)
  ['m4-trazabilidad', 'base', 320, 568], ['m4-trazabilidad', 'base', 390, 844], ['m4-trazabilidad', 'base', 430, 932],
  ['m4-trazabilidad', 'base', 1024, 768], ['m4-trazabilidad', 'cerrar', 390, 844], ['m4-trazabilidad', 'cerrar', 768, 1024],
  ['m4-trazabilidad', 'cerrada', 390, 844], ['m4-trazabilidad', 'producto', 360, 740],
];

const MIN_TOQUE = 44;        // R-T1 · px CSS (WCAG 2.5.8 pide 24; 44 por uso en movimiento)
const MIN_FUENTE_CAMPO = 16; // R-T2 · evita el zoom automático de iOS al enfocar
// R-D1 · ningún dato clínico ni contenido de evidencia en la URL; los ids y el estado sí
const DATOS_EN_URL = /[?&](contenido|evidencia|bytes|titulo|pasos|motivo|defecto)=/i;

(async () => {
  const navegador = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--hide-scrollbars'] });
  const pagina = await navegador.newPage();
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    await pagina.setViewport({ width: ancho, height: alto, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const url = pathToFileURL(path.join(proto, archivo + '.html')).href + (estado === 'base' ? '' : '?estado=' + estado);
    await pagina.goto(url, { waitUntil: 'load' });
    await pagina.evaluate(() => document.querySelector('[aria-invalid="true"]:not([hidden])')?.scrollIntoView({ block: 'center' }));
    const r = await pagina.evaluate((minToque, minFuente, patron, esCaso) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[hidden]') && !el.closest('details:not([open]) > :not(summary)'); };
      const anchoReal = document.documentElement.clientWidth;
      const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea, .hoja, .chip')].filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.right > anchoReal + 0.5 || b.left < -0.5; }).length;
      const desborde = Math.max(document.documentElement.scrollWidth - anchoReal, fuera);
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary, .btn')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      // Radios y casillas: el objetivo táctil real es su etiqueta (semana 9 B-04)
      const opciones = [...document.querySelectorAll('input[type=radio], input[type=checkbox]')].filter(visible).map((i) => i.closest('label')).filter((l) => l && visible(l))
        .filter((l) => l.getBoundingClientRect().height < minToque).map((l) => `label「${l.innerText.trim().slice(0, 24)}」alto ${Math.round(l.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]):not([type=file]), select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const alcanceVisible = [...document.querySelectorAll('.barra .hospital')].some((el) => el.getBoundingClientRect().height > 0);
      const datosEnUrl = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => new RegExp(patron).test(h));
      // R-P1 · al ejecutar, QUÉ se prueba (caso, versión, build) queda pegado bajo la barra y en el 25 % superior
      // AUN DESPUÉS DE DESPLAZARSE. La primera versión medía sin desplazar y una cabecera estática pasaba igual
      // (evidencia/verificacion-responsive-control-1-sin-desplazar.txt).
      const yAntes = window.scrollY;
      window.scrollTo(0, 400);
      const barra = document.querySelector('.barra').getBoundingClientRect().bottom;
      const caso = !esCaso || [...document.querySelectorAll('.caso-cabecera')].some((el) => { const b = el.getBoundingClientRect(); return visible(el) && Math.abs(b.top - barra) <= 1 && b.bottom <= window.innerHeight * 0.25; });
      window.scrollTo(0, yAntes);
      // R-B1 · ningún control deshabilitado (semana 9 B-03)
      const deshabilitados = document.querySelectorAll('button[disabled], input[disabled], select[disabled], textarea[disabled]').length;
      // R-S1 · cada chip de estado lleva texto, no solo color
      const chipsMudos = [...document.querySelectorAll('.chip')].filter(visible).filter((c) => c.innerText.replace(/[^\p{L}]/gu, '').length < 2).length;
      return { desborde, chicos: [...chicos, ...opciones], fuentes, alcanceVisible, datosEnUrl, caso, deshabilitados, chipsMudos };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO, DATOS_EN_URL.source, archivo.startsWith('m2'));
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.alcanceVisible && r.datosEnUrl.length === 0 && r.caso && r.deshabilitados === 0 && r.chipsMudos === 0;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(40)} desborde=${r.desborde}  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  alcance=${r.alcanceVisible ? 'sí' : 'NO'}  datos-en-url=${r.datosEnUrl.length}  caso-arriba=${r.caso ? 'sí' : 'NO'}  disabled=${r.deshabilitados}  chips-mudos=${r.chipsMudos}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
    r.datosEnUrl.forEach((c) => console.log('        dato en la URL: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
