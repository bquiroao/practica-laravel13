# Declaración de uso de inteligencia artificial — Semana 1

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 1 — Modelado UML del proceso «asignación de rol, autorización de una operación y denegación trazable» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Revisar el código y el esquema de base de datos existentes (`hospital_memberships`, `TenantMiddleware`, `JwtAuth`, `config/permission.php`) para que el modelo se basara en el sistema real.
- Redactar la narrativa de alcance, la especificación de casos de uso y la matriz de trazabilidad.
- Escribir las fuentes PlantUML de los diagramas de casos de uso, actividad y secuencia, y generar sus imágenes.
- Preparar la guía de defensa oral.

## 3. Prompts relevantes

1. Organizar la documentación en una carpeta `docs` dividida por módulo.
2. Instrucción de la tarea, entregada textualmente:
   > Modele el proceso «asignación de rol, autorización de una operación y denegación trazable». Casos de uso: delimitar actores y objetivo; el de actividad debe incluir decisiones, excepciones y resultado; el de secuencia debe mostrar participantes, mensajes, validaciones y respuesta. Mantenga trazabilidad entre los tres diagramas. Entregable/Evidencia: UML de casos de uso y narrativa de alcance.
3. Agregar en cada tarea semanal un archivo `DECLARACION_IA` y otro `GUIA_DEFENSA_ORAL`.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-01-uml-proceso-rbac.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Actores y casos de uso | | |
| Reglas de negocio R-01 a R-08 | | |
| Diagrama de actividad | | |
| Diagrama de secuencia | | |
| Matriz de trazabilidad | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Leí completo el documento y entiendo cada decisión del modelo.
- [ ] Verifiqué que los diagramas correspondan a la tabla `hospital_memberships` y a los middleware del repositorio.
- [ ] Comprobé que los identificadores CU, A, D, E, S y R sean coherentes entre los tres diagramas y la matriz.
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
