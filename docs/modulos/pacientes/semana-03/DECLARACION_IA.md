# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 3 — Micro-HIS Pacientes en PHP vanilla por capas y vista arquitectónica C4/UML |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Diseñar las cuatro capas del micro-HIS a partir de los contratos de la semana 2 y del esquema real (`local_patients`, `mpi_identifiers`).
- Programar el micro-HIS, el esquema SQL, los dobles de prueba (en memoria y de PDO) y las pruebas por capa, de arquitectura y de integración.
- Ejecutar las pruebas, la comprobación por mutación y una prueba de humo del servidor embebido.
- Dibujar las vistas C4 (contexto, contenedores, componentes), el diagrama UML de paquetes y la vista dinámica, y redactar el documento y la guía de defensa.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Inicie «Micro-HIS Pacientes: registro, edición, búsqueda y detalle» en PHP 8.2+ vanilla para ejecutar «registro, búsqueda y actualización segura de un paciente». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
2. Instrucción permanente: si hace falta código en el proyecto principal (fuera de `docs/`), agregarlo. Esta semana no se agregó, y el motivo está en el documento (§13): la tarea exige PHP sin framework y el proyecto principal es Laravel.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `../micro-his-pacientes/` (código, esquema, pruebas, README) | Borrador completo |
| `semana-03-arquitectura-micro-his.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `evidencia/salida-pruebas.txt` y `evidencia/mutaciones.txt` | Salida real de la ejecución con PHP 8.4.26 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Hechos a tener en cuenta:**
- La prueba de integración con PostgreSQL **no se ejecutó**: el servidor local pide contraseña y la IA no la tenía ni intentó adivinarla.
- La primera comprobación por mutación se hizo con `sed`, y dos mutaciones parecían sobrevivir. En realidad `sed` no las había aplicado por un problema de escapado. Se reemplazó por un script PHP que verifica que cada mutación se aplique antes de ejecutar las pruebas. Resultado: 8 de 8 detectadas.
- Al regenerar la vista dinámica, un `sed` partió una etiqueta en dos líneas y PlantUML marcó el error. Se corrigió a mano.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones arquitectónicas DA-01 a DA-09 | | |
| Vistas C4 (contexto, contenedores, componentes) | | |
| Diagrama de paquetes y vista dinámica | | |
| Código del micro-HIS (cuatro capas) | | |
| Esquema SQL (cifrado del documento, versión, trigger) | | |
| Pruebas (DOM, APP, PER, HTTP, ARQ, INT) y mutaciones | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` en `micro-his-pacientes` y obtuve 37 pasan, 0 fallan, 1 omitida.
- [ ] (Opcional) Definí `PACIENTES_DB_DSN` y ejecuté las pruebas de integración con mi PostgreSQL.
- [ ] Puedo explicar la regla de dependencias y mostrar cómo la verifica ARQ-01.
- [ ] Puedo explicar por qué el `UPDATE` incluye `AND version = :version`.
- [ ] Comprobé que las referencias bibliográficas existan.

**Fecha de validación:** ____________
