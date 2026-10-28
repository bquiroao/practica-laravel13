# Guía de defensa oral — Semana 5

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md) · **Código:** [`../micro-his-notas-soap/`](../micro-his-notas-soap/)

## 1. Resumen en 60 segundos

> «Convertí el micro-HIS en un servidor con contrato. El contrato está en OpenAPI 3.1: cuatro operaciones, cada una con su permiso, sus errores y sus cabeceras. Hay cuatro pruebas que comparan el YAML con el código, así que si alguien cambia una ruta sin actualizar el contrato, las pruebas fallan. También hice un cliente, que es lo que usaría Expediente para pedir notas por red: tiene timeout, un reintento y un circuit breaker. Lo más importante es la idempotencia: si se pierde la respuesta de un registro y el cliente reintenta con la misma clave, el servidor devuelve la misma nota y no la duplica. Después evalué si Notas SOAP debería ser un microservicio. Medí el mismo registro dentro del proceso y por red: por red cuesta 14 veces más, y 37 veces más si los vecinos también estuvieran separados. Además, cada registro necesita tres validaciones de otros módulos y una transacción en una sola base. Ninguno de los criterios C1 a C5 se cumple, así que la decisión es no separarlo y dejarlo como módulo con una frontera clara. La migración está por fases y solo llega a extraerlo si los números cambian.»

## 2. Orden sugerido de la exposición

1. **Cliente-servidor:** quién llama a quién y qué decide cada lado (sección 3).
2. **Contrato:** la tabla de endpoints y permisos, y API-01 a API-04 (sección 4).
3. **Frontera:** el diagrama de lo que el registro necesita de otros módulos (sección 5.1).
4. **Medición:** la tabla A, B, C, D y C0, y cómo leerla (sección 5.2).
5. **Criterios C1 a C5 y decisión** (sección 5.3).
6. **Resiliencia:** el diagrama de la respuesta perdida (sección 9).
7. **Migración y plan de PR** (secciones 12 y 13).
8. **Demostración** (sección 5 de esta guía).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| No separar Notas SOAP como microservicio | Tres validaciones síncronas por registro (R-02, R-03, permiso), una transacción que necesita la misma base (R-06), la red cuesta 14× a 37× y C1 a C5 no se cumplen |
| Escribir el contrato aunque no se separe | Un contrato sirve igual para la SPA y para Expediente dentro del monolito. B (HTTP en proceso) cuesta solo 1,4×: lo caro es la red, no el contrato |
| Probar el contrato contra el código | Un YAML que nadie compara se queda viejo. API-01 a 04 fallan si no coinciden (la evidencia lo demuestra) |
| Permiso por ruta además del permiso en el caso de uso | La ruta corta lo grueso (enfermería no registra) antes de tocar la base; el caso de uso mantiene lo fino (asignación, autor) |
| `Idempotency-Key` solo en el registro | Es la única operación que crea algo. La firma ya es segura sola: firmar dos veces da 409 |
| La clave se guarda en la misma transacción que la nota | Si la nota se revierte, la clave también; así un reintento puede crearla (APP-16) |
| La clave es por usuario | Otro médico no puede usar mi clave para leer mi nota (APP-15) |
| La firma no se reintenta sola | Si la respuesta se pierde, el reintento daría 409 y el médico pensaría que hubo un error. Es mejor consultar la nota |
| El listado no trae el texto SOAP | La línea de tiempo no lo necesita; se expone menos información clínica (OWASP API3) |
| Circuit breaker en el cliente | Si Notas SOAP se cae, Expediente no espera un timeout en cada consulta; responde «no disponible» de inmediato |
| Umbral de 150 ms por salto | Es el presupuesto para que la pantalla completa, con sus otras consultas, quepa en los 2 s de RNF-07 |

## 4. Preguntas probables y respuestas

**¿Qué es la arquitectura cliente-servidor?**
Es un estilo en el que un lado pide (el cliente) y otro atiende (el servidor) por medio de un contrato. Aquí el cliente es la SPA o Expediente, y el servidor es Notas SOAP. El servidor no guarda estado entre peticiones: cada una trae el token y el hospital.

**¿Qué es un microservicio y en qué se diferencia de un módulo?**
Un microservicio se despliega por separado, tiene su propia base de datos y se comunica solo por la red. Un módulo vive en la misma aplicación y la misma base, pero respeta una frontera: solo se usa a través de su interfaz. Notas SOAP hoy es un módulo con frontera.

**¿Por qué no lo separó, si ya tiene contrato?**
Porque cada registro necesita preguntar a Expediente si el expediente está abierto y a asignaciones si el médico está asignado. Separado, eso son tres llamadas de red por nota. Además, validar y guardar dejarían de ser atómicos: el expediente podría cerrarse entre la pregunta y el INSERT. Y ninguno de los criterios C1 a C5 se cumple.

**Sus números son menos de un milisegundo. ¿Eso no prueba que la red es barata?**
Las cifras absolutas son pequeñas porque todo corre en la misma máquina y sin base. Por eso uso proporciones: la red multiplica 14 veces el costo y C0 muestra que es el cruce de red, no la lógica. En una red real cada salto cuesta milisegundos. En Windows un compañero midió entre 33 y 62 ms por salto. Por eso la fase 3 exige medir en staging.

**¿Qué pasa si se pierde la respuesta de un registro?**
El cliente no recibe nada, cuenta una falla, espera 100 ms y reintenta con la **misma** `Idempotency-Key`. El servidor encuentra la clave con la misma huella y devuelve 201 con la nota original y `Idempotent-Replayed: true`, sin insertar otra. Lo prueban CLI-02 (con una respuesta perdida a propósito) e INT-09 (con PDO real).

