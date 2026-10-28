# Reproducir verificaciones

2026-10-28, desde raíz del worktree. PHP 8.2+ con pdo_sqlite y Node para contrato:

```powershell
npm install --prefix "$env:TEMP/shi-api-qa" @apidevtools/swagger-parser --no-audit --no-fund
$env:OPENAPI_PARSER_MODULE="$env:TEMP/shi-api-qa/node_modules/@apidevtools/swagger-parser"
node docs/modulos/ordenes-laboratorio-emr/semana-05/verificacion/validate.cjs
php docs/modulos/ordenes-laboratorio-emr/semana-04/mvc/tests/run.php
php docs/modulos/ordenes-laboratorio-emr/semana-03/micro-his/tests/run.php
```

contrato.txt: validación estructural y límites de OpenAPI. regresion-semana-04.txt: 34 aserciones. regresion-semana-03.txt: 19. Sin endpoint operativo ni pruebas de identidad/idempotencia propuestas.