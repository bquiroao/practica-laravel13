# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y aplicación de LSP al flujo «registro, búsqueda y actualización segura de un paciente» |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Derivar los RF, RNF y criterios de aceptación a partir de los casos de uso, nodos y reglas de la semana 1.
- Consultar la fuente obligatoria del curso (MVP Cluster) para citar textualmente la definición de LSP.
- Diseñar y programar el ejemplo PHP: el diseño antes (con tres violaciones de LSP), el diseño después, las pruebas de contrato y el script `verificar.php`.
- Ejecutar las pruebas y una comprobación por mutación sobre una copia del ejemplo.
- Dibujar los diagramas de clases antes/después en PlantUML y redactar el documento y la guía de defensa oral.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Aplique LSP al diseño del flujo «registro, búsqueda y actualización segura de un paciente». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.
2. Instrucciones permanentes: agregar `DECLARACION_IA` y `GUIA_DEFENSA_ORAL` en cada semana y poner mi nombre como estudiante.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `semana-02-lsp-requerimientos-diseno.md` | Borrador completo |
| `ejemplo-lsp/` (43 archivos PHP y README) | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `evidencia/salida-verificar.txt` y `evidencia/mutaciones.txt` | Salida real de la ejecución con PHP 8.4.26 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Errores encontrados durante el trabajo:**
- La primera ejecución dio 24/25. La prueba ANT-04 contaba también la palabra `instanceof` dentro de un comentario. Se corrigió para contar solo los tokens del código.
- La comprobación por mutación mostró que el contrato del repositorio estaba incompleto: una caché que no se invalidaba pasaba todas las pruebas (mutación M4). Se agregó la cláusula R8.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF-01 a RF-11 (incluido RF-04, nuevo: pacientes sin documento) | | |
| RNF-01 a RNF-09 | | |
| Criterios de aceptación CA-01 a CA-12 | | |
| Las tres violaciones del diseño antes (V1, V2, V3) | | |
| Diseño después y pruebas de contrato | | |
| Comprobación por mutación | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php docs/modulos/pacientes/semana-02/ejemplo-lsp/verificar.php` y obtuve 25/25.
- [ ] Puedo explicar con mis palabras qué cláusula del contrato rompe cada violación (V1, V2, V3).
- [ ] Revisé que cada CA tenga su prueba y que cada RF tenga origen en la semana 1.
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo modificar el ejemplo en vivo (ver `GUIA_DEFENSA_ORAL.md`, sección 6).

**Fecha de validación:** ____________
