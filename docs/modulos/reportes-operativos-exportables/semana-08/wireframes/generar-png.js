// Genera un PNG por wireframe:  node generar-png.js   (requiere Playwright)
const { chromium } = require('playwright'); const path = require('path');
(async () => {
  const b = await chromium.launch(); const p = await b.newPage({ viewport: { width: 1020, height: 800 }, deviceScaleFactor: 1.5 });
  await p.goto('file://' + path.resolve(__dirname, 'wireframes.html'));
  for (const id of ['wf1', 'wf2', 'wf3', 'wf4', 'wf5', 'wf6']) { await p.locator('#' + id).screenshot({ path: path.join(__dirname, `semana-08-${id}.png`) }); }
  await b.close();
})();
