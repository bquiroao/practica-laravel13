# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 15 — Pruebas unitarias, de caja blanca y de caja negra |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y escribir las 21 pruebas nuevas (UNI, CB, CN) y las 6 pruebas de defectos, siguiendo la estructura de la semana 15 de RBAC.
- Hacer las pruebas exploratorias y medir la cobertura de la caja blanca con `phpdbg`.
- Redactar la matriz RF/RNF → prueba, el registro de defectos, el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe y ejecute pruebas de Pacientes: registro, edición, búsqueda y detalle trazadas a RF/RNF: unitarias para reglas de identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad; caja blanca con ramas/condiciones del flujo «registro, búsqueda y actualización segura de un paciente»; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos. Entregable/Evidencia: Matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana se agregaron pruebas y dos opciones al ejecutor (`--solo`, `--defectos`), pero **no se cambió el código de `src/`**.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `tests/Dominio/UnitariasReglasPrueba.php`, `tests/Aplicacion/CajaBlancaPrueba.php`, `tests/Presentacion/CajaNegraPrueba.php`, `tests/Defectos/DefectosPrueba.php` | Borrador completo |
| `tests/ejecutar.php` (opciones nuevas) | Copiadas del ejecutor de RBAC |
| `herramientas/cobertura-caja-blanca.php` | Adaptado del de RBAC (otras clases objetivo) |
| `herramientas/exploratorias.php`, `diagramas/semana-15-grafo-registrar.puml` | Borrador completo |
| `matriz-rf-rnf-pruebas.md`, `registro-defectos.md`, `semana-15-pruebas-pacientes.md` | Borrador completo (estructura de RBAC, contenido de Pacientes) |
| `evidencia/*.txt`, `capturas/*.png` | Salida real de los comandos; las capturas se generaron a partir de esos textos |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **Una prueba falló por un error de la IA, no del sistema.** CN-01 esperaba `CUERPO_REQUERIDO` y el contrato dice `CUERPO_INVALIDO`. Se corrigió la prueba después de revisar `openapi.yaml`.
- **El número de pruebas afectadas por DEF-05 se midió, no se estimó.** El primer borrador decía «cerca de 40». En una copia aparte, con el verificador agregado, la suite ni siquiera arranca, y sin el archivo que aborta fallan 70 de 100. El texto final usa la cifra medida.
- **Los defectos DEF-01..DEF-05 vienen de la revisión de la S13**, en código que también escribió la IA. DEF-06 es nuevo.
- **La regla del dígito verificador del DPI** (módulo 11 sobre los 8 primeros dígitos) es la que se usa habitualmente. Debe confirmarse con la norma del RENAP antes de corregir DEF-05.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pruebas UNI, CB y CN | | |
| Severidades de DEF-01..DEF-06 | | |
| Opción elegida para DEF-04 | | |
| NC-01 y escalamiento | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y obtuve 108 · 106 · 0 · 2.
- [ ] Ejecuté `php tests/ejecutar.php --defectos --solo=DEF` y fallan los 6.
- [ ] Ejecuté la cobertura con `phpdbg` y obtuve 98.2 %.
- [ ] Revisé la tabla de permisos de CN-05 contra `config.php`.

**Fecha de validación:** ____________
