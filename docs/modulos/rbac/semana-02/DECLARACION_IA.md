# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y aplicación de OCP |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Consultar la fuente obligatoria (https://mvpcluster.com/diseno-de-software-2/) para citar la definición de OCP.
- Redactar los requerimientos funcionales y no funcionales y los criterios de aceptación a partir del modelo de la semana 1.
- Diseñar e implementar el ejemplo PHP con el diseño antes/después y la regla de extensión.
- Escribir el script de verificación (18 pruebas) y ejecutarlo para obtener la evidencia.
- Escribir los diagramas de clases en PlantUML y generar sus imágenes.
- Preparar la guía de defensa oral.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Aplique OCP al diseño del flujo «asignación de rol, autorización de una operación y denegación trazable». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. Indicaciones permanentes: en cada tarea semanal agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md`.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-02-ocp-requerimientos-diseno.md` | Borrador completo |
| `ejemplo-ocp/` (código PHP, `verificar.php` y `README.md`) | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `evidencia/salida-verificar.txt` | Salida real de `php verificar.php` (PHP 8.4.26) |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF-01 a RF-10 | | |
| RNF-01 a RNF-09 | | |
| Criterios de aceptación CA-01 a CA-14 | | |
| Diseño antes/después y diagramas | | |
| Código del ejemplo y regla de extensión | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php docs/modulos/rbac/semana-02/ejemplo-ocp/verificar.php` en mi equipo y obtuve 18/18.
- [ ] Desactivé la línea de `ReglaRedHospitalaria` en `composicion.php`, verifiqué que solo falla OCP-01 y la restauré.
- [ ] Leí `Autorizador.php` y puedo explicar por qué no necesita cambios para agregar una regla.
- [ ] Revisé que cada RF/RNF tenga al menos un criterio o prueba asociada.
- [ ] Verifiqué la cita de OCP contra la fuente obligatoria.
- [ ] Puedo explicar y modificar el diseño sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
