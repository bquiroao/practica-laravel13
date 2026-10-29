// Semana 10: comprueba la pantalla responsive en celular y escritorio.  Uso: node verificar-responsive.js   (necesita playwright)
const { chromium } = require('playwright');
(async () => {
  const b = await chromium.launch(); let total = 0, fallas = 0;
  const ver = (id, ok, d) => { total++; fallas += ok ? 0 : 1; console.log(`${id.padEnd(7)} ${ok ? 'PASA ' : 'FALLA'} ${d}`); };
  for (const w of [320, 360, 390, 430, 768, 1024]) {
    const p = await b.newPage({ viewport: { width: w, height: 800 } });
    await p.goto('file://' + __dirname + '/consulta-responsive.html');
    const r = await p.evaluate(() => {
      const vis = (e) => e.offsetParent !== null || getComputedStyle(e).position === 'fixed';
      const peque = [...document.querySelectorAll('input,select,button,.tabs a')].filter(vis).filter((e) => e.getBoundingClientRect().height < 44 || e.getBoundingClientRect().width < 44).length;
      const letras = [...document.querySelectorAll('input,select,button')].filter(vis).filter((e) => parseFloat(getComputedStyle(e).fontSize) < 16).length;
      return { ancho: document.documentElement.scrollWidth, tarjetas: getComputedStyle(document.querySelector('.cards')).display !== 'none', tabla: getComputedStyle(document.querySelector('table')).display !== 'none', peque, letras };
    });
    const movil = w <= 430;
    ver(`R-${w}`, r.ancho <= w && r.peque === 0 && r.letras === 0 && r.tarjetas === (w < 768) && r.tabla === (w >= 768),
      `${w}px: sin desborde (${r.ancho}px), ${r.peque} objetivos menores a 44px, ${r.letras} textos menores a 16px, ${movil ? 'tarjetas' : w < 768 ? 'tarjetas' : 'tabla'}`);
    if ([320, 430, 1024].includes(w)) await p.screenshot({ path: `${__dirname}/consulta-${w}.png`, fullPage: true });
    await p.close();
  }
  await b.close(); console.log(`\nTotal: ${total}  Pasan: ${total - fallas}  Fallan: ${fallas}`); process.exit(fallas ? 1 : 0);
})();
