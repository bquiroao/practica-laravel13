# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Josué David Morales Ramírez (carné 1890-23-10545) |
| **Módulo** | Catálogo de salas, wards y camas |
| **Entrega** | Semana 1 (tarea Odoo 2443) — UML del proceso «creación de sala y cama con control de estado operativo» |

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió los borradores, generó las imágenes con PlantUML local, creó la rama, hizo el commit y, por orden del estudiante, el push; el estudiante abrió el PR desde la web y Claude Code corrigió después su título y su cuerpo con `gh pr edit`. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión de Claude Code. Ayudó a organizar las tareas de Odoo y a preparar los prompts, y sugirió un borrador de las respuestas de comprobación que el estudiante revisó y reescribió con sus palabras. |

## 2. Propósito del uso

- Revisar el repositorio para basar el modelo en él: tablas `wards` y `beds`, restricciones `UNIQUE`, `DEFAULT` y `ON DELETE RESTRICT`, nombres de actores del módulo RBAC y estructura de `docs/`.
- Redactar la narrativa de alcance, las reglas de negocio, la especificación de casos de uso y la matriz de trazabilidad.
- Escribir las fuentes PlantUML de los tres diagramas y generar sus PNG con `plantuml.jar` local (`-charset UTF-8`).
- Formular dos preguntas de comprobación y preparar esta declaración y la guía de defensa oral.

## 3. Prompts relevantes

1. El enunciado de la tarea 2443, tomado textualmente de Odoo: «Modele el proceso “creación de sala y cama con control de estado operativo”. Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas.»
2. Instrucciones de trabajo: un PR por tarea con un máximo de 400 líneas; documentos compactos, con tablas en vez de prosa; explicar cada decisión de modelado; mostrar el borrador y esperar revisión antes de los diagramas; usar PlantUML local y nunca el servidor público.
3. «Aprobado: crea los 3 .puml de la 2443 y genera los PNG con -charset UTF-8. Luego prepara DECLARACION_IA.md y GUIA_DEFENSA_ORAL.md con lo que realmente hicimos.»

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-01-uml-catalogo-camas.md`, `../README.md` y la fila del módulo en `docs/README.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; las imágenes se generaron con PlantUML |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**

- Las propuso la IA y no salen del enunciado: el actor «Supervisor de enfermería», la equivalencia «sala = ward», las transiciones de R-05, los códigos HTTP y los endpoints `POST /wards/{id}/beds` y `PATCH /beds/{id}/status`. El contrato se formaliza en la Semana 5.
- La IA verificó el origen de R-01, R-02, R-04 y R-08 leyendo la migración del esquema HOSPITAL.
- Al revisar el PNG, la IA detectó que D6 decía cosas distintas en la actividad y en la narrativa, y corrigió la narrativa.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Narrativa, actores, reglas y casos de uso | Aceptado | El estudiante aprobó los borradores sin pedir cambios. |
| Diagramas | Aceptado | Generados después de la aprobación. |
| Respuestas a las preguntas de comprobación | Modificado | Claude Code formuló las preguntas. Claude (chat de claude.ai) sugirió un borrador de respuestas, y el estudiante lo revisó y lo reescribió con sus palabras. |

## 6. Validación humana

- [x] Revisé que los tres diagramas usen los mismos identificadores que la matriz (§9).
- [ ] Comparé las reglas R-01, R-02, R-04 y R-08 con la migración del esquema HOSPITAL.
- [ ] Puedo explicar sin apoyo por qué Admisión es actor y RBAC no.

**Fecha de validación:** 25/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
