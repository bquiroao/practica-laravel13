# Declaración de uso de inteligencia artificial — Semana 9

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 9 — Evaluación del diseño, usabilidad y accesibilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Aplicar las heurísticas de Nielsen y los criterios WCAG 2.2 AA al diseño de la semana 8, y completar el checklist.
- Escribir el medidor de contraste (`herramientas/contraste.py`) con los colores exactos del generador de wireframes.
- Generar los recortes marcados de cada hallazgo a partir de los PNG de la semana 8. El script de recorte quedó fuera del repositorio porque solo produce imágenes.
- Hacer el recorrido de teclado sobre la especificación.
- Redactar los hallazgos y el backlog priorizado con criterios verificables.
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Evalúe el flujo UX de RBAC: roles, permisos y protección de rutas con heurísticas de usabilidad y WCAG: teclado, foco, contraste, etiquetas, mensajes y prevención de errores. Priorice correcciones según impacto en roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables. Entregable/Evidencia: Checklist completado, evidencia de al menos 6 hallazgos y backlog priorizado con corrección propuesta y criterio verificable.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-09-evaluacion-ux-rbac.md`, `checklist-evaluacion.md` | Borrador completo |
| `herramientas/contraste.py` | Borrador completo |
| `evidencia/contraste-wcag.txt` | Salida real del script (Python 3) |
| `evidencia/hallazgos/*.png` | Recortes de los wireframes de la semana 8 con marcas, generados con Pillow |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- Se evaluó el **diseño**: no hay frontend implementado. El contraste es una medición real; el teclado y el lector de pantalla se evaluaron recorriendo la especificación, no con NVDA.
- Las severidades son juicio de un solo evaluador, sin pruebas con usuarios (§7 del documento).
- Al recontar el checklist, el resumen tenía mal los totales de WCAG; se corrigieron a partir de las tablas.
- Un recorte (H-03) marcaba al principio un borde que sí cumple. Se corrigió para marcar solo la casilla.
- El criterio de BL-09 citaba una prueba que no correspondía. Se corrigió a HTTP-06, que existe y pasa.
- Los wireframes de la semana 8 **no** se modificaron: las correcciones quedan en el backlog.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Checklist (heurísticas, WCAG, RBAC) | | |
| Hallazgos H-01..H-16 y sus severidades | | |
| Modelo de priorización (severidad × impacto RBAC) | | |
| Backlog BL-01..BL-16 y criterios | | |
| Medidor de contraste | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `python herramientas/contraste.py` y obtuve los mismos valores.
- [ ] Revisé cada recorte de `evidencia/hallazgos/` contra el wireframe original.
- [ ] Estoy de acuerdo con la severidad de los hallazgos P1, o la ajusté.
- [ ] Puedo explicar cómo se verifica el criterio de cada ítem P1.

**Fecha de validación:** ____________
