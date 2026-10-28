# Evidencia de inspección

Fecha 2026-10-28, base semana 8 fb3ed3f. Capturas reales de SVG en Edge headless con viewport 1100 × 700; no pantallas conectadas a API. Archivo inspeccion.json contiene estilos calculados, dos contrastes, detección del borde derecho y recuento de controles. No cubre todas las combinaciones de color, superposiciones, lector ni reflujo.

Desde raíz del worktree, PowerShell:

```powershell
npm install --prefix "$env:TEMP/shi-browser-qa" playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE="$env:TEMP/shi-browser-qa/node_modules/playwright"
$env:BROWSER_PATH='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node docs/modulos/gestion-usuarios-tenants-autenticacion/semana-09/verificacion/inspeccionar.cjs
```

Para reproducir se necesita Edge en la ruta indicada u otra mediante BROWSER_PATH. El script captura los seis archivos originales; los hallazgos semánticos provienen de inspección experta del diseño y están explicados en README, no son un resultado automatizado de WCAG.