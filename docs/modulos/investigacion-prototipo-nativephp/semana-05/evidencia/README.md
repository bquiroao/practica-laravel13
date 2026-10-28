# Reproducir verificación

Fecha: 2026-10-28. PHP 8.2.33; dependencia temporal @apidevtools/swagger-parser instalada con npm fuera del repositorio.

Desde raíz de este worktree, en PowerShell:

```powershell
php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php
npm install --prefix "$env:TEMP/shi-api-qa" @apidevtools/swagger-parser --no-audit --no-fund
$env:OPENAPI_PARSER_MODULE="$env:TEMP/shi-api-qa/node_modules/@apidevtools/swagger-parser"
node docs/modulos/investigacion-prototipo-nativephp/semana-05/verificacion/validate.cjs
```

Resultados guardados: regresion-semana-02.txt (14 aserciones), contrato.txt (estructura y límites estáticos). No hay pruebas de servidor ni notificación nativa. Diagramas son fuentes editables, sin afirmar renderizado.
