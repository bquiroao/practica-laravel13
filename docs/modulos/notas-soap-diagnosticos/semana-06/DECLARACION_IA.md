# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de la arquitectura y cambio práctico «Corregir una nota SOAP firmada» |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso a la carpeta de mi worktree. Escribió el código, las pruebas y los documentos en un entorno de trabajo, ejecutó las pruebas, la validación del contrato y la demostración contra PostgreSQL, y copió los archivos a mi carpeta. No hizo commits, push ni PR: los hice yo. |

## 2. Propósito del uso

- **Cambio práctico:** proponer «Corregir una nota firmada» a partir de la regla R-07 de mi semana 1. Escribir las pruebas primero e implementarlo en el micro-HIS: dominio, caso de uso, formulario, ruta, adaptadores PDO y en memoria, esquema y contrato OpenAPI.
- **Presentación:** generar las 8 diapositivas (`.pptx`) con notas del orador y su copia en PDF.
- **Documentos de la entrega:** escribir el diagrama trazable, el de capas, la matriz decisión → evidencia, el registro del cambio, el documento principal, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. "Ya empecemos con la siguiente" (semana 6, después de entregar la semana 5).
2. Pregunta sobre si había que esperar a que fusionaran el PR #15 para seguir, y la respuesta de seguir en una rama propia desde `origin/develop`.
3. El enunciado específico de la semana 6 no estaba en el repositorio: el plan del README solo dice «Primera evaluación parcial: defensa teórica y caso práctico arquitectónico». La IA propuso un cambio práctico y yo lo elegí: "coloquemos el que sugieres".
4. Seguir la misma forma de entrega que usa el grupo para la semana 6: presentación de 6 a 8 diapositivas, diagrama trazable, matriz decisión → evidencia y registro del cambio práctico.
5. El revisor automático del curso marcó el PR #20 porque cambiaba 2,014 líneas (el umbral es 400). Decidí dividir la entrega en cinco PRs de menos de 400 líneas cada uno y guardar las salidas de las pruebas como extractos (las pruebas que fallan o las nuevas, y los totales).

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Actores, CU-01 a CU-05, reglas R-01 a R-08 (incluida R-07 que define la corrección) | Míos, de la semana 1 |
| RF-01 a RF-10, RNF, CA-01 a CA-12 y SRP | Míos, de la semana 2 |
| Micro-HIS, contrato, cliente y medición | De las semanas 3 a 5 (generados con IA a partir de mi diseño) |
| Columnas `supersedes_id` y `correction_reason` | Migración 000400 de HOSPITAL del repositorio del grupo |
| Elección del cambio práctico | Propuesta de la IA, aceptada por mí |
| Cambios al código (`CorregirNota`, `NotaSoap::corregir()`, formulario, ruta, adaptadores, esquema, contrato 1.1.0) | Generados con IA |
| Pruebas nuevas (DOM-10, COR-01 a COR-08, REP-09, INT-11, escenarios de API-03, INT-06 ampliada) | Generadas con IA, escritas antes que el código |
| `presentacion/semana-06-defensa-notas-soap.pptx` y `.pdf` | Generados con IA (pptxgenjs; PDF exportado con LibreOffice) |
| `diagramas/*.puml` y `*.png` | Generados con IA; imágenes generadas con PlantUML |
| `matriz-decision-evidencia.md`, `registro-cambio-practico.md`, `semana-06-defensa-arquitectura.md` | Borradores generados con IA |
| `evidencia/*.txt` | Extractos reales de las pruebas en cada paso (PHP 8.3.6), del lint (Redocly CLI 1.34.3) y de la API contra PostgreSQL 16 |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Verificaciones que hizo la herramienta:**

- **Pruebas:** guardó el estado rojo (13 fallas), el intermedio con el contrato sin actualizar (3 fallas) y el verde (0 fallas), con y sin PostgreSQL. En el camino detectó tres errores en sus propias pruebas (DOM-10, COR-08 y ARQ-14) y corrigió las pruebas, no el código. Está contado en el registro, §5.
- **Contrato y presentación:** validó el contrato con Redocly y el `.pptx` con el validador del formato. Revisó las 8 diapositivas exportadas a imagen y corrigió títulos de dos líneas, palabras partidas y flechas mal dibujadas.
- **Archivos sin cambios:** comprobó con `diff` que los casos de uso existentes, la transacción, la bandeja de salida y el cliente no cambiaron.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio práctico (corregir nota firmada) y sus reglas | | |
| Implementación del cambio | | |
| Presentación (contenido y diseño) | | |
| Diagrama trazable | | |
| Matriz decisión → evidencia | | |
| Registro del cambio | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí la presentación en PowerPoint, la ensayé con las notas del orador y ajusté lo que no diría con mis palabras.
- [ ] Ejecuté `php tests/ejecutar.php` en mi equipo y anoto el resultado: ____________
- [ ] Hice la demostración de la guía (corregir por HTTP) en mi equipo.
- [ ] Puedo recorrer el diagrama trazable de punta a punta para los dos hilos (registrar y corregir).
- [ ] Puedo explicar por qué la corrección no modifica la nota firmada y por qué hay un `UNIQUE` en la base.
- [ ] Revisé cada fila de la matriz contra el archivo y la prueba que cita.
- [ ] Confirmé con el docente si el cambio práctico elegido sirve o si pide otro.

**Fecha de validación:** ____________
