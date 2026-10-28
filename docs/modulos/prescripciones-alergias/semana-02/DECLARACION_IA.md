# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Prescripciones electrónicas con validación de alergias |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y aplicación de DIP al flujo «creación de prescripción con verificación previa de alergias» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Ejecutó las pruebas contra un PostgreSQL 18 local. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Derivar los RF, RNF y criterios de aceptación desde las reglas de la semana 1.
- Diseñar y escribir el ejemplo ejecutable antes/después (dominio, puertos, adaptadores, composición).
- Escribir `verificar.php` (24 pruebas) y `mutaciones.php` (13 mutaciones), y ejecutarlos.
- Dibujar los diagramas de clases y redactar el documento y la guía.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Aplique DIP al diseño del flujo «creación de prescripción con verificación previa de alergias». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. Instrucciones permanentes: en cada tarea, agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-02-dip-requerimientos-diseno.md` | Borrador completo |
| `ejemplo-dip/` (antes, después, `verificar.php`, `mutaciones.php`) | Código completo |
| `diagramas/*.puml` y `*.png` | Borrador completo; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salidas reales de la ejecución |
| `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- La primera ejecución de DIP-01 falló por un falso positivo (la palabra «PDO» en un comentario). Se corrigió el instrumento para analizar el código sin comentarios.
- El defecto del diseño «antes» (DIP-05) es **intencional**: representa el error típico que DIP evita, y se demuestra ejecutándolo.
- Los datos (pacientes, medicamentos, códigos ATC, grupos) son ficticios y simplificados; los grupos de reactividad cruzada deben validarse con farmacia.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF-01..RF-14 y RNF-01..RNF-08 | | |
| Puertos y contrato de `FuenteAlergias` | | |
| Ejemplo antes/después | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `verificar.php` y `mutaciones.php` y obtuve los mismos resultados.
- [ ] Revisé que el diseño «antes» sea un punto de partida realista.

**Fecha de validación:** ____________
