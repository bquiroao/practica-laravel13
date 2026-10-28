# Declaración de uso de inteligencia artificial — Semana 4

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Entrega** | Semana 4 — Arquitectura en capas y patrón repositorio: MVC en la entrada, interfaz Repository con adaptadores PDO e InMemory, repositorio de datos compartido |

## 1. Herramienta utilizada

| Herramienta | Proveedor | Modo de uso |
|---|---|---|
| Claude (aplicación de escritorio, modo Cowork) | Anthropic | Asistente con acceso a mi repositorio local. Escribió el código y la documentación en un entorno de trabajo, ejecutó las pruebas y copió los archivos a mi carpeta. No hizo commits ni push: los hice yo. |

## 2. Propósito del uso

- Reorganizar la Presentación del micro-HIS de la semana 3 en controlador, modelo de entrada y vistas (MVC).
- Pasar los adaptadores en memoria de las pruebas a código de producción y escribir las pruebas de contrato que corren contra los dos adaptadores.
- Revisar las migraciones de CENTRAL y HOSPITAL del repositorio del grupo para basar el análisis del repositorio compartido en tablas reales.
- Escribir el prototipo de la bandeja de salida (evento, puerto, adaptadores y decorador).
- Escribir las fuentes PlantUML de los cinco diagramas y generar sus imágenes.
- Redactar el documento de la semana, la guía de defensa oral y esta declaración.

## 3. Prompts relevantes

1. "Ya, ¿qué sigue?" (empezar la semana 4 después de entregar la semana 3).
2. Texto de la tarea:
   > Continúe el MISMO «Micro-HIS Notas SOAP y diagnósticos» y el flujo «registro de nota SOAP y asociación de diagnóstico clínico». Organice la entrada con MVC; defina una interfaz Repository en el límite apropiado y adaptadores PDO e InMemory. El controlador no tendrá SQL ni reglas de negocio. Además, analice y diagrame cómo un repositorio de datos compartido integraría este módulo. Entregable/Evidencia: Capas, responsabilidades y objetos reutilizables.

## 4. Contenido de origen y contenido generado

| Elemento | Origen |
|---|---|
| Reglas R-01 a R-08, RF/RNF y CA | Míos, de las semanas 1 y 2 |
| Micro-HIS base (capas, PDO, pruebas) | Semana 3 |
| Tablas de CENTRAL y HOSPITAL usadas en el análisis | Migraciones del repositorio del grupo (`database/migrations/central/` y `hospital/`) |
| Cambios al código de `micro-his-notas-soap/` | Generados con IA |
| Pruebas nuevas (`REP`, `ARQ-09` a `ARQ-12`, `HTTP-08` a `HTTP-10`, `APP-10` a `APP-12`, `DOM-09`, `INT-07`, `INT-08`) | Generadas con IA |
| `semana-04-mvc-repository.md` | Borrador generado con IA |
| `diagramas/*.puml` y `*.png` | Generados con IA; imágenes generadas con PlantUML |
| `evidencia/*` | Salida real de las pruebas, de la demostración y de la API, ejecutadas con PHP 8.3 y PostgreSQL 16 |
| `GUIA_DEFENSA_ORAL.md` | Borrador generado con IA |
| `DECLARACION_IA.md` | Borrador (este archivo) |

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Organización MVC (controlador, formulario, vistas) | | |
| Ubicación de la interfaz Repository en el Dominio | | |
| Adaptadores en memoria y pruebas de contrato | | |
| Análisis del repositorio compartido (decisiones RC-01 a RC-07) | | |
| Prototipo de bandeja de salida | | |
| Diagramas | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` en mi computadora y anoto el resultado: ____________
- [ ] Ejecuté `php bin/demo-en-memoria.php` y entiendo qué muestra.
- [ ] Puedo explicar por qué la interfaz Repository está en el Dominio y no en Persistencia.
- [ ] Revisé que el controlador no tenga SQL ni reglas de negocio.
- [ ] Revisé en las migraciones del grupo las tablas de CENTRAL que cita el análisis.
- [ ] Comprobé que las referencias bibliográficas existan.
- [ ] Puedo explicar y modificar el código y los diagramas sin ayuda (ver `GUIA_DEFENSA_ORAL.md`).

**Fecha de validación:** ____________
