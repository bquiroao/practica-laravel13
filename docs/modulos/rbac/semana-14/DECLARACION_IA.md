# Declaración de uso de inteligencia artificial — Semana 14

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 14 — Confiabilidad y aseguramiento de calidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Definir las 7 métricas y escribir `medir-sqa.php`, que las calcula sobre el código real.
- Medir la línea base y comprobar con dos defectos inyectados (en copias aparte) que las métricas los detectan.
- Redactar la matriz de 12 riesgos, los 14 controles (preventivos y detectivos), los responsables, el calendario y el plan de acción.
- Escribir las consultas SQL del control CD-04 y el tablero de seguimiento.
- Redactar la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Defina el plan SQA de RBAC: roles, permisos y protección de rutas: calidad de «asignación de rol, autorización de una operación y denegación trazable», confiabilidad, seguridad, trazabilidad y riesgos de roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables. Asigne responsable, umbral, fuente y acción para cada métrica y control. Entregable/Evidencia: Plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos/detectivos, responsables y calendario de seguimiento.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `plan-sqa-rbac.md`, `matriz-riesgos.md`, `semana-14-sqa-rbac.md` | Borrador completo |
| `herramientas/medir-sqa.php`, `herramientas/revision-bitacora.sql` | Borrador completo |
| `evidencia/*.txt` | Salidas reales del script (PHP 8.4) |
| `plantillas/tablero-seguimiento.csv` | Primera fila = línea base medida |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- **La línea base no se maquilló.** Hay 2 métricas en ROJO y 1 en ÁMBAR. M2 descubrió un hueco real en las pruebas: los códigos de negocio `USUARIO_INACTIVO` y `USUARIO_NO_ENCONTRADO` no se afirman.
- **Los defectos se inyectaron en copias**, extraídas con `git archive`. El repositorio no se tocó.
- **Hay piezas sin ejecutar o sin implementar:**
  - las consultas SQL de CD-04 no se ejecutaron porque no hay PostgreSQL configurado;
  - M7 se midió en memoria;
  - CP-05 y CD-05 están definidos pero no implementados (S15).
- **Probabilidad e impacto son juicio del autor.** Se apoyan en evidencia (hallazgos y métricas) y el equipo debe revisarlos.
- **No se asignaron nombres de compañeros:** RSQA, RS y MR quedan por confirmar.
- **El Micro-HIS no se modificó.** Los arreglos de M2 y M6 tocan el paquete congelado de la revisión S13.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Métricas M1–M7, umbrales y acciones | | |
| Matriz de riesgos (P, I, nivel) | | |
| Controles CP-01..07 y CD-01..07 | | |
| Responsables y calendario | | |
| Plan de acción de la línea base | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `medir-sqa.php` y obtuve la misma línea base.
- [ ] Revisé la probabilidad y el impacto de los 3 riesgos altos y estoy de acuerdo, o los ajusté.
- [ ] Acordé con el grupo quién es RSQA, RS y MR.
- [ ] Puedo explicar por qué M2 está en rojo aunque todas las pruebas pasen.

**Fecha de validación:** ____________
