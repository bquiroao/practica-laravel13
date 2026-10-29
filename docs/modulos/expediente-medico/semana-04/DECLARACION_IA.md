# Declaración de uso de inteligencia artificial — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Entrega:** Semana 4 (tarea Odoo 2506) — MVC, interfaz Repository con adaptadores PDO e InMemory, y análisis del repositorio de datos compartido para «apertura y consulta autorizada del expediente longitudinal»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la interfaz `RepositorioExpedientes`, la vista, las rutas, el front controller, los adaptadores en memoria y los cambios del controlador, del adaptador PDO, del bootstrap y de las pruebas. También escribió el documento y los diagramas. Ejecutó `php -l`, las pruebas, una prueba HTTP con `php -S` y `curl`, y una prueba de mutación en una carpeta temporal; generó los PNG con PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Continuar el Micro-HIS de la Semana 3 organizando la entrada con MVC, con un controlador sin SQL ni reglas, una interfaz Repository con dos adaptadores probados con el mismo contrato, y analizar cómo lo integraría un repositorio de datos compartido.

## 3. Prompts relevantes

El enunciado de la tarea 2506, tomado textualmente de Odoo, y la orden del estudiante de retomar las semanas 4 y 5 pendientes en cuanto se integraran los PR de los que dependían, trabajando por lotes y verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales. En `micro-his-expediente/` son nuevos `public/index.php`, `src/Presentacion/VistaJson.php`, `src/Presentacion/Rutas.php` y `src/Persistencia/RepositoriosEnMemoria.php`. Se modificaron `bootstrap.php`, `src/Aplicacion/ServicioExpediente.php`, `src/Persistencia/RepositoriosPdo.php`, `src/Presentacion/ControladorExpediente.php` y `pruebas/pruebas.php`. También entran `semana-04/` (documento, 2 `.puml` y 2 `.png`, y la salida de las pruebas y de HTTP), esta declaración, la guía y la fila de la semana 4 en el `README.md` del módulo. La estructura sigue la semana 4 de los otros cinco módulos, ya integrada en `develop`. Supuestos: la interfaz Repository cubre solo la base HOSPITAL, y CENTRAL queda como puerto aparte, porque tiene otro dueño. La ruta para abrir es `POST /patients/{id}/record`, una propuesta, porque la semana 1 solo fija las de consulta. El profesional y el hospital llegan en las cabeceras `X-Profesional` y `X-Tenant-ID` hasta tener sesión. Las bases de la demo se eligen con `EXPEDIENTE_DSN` y `CENTRAL_DSN` y, por omisión, son SQLite temporales. La fábrica `crearControlador` pasa al espacio de nombres del módulo, y los dueños de datos del repositorio compartido son una propuesta. Validación: 25 de 25 pruebas en verde. En la prueba HTTP, `POST` dio 201, 409 E3 y 422 E2; `GET` local 200; `GET` externo 200 con h2; un profesional sin asignación 403 E4, y `DELETE` 404. La mutación de `CentralEnMemoria` hizo fallar 1 prueba. El script de autorrevisión de la semana 13 usa la fábrica anterior. Claude Code ya lo adaptó en una copia, que da la misma salida, pero el ajuste va en un PR aparte del módulo, junto con las filas pendientes del README, para que este PR tenga un solo propósito y quepa en 400 líneas. Al hacer la prueba HTTP, Claude Code corrigió un error propio: las rutas de las bases estaban en formato de Git Bash, que el PHP de Windows no abre.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Código, documento y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 29/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
