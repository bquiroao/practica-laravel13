# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 10 — Adaptación móvil (320–430 px) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y construir las 4 pantallas responsive (HTML y CSS estáticos, sobre la base compartida con Pacientes).
- Adaptar el verificador responsive y el script de reintentos; generar las capturas y las tiras de los escenarios.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Adapte «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» de Gobernanza y auditoría de movimientos del sistema a móvil (320–430 px) priorizando contenido, reducción de carga cognitiva, navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada para audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas. Entregable/Evidencia: Propuesta responsive de 3–5 pantallas, reglas de breakpoint y dos escenarios móviles con decisiones de contenido y error.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-10/prototipo/*` | Completo; la base de `estilos.css` y `estado.js` viene del prototipo de Pacientes |
| `semana-10/herramientas/*` | Adaptados de Pacientes y corregidos (ver abajo) |
| `semana-10/capturas/*`, `escenarios/*`, `evidencia/*` | Salida real de los scripts |
| `semana-10/*.md` | Borradores |

**Hechos a tener en cuenta:**
- **La primera corrida del verificador falló en 14 vistas** (`summary` de 22 px). Se corrigió el CSS.
- **Revisando las capturas a mano se vio un desborde** que el verificador no detectaba. La causa era un error de medición del instrumento (`innerWidth`), que ya traía el verificador de Pacientes. Se corrigió y se probó que ahora lo detecta.
- **El script de reintentos mostró algo no previsto:** repetir la consulta trae arriba la meta-auditoría de la consulta anterior. Se documentó como comportamiento real y cambió el diseño (sin reintentos automáticos).
- **Los tiempos de espera de 5 s y 20 s son una propuesta**, no una medición.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pantallas M1–M4 | | |
| Reglas de breakpoint | | |
| Decisión de no reintentar solo | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en el modo móvil del navegador a 320 y 360 px.
- [ ] Ejecuté los dos scripts de `herramientas/`.

**Fecha de validación:** ____________
