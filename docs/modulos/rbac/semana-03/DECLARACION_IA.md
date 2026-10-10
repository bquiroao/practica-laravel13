# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 3 — Arquitectura del Micro-HIS RBAC (C4/UML, capas y PDO) |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Diseñar la arquitectura en cuatro capas a partir del diseño OCP de la semana 2.
- Implementar el Micro-HIS RBAC en PHP 8.2+ vanilla: dominio, casos de uso, adaptadores PDO, front controller, esquema SQL y datos ficticios.
- Escribir las pruebas por capa, los dobles de PDO y las pruebas de arquitectura, y ejecutarlas.
- Escribir los diagramas C4 y UML en PlantUML y generar sus imágenes.
- Redactar el documento de arquitectura, el README del micro-HIS y la guía de defensa oral.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Inicie «Micro-HIS RBAC: roles, permisos y protección de rutas» en PHP 8.2+ vanilla para ejecutar «asignación de rol, autorización de una operación y denegación trazable». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-rbac/` (código, SQL, pruebas, README) | Borrador completo |
| `semana-03-arquitectura-micro-his.md` | Borrador completo |
| `diagramas/*.puml` y `diagramas/*.png` | Borrador completo; las imágenes se generaron con PlantUML 1.2024.7 (biblioteca C4-PlantUML incluida) |
| `evidencia/salida-pruebas.txt` | Salida real de `php tests/ejecutar.php` (PHP 8.4.26) |
| `GUIA_DEFENSA_ORAL.md` | Borrador completo |
| `DECLARACION_IA.md` | Borrador (este archivo) |

**Verificaciones que hizo la herramienta:**

- `php -l` sin errores en todos los archivos.
- 32 pruebas pasan, 0 fallan y 1 queda omitida (integración con PostgreSQL, sin credenciales).
- Se inyectaron violaciones temporales para comprobar que las pruebas de arquitectura fallan, y luego se revirtieron.
- Prueba manual del front controller con `php -S`: respondió 503 controlado sin base de datos.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones arquitectónicas DA-01 a DA-08 | | |
| Diagramas C4 (contexto, contenedores, componentes) | | |
| Diagrama de paquetes y vista dinámica | | |
| Código de las cuatro capas | | |
| Esquema SQL y datos de demostración | | |
| Pruebas y dobles | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` en mi equipo y obtuve 0 fallas.
- [ ] (Opcional) Configuré `MICROHIS_DB_DSN` y ejecuté las pruebas de integración contra mi PostgreSQL.
- [ ] Levanté la API con `php -S` y probé al menos una ruta con curl.
- [ ] Puedo explicar la regla de dependencias y por qué las interfaces de repositorio están en el Dominio.
- [ ] Puedo señalar en el código dónde se usan las sentencias preparadas.
- [ ] Revisé que los diagramas correspondan a las carpetas y clases reales.

**Fecha de validación:** ____________
