# Declaración de uso de inteligencia artificial — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API: OpenAPI/Postman y documentación técnica (adaptación: centro de documentación y manuales por rol) · **Entrega:** Semana 4 (tarea Odoo 2476) — MVC, interfaz Repository con adaptadores PDO e InMemory, y análisis del repositorio de datos compartido para «publicación y consulta de documentación y manuales diferenciados por rol»

## 1. Herramientas utilizadas

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Acceso de lectura y escritura al repositorio y a la terminal. Escribió la interfaz `RepositorioDocumentos`, la vista, las rutas, el front controller, el adaptador en memoria, los cambios del controlador, del adaptador PDO, del bootstrap y de las pruebas, el documento y los diagramas; ejecutó `php -l`, las pruebas, una prueba HTTP con `php -S` y `curl`, y una prueba de mutación en una carpeta temporal; generó los PNG con PlantUML local; creó la rama e hizo el commit. El push y el PR con `gh` se hacen solo tras la aprobación del estudiante. |
| Claude (chat de claude.ai) | No registrado | Anthropic | Herramienta distinta de esta sesión. Ayudó a organizar las tareas de Odoo y a preparar los prompts. |

## 2. Propósito del uso

Continuar el Micro-HIS de la Semana 3 organizando la entrada con MVC, con un controlador sin SQL ni reglas, una interfaz Repository con dos adaptadores probados con el mismo contrato, y analizar cómo lo integraría un repositorio de datos compartido.

## 3. Prompts relevantes

El enunciado de la tarea 2476, tomado textualmente de Odoo, y la orden del estudiante de trabajar por lotes en modo autónomo con buenas prácticas, verificando cada requisito con evidencia real.

## 4. Contenido generado y supuestos de la IA

Archivos del commit, todos borradores de Claude Code salvo las salidas reales: en `micro-his-contratos-api/`, `public/index.php`, `src/Presentacion/VistaJson.php`, `src/Presentacion/Rutas.php` y `src/Persistencia/RepositorioEnMemoria.php` (nuevos), y `bootstrap.php`, `src/Aplicacion/ServiciosDocumentacion.php`, `src/Persistencia/RepositorioPdo.php`, `src/Presentacion/ControladorDocumentacion.php` y `pruebas/pruebas.php` (modificados); `semana-04/semana-04-mvc-repository.md`, `semana-04/diagramas/` (2 `.puml` y 2 `.png`), `semana-04/evidencia/` (salida de pruebas y de HTTP), `DECLARACION_IA.md`, `GUIA_DEFENSA_ORAL.md` y las filas de las semanas 2 a 4 en el `README.md` del módulo. Supuestos: en la demo, usuario y roles llegan por las cabeceras `X-Usuario` y `X-Roles`; la base de la demo se elige con `CONTRATOS_DSN` y, por omisión, es un SQLite temporal; el centro vive en la base CENTRAL; la fábrica `crearControlador` pasa al espacio de nombres del módulo; los dueños de datos del repositorio compartido son una propuesta.

## 5. Contenido aceptado, modificado o descartado

| Elemento | Decisión | Detalle |
|---|---|---|
| Código, documento y diagramas (Claude Code) | Aceptado | El estudiante revisó el paquete de revisión del lote y lo aprobó con «ok» (ver §6). |
| Orden de tareas y prompts (Claude, chat de claude.ai) | Aceptado | El estudiante los usó para organizar el trabajo. |

## 6. Validación humana

- [x] Revisé el paquete de revisión de esta entrega (requisitos del enunciado, archivos, líneas, validaciones y supuestos) y la apruebo.
- [x] Acepto los supuestos anotados en §4.

**Fecha de validación:** 27/10/2026

Casillas de la §6 marcadas por Claude Code según lo declarado por el estudiante.
