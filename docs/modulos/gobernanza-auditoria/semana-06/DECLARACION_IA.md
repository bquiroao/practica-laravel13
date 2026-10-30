# Declaración de uso de inteligencia artificial — Semana 6

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 6 — Primera evaluación parcial: defensa de la arquitectura y cambio práctico |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Elegir y analizar el cambio práctico, escribir sus pruebas primero e implementarlo en el micro-HIS.
- Hacer el diagrama trazable, el de capas, la matriz decisión → evidencia y el registro del cambio.
- Generar la presentación (pptxgenjs, exportada a PDF con PowerPoint) con notas del orador.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Defienda la arquitectura propuesta para «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema: conecte actores, UML, RF/RNF, principio SOLID, capas, Repository y contrato API. Responda un cambio práctico considerando audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Entregable/Evidencia: Presentación de 6–8 diapositivas, diagrama trazable, matriz decisión→evidencia y registro del cambio práctico defendido.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-auditoria/` (corrección trazable, contrato 1.1.0, pruebas CORR, CON-*-09, INT-08, mutaciones M19..M21, demo) | Borrador completo |
| `semana-06/*` (documentos, diagramas, presentación) | Borradores; la presentación se revisó en imagen y se corrigieron dos defectos de maquetación |
| `semana-06/evidencia/*.txt` | Salida real de los comandos |

**Hechos a tener en cuenta:**
- **El cambio lo eligió la IA:** corrección trazable de un movimiento. Ya estaba previsto en la semana 1 (R-02, `corrige_a`) pero no se había implementado. Hay que validar con el docente que sea un cambio aceptable.
- **CORR-05 pasaba antes de implementar.** No es un error: la idempotencia de la semana 3 ya lo cubría. Se mantuvo como guardia del orden de las comprobaciones.
- **ARQ-03 marcó un falso positivo** de su heurística (comillas dobles en marcadores de SQL). Se cambió el código para cumplirla, no la prueba.
- **Revisión visual de la presentación:**
  - el diagrama de la diapositiva 6 invadía la nota al pie;
  - el gráfico de la 5 redondeaba 0,07 a 0.
  Las dos se corrigieron.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Cambio práctico elegido | | |
| Diapositivas y notas del orador | | |
| Matriz D-01..D-21 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ensayé la presentación con las notas del orador (≈ 12 min).
- [ ] Ejecuté la demo del cambio y la mutación M20 en vivo.

**Fecha de validación:** ____________
