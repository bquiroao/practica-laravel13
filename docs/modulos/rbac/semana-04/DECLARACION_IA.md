# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 4 — Arquitectura en capas, MVC y patrón Repository |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Reorganizar la Presentación del Micro-HIS RBAC en MVC: controladores, formularios y vistas.
- Agregar adaptadores InMemory de producción junto a los PDO, con fábricas y selección por configuración.
- Reemplazar los dobles de prueba duplicados por decoradores sobre los adaptadores reales.
- Escribir la suite de contrato del patrón Repository, las pruebas MVC y las reglas de arquitectura ARQ-05 a ARQ-07, y ejecutarlas.
- Revisar el esquema federado del HIS (migraciones CENTRAL/HOSPITAL) para el análisis del repositorio de datos compartido.
- Escribir los diagramas y redactar el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Continúe el MISMO «Micro-HIS RBAC: roles, permisos y protección de rutas» y el flujo «asignación de rol, autorización de una operación y denegación trazable». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Cambios en `micro-his-rbac/` (Presentación MVC, Persistencia InMemory, fábricas, `bin/demo.php`, `datos-demo.php`, pruebas nuevas, README) | Borrador completo |
| `semana-04-capas-mvc-repositorio.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 |
| `evidencia/salida-pruebas.txt` y `evidencia/salida-demo.txt` | Salidas reales de los comandos (PHP 8.4.26) |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Verificaciones que hizo la herramienta:**

- `php -l` sin errores en todos los archivos.
- Pruebas: 44 pasan, 0 fallan y 2 omitidas (las que requieren PostgreSQL).
- Se inyectaron violaciones temporales para comprobar que ARQ-05, ARQ-06 y ARQ-07 fallan, y luego se revirtieron.
- La API real se probó con `php -S` en modo memoria.

**Corrección durante el trabajo:** la prueba MVC-01 detectó que la validación de fechas aceptaba `"x"` (zona horaria militar). Se corrigió exigiendo ISO-8601.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| MVC: controladores, formularios y vistas | | |
| Interfaces Repository en el Dominio | | |
| Adaptadores InMemory, fábricas y selección por configuración | | |
| Suite de contrato y decoradores de prueba | | |
| Análisis del repositorio de datos compartido (§8) | | |
| Diagramas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` y `php bin/demo.php` en mi equipo.
- [ ] (Opcional) Ejecuté la suite de contrato contra PostgreSQL (`CON-PDO-*`) definiendo `MICROHIS_DB_DSN`.
- [ ] Puedo explicar por qué la interfaz Repository está en el Dominio y no en la Persistencia.
- [ ] Puedo mostrar que un controlador no tiene SQL ni reglas de negocio.
- [ ] Revisé la correspondencia de tablas con `hospital_memberships`, `central_users` y `local_user_refs` en las migraciones del HIS.

**Fecha de validación:** ____________
