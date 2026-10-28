# Reproducción

Fecha: 2026-10-28. SVG de diseño en Edge headless. Desde raíz del worktree, PowerShell:

```powershell
npm install --prefix "$env:TEMP/shi-browser-qa" playwright --no-audit --no-fund
$env:PLAYWRIGHT_MODULE="$env:TEMP/shi-browser-qa/node_modules/playwright"
$env:BROWSER_PATH='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe'
node docs/modulos/investigacion-prototipo-nativephp/semana-10/verificacion/medir.cjs
```

15 archivos a 320/375/430; las mediciones registran texto fuera del lienzo (se exige lista vacía); cinco capturas a 375. La prueba no mide responsive CSS, interacción, caracteres futuros ni runtime NativePHP. Las variantes son diseños estáticos, sin estado real de HIS/OS.