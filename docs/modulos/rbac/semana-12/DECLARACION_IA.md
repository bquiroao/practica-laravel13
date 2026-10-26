# Declaración de uso de inteligencia artificial — Semana 12

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 12 — Segunda evaluación parcial |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Elegir el cambio solicitado (B-6, hallazgo medido en la S11) y analizar sus opciones.
- Escribir las pruebas PEF-01..10 antes de implementar, y luego implementar el cambio en el Micro-HIS:
  - caso de uso, formulario, vista, controlador, ruta y composición;
  - contrato v1.2.0;
  - ajuste justificado de ARQ-08 y API-03.
- Aplicar el cambio al prototipo y actualizar las herramientas: paridad con conteo de eventos, recorrido con comprobaciones de bitácora y medidor de ruido nuevo.
- Escribir la matriz decisión → RF/RNF → evidencia y formalizar RNF-10..14 y RF-15.
- Generar la presentación (pptxgenjs), el mapa de navegación actualizado, el guion del recorrido, el registro del cambio, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Defienda las decisiones de componentes, UX, accesibilidad y movilidad de RBAC: roles, permisos y protección de rutas. Demuestre con el prototipo cómo «asignación de rol, autorización de una operación y denegación trazable» trata roles, estados, errores y riesgos asociados con roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables. Entregable/Evidencia: Presentación breve, recorrido navegable, matriz decisión→RF/RNF→evidencia y respuesta documentada a un cambio solicitado.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Micro-HIS: 3 clases nuevas, `PermisosEfectivosPrueba.php`; cambios en controlador, rutas, composición, OpenAPI, ARQ-08, API-03 y README | Borrador completo |
| `semana-12/prototipo/` | Copia del de la S11 con el cambio aplicado |
| `semana-12/herramientas/` | Copia de la S11 actualizada + `ruido-auditoria.cjs` nuevo |
| `capturas/`, `evidencia/*.txt` | Salidas reales de los scripts (PHP 8.4, Node 24, Chrome headless) |
| `presentacion/*.pptx`, `*.pdf` | Generados con pptxgenjs y exportados con PowerPoint |
| `diagramas/semana-12-mapa-navegacion.*` | PlantUML 1.2024.7 |
| Documentos `.md` de la semana | Borradores |

**Notas de honestidad:**

- **El cambio se eligió y no fue pedido por un tercero.** Se tomó la brecha B-6 que la propia S11 midió, redactada como solicitud del auditor.
- **El rojo fue real.** Con las pruebas escritas y sin implementación, fallaron 9. Al implementar fallaron 2 guardas (API-01, ARQ-08). ARQ-08 se **amplió por nombre**, con justificación, y no se desactivó.
- **El costo se reporta.** `AutorizacionControlador` sube de Ce 7 a 10 y las líneas duplicadas de 29 a 30.
- **Los RNF-10..14 y RF-15 se formalizaron en esta entrega.** El documento lo declara.
- **La presentación tuvo dos correcciones visuales:** títulos que se partían y un guion de «D-18» cortado.
- **Pendiente.** No se probó con lectores de pantalla ni contra el backend real por red.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Elección del cambio (B-6) y la opción D | | |
| Implementación y pruebas PEF-01..10 | | |
| Ampliación de ARQ-08 | | |
| Matriz y RNF formalizados | | |
| Presentación y guion del recorrido | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` (80 · 78 · 0 · 2) y revisé las pruebas PEF.
- [ ] Hice el recorrido navegable completo (partes A y B) en escritorio y en móvil.
- [ ] Ejecuté `ruido-auditoria.cjs` sobre los prototipos de la S11 y la S12 (9 → 0).
- [ ] Ensayé la presentación con las notas del orador.

**Fecha de validación:** ____________
