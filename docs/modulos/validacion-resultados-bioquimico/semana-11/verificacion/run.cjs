const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const {pathToFileURL}=require('node:url');
const path=require('node:path');
const fs=require('node:fs');
const assert=require('node:assert/strict');
(async()=>{
const base=path.resolve(process.argv[2] || path.join(__dirname,'..'));
const browser=await chromium.launch({executablePath:process.env.BROWSER_PATH || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless:true});
const log=[];
for(const [name,width,height] of [['desktop',1366,900],['movil',375,812],['movil-320',320,812],['movil-430',430,900]]){
 const page=await browser.newPage({viewport:{width,height}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(pathToFileURL(path.join(base,'prototipo/index.html')).href);
 const click=action=>page.locator(`[data-action="${action}"]`).click();
 await click('review');await click('validate');await click('save');await page.getByRole('heading',{name:'Decisión registrada'}).waitFor();
 assert.equal(await page.locator('.success').textContent(),'Validado · Simulación confirmada');
 if(['desktop','movil'].includes(name))await page.screenshot({path:path.join(base,`evidencia/${name}-camino-feliz.png`),fullPage:true});
 await click('list');await click('review');assert.equal(await page.locator('[data-action="validate"]').count(),0);
 await page.locator('#reset').click();await click('review');await page.locator('#conflict').check();await click('validate');await click('save');await page.getByRole('heading',{name:'El resultado cambió'}).waitFor();
 assert.equal(await page.locator('.success').count(),0);
 if(['desktop','movil'].includes(name))await page.screenshot({path:path.join(base,`evidencia/${name}-error-critico.png`),fullPage:true});
 await click('reload');assert.match(await page.locator('#content').textContent(),/Versión 2/);
 await click('reject');await click('review-reject');assert.match(await page.locator('#reason-error').textContent(),/Escribe un motivo/);
 await page.locator('#reason').fill('Muestra no apta <demo>');await click('review-reject');await click('cancel');assert.equal(await page.locator('#reason').inputValue(),'Muestra no apta <demo>');await click('review-reject');await click('save');await page.getByRole('heading',{name:'Decisión registrada'}).waitFor();
 assert.match(await page.locator('.success').textContent(),/Rechazado/);assert.match(await page.locator('dl').textContent(),/Muestra no apta <demo>/);
 await page.locator('#role').selectOption('CAPTURE');await page.getByRole('heading',{name:'Este rol no puede decidir'}).waitFor();assert.equal(await page.locator('[data-action="save"]').count(),0);
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
 assert.deepEqual(errors,[]);
 log.push(`PASS ${name} ${width}x${height}: validación, inmutabilidad final, conflicto sin éxito, recarga v2, motivo obligatorio, cancelar conserva motivo, rechazo trazable, rol restringido, sin desbordamiento horizontal ni errores JS.`);
 await page.close();
}
await browser.close();fs.writeFileSync(path.join(base,'evidencia/verificacion.txt'),'Edge headless + Playwright; 2026-10-27\n'+log.join('\n')+'\n');console.log(log.join('\n'));
})().catch(e=>{console.error(e);process.exitCode=1});