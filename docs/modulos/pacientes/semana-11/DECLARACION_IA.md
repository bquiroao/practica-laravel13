# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 11 — Prototipo navegable desktop y móvil |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Escribir el prototipo navegable: rutas, vistas, formularios, diálogo y anuncios.
- Escribir la API simulada a partir del código del Micro-HIS: reglas, orden y códigos.
- Escribir las herramientas de verificación: `paridad.cjs` y `paridad.php` contra el kernel real, y `recorrido.cjs` con Puppeteer y axe-core.
- Generar las 30 capturas y el mapa de navegación.
- Redactar el documento de la semana, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Construya un prototipo navegable desktop y móvil del flujo principal de Pacientes: registro, edición, búsqueda y detalle, cubriendo camino feliz y un error crítico relacionado con identidad del paciente, datos demográficos, búsqueda, edición, duplicados y confidencialidad; mantenga consistencia de roles, estados y accesibilidad. Entregable/Evidencia: Enlace o archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana no se modificó el Micro-HIS: el prototipo lo usa como referencia.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | Borrador completo; la hoja de estilos parte de la semana 10 |
| `herramientas/*` | Borrador completo, siguiendo las herramientas de la semana 11 de RBAC |
| `capturas/**`, `diagramas/*.png` | Generadas por los scripts y PlantUML |
| `evidencia/*.txt` | Salida real de los scripts |
| `semana-11-prototipo-pacientes.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La paridad dio 33 de 33 en la primera ejecución completa.** Antes hubo que cambiar la forma de pasar los pacientes de demostración al script PHP: leerlos con una expresión regular desde `datos.js` falló, así que ahora se envían por la entrada estándar.
- **El recorrido encontró dos defectos reales del prototipo**, corregidos y documentados en §6:
  - el contraste del avatar de admisión (4.07:1);
  - una condición de carrera al cambiar de pantalla durante una búsqueda.
- **Una captura salió con la pantalla todavía cargando.** El recorrido daba por buena la comprobación porque leía el texto después de tomar la foto. Se agregó la espera a que termine la carga.
- **La brecha B-4 apareció al construir la edición:** el detalle no devuelve lo que el `PUT` exige. No se resolvió en el contrato esta semana; el prototipo pide confirmar el documento.
- **El mapa de navegación salió con «1.» delante de las rutas** (PlantUML interpretó `#` como una lista numerada); se corrigió.
- Todos los pacientes y datos son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo (rutas, vistas, estados) | | |
| Elección del error crítico | | |
| Herramientas de paridad y recorrido | | |
| Brecha B-4 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí `prototipo/index.html` y recorrí el camino feliz y el error crítico con los tres roles.
- [ ] Probé la red simulada («perder la próxima respuesta») y entiendo el resultado.
- [ ] Ejecuté `node herramientas/paridad.cjs` y obtuve 33 iguales.
- [ ] Puedo explicar la brecha B-4.

**Fecha de validación:** ____________
