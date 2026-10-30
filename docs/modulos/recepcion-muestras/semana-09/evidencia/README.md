# Reproducción de inspección

Fecha 2026-10-28; Edge headless/Playwright. Desde raíz del worktree:

```powershell
npm install --prefix "$env:TEMP/shi-browser-qa" playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE="$env:TEMP/shi-browser-qa/node_modules/playwright"
$env:BROWSER_PATH='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node docs/modulos/recepcion-muestras/semana-09/verificacion/inspeccionar.cjs
```

inspeccion.json registra contraste de cabecera, textos fuera del lienzo y controles. Seis capturas PNG de SVG a 1280 × 820. El hallazgo de falta de interacción limita artefacto, no acusa una aplicación operativa. No se probaron lector de pantalla, zoom, impresora, teclado funcional ni permisos.