# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de arquitectura y cambio práctico |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- **Cambio práctico «Revocar un rol»:** escribir las pruebas primero e implementarlo en el Micro-HIS. Incluye dominio, aplicación, persistencia PDO e InMemory, MVC, contrato OpenAPI, esquema y datos demo.
- **Presentación:** generar las 8 diapositivas (`.pptx`) con la librería pptxgenjs y exportar el PDF y las imágenes de revisión con PowerPoint.
- **Documentos de la entrega:** escribir el diagrama trazable y el diagrama de capas, la matriz decisión→evidencia, el registro del cambio, el documento principal, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Defienda la arquitectura propuesta para «asignación de rol, autorización de una operación y denegación trazable» en RBAC: roles, permisos y protección de rutas: conecte actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico considerando roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables. Entregable/Evidencia: Presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.
2. Elección del formato de la presentación: archivo `.pptx` dentro del repositorio.
3. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambio en `micro-his-rbac/` (13 archivos nuevos de código, `RevocacionPrueba.php`, modificaciones en el puerto, adaptadores, rutas, composición, contrato, esquema y datos) | Borrador completo |
| `presentacion/semana-06-defensa-rbac.pptx` y `.pdf` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo (PlantUML 1.2024.7) |
| `matriz-decision-evidencia.md`, `registro-cambio-practico.md`, `semana-06-defensa-arquitectura.md` | Borrador completo |
| `evidencia/*.txt` | Salidas reales de los comandos (PHP 8.4.26, Redocly CLI 1.34.3) |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Verificaciones que hizo la herramienta:**

- **Pruebas:** el estado rojo antes de implementar (8 fallas) y el verde después (65 pasan, 0 fallan, 2 omitidas).
- **Contrato y presentación:** el lint de Redocly sobre el contrato v1.1.0 y la validación estructural del `.pptx`.
- **Revisión visual:** de las 8 diapositivas, exportadas con PowerPoint. Se corrigieron un subtítulo cortado, un diagrama desactualizado y un código partido en dos líneas.
- **Código de autorización intacto:** se comprobó con `git diff --quiet` que la autorización no se modificó.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio práctico (revocar rol) y sus reglas | | |
| Implementación del cambio | | |
| Presentación (contenido y diseño) | | |
| Diagrama trazable | | |
| Matriz decisión → evidencia | | |
| Registro del cambio | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí la presentación en PowerPoint, la ensayé con las notas del orador y ajusté lo que no diría con mis palabras.
- [ ] Ejecuté `php tests/ejecutar.php` y `php bin/demo.php` en mi equipo.
- [ ] Puedo recorrer el diagrama trazable de punta a punta para los tres hilos (asignar, autorizar, revocar).
- [ ] Puedo explicar por qué la autorización cambió sin modificar `AutorizarOperacion`.
- [ ] Revisé cada fila de la matriz contra el archivo y la prueba que cita.

**Fecha de validación:** ____________
