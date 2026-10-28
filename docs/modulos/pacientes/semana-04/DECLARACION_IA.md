# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Pacientes: registro, edición, búsqueda y detalle |
| **Entrega** | Semana 4 — Capas, MVC y patrón Repository en el Micro-HIS Pacientes; análisis del repositorio de datos compartido |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Reorganizar la entrada del micro-HIS en MVC (controlador, formulario, vistas) sin tocar el Dominio ni la Aplicación.
- Convertir los dobles en memoria en adaptadores InMemory de producción, agregar las fábricas y la selección por configuración, y escribir la suite de contrato común a PDO e InMemory.
- Agregar pruebas MVC y de arquitectura (ARQ-06 a ARQ-08), ampliar la comprobación por mutación y crear la demo sin base de datos.
- Revisar las migraciones reales (CENTRAL: MPI y sincronización; HOSPITAL: `local_patients`) y la especificación del esquema federado (`openspec/`) para el análisis del repositorio compartido.
- Dibujar los diagramas y redactar el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Continúe el MISMO «Micro-HIS Pacientes: registro, edición, búsqueda y detalle» y el flujo «registro, búsqueda y actualización segura de un paciente». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. Instrucción permanente: modificar el proyecto principal cuando haga falta. Esta semana **no** se modificó. Las tres migraciones necesarias para integrar (`version`, `local_access_audit`, `outbox_event`) se documentaron como trabajo pendiente porque afectan un esquema compartido por el equipo y no se pudieron validar en este equipo.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `../micro-his-pacientes/` (MVC, adaptadores InMemory, fábricas, suite de contrato, pruebas MVC y ARQ, demo, mutaciones, README) | Borrador completo |
| `semana-04-capas-mvc-repositorio.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; imágenes generadas con PlantUML 1.2024.7 |
| `evidencia/*.txt` | Salida real de la ejecución con PHP 8.4.26 |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Hechos a tener en cuenta:**
- En la primera ejecución falló CON-MEM-02 por un error de la propia prueba: usaba `1990-01-1`, que no es una fecha parcial válida según R-10. Se corrigió la prueba, no el código.
- El diagrama de Repository hizo fallar el motor de dibujo de PlantUML (smetana). Se simplificó quitando dos relaciones con `Restaurable`.
- La suite de contrato contra PDO y la integración **no se ejecutaron** por falta de la contraseña de PostgreSQL.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| MVC: controlador, formulario y vistas | | |
| Interfaces Repository en el Dominio | | |
| Adaptadores InMemory y fábricas | | |
| Suite de contrato | | |
| Análisis del repositorio compartido (§8) | | |
| Tabla de objetos reutilizables (§6) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y obtuve 50 pasan, 0 fallan, 2 omitidas.
- [ ] Ejecuté `php bin/demo.php` y entiendo cada respuesta.
- [ ] Puedo explicar por qué la interfaz Repository está en el Dominio y no en la Persistencia.
- [ ] Verifiqué las tablas citadas en §8 contra `database/migrations/`.
- [ ] Comprobé que las referencias bibliográficas existan.

**Fecha de validación:** ____________
