# Semana 15 — Pruebas de ingreso de resultados

**Estudiante:** Lopez Perez, Mercedes Azucena

**Flujo:** Captura y corrección controlada de resultados pendientes.

## Línea base y alcance

Se prueba el código real del refactor de [semana 7](../semana-07/despues/Refactor.php), apoyado en el dominio/repositorio de [semana 4](../semana-04/ejemplo/README.md). La rama se creó desde semana-7-ingreso-resultados-componentes-mercedes-lopez, commit 88a642b, para incluir el soporte existente de valores, unidades y rangos. Esa base debe integrarse antes de esta entrega; no se copian reglas para fabricar resultados en pruebas. No se modifican semanas anteriores ni módulos ajenos.

Rangos y datos son ficticios: 4–6 mmol/L solo ilustra el comportamiento del código, no es un criterio clínico. No se prueba validación bioquímica ni se valida/publica automáticamente un resultado fuera de rango.

## Comandos y resultados

PHP 8.2.33 con PDO SQLite:
```powershell
php docs/modulos/ingreso-resultados-laboratorio/semana-15/pruebas/run.php
php docs/modulos/ingreso-resultados-laboratorio/semana-15/pruebas/run.php --defectos
php docs/modulos/ingreso-resultados-laboratorio/semana-04/ejemplo/tests/run.php
```

- Suite de semana 15: **13 casos, 0 fallidos**: 4 unitarios, 3 caja blanca y 6 caja negra.
- Reproducción: **3 defectos observados**, no corregidos. En --defectos, PASS confirma el comportamiento defectuoso documentado.
- Regresión de semana 4: **46 aserciones exitosas**, incluido contrato InMemory/PDO SQLite.

El runner devuelve código 1 ante cualquier observación inesperada. El reloj y los identificadores son deterministas; no se escribe sobre bases hospitalarias. La suite principal usa el repositorio real en memoria; la regresión usa también SQLite en memoria.

## Matriz RF/RNF → prueba

RF-01..RF-07 y RNF-01..RNF-05 conservan la definición de [semana 2](../semana-02/semana-02-lsp-ingreso-resultados.md). Se trazan además criterios EV de la consigna: EV-VAL (valor/unidad/rango del refactor), EV-PERM (permisos y hospital) y EV-ERR (errores de persistencia). No se inventa que esos controles existan en el servicio.

| ID | Tipo | RF/RNF | Entrada / condición y esperado |
|---|---|---|---|
| U01 | Unitaria | EV-VAL, RNF-05 | Coma/punto y espacios: forma canónica equivalente con unidad. |
| U02 | Unitaria | EV-VAL, RNF-03 | Vacío, blancos, texto, falta unidad o decimal mal formado: excepción de entrada inválida. |
| U03 | Unitaria | EV-VAL | Límites inclusivos 4 y 6 normales, vecinos 3.999 LOW y 6.001 HIGH; rango invertido inválido. |
| U04 | Unitaria | RF-02/03/04, RNF-02 | Corrección conserva original, enlaza v1→v2, queda pendiente y exige motivo. |
| W01 | Caja blanca | RF-01, RNF-03 | Valor vacío retorna antes de leer/escribir: espía cuenta cero efectos. |
| W02 | Caja blanca | EV-VAL | Unidad distinta retorna UNIT_MISMATCH antes de persistencia. |
| W03 | Caja blanca | RF-07 | Escritura condicional rechazada retorna STORE_CONFLICT y no agrega versión. |
| B01 | Caja negra | RF-01/04 | Captura válida guarda valor canónico, autor y pendiente. |
| B02 | Caja negra | RF-02/03 | Motivo vacío no agrega historial; motivo válido crea segunda versión. |
| B03 | Caja negra | RF-01/07 | Captura duplicada y expectedVersionId obsoleto se rechazan. |
| B04 | Caja negra | RF-05, RNF-03 | Validado no admite corrección; ítem sin resultado devuelve NOT_FOUND. |
| B05 | Caja negra | EV-VAL, RF-04 | Unidad diferente rechazada; fuera de rango se captura con alerta HIGH, sin validarlo. |
| B06 | Caja negra | RF-01 | Autor vacío bloqueado. No equivale a autorización por rol. |
| D01 | Caja negra negativa / defecto | EV-PERM | Actor arbitrario no vacío se acepta: ausencia de verificación de permisos/hospital reproducida. |
| D02 | Límite de precisión / defecto | EV-VAL | 6.00001 se guarda como 6 pero el resultado conserva HIGH. |
| D03 | Error inyectado / defecto | EV-ERR, RNF-03 | Fallo de escritura lanza excepción al llamador; no hay resultado explícito de error. |

Caja negra utiliza comandos/resultados públicos y observables de repositorio; caja blanca inspecciona los retornos tempranos y la rama de conflicto mediante un decorador espía del repositorio real. No hay medición porcentual de cobertura con Xdebug. Los límites numéricos son datos de prueba del rango, no máximos de aceptación clínica.

## Defectos registrados

| ID | Severidad propuesta / estado | Esperado frente a observado | Reproducción / acción recomendada |
|---|---|---|---|
| IR-D01 | Alta para integración / abierto | Se requiere autorización de rol y hospital; EntryService solo exige actor no vacío y acepta SIN-PERMISO. | --defectos D01. Incorporar contexto autenticado y verificación en límite apropiado; probar rol denegado y acceso cruzado. Esto demuestra ausencia de control en el ejemplo, no un ataque contra un HIS desplegado. |
| IR-D02 | Alta / abierto | Valor guardado y clasificación deben usar la misma precisión definida. 6.00001 queda guardado como 6 mmol/L pero conserva HIGH para rango 4–6. | --defectos D02. Definir precisión por prueba/catálogo, evitar redondeo silencioso y clasificar el mismo valor que se guarda. Retest con valores en torno al límite. |
| IR-D03 | Media / abierto | Error de persistencia explícito sin éxito falso; el servicio propaga RuntimeException aunque no escribe historial. | --defectos D03. Definir traducción de errores conocidos y presentación recuperable; no capturar indiscriminadamente errores de programación. |

Ubicación: EntryService/ResultValue de semana 7. Propietaria propuesta: autora del módulo. Cierre pendiente de corrección y retest independiente; esta entrega registra hallazgos y no altera silenciosamente código de semanas anteriores. Permisos/tenant siguen pendientes: no se cuenta como “prueba de seguridad aprobada” que el runner reproduzca D01.

## Evidencia y defensa

- [Salida suite](evidencia/salida-suite.txt) · [Captura](evidencia/captura-suite.png).
- [Salida defectos](evidencia/salida-defectos.txt) · [Captura](evidencia/captura-defectos.png).
- [Regresión](evidencia/salida-regresion.txt).

Las capturas representan en navegador las salidas reales guardadas en reporte.html; no son capturas de una UI clínica ni de una terminal interactiva. La reproducción no implica corrección.

Defensa sugerida: ejecutar suite y mostrar unidad/rango, corrección pendiente e historial; explicar espías W01–W03; ejecutar --defectos y contrastar el 100 % de casos funcionales del alcance con los tres defectos abiertos. Concluir que el flujo tiene reglas probadas pero no está aprobado para integración clínica mientras falten permisos y se mantenga la discrepancia de precisión.