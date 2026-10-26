# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 2 — RF/RNF, criterios de aceptación y aplicación de SRP al flujo «registro de nota SOAP y asociación de diagnóstico clínico» |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso a la carpeta de mi rama. Escribió los archivos en la carpeta; los commits y el push los hice yo. |

## 2. Propósito del uso

- Revisar mi entrega original de la semana 2 (repositorio personal), que tenía RF/RNF y un ejemplo de SRP y OCP sin evidencia ejecutable.
- Seguir la estructura de la semana 2 que subió un compañero (`docs/modulos/<modulo>/semana-02/` con ejemplo ejecutable y evidencia).
- Derivar los RF/RNF y los criterios de aceptación de las reglas R-01 a R-08 de la semana 1.
- Escribir el ejemplo en PHP 8.2+ (antes y después), el script `verificar.php` y los diagramas de clases.
- Redactar el documento principal y la guía de defensa oral.

## 3. Prompts relevantes

1. "Ya démosle con el siguiente" (continuar con la semana 2 después de la semana 1).
2. Texto de la tarea:
   > Aplique SRP al diseño del flujo «registro de nota SOAP y asociación de diagnóstico clínico». Defina RF/RNF y criterios de aceptación; muestre el diseño antes/después, justifique responsabilidades y dependencias y aporte evidencia verificable. Entregable/Evidencia: RF/RNF, criterios de aceptación y ejemplo SOLID.

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Idea de separar `SoapNoteService`, `DiagnosisService` y `ElectronicSignatureService` | Mía, de mi entrega original de la semana 2 |
| `semana-02-srp-requerimientos-diseno.md` | Borrador generado con IA |
| `ejemplo-srp/` (antes, después y `verificar.php`) | Borrador generado con IA |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador generado con IA; imágenes generadas con PlantUML |
| `evidencia/salida-verificar.txt` | Salida real de ejecutar `verificar.php` |
| `GUIA_DEFENSA_ORAL.md` | Borrador generado con IA |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| RF-01 a RF-10 | | |
| RNF-01 a RNF-07 | | |
| Criterios de aceptación CA-01 a CA-12 | | |
| Diseño antes y después | | |
| Pruebas de `verificar.php` | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `verificar.php` en mi computadora y obtuve 20 pruebas aprobadas.
- [ ] Leí el código de `antes/` y de `despues/` y puedo explicar qué hace cada clase.
- [ ] Puedo mostrar en el código dónde estaba cada uno de los tres defectos (DEF-01 a DEF-03).
- [ ] Comprobé que cada CA tenga su prueba con el mismo ID.
- [ ] Comprobé que las referencias bibliográficas existan.

**Fecha de validación:** ____________
