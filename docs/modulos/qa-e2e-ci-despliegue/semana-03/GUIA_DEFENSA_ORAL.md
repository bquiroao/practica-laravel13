# Guía de defensa oral — Semana 3 (Micro-HIS por capas)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Documento:** [semana-03-arquitectura-micro-his-e2e.md](semana-03-arquitectura-micro-his-e2e.md)

## 1. Resumen en 60 segundos

El Micro-HIS E2E está en PHP vanilla con cuatro capas y PDO con sentencias preparadas sobre PostgreSQL. Tiene 8 casos de uso: del escenario E2E al quality gate, pasando por la versión, el plan, la ejecución y los resultados con evidencia.

Las reglas críticas están también en la base: plan congelado, digest de la ejecución, solo agregar e idempotencia.

Resultados:
- 39 pruebas: camino feliz, reglas y errores de persistencia, con dobles y con PostgreSQL real;
- 16 mutaciones detectadas;
- una demo por HTTP real.

La base encontró un error que los dobles escondían: una carrera con la misma clave habría dado 500 en vez de 409. Y las mutaciones mostraron dos huecos en mis pruebas.

## 2. Demostración sugerida (4 minutos)

1. `php tests/ejecutar.php`: 32 pasan, 7 omitidas sin base.
2. Con `E2E_DB_DSN`: 39/39.
3. `bash bin/demo-http.sh`: el plan sin el crítico da 422; un PASA con dos intentos queda INESTABLE; el pipeline recibe 403 al evaluar; el Responsable de release recibe RECHAZADO.
4. Abrir `schema.sql`: el trigger que exige el digest de la versión.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué las reglas también en la base? | Porque alguien puede escribir sin pasar por la aplicación (un script, una migración). INT-02..INT-05 lo prueban saltándose la aplicación. |
| ¿Por qué el almacén antes que la base? | Fail-closed: si la evidencia no se confirma, no se registra el resultado. Al revés quedaría un resultado que apunta a algo que no existe. |
| ¿Por qué las reglas antes que el almacén? | Para no dejar artefactos huérfanos de un resultado rechazado. La mutación M12 lo verifica. |
| ¿Qué fue lo de INT-05? | `traducir()` declaraba devolver `RuntimeException` y devolvía un `DomainException`: `TypeError` y 500. Solo aparece con el `UNIQUE` real de PostgreSQL. |
| ¿Para qué sirve la regla de digest en el gate si la ejecución ya lo exige al iniciar? | Defensa en profundidad para datos importados o alterados. Ninguna prueba llegaba a ella hasta que la mutación M7 lo mostró: DOM-10. |
| ¿Cómo verifica las capas? | ARQ-01..05 leen los `use` de cada archivo; Presentación ni siquiera importa el Dominio. |
