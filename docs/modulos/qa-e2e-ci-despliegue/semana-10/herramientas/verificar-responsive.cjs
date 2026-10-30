// Captura el prototipo de la consola de release en cada ancho y verifica reglas de breakpoint medibles (semana 10).
// Uso: node verificar-responsive.cjs <carpeta-prototipo> <carpeta-capturas> [ruta-chrome]
// Requiere puppeteer-core. Termina con código 1 si alguna regla falla.
const puppeteer = require('puppeteer-core');
const path = require('path');
const { pathToFileURL } = require('url');

const [proto, salida, chrome = 'C:/Program Files/Google/Chrome/Application/chrome.exe'] = process.argv.slice(2);

// [archivo, estado, ancho, alto]
const VISTAS = [
  ['m1-versiones', 'base', 320, 568], ['m1-versiones', 'base', 360, 740], ['m1-versiones', 'base', 390, 844], ['m1-versiones', 'base', 430, 932],
  ['m1-versiones', 'base', 768, 1024], ['m1-versiones', 'base', 1024, 768], ['m1-versiones', 'carga', 360, 740], ['m1-versiones', 'vacio', 360, 740],
  ['m1-versiones', 'sin-conexion', 320, 568], ['m1-versiones', 'ops', 360, 740],
  // escenario 2: el responsable de release sigue la ejecución y registra la decisión camino a la reunión (390 px)
  ['m2-ejecucion', 'base', 320, 568], ['m2-ejecucion', 'base', 360, 740], ['m2-ejecucion', 'base', 390, 844], ['m2-ejecucion', 'base', 1024, 768],
  ['m2-ejecucion', 'pausada', 390, 844], ['m2-ejecucion', 'nuevo', 390, 844], ['m2-ejecucion', 'sin-conexion', 390, 844],
  ['m2-ejecucion', 'completada', 390, 844], ['m2-ejecucion', 'completada', 768, 1024],
  ['m3-gate', 'base', 320, 568], ['m3-gate', 'base', 390, 844], ['m3-gate', 'base', 430, 932], ['m3-gate', 'base', 1024, 768],
  ['m3-gate', 'confirmar', 390, 844], ['m3-gate', 'confirmar', 768, 1024], ['m3-gate', 'registrada', 390, 844], ['m3-gate', 'cambio', 390, 844],
  ['m3-gate', 'incompleta', 360, 740],
  // escenario 1: el operador registra el smoke desde el teléfono, en la sala de servidores (360 px)
  ['m4-desplegar', 'base', 320, 568], ['m4-desplegar', 'base', 360, 740], ['m4-desplegar', 'base', 430, 932], ['m4-desplegar', 'base', 1024, 768],
  ['m4-desplegar', 'digest-distinto', 320, 568], ['m4-desplegar', 'digest-distinto', 360, 740], ['m4-desplegar', 'listo', 360, 740],
  ['m4-desplegar', 'smoke-falla', 360, 740], ['m4-desplegar', 'confirmar-falla', 360, 740], ['m4-desplegar', 'confirmar', 360, 740], ['m4-desplegar', 'confirmar', 768, 1024],
  ['m4-desplegar', 'desplegado', 360, 740], ['m4-desplegar', 'revertido', 360, 740], ['m4-desplegar', 'vence-pronto', 390, 844],
  ['m4-desplegar', 'vencida', 360, 740],
];

const MIN_TOQUE = 44;        // R-T1 · px CSS (WCAG 2.5.8 pide 24; 44 por uso en movimiento)
const MIN_FUENTE_CAMPO = 16; // R-T2 · evita el zoom automático de iOS al enfocar
// R-D1 · ni el token ni el contenido de una evidencia viajan en la URL
const DATOS_EN_URL = /[?&](token|bearer|authorization|bytes|contenido|evidencia)=/i;