**¿Y si el cliente reutiliza la clave para otra nota?**
La huella SHA-256 del contenido no coincide y el servidor responde 422 `idempotency_key_reutilizada` (APP-14, HTTP-12).

**¿Qué es un circuit breaker?**
Es un interruptor en el cliente. Después de 3 fallas seguidas se «abre» y deja de llamar al servidor durante 30 s, respondiendo «no disponible» de inmediato. Luego deja pasar una prueba: si funciona, se cierra; si falla, se vuelve a abrir (CLI-06). Lo describe Nygard en *Release It!*.

**¿Qué es `X-Request-ID` y para qué sirve?**
Es un identificador que acompaña a la petición de punta a punta. Si un médico reporta un error, con ese id se encuentra su línea en el registro de acceso sin buscar por paciente ni por texto clínico.

**¿Qué guarda el registro de acceso? ¿No es un riesgo?**
Guarda id, método, ruta, estado, duración, usuario y tenant. No guarda token, cuerpo ni texto clínico (OBS-02). Si el registro falla, la respuesta no cambia (OBS-03).

**¿Quién es dueño de cada tabla?**
`clinical_notes`, `diagnoses`, `idempotency_keys` y las filas de la bandeja de salida son de este módulo. `medical_records` es de Expediente y `care_assignments` es de admisión (por confirmar). Esas dos solo las leo, y siempre por un puerto, para poder cambiar el adaptador si mañana tienen contrato.

**¿Qué es el patrón strangler fig?**
Es una forma de migrar por partes, como la higuera que crece alrededor de un árbol: lo nuevo se pone al lado de lo viejo, el tráfico se pasa de a poco y lo viejo se retira cuando ya nadie lo usa. Cada paso tiene una compuerta y una vuelta atrás.

**¿Cómo se relaciona esto con el repositorio de datos compartido de la semana 4?**
El evento `NotaSoapFirmada` y la bandeja de salida de la semana 4 son la comunicación asíncrona de esta semana. No cambiaron. Esta semana se agregó la parte síncrona (el contrato) y la evaluación de si separar.

**¿Por qué la rama del plan es `feature/asii-11-…` y no `semana-5-…`?**
Las entregas semanales usan la convención del grupo. La integración real al HIS usa la del README oficial, que es la que valida el check «ASII PR Guard».

## 5. Recorrido de demostración

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php
php bin/medir.php 1000 100
```

1. Mostrar el resumen de las pruebas: `Total: 101 … Fallan: 0` (sin PostgreSQL sale `Omitidas: 1`, que es INT-06).
2. Mostrar la tabla de la medición y comparar el p50 de A con el de C.
3. Romper el contrato a propósito: en `api/openapi.yaml`, cambiar el `x-permiso` de la firma a `notas_soap.consultar`. Correr las pruebas: falla API-01. Revertir el cambio.
4. Levantar la API sin base de datos y probar la idempotencia. `php -S` atiende cada petición en un proceso nuevo, así que hace falta un archivo para conservar lo guardado entre peticiones:

   ```bash
   NOTASSOAP_ALMACEN=memoria NOTASSOAP_MEMORIA_ARCHIVO=/tmp/notas-demo.ser php -S 127.0.0.1:8080 -t public
   ```

   En otra terminal, enviar dos veces el mismo registro con la misma clave:

   ```bash
   curl -i -X POST http://127.0.0.1:8080/api/v1/notas-soap -H "X-Tenant-ID: hosp-oriente" \
     -H "Authorization: Bearer token-mendez" -H "Idempotency-Key: demo-clave-0001" --data @ejemplos/nota.json
   ```

   La segunda respuesta trae `Idempotent-Replayed: true` y el mismo `clinical_note_id`. En PowerShell se usa `curl.exe` y, antes de `php -S`, `$env:NOTASSOAP_ALMACEN="memoria"` y `$env:NOTASSOAP_MEMORIA_ARCHIVO="$env:TEMP\notas-demo.ser"`. Para empezar de cero, borrar ese archivo.
5. Con `token-ana` (enfermería), el mismo `POST` responde 403 y `GET /api/v1/expedientes/exp-carlos/notas-soap` responde 200 con resúmenes.

## 6. Cambios que podrían pedirte en vivo

| Cambio pedido | Qué modificar |
|---|---|
| «Que enfermería también pueda firmar» | `config/config.php`, en `permisos_por_rol`. Pero el caso de uso igual exige ser el autor, así que respondería 403 en `FirmarNota`. Explicar las dos barreras |
| «Agregar un endpoint para listar los diagnósticos de una nota» | Una `Ruta` nueva en `Rutas.php` con su permiso, la acción en el controlador y la operación en `openapi.yaml`. Si falta en el YAML, falla API-01 |
| «Que el circuito se abra con 5 fallas» | El primer argumento de `CircuitoInterruptor` (umbral). Ajustar CLI-06 |
| «Que las claves de idempotencia caduquen» | Una columna `created_at` en `idempotency_keys` (ya existe en el esquema) y, en `buscar`, ignorar las más antiguas que 24 h |
| «Agregar un nuevo código de error» | Lanzarlo en el caso de uso, mapearlo en el `Kernel` y agregarlo al `enum` de `Error.code`. Si falta en el enum, falla API-04 |
| «¿Cuándo sí lo separaría?» | Sección 5.3: si se cumple alguno de C1 a C5 **y** el p95 por salto en staging es de 150 ms o menos |
