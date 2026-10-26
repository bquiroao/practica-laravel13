# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 10 — Diseño para movilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el prototipo responsive de 5 pantallas (HTML/CSS) partiendo de la hoja de estilos del prototipo de RBAC.
- Adaptar el verificador de breakpoints (Puppeteer + Chrome) y agregarle la regla R-D1 (sin datos personales en la URL).
- Escribir `reintento.php`, que mide contra el Micro-HIS si es seguro reintentar cada operación.
- Generar las capturas y las tiras de los dos escenarios.
- Redactar el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Adapte «registro, búsqueda y actualización segura de un paciente» a móvil (320–430 px): jerarquice los datos y acciones de identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad, reduzca carga cognitiva y defina navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada. Entregable/Evidencia: Propuesta responsive de 3–5 pantallas, reglas de breakpoint y dos escenarios móviles con decisiones de contenido y error.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana no se modificó el Micro-HIS ni la aplicación Laravel; el prototipo es estático.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*.html`, `estilos.css`, `estado.js` | Borrador completo; la hoja de estilos y `estado.js` parten del prototipo de RBAC |
| `herramientas/verificar-responsive.cjs` | Adaptado del de RBAC, con las vistas de Pacientes y la regla R-D1 |
| `herramientas/reintento.php` | Borrador completo |
| `capturas/*.png`, `escenarios/*.png` | Generadas por los scripts (Chrome headless y Pillow) |
| `evidencia/*.txt` | Salida real de los scripts |
| `semana-10-movil-pacientes.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La primera verificación dio 38 de 39.** En escritorio, los códigos de la tabla de resultados eran enlaces de 21 px de alto. Se corrigió el CSS y se volvió a medir: 39 de 39.
- **`reintento.php` mostró algo que no se había supuesto:** sin `Idempotency-Key`, el reintento del registro no duplica, pero responde un 409 engañoso. Eso llevó a la regla de enviar siempre la clave.
- Una leyenda de la tira del escenario 2 salió cortada; se agrandó el margen y se regeneró.
- Las capturas son de Chrome con viewport de teléfono, no de dispositivos reales.
- Todos los pacientes del prototipo son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Jerarquía de contenido y reglas de breakpoint | | |
| Las 5 pantallas | | |
| Política de conexión limitada | | |
| Escenarios 1 y 2 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en mi teléfono y recorrí las 5 pantallas.
- [ ] Ejecuté `php herramientas/reintento.php` y obtuve la misma tabla.
- [ ] Puedo explicar por qué el `PUT` no se reintenta y el `POST` sí.

**Fecha de validación:** ____________