(async () => {
  const navegador = await puppeteer.launch({ executablePath: chrome, headless: true, args: ['--hide-scrollbars'] });
  const pagina = await navegador.newPage();
  let fallas = 0;
  for (const [archivo, estado, ancho, alto] of VISTAS) {
    await pagina.setViewport({ width: ancho, height: alto, deviceScaleFactor: 2, isMobile: ancho < 768, hasTouch: ancho < 1024 });
    const url = pathToFileURL(path.join(proto, archivo + '.html')).href + (estado === 'base' ? '' : '?estado=' + estado);
    await pagina.goto(url, { waitUntil: 'load' });
    // El desplazamiento al error lo hace la PÁGINA (estado.js), no el instrumento: así R-F1 mide el comportamiento real
    const r = await pagina.evaluate((minToque, minFuente, patron, conContexto, esDespliegue, esEjecucion, estado) => {
      const visible = (el) => { const b = el.getBoundingClientRect(); const s = getComputedStyle(el); return b.width > 0 && b.height > 0 && s.visibility !== 'hidden' && !el.closest('[hidden]') && !el.closest('details:not([open]) > :not(summary)'); };
      // R-F1 · con un error, el foco está en el campo inválido y el campo y su mensaje se ven enteros, entre lo fijo de
      // arriba (barra, contexto) y lo fijo de abajo (acción, navegación). Primera corrida: el bloque distinto del digest
      // quedaba bajo la barra de acción a 320 px. WCAG 2.4.11 y semana 9 B-03.
      const campo = [...document.querySelectorAll('[aria-invalid="true"]')].find(visible);
      let errorVisible = true;
      if (campo) {
        const arriba = Math.max(0, ...[...document.querySelectorAll('.barra, .contexto')].filter(visible).map((el) => el.getBoundingClientRect().bottom));
        const abajo = Math.min(window.innerHeight, ...[...document.querySelectorAll('.accion-fija, .nav-inferior')].filter(visible)
          .filter((el) => getComputedStyle(el).position === 'fixed').map((el) => el.getBoundingClientRect().top));
        const mensaje = document.getElementById(campo.getAttribute('aria-describedby'));
        errorVisible = document.activeElement === campo && [campo, mensaje].filter(Boolean)
          .every((el) => { const b = el.getBoundingClientRect(); return b.top >= arriba - 1 && b.bottom <= abajo + 1; });
      }
      const anchoReal = document.documentElement.clientWidth;
      const fuera = [...document.querySelectorAll('button, a[href], input, select, textarea, .hoja, .chip')].filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.right > anchoReal + 0.5 || b.left < -0.5; }).length;
      const desborde = Math.max(document.documentElement.scrollWidth - anchoReal, fuera);
      const chicos = [...document.querySelectorAll('button, a[href], input:not([type=checkbox]):not([type=radio]), select, textarea, summary, .btn')]
        .filter(visible)
        .filter((el) => { const b = el.getBoundingClientRect(); return b.height < minToque || b.width < minToque; })
        .map((el) => `${el.tagName.toLowerCase()}「${(el.innerText || el.value || el.getAttribute('aria-label') || '').trim().slice(0, 24)}」${Math.round(el.getBoundingClientRect().width)}×${Math.round(el.getBoundingClientRect().height)}`);
      // Radios: el objetivo táctil real es su etiqueta
      const opciones = [...document.querySelectorAll('input[type=radio], input[type=checkbox]')].map((i) => i.closest('label')).filter((l) => l && visible(l))
        .filter((l) => l.getBoundingClientRect().height < minToque).map((l) => `label「${l.innerText.trim().slice(0, 24)}」alto ${Math.round(l.getBoundingClientRect().height)}`);
      const fuentes = [...document.querySelectorAll('input:not([type=radio]):not([type=checkbox]), select, textarea')].filter(visible)
        .filter((el) => parseFloat(getComputedStyle(el).fontSize) < minFuente).map((el) => el.id);
      const alcanceVisible = [...document.querySelectorAll('.barra .hospital')].some((el) => el.getBoundingClientRect().height > 0);
      const datosEnUrl = [...document.querySelectorAll('a[href]')].map((a) => a.getAttribute('href')).filter((h) => new RegExp(patron).test(h));
      // R-C1 · QUÉ versión se ejecuta, evalúa o despliega queda pegado bajo la barra y en el 25 % superior, AUN DESPUÉS DE DESPLAZARSE
      const yAntes = window.scrollY;
      window.scrollTo(0, 400);
      const barra = document.querySelector('.barra').getBoundingClientRect().bottom;
      const contexto = !conContexto || [...document.querySelectorAll('.contexto')].some((el) => { const b = el.getBoundingClientRect(); return visible(el) && Math.abs(b.top - barra) <= 1 && b.bottom <= window.innerHeight * 0.25; });
      window.scrollTo(0, yAntes);
      // R-B1 · ningún control deshabilitado (semana 9 B-03)
      const deshabilitados = document.querySelectorAll('button[disabled], input[disabled], select[disabled], textarea[disabled], a[aria-disabled="true"]').length;
      // R-S1 · cada chip de estado lleva texto, no solo color o icono (semana 9 B-10)
      const chipsMudos = [...document.querySelectorAll('.chip')].filter(visible).filter((c) => c.innerText.replace(/[^\p{L}]/gu, '').length < 2).length;
      // R-G1 · al desplegar, todo digest a la vista está completo: sin «…» (semana 9 B-08)
      const digestCortado = !esDespliegue ? 0 : [...document.querySelectorAll('.digest, textarea.digest-campo')].filter(visible)
        .filter((el) => (el.value ?? el.innerText).includes('…') && !(el.tagName === 'TEXTAREA' && el.value === '')).length;
      // R-V1 · la ejecución en curso tiene región viva y un botón visible para pausar (semana 9 B-06)
      const enCurso = ['base', 'nuevo', 'pausada', 'sin-conexion'].includes(estado);
      const vivoOk = !esEjecucion || !enCurso || (document.querySelector('[aria-live]') !== null
        && (estado === 'sin-conexion' || [...document.querySelectorAll('button[aria-pressed]')].some(visible)));
      // R-X1 · desde XL la tabla REEMPLAZA a las tarjetas: con una tabla a la vista no queda ninguna tarjeta de escenario
      // (verificación de la semana 10: a 1024 px se veían las dos).
      const duplicado = [...document.querySelectorAll('table.tabla')].some(visible) && [...document.querySelectorAll('.tarjeta.escenario')].some(visible);
      return { duplicado, desborde, chicos: [...chicos, ...opciones], fuentes, alcanceVisible, datosEnUrl, contexto, deshabilitados, chipsMudos, digestCortado, vivoOk, errorVisible };
    }, MIN_TOQUE, MIN_FUENTE_CAMPO, DATOS_EN_URL.source, archivo !== 'm1-versiones', archivo === 'm4-desplegar', archivo === 'm2-ejecucion', estado);
    const nombre = `${archivo}-${estado}-${ancho}.png`;
    await pagina.screenshot({ path: path.join(salida, nombre) });
    const ok = r.desborde <= 0 && r.chicos.length === 0 && r.fuentes.length === 0 && r.alcanceVisible && r.datosEnUrl.length === 0
      && r.contexto && r.deshabilitados === 0 && r.chipsMudos === 0 && r.digestCortado === 0 && r.vivoOk && r.errorVisible && !r.duplicado;
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(38)} desborde=${r.desborde}  toque<${MIN_TOQUE}=${r.chicos.length}  fuente<${MIN_FUENTE_CAMPO}=${r.fuentes.length}  alcance=${r.alcanceVisible ? 'sí' : 'NO'}  datos-en-url=${r.datosEnUrl.length}  contexto-arriba=${r.contexto ? 'sí' : 'NO'}  disabled=${r.deshabilitados}  chips-mudos=${r.chipsMudos}  digest-cortado=${r.digestCortado}  vivo=${r.vivoOk ? 'sí' : 'NO'}  error-a-la-vista=${r.errorVisible ? 'sí' : 'NO'}  duplicado=${r.duplicado ? 'SÍ' : 'no'}`);
    r.chicos.forEach((c) => console.log('        objetivo chico: ' + c));
    r.fuentes.forEach((c) => console.log('        campo con fuente chica: ' + c));
    r.datosEnUrl.forEach((c) => console.log('        dato en la URL: ' + c));
  }
  await navegador.close();
  console.log(`\nVistas: ${VISTAS.length}  Fallan: ${fallas}`);
  process.exit(fallas ? 1 : 0);
})();
