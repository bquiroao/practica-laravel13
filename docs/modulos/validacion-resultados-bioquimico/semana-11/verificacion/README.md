# Repetir la verificación

Requiere Node.js, Playwright y Edge instalado. No instala dependencias en el prototipo.

Ejemplo PowerShell desde este worktree:
```powershell
$qa = Join-Path $env:TEMP 'shi-browser-qa'
npm install --prefix $qa playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE = Join-Path $qa 'node_modules/playwright'
node docs/modulos/validacion-resultados-bioquimico/semana-11/verificacion/run.cjs
```

BROWSER_PATH permite indicar otro ejecutable Chromium. La prueba abre el archivo local, recorre decisiones y conflictos a cuatro tamaños y regenera capturas y evidencia. No prueba autenticación real ni servicios clínicos. Las capturas llevan la hora real de ejecución de la simulación.