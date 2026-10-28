# Micro-HIS de ingreso de resultados — Semana 4 (MVC + Repository)

Evolución del prototipo de la Semana 3. Mismo flujo («captura y corrección controlada de resultados pendientes»), con entrada MVC y dos adaptadores intercambiables del puerto `LabResultRepository`. PHP 8.2+ sin framework; `pdo_sqlite` solo se usa con el adaptador PDO.

## Estructura

- `src/Presentation/Cli/LabResultCommand.php`: front controller; enruta la acción.
- `src/Presentation/Mvc/Mvc.php`: `ResultForm` (valida formato), `ResultController` (coordina), `ResultView` (texto y código de salida), `Response`.
- `src/Application`: caso de uso y puertos (`LabResultRepository`, `ResultIdentifierGenerator`).
- `src/Domain`: versiones pendientes y validadas inmutables.
- `src/Persistence`: `PdoLabResultRepository` e `InMemoryLabResultRepository`, con la misma semántica.
- `bootstrap.php`: composición. `LAB_RESULTS_PERSISTENCE=pdo` (por defecto) o `memory`.
- `bin/demo.php`: demostración en memoria (captura, corrección, reintento obsoleto).
- `tests/run.php`: contrato ejecutado contra ambos adaptadores, caso de uso, MVC y reglas de arquitectura.

## Ejecución

```powershell
Get-ChildItem . -Filter '*.php' -Recurse | ForEach-Object { php -l $_.FullName }
php tests/run.php
php bin/demo.php
php bin/result.php --action=capture --item=ITEM-001 --value=5.4 --actor=TECH-01
```

Con `LAB_RESULTS_PERSISTENCE=memory` cada invocación empieza vacía; úsalo solo con `bin/demo.php` o pruebas. La base SQLite se guarda en `data/lab-results.sqlite` (o `LAB_RESULTS_DATABASE`).

## Límites

Sin validación clínica, autorización real, órdenes reales ni endpoint de validación. Los resultados validados solo existen como fixtures de prueba. Datos sintéticos.
