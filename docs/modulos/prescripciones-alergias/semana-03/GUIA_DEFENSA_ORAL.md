# Guía de defensa oral — Semana 3

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

## 1. Resumen en 60 segundos

> «Inicié el Micro-HIS Prescripciones en PHP vanilla, sin framework. Tiene cuatro capas:
> - **Presentación:** el kernel HTTP autentica, exige que el hospital de la cabecera sea el del token y traduce resultados a códigos HTTP.
> - **Aplicación:** los casos de uso emitir, verificar previamente y consultar, que dependen solo de puertos.
> - **Dominio:** las reglas puras de dosis y de alergias.
> - **Persistencia:** seis adaptadores PDO con sentencias preparadas sobre PostgreSQL. La tabla de alergias tiene las columnas reales del HIS.
>
> La prescripción, sus ítems y la traza para Auditoría se guardan en una sola transacción, con una bandeja local. Una prescripción emitida no se puede cambiar, ni siquiera con SQL, porque hay un trigger. Si la tabla de alergias no responde, no se emite.
>
> Tengo 55 pruebas: camino feliz, reglas y errores de persistencia, con dobles y con PostgreSQL real. Además, 14 de 14 mutaciones detectadas y un recorrido HTTP real. Ese recorrido encontró dos defectos que los dobles no veían.»

## 2. Orden sugerido de la exposición

1. **C4 contexto y contenedores:** los cinco sistemas externos y cómo se simulan aquí.
2. **Componentes y paquetes:** la regla de dependencias y las pruebas ARQ que la vigilan.
3. **Vista dinámica:** las dos transacciones (emisión y traza de un bloqueo).
4. **Esquema:** `allergies` real, el trigger, el `UNIQUE` de idempotencia y la bandeja.
5. **Evidencia:** ejecutar la suite con base y mostrar INT-06 (tabla caída → 503) e INT-09 (paridad).
6. **Hallazgos H-1..H-3.**

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Bandeja local (outbox) para la traza | Una llamada HTTP a Auditoría no entra en la transacción; con la bandeja, prescripción y traza son atómicas (APP-14, INT-05) |
| `UNIQUE (hospital, idempotency_key)` además de la búsqueda | Dos peticiones simultáneas pasan la búsqueda; la base decide la carrera y el caso de uso responde como a un reintento (APP-12, INT-04) |
| Trigger de inmutabilidad | Lo firmado no cambia, aun con SQL directo (INT-02); la aplicación ni siquiera tiene métodos para cambiarlo (DOM-08) |
| `Resultado` sin HTTP | La misma situación (`VERIFICACION_NO_DISPONIBLE`) es 200 en la previa y 503 en la emisión: lo decide la Presentación |
| Restricciones de la base que repiten reglas | Segunda línea de defensa: no reemplazan al Dominio, lo respaldan (INT-07) |
| Paridad dobles ↔ PDO (INT-09) | Los dobles se usan en 46 pruebas; si se desvían de la realidad, todas mienten. H-3 lo demostró |

## 4. Preguntas probables y respuestas

**¿Por qué el repositorio está en el Dominio y la fuente de alergias en la Aplicación?**
El repositorio guarda el agregado del dominio (`Prescripcion`): es un concepto del dominio. La fuente de alergias es otro módulo del HIS al que el caso de uso consulta: es una necesidad de la aplicación. En los dos casos el puerto lo define quien lo usa.

**¿Cómo se prueba un error de persistencia sin base?**
Con dos dobles:
- `PdoQueFalla`, un PDO que lanza en cada operación con un mensaje que contiene una contraseña falsa; PER-02 comprueba que no se filtra.
- `TransaccionEnMemoria`, que puede fallar en el COMMIT y restaura el estado (APP-15).

Con base, INT-05 y INT-06 hacen lo mismo de verdad.

**¿Qué detectan las mutaciones que solo la integración detecta?**
Tres:
- M12: el SQL ignora el hospital.
- M13: la carrera no se traduce.
- M14: el esquema sin trigger.

Son defectos en SQL o en el esquema, y por eso los dobles no los pueden ver.

**¿Por qué H-2 importa si la hora es la misma?**
Porque el cliente compara textos, y el mismo instante salía distinto según el adaptador. Además, la semana 1 decidió guardar en UTC: la salida debe ser consistente con esa decisión.

## 5. Recorrido de demostración

1. `php tests/ejecutar.php` → 46 pasan, 9 omitidas.
2. Con `PRESCRIPCIONES_DB_DSN` → 55/55.
3. `bash bin/demo-http.sh` → leer las respuestas 3 (201 + `Location`), 4 (reintento) y 5 (bloqueo).
4. `php tests/mutaciones.php` → 14/14.
