# Guía de defensa oral — Semana 2

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-02-srp-requerimientos-diseno.md](semana-02-srp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «SRP dice que una clase debe tener una sola razón para cambiar. Antes, una sola clase validaba, guardaba, auditaba y respondía HTTP: cuatro razones, pedidas por cuatro personas distintas. Después hay cinco piezas: el servicio ordena el flujo, la política decide las transiciones, el repositorio guarda, la auditoría deja evidencia y `RespuestaHttp` traduce a códigos. El script de verificación demuestra que el comportamiento es idéntico en 11 escenarios y que la política se prueba sola.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Dividir por «quién pide el cambio» | Es la definición práctica de SRP: una razón de cambio = un actor que la pide. |
| El servicio devuelve E2–E6, no HTTP | El dominio no debe saber de HTTP; si cambia el contrato, solo cambia `RespuestaHttp`. |
| Prueba de paridad antes/después | Demuestra que el refactor no cambió el comportamiento. |
| Prueba de mutación | Demuestra que las pruebas detectan una regla rota. |

## 3. Preguntas probables

**Si enfermería agrega el estado «en limpieza», ¿qué cambia?** Solo `PoliticaEstadosCama` (la lista de transiciones) y su prueba; el servicio, el repositorio y la auditoría no se tocan (RNF-02).

**¿Por qué no basta con métodos separados en la misma clase?** Porque la clase seguiría cambiando por cuatro motivos y cualquier cambio obligaría a volver a probarla entera.

**¿Cómo se corre la evidencia?** `php docs/modulos/catalogo-salas-camas/semana-02/ejemplo-srp/verificar.php`; debe terminar con «RESULTADO: todo correcto» y exit 0.
