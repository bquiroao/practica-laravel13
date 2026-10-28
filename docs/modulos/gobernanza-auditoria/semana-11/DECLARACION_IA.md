# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 11 — Prototipo navegable desktop y móvil |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir el prototipo: API simulada con el comportamiento del Micro-HIS, aplicación y datos de demostración.
- Escribir la prueba de paridad contra el kernel real y el recorrido automático con axe-core.
- Hacer el mapa de navegación y redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Construya un prototipo navegable desktop y móvil del flujo principal de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema, cubriendo camino feliz y un error crítico de audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas; mantenga consistencia de roles, estados y accesibilidad. Entregable/Evidencia: Enlace o archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-11/prototipo/*` | Completo; el CSS base viene de la semana 10 |
| `semana-11/herramientas/*` | Completo; el patrón (paridad y recorrido) viene de la semana 11 de Pacientes |
| `semana-11/capturas/*`, `evidencia/*` | Salida real de los scripts |
| `semana-11/*.md`, `diagramas/*` | Borradores |

**Hechos a tener en cuenta:**
- **La paridad dio 0 diferencias en el primer intento.** Por eso se agregó un control con un defecto inyectado, que demuestra que la herramienta sí detecta diferencias.
- **El paso 20 de la paridad** se llamaba «→ TRUNCADA» pero daba ALTERADA. El comportamiento era correcto (la ruptura se informa primero); se renombró y se agregó un paso que sí da TRUNCADA.
- **4 defectos de la interfaz** se encontraron con el recorrido y revisando capturas (§7 del documento), entre ellos un id duplicado que dejaba sin botón «Buscar».
- **Los sellos de la API simulada no son sha256** (limitación declarada).

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo y datos de demostración | | |
| Error crítico elegido (cadena ALTERADA) | | |
| Mapa de navegación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Recorrí el camino feliz y el error crítico a mano en escritorio y en móvil.
- [ ] Ejecuté `paridad.cjs` y `recorrido.cjs`.

**Fecha de validación:** ____________
