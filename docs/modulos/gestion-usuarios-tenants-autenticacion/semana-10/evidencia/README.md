# Verificación de pantallas

2026-10-28. Edge headless + Playwright, fuentes SVG estáticas. Desde raíz:

```powershell
npm install --prefix "$env:TEMP/shi-browser-qa" playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE="$env:TEMP/shi-browser-qa/node_modules/playwright"
$env:BROWSER_PATH='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node docs/modulos/gestion-usuarios-tenants-autenticacion/semana-10/verificacion/medir.cjs
```

La suite comprueba encaje de texto en 15 SVG de 320/375/430 px y genera cinco capturas a 375 px. No mide interacción, CSS responsive, datos largos, zoom, teclado o comportamiento de seguridad. Las pantallas son ilustraciones de propuesta.