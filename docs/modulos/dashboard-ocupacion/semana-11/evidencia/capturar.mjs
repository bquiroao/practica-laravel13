// Captura el prototipo (escritorio 1280 px, móvil 360 px) y mide el scroll horizontal a 320, 360 y 430 px con Edge sin interfaz y el protocolo DevTools, que fija el ancho exacto.
// Uso: node capturar.mjs <index.html> <carpeta de capturas> <nombre=ruta[@acción JS]> ...   (EDGE=ruta del ejecutable, opcional)
// La acción, si existe, se ejecuta en la página para recorrer el flujo como lo haría el usuario (llenar y pulsar).
import { spawn } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const [indice, salida, ...pares] = process.argv.slice(2);
const rutas = pares.map((p) => { const [nombre, resto] = p.split(/=(.*)/s); const [ruta, accion] = resto.split(/@(.*)/s); return [nombre, ruta, accion]; });
const EDGE = process.env.EDGE ?? 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const perfil = mkdtempSync(join(tmpdir(), 'edge-')); // perfil propio: el puerto queda en su DevToolsActivePort
const VISTAS = { escritorio: [1280, 800], movil: [360, 780] };
const ANCHOS_MEDIDOS = [320, 360, 430];

const navegador = spawn(EDGE, ['--headless=new', '--disable-gpu', '--remote-debugging-port=0', // 0: Edge elige un puerto libre
  `--user-data-dir=${perfil}`, 'about:blank'], { stdio: 'ignore' });
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));

async function conectar() {
  for (let i = 0; i < 50; i++) {
    try {
      const [puerto] = readFileSync(join(perfil, 'DevToolsActivePort'), 'utf8').split('\n');
      const paginas = await (await fetch(`http://127.0.0.1:${puerto}/json/list`)).json();
      const pagina = paginas.find((p) => p.type === 'page');
      if (pagina) return new WebSocket(pagina.webSocketDebuggerUrl);
    } catch { /* el navegador todavía no abre el puerto */ }
    await esperar(200);
  }
  throw new Error('Edge no abrió el puerto de depuración');
}

const ws = await conectar();
await new Promise((r) => ws.addEventListener('open', r, { once: true }));
let siguiente = 0;
const pendientes = new Map();
ws.addEventListener('message', ({ data }) => {
  const m = JSON.parse(data);
  if (m.id && pendientes.has(m.id)) { pendientes.get(m.id)(m); pendientes.delete(m.id); }
});
const cdp = (method, params = {}) => new Promise((ok, falla) => {
  const id = ++siguiente;
  pendientes.set(id, (m) => (m.error ? falla(new Error(`${method}: ${m.error.message}`)) : ok(m.result)));
  ws.send(JSON.stringify({ id, method, params }));
});

async function abrir(ruta, accion, ancho, alto) {
  await cdp('Emulation.setDeviceMetricsOverride', { width: ancho, height: alto, deviceScaleFactor: 1, mobile: ancho < 500 });
  await cdp('Page.navigate', { url: 'about:blank' });
  await cdp('Page.navigate', { url: `${pathToFileURL(resolve(indice)).href}#${ruta}` });
  await esperar(400);
  if (accion) { await cdp('Runtime.evaluate', { expression: accion }); await esperar(400); }
  const { result } = await cdp('Runtime.evaluate', { returnByValue: true, expression:
    '({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth, titulo: document.title, foco: document.activeElement.tagName })' });
  return result.value;
}

try {
  await cdp('Page.enable');
  const lineas = ['Ruta | Ancho | scrollWidth | clientWidth | Scroll horizontal | Título | Foco'];
  for (const [nombre, ruta, accion] of rutas) {
    for (const [vista, [ancho, alto]] of Object.entries(VISTAS)) {
      await abrir(ruta, accion, ancho, alto);
      const { cssContentSize: c } = await cdp('Page.getLayoutMetrics');
      const { data } = await cdp('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true,
        clip: { x: 0, y: 0, width: ancho, height: Math.ceil(c.height), scale: 1 } });
      mkdirSync(join(salida, vista), { recursive: true });
      writeFileSync(join(salida, vista, `${nombre}.png`), Buffer.from(data, 'base64'));
    }
    for (const ancho of ANCHOS_MEDIDOS) {
      const m = await abrir(ruta, accion, ancho, 780);
      lineas.push(`${nombre} (#${ruta}) | ${ancho} | ${m.sw} | ${m.cw} | ${m.sw > m.cw ? 'SÍ' : 'no'} | ${m.titulo} | ${m.foco}`);
    }
  }
  console.log(lineas.join('\n'));
} finally {
  ws.close();
  navegador.kill();
}
