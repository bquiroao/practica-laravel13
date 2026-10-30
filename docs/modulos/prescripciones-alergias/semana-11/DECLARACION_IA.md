# Declaración de uso de inteligencia artificial — Semana 11

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Entrega** | Semana 11 — Prototipo navegable desktop y móvil |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó la paridad contra el kernel del Micro-HIS, el recorrido (Chrome sin interfaz y axe-core) y PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Construir el prototipo navegable (una aplicación con la API simulada) a partir de las semanas 8, 9 y 10.
- Escribir las herramientas de paridad (`paridad.cjs`, `paridad.php`, `paridad-secuencia.json`) y de recorrido (`recorrido.cjs`).
- Dibujar el mapa de navegación; redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Construya un prototipo navegable desktop y móvil del flujo principal de «creación de prescripción con verificación previa de alergias» en Prescripciones electrónicas con validación de alergias, cubriendo camino feliz y un error crítico de prescripciones, medicamentos, dosis, alergias, validación previa y bloqueo seguro; mantenga consistencia de roles, estados y accesibilidad. Entregable/Evidencia: Enlace o archivo del prototipo, mapa de navegación y capturas desktop/móvil del camino feliz y del error crítico.
2. «Ok, continua con las demás semanas».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*` | Código completo. La API simulada copia las reglas del Micro-HIS clase por clase; el CSS es el de la semana 10 con agregados |
| `herramientas/*` | Código completo; la estructura se adaptó de Auditoría, semana 11 |
| `capturas/*`, `evidencia/*`, `diagramas/*.png` | Salidas reales de las herramientas |
| `semana-11-prototipo-prescripciones.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **La paridad se ejecuta contra el kernel real** (adaptadores en memoria de producción), no contra otra copia.
- **El defecto 2 (botón inalcanzable en el teléfono) lo encontró el recorrido.** La regla «tapados» se agregó entonces; la primera corrida quedó como evidencia.
- **La hoja «Confirmar con el paciente» se probó con un script puntual** que no quedó en el repositorio (ver §8 del documento).
- **Los nombres de pacientes, médicos y hospitales son ficticios.**

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Prototipo y error crítico elegido | | |
| Herramientas de paridad y recorrido | | |
| Observaciones de la API (§9) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Recorrí el prototipo en el navegador, en escritorio y en el emulador de móvil.
- [ ] Ejecuté `paridad.cjs` y `recorrido.cjs`.

**Fecha de validación:** ____________
