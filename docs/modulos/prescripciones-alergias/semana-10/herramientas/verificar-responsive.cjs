// Captura el prototipo en cada ancho y verifica reglas de breakpoint medibles (semana 10, Prescripciones).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-prescribir', 'base', 320, 568], ['m1-prescribir', 'base', 360, 740], ['m1-prescribir', 'base', 430, 860],
  ['m1-prescribir', 'base', 768, 1024], ['m1-prescribir', 'base', 1024, 768], ['m1-prescribir', 'error', 360, 740],
  ['m1-prescribir', 'deshacer', 360, 740], ['m1-prescribir', 'salir', 320, 568], ['m1-prescribir', 'sin-conexion', 360, 740],
  // escenario 1: el médico emite con advertencia en la ronda, con señal débil (360 px)
  ['m2-verificacion', 'carga', 360, 740], ['m2-verificacion', 'sin-hallazgos', 360, 740], ['m2-verificacion', 'advertencia', 360, 740],
  ['m2-verificacion', 'advertencia', 320, 568], ['m2-verificacion', 'advertencia', 1024, 768], ['m2-verificacion', 'bloqueo', 360, 740],
  ['m2-verificacion', 'sin-registros', 360, 740], ['m2-verificacion', 'no-disponible', 360, 740], ['m2-verificacion', 'justificar', 360, 740],
  ['m2-verificacion', 'justificar', 768, 1024], ['m2-verificacion', 'sin-override', 360, 740], ['m2-verificacion', 'confirmar', 320, 568],
  ['m3-emision', 'emitiendo', 360, 740], ['m3-emision', 'lenta', 360, 740], ['m3-emision', 'emitida', 360, 740],
  ['m3-emision', 'emitida', 1024, 768], ['m3-emision', 'rechazada', 360, 740], ['m3-emision', 'no-emitida', 360, 740],
  ['m3-emision', 'incierto', 360, 740], ['m3-emision', 'incierto', 320, 568], ['m3-emision', 'reintentando', 360, 740],
  ['m3-emision', 'ya-emitida', 360, 740], ['m3-emision', 'sin-conexion', 360, 740],
  // escenario 2: la médica de guardia anula con red intermitente (320 px)
  ['m4-prescripcion', 'base', 320, 568], ['m4-prescripcion', 'base', 360, 740], ['m4-prescripcion', 'base', 430, 860],
  ['m4-prescripcion', 'base', 1024, 768], ['m4-prescripcion', 'medico', 320, 568], ['m4-prescripcion', 'carga', 360, 740],
  ['m4-prescripcion', 'anulada', 360, 740], ['m4-prescripcion', 'no-encontrado', 360, 740], ['m4-prescripcion', 'sin-conexion', 360, 740],
  ['m4-prescripcion', 'anular', 320, 568], ['m4-prescripcion', 'anular', 768, 1024], ['m4-prescripcion', 'anular-error', 320, 568],
  ['m4-prescripcion', 'anular-incierto', 320, 568], ['m4-prescripcion', 'anulada-por-usted', 320, 568],
];

const MIN_TOQUE = 44;        // px CSS: regla R-T1 (WCAG 2.5.8 pide 24; usamos 44 por uso en movimiento)
const MIN_FUENTE_CAMPO = 16; // px: regla R-T2 (evita el zoom automático de iOS al enfocar)
// R-D1 (protección de datos, semana 8): ningún dato clínico viaja en la URL; los ids sí pueden
const DATOS_EN_URL = /[?&](medicamento|dosis|items|alergia|alergias|justificacion|motivo|via|dias)=/i;

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
    const r = await pagina.evaluate((minToque, minFuente, patron, conAlergias) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[inert]') && !el.closest('details:not([open]) > :not(summary)'); };
      // Chrome en modo móvil ENSANCHA innerWidth cuando el contenido no cabe: la referencia es clientWidth (lección de Auditoría, semana 10)
      const anchoReal = document.documentElement.clientWidth;
      const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea, .hoja, .toast')].filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.right > anchoReal + 0.5 || b.left < -0.5; }).length;
      const desborde = Math.max(document.documentElement.scrollWidth - anchoReal, fuera);
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary, .btn')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      // Las casillas se miden por su etiqueta, que es el objetivo táctil real (semana 9 B-03)
      const opciones = [...document.querySelectorAll('input[type=radio], input[type=checkbox]')].map((i) => i.closest('label')).filter((l) => l && visible(l))
        .filter((l) => l.getBoundingClientRect().height < minToque).map((l) => `label「${l.innerText.trim().slice(0, 24)}」alto ${Math.round(l.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]), select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const alcanceVisible = [...document.querySelectorAll('.barra .hospital')].some((el) => el.getBoundingClientRect().height > 0);
      const datosEnUrl = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => new RegExp(patron).test(h));
      // R-P1: al prescribir y verificar, las alergias se ven arriba, PEGADAS a lo fijo de encima (barra y aviso de red visible):
      // ni un hueco que deje ver el contenido de atrás ni contenido tapado. La primera versión solo pedía «en el 40 % superior»
      // y no vio la franja corrida 44 px (ver evidencia/verificacion-responsive-control-alergias.txt).
      const techo = Math.max(...[...document.querySelectorAll('.barra, .red')].filter(visible).map((el) => el.getBoundingClientRect().bottom));
      const alergias = !conAlergias || [...document.querySelectorAll('.alergias')].some((el) => { const b = el.getBoundingClientRect(); return visible(el) && Math.abs(b.top - techo) <= 1 && b.bottom <= window.innerHeight * 0.4; });
      // R-B1: ningún control usa disabled; se usa aria-disabled con la razón visible (semana 9 B-01)
      const deshabilitados = document.querySelectorAll('button[disabled], input[disabled], select[disabled], textarea[disabled]').length;
      return { desborde, chicos: [...chicos, ...opciones], fuentes, alcanceVisible, datosEnUrl, alergias, deshabilitados };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO, DATOS_EN_URL.source, archivo.startsWith('m1') || archivo.startsWith('m2'));
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.alcanceVisible && r.datosEnUrl.length === 0 && r.alergias && r.deshabilitados === 0;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(42)} desborde=${r.desborde}  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  alcance=${r.alcanceVisible ? 'sí' : 'NO'}  datos-en-url=${r.datosEnUrl.length}  alergias=${r.alergias ? 'sí' : 'NO'}  disabled=${r.deshabilitados}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
    r.datosEnUrl.forEach((c) => console.log('        dato clínico en la URL: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
