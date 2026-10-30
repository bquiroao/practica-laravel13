# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 1 — Modelado UML del proceso «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Revisar el esquema real del proyecto para basar el modelo en él:
  - no existe una tabla `audit_logs`;
  - `cross_hospital_access_audits` ya es de solo inserción, con un trigger;
  - `central_users` y `hospitals` están en la base central.
- Redactar la narrativa de alcance, las reglas de negocio, la especificación de casos de uso y la matriz de trazabilidad.
- Escribir las fuentes PlantUML de los diagramas de casos de uso, actividad y secuencia, y generar sus imágenes.
- Preparar la guía de defensa oral.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Modele el proceso «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.
3. La delimitación del módulo en el README del proyecto: super administrador, tabla `audit_logs`, interfaz protegida y filtros obligatorios por usuario, módulo y rango libre de fechas.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-01-uml-proceso-auditoria.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; las imágenes se generaron con PlantUML |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La numeración S-01..S-53 de la matriz **se calculó desde el archivo `.puml`** (cada flecha suma uno), no a mano.
- Las decisiones de diseño (base central, sello encadenado, meta-auditoría, actor «auditor del hospital») las propuso la IA. Hay que validarlas con el docente y con los módulos emisores.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso | | |
| Reglas R-01..R-10 | | |
| Ubicación de `audit_logs` en la base central | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Revisé que los tres diagramas compartan los identificadores de la matriz.
- [ ] Revisé las reglas contra la delimitación del README.

**Fecha de validación:** ____________
