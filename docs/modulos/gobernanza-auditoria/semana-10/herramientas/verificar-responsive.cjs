// Captura el prototipo en cada ancho y verifica reglas de breakpoint medibles (semana 10, Auditoría).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-consultar', 'base', 320, 568], ['m1-consultar', 'base', 360, 740], ['m1-consultar', 'base', 430, 860],
  ['m1-consultar', 'base', 768, 1024], ['m1-consultar', 'base', 1024, 768], ['m1-consultar', 'error', 360, 740],
  ['m1-consultar', 'sin-conexion', 360, 740], ['m1-consultar', 'red', 320, 568], ['m1-consultar', 'confirmar-red', 320, 568],
  ['m1-consultar', 'confirmar-red', 768, 1024],
  // escenario 1: el auditor revisa los denegados de hoy con señal débil (360 px)
  ['m2-resultados', 'carga', 360, 740], ['m2-resultados', 'lenta', 360, 740], ['m2-resultados', 'base', 360, 740],
  ['m2-resultados', 'error-mas', 360, 740], ['m2-resultados', 'fin', 360, 740], ['m2-resultados', 'vacio', 360, 740],
  ['m2-resultados', 'base', 320, 568], ['m2-resultados', 'base', 430, 860], ['m2-resultados', 'base', 768, 1024],
  ['m2-resultados', 'base', 1024, 768], ['m2-resultados', 'red', 360, 740], ['m2-resultados', 'sin-conexion', 360, 740],
  ['m3-detalle', 'base', 360, 740], ['m3-detalle', 'correccion', 360, 740], ['m3-detalle', 'base', 320, 568],
  ['m3-detalle', 'carga', 360, 740], ['m3-detalle', 'no-encontrado', 360, 740], ['m3-detalle', 'sin-conexion', 360, 740],
  ['m3-detalle', 'base', 1024, 768],
  // escenario 2: el super administrador verifica la cadena con conexión intermitente (320 px)
  ['m4-verificar', 'base', 320, 568], ['m4-verificar', 'error', 320, 568], ['m4-verificar', 'confirmar', 320, 568],
  ['m4-verificar', 'verificando', 320, 568], ['m4-verificar', 'incierto', 320, 568], ['m4-verificar', 'integra', 320, 568],
  ['m4-verificar', 'alterada', 320, 568], ['m4-verificar', 'truncada', 360, 740], ['m4-verificar', 'sin-conexion', 360, 740],
  ['m4-verificar', 'base', 768, 1024],
];

const MIN_TOQUE = 44;        // px CSS: regla R-T1 (WCAG 2.5.8 pide 24; usamos 44 por uso en movimiento)
const MIN_FUENTE_CAMPO = 16; // px: regla R-T2 (evita el zoom automático de iOS al enfocar)
const DATOS_EN_URL = /[?&](desde|hasta|usuario|modulo|accion|resultado|cursor|sello)=/i; // regla R-D1 (protección de datos, semana 8)

(async () => {
  const navegador = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--hide-scrollbars'] });
  const pagina = await navegador.newPage();
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    await pagina.setViewport({ width: ancho, height: alto, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const url = pathToFileURL(path.join(proto, archivo + '.html')).href + (estado === 'base' ? '' : '?estado=' + estado);
    await pagina.goto(url, { waitUntil: 'load' });
    // Como el foco del navegador: en un estado de error, el campo inválido se ve en pantalla (no queda bajo el borde)
    await pagina.evaluate(() => document.querySelector('[aria-invalid="true"]')?.scrollIntoView({ block: 'center' }));
    const r = await pagina.evaluate((minToque, minFuente, patron) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[inert]') && !el.closest('details:not([open]) > :not(summary)'); };
      // En modo móvil Chrome ENSANCHA innerWidth cuando el contenido no cabe (320 → 362 con el defecto de las fechas):
      // la referencia es clientWidth, que conserva el ancho pedido. Se cuentan también controles fuera de la ventana.
      const anchoReal = document.documentElement.clientWidth;
      const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea, .hoja')].filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.right > anchoReal + 0.5 || b.left < -0.5; }).length;
      const desborde = Math.max(document.documentElement.scrollWidth - anchoReal, fuera);
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      // Los radios y casillas se miden por su etiqueta, que es el objetivo táctil real
      const opciones = [...document.querySelectorAll('input[type=radio], input[type=checkbox]')].map((i) => i.closest('label')).filter((l) => l && visible(l))
        .filter((l) => l.getBoundingClientRect().height < minToque).map((l) => `label「${l.innerText.trim().slice(0, 24)}」alto ${Math.round(l.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]), select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const alcanceVisible = [...document.querySelectorAll('.barra .hospital')].some((el) => el.getBoundingClientRect().height > 0);
      const datosEnUrl = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => new RegExp(patron).test(h));
      return { desborde, chicos: [...chicos, ...opciones], fuentes, alcanceVisible, datosEnUrl };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO, DATOS_EN_URL.source);
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.alcanceVisible && r.datosEnUrl.length === 0;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(38)} desborde=${r.desborde}  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  alcance=${r.alcanceVisible ? 'sí' : 'NO'}  datos-en-url=${r.datosEnUrl.length}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
    r.datosEnUrl.forEach((c) => console.log('        dato de la consulta en la URL: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
