# Micro-HIS de ingreso de resultados — PHP CLI

Prototipo académico en PHP 8.2+ sin framework ni dependencias externas. Permite capturar un resultado inicial pendiente y corregirlo mediante una versión nueva, enlazada a la anterior y con motivo obligatorio. Persiste con PDO y consultas preparadas sobre SQLite.

## Requisitos y estructura

Se requiere PHP 8.2+ con `pdo_sqlite`.

- `src/Presentation/Cli`: valida opciones y presenta el resultado.
- `src/Application`: coordina captura/corrección y define contratos de repositorio e identificador.
- `src/Domain`: tipos inmutables para versiones pendientes y validadas.
- `src/Persistence`: repositorio PDO con lectura de versión vigente y append condicional.
- `src/Infrastructure/Identity`: generación de identificadores de versión.
- `database/schema.sql`: esquema, restricciones de historial e índice de consulta.
- `tests/run.php`: pruebas con dobles deterministas y PDO SQLite en memoria para reglas, persistencia, conflicto y fallo de escritura.

La base SQLite se crea en `data/lab-results.sqlite` la primera vez. Para usar otra ubicación, define `LAB_RESULTS_DATABASE` con una ruta escribible. Los datos de demostración son sintéticos.

## Ejecución

Desde esta carpeta:

```powershell
Get-ChildItem . -Filter '*.php' -Recurse | ForEach-Object { php -l $_.FullName }
php tests/run.php
php bin/result.php --action=capture --item=ITEM-001 --value=5.4 --actor=TECH-01
php bin/result.php --action=correct --item=ITEM-001 --expected=RES-ID-DEVUELTO-POR-CAPTURE --value=5.6 --reason="Corrección de transcripción" --actor=TECH-02
```

Reemplaza `RES-ID-DEVUELTO-POR-CAPTURE` por el ID exacto de la versión vigente que mostró la captura. Si otra operación cambió el resultado, devuelve conflicto sin anexar la corrección. El valor es texto deliberadamente: el módulo no define tipos/unidades/rangos por examen ni evalúa significado clínico.

## Decisiones y límites

- La captura inicial solo se guarda cuando no existe versión para el ítem.
- La corrección conserva la versión previa, exige motivo y crea una nueva versión pendiente.
- La escritura de corrección es condicional en SQL preparado: solo se anexa si la versión esperada aún es la última y está pendiente.
- Los resultados validados son de solo lectura en este flujo. No se implementa validación, publicación, firma profesional ni autorizaciones productivas.
- Se reciben referencias de ítem y autor de demostración; no se consulta una orden real ni un sistema de identidad.
- La CLI presenta datos sintéticos para demostrar el contrato; no es una UI clínica ni debe conectarse a datos reales.
