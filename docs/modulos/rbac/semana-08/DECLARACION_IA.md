# Declaración de uso de inteligencia artificial — Semana 8

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 8 — Diseño de experiencia de usuario |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Revisar el contrato OpenAPI, los códigos de error del backend y los componentes Vue de la semana 7, para que el diseño se apoye en lo que existe.
- Diseñar los user flows por rol (4 diagramas de actividad PlantUML).
- Generar los 6 wireframes anotados con un script (pptxgenjs) y exportarlos a PDF y PNG con PowerPoint.
- Redactar:
  - el catálogo de mensajes;
  - las reglas de interacción;
  - la matriz de estados;
  - la ayuda contextual;
  - las reglas de protección de datos;
  - la trazabilidad.
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Diseñe el flujo UX de «asignación de rol, autorización de una operación y denegación trazable» por sus roles autorizados. Incluya inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos vinculados con roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables. Entregable/Evidencia: User flow por rol y 4–6 wireframes anotados con estados, mensajes, validaciones y reglas de interacción.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-08-ux-rbac.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo (PlantUML 1.2024.7) |
| `wireframes/semana-08-wireframes-rbac.pptx`, `.pdf` y `*.png` | Generados con un script escrito por la IA; exportados con PowerPoint |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- El flujo del administrador empezó como un solo diagrama. Quedó demasiado enredado y se dividió en dos: asignar y revocar.
- La revisión visual de los wireframes encontró un número de anotación encima de un título (W6). Se corrigió y se volvió a exportar.
- Al diseñar se detectaron tres brechas del contrato (B-1..B-3, §11). Se documentan y **no** se resuelven esta semana.
- Los wireframes son diseño. No se implementó ninguna pantalla ni se modificó el código del Micro-HIS.
- El paciente «P-00123» y los datos de los wireframes son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| User flows por rol | | |
| Wireframes W1–W6 y sus anotaciones | | |
| Catálogo de mensajes M-01..M-31 | | |
| Reglas de interacción y protección de datos | | |
| Brechas B-1..B-3 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé los 4 user flows y puedo recorrer cada rama en voz alta.
- [ ] Revisé los 6 wireframes en el `.pptx` y puedo explicar cada anotación.
- [ ] Comprobé que los códigos del catálogo de mensajes existen en `micro-his-rbac/src/`.
- [ ] Puedo explicar por qué el 403 no muestra el motivo y dónde sí se ve.

**Fecha de validación:** ____________
