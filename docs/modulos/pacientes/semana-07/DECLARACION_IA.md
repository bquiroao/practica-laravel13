# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 7 — Diseño de componentes y refactorización |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el instrumento de medición `bin/acoplamiento.php`, adaptado del que se usó en RBAC a las 6 preocupaciones de Pacientes, y usarlo para elegir el punto a refactorizar.
- Escribir las pruebas de caracterización REF-01..03 y ejecutarlas sobre el código anterior.
- Hacer el refactor: `RevisionDeFicha`, `Capacidades`, `ControlAcceso::capacidades()` y los constructores con nombre de `EventoAuditoria`.
- Diseñar los componentes del frontend en Vue y redactar los contratos de entrada/salida.
- Redactar el documento de la semana, los diagramas, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe los componentes backend y frontend propios de Pacientes: registro, edición, búsqueda y detalle para «registro, búsqueda y actualización segura de un paciente». Refactorice conceptualmente el punto con mayor acoplamiento entre identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad, separando UI, aplicación, dominio y persistencia sin ampliar el módulo. Entregable/Evidencia: Diagrama de componentes, contratos de entrada/salida y comparación antes/después de un refactor concreto con justificación.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana el refactor se hizo en el Micro-HIS (`docs/modulos/pacientes/micro-his-pacientes/`). El frontend Vue quedó como diseño, sin tocar `resources/js`, porque sus archivos base son compartidos con otros módulos.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `../micro-his-pacientes/`: `RevisionDeFicha`, `Capacidades`, casos de uso, `ControlAcceso`, `EventoAuditoria`, `contenedor.php`, `bin/acoplamiento.php`, `RefactorPrueba.php`, mutaciones M21/M23/M24 | Borrador completo |
| `semana-07-componentes-refactor.md` | Borrador completo |
| `contratos-entrada-salida.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; imágenes generadas con PlantUML |
| `refactor/antes/` | Copia literal del código anterior al refactor |
| `evidencia/*.txt` | Salida real de PHP 8.4.26 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Hechos a tener en cuenta:**
- **La primera medición tenía un falso positivo.** La marca `buscar` contaba como «búsqueda» la llamada `idempotencia->buscar()`, y `RegistrarPaciente` aparecía con 5 preocupaciones. Se quitó esa marca **antes** de elegir el punto y de medir el «después»; con la corrección, `RegistrarPaciente` tiene 4.
- **Dos pruebas de caracterización fallaron al principio por un error de la propia prueba** (un argumento posicional que caía en el parámetro equivocado), no del código. Se corrigieron con argumentos con nombre y entonces pasaron sobre el código anterior.
- **La mutación M21 dejó de aplicarse** después del refactor, porque el código que vigila se movió a `RevisionDeFicha`. Se reubicó y se agregaron M23 y M24 para las piezas nuevas.
- **La columna «Flags» del instrumento empeoró** (8 → 10) porque cuenta el nombre de variable `$puede`. Se reporta así en el documento, sin ajustar el instrumento después de ver el resultado.
- **Las pruebas contra PostgreSQL real no se ejecutaron** (2 omitidas), igual que en semanas anteriores. El refactor no tocó la Persistencia.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del punto a refactorizar | | |
| `RevisionDeFicha` y `Capacidades` | | |
| Pruebas de caracterización REF-01..03 | | |
| Diseño de componentes del frontend | | |
| Contratos de entrada/salida | | |
| Diagramas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y obtuve 81 pasan, 0 fallan, 2 omitidas.
- [ ] Ejecuté `php bin/acoplamiento.php` y entiendo las columnas Ce, Ca y P.
- [ ] Ejecuté `php tests/mutaciones.php` y obtuve 24/24 detectadas.
- [ ] Puedo explicar por qué el orden de las reglas es una regla de negocio.
- [ ] Comprobé que las referencias bibliográficas existan.

**Fecha de validación:** ____________
