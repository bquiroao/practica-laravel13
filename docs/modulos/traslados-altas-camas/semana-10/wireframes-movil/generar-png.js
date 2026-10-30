// Genera un PNG por pantalla móvil:  node generar-png.js   (requiere Playwright)
const { chromium } = require('playwright'); const path = require('path');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1180, height: 800 }, deviceScaleFactor: 1.5 });
  await p.goto('file://' + path.resolve(__dirname, 'moviles.html'));
  for (const id of ['m1', 'm2', 'm3', 'm4', 'm5']) { await p.locator('#' + id).screenshot({ path: path.join(__dirname, `semana-10-${id}.png`) }); }
  await b.close();
})();
