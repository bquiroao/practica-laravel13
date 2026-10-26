# Declaración de uso de inteligencia artificial — Semana 3

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 3 — Micro-HIS en PHP 8.2 vanilla con capas Presentation, Application, Domain y Persistence; vista arquitectónica C4/UML |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso a mi repositorio local. Escribió el código y la documentación en un entorno de trabajo, ejecutó las pruebas y copió los archivos a mi carpeta. No hizo commits ni push: los hice yo. |

## 2. Propósito del uso

- Diseñar la separación en cuatro capas y la regla de dependencias a partir de las clases de la semana 2.
- Escribir el código del micro-HIS (dominio, casos de uso, adaptadores PDO, controlador HTTP) y el esquema PostgreSQL basado en las tablas `clinical_notes` y `diagnoses` del repositorio del grupo.
- Escribir las pruebas: dobles de PDO, repositorios en memoria, pruebas de arquitectura con `token_get_all()` e integración con SQLite y PostgreSQL.
- Escribir las fuentes PlantUML de los diagramas C4 (contexto, contenedores, componentes), el de paquetes y el de la vista dinámica, y generar sus imágenes.
- Redactar el documento de la semana, la guía de defensa oral y esta declaración.

## 3. Prompts relevantes

1. "Ya sigamos" (continuar con la semana 3 después de entregar la semana 2).
2. Texto de la tarea:
   > Inicie «Micro-HIS Notas SOAP y diagnósticos» en PHP 8.2+ vanilla para ejecutar «registro de nota SOAP y asociación de diagnóstico clínico». Separe Presentation, Application, Domain y Persistence; use PDO con sentencias preparadas, sin framework, y pruebe al menos camino feliz, regla de dominio y error de persistencia mediante dobles o una base de prueba. Entregable/Evidencia: Vista arquitectónica C4/UML del módulo y dependencias.
3. Seguir la estructura que usan mis compañeros en `docs/modulos/<modulo>/` y entregar cada semana como un PR propio desde `develop`.

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Reglas R-01 a R-08, casos de uso CU-01 a CU-05 | Míos, de la semana 1 |
| Requerimientos RF/RNF y criterios CA-01 a CA-12 | Míos, de la semana 2 |
| Tablas y columnas (`clinical_notes`, `diagnoses`, `care_assignments`) | Repositorio del grupo (migraciones de la base HOSPITAL) |
| Código de `micro-his-notas-soap/` | Generado con IA a partir de mi diseño de las semanas 1 y 2 |
| Pruebas y dobles de `tests/` | Generados con IA |
| `semana-03-arquitectura-micro-his.md` | Borrador generado con IA |
| `diagramas/*.puml` y `*.png` | Generados con IA; imágenes generadas con PlantUML |
| `evidencia/*.txt` | Salida real de las pruebas y de la API ejecutadas con PHP 8.3 y PostgreSQL 16 |
| `GUIA_DEFENSA_ORAL.md` | Borrador generado con IA |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Decisiones DA-01 a DA-09 | | |
| Diagramas C4 (contexto, contenedores, componentes) | | |
| Diagrama de paquetes y regla de dependencias | | |
| Vista dinámica | | |
| Código del micro-HIS | | |
| Pruebas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` en mi computadora y anoto el resultado: ____________
- [ ] Leí el documento y puedo explicar por qué Dominio no depende de Persistencia.
- [ ] Revisé que todas las consultas usen `prepare()` y `execute()` con marcadores.
- [ ] Comprobé que no hay credenciales en el repositorio (solo variables de entorno).
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar el código y los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
