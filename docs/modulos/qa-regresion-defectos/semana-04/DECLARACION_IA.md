# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Entrega** | Semana 4 — Capas, MVC, patrón Repository y repositorio de datos compartido |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Escribió el código, las pruebas y el validador. Los ejecutó contra un PostgreSQL 18 local y generó los diagramas con PlantUML. Con autorización del estudiante, también creó la rama, hizo el commit y abrió y mergeó el PR. |

## 2. Propósito del uso

- Separar la entrada HTTP en formularios, controladores y vistas (MVC).
- Convertir los dobles de prueba en adaptadores InMemory de producción y escribir la suite de contrato del Repository.
- Analizar la integración con la base central del HIS, escribir el SQL de integración y su validador.
- Redactar el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Continúe el mismo Micro-HIS: aplique MVC en la entrada, defina una interfaz Repository en el límite apropiado con adaptadores PDO e InMemory, y asegure que el controlador no contenga SQL ni reglas de negocio. Analice y diagrame cómo se integraría el módulo con un repositorio de datos compartido. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. «Ok, haz todas las semanas, están en el mismo documento pdf que te mandé».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-qa/src/Presentacion/Http/{Formularios,Controladores,Vistas}/*`, `src/Persistencia/EnMemoria/*`, `src/Persistencia/FabricaAdaptadores.php` | Código nuevo |
| `micro-his-qa/database/{datos-demo.php,integracion-central.sql}`, `bin/{demo.php,validar-integracion-central.php}` | Código nuevo |
| `micro-his-qa/tests/{Contratos,Mvc}/*`, cambios en `tests/Dobles`, `Arquitectura`, `Integracion` y `mutaciones.php` | Pruebas |
| `semana-04/diagramas/*` | Fuentes PlantUML y sus PNG |
| `semana-04/evidencia/*` | Salidas reales. `6-contrato-en-rojo.txt` resume la primera corrida real de la suite de contrato |
| `semana-04-capas-mvc-repositorio.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Hechos a tener en cuenta:**
- **`integracion-central.sql` es una propuesta**, no una migración del HIS. Los roles `qa_tester`, `qa_lead` y `qa_analyst` son nombres propuestos: el HIS todavía no define esos `role_code`.
- **El validador sí usa el SQL real de las migraciones C0 y C1** del proyecto, extraído de los archivos.
- **De las 6 fallas de la primera corrida del contrato, 4 fueron errores de la propia prueba.** Están identificadas como tales.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Separación MVC (formularios solo forma) | | |
| Adaptadores InMemory en producción | | |
| Base central como repositorio compartido (opción B) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté las pruebas con y sin `QA_DB_DSN`, y el validador de integración.
- [ ] Revisé que los controladores no tengan SQL ni reglas.
- [ ] Revisé el análisis del repositorio compartido.

**Fecha de validación:** ____________
