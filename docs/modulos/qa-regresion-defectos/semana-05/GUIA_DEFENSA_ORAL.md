# Guía de defensa oral — Semana 5 (cliente-servidor, contrato API y frontera)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md)

## 1. Resumen en 60 segundos

Evalué dos fronteras y medí las dos.

**El almacén de evidencias sí se separa:**
- cuesta 11 ms por resultado;
- saca de la base capturas de hasta 5 MB;
- si se cae, el servidor dice «no» en 2 ms (fail-closed, con circuito);
- el cliente deja el resultado **pendiente** y lo reenvía con la misma clave.

**El módulo QA no se separa:**
- ninguno de los cinco criterios medibles se cumple;
- separarlo obligaría a consultar las membresías por API con caché, y perdería la revocación inmediata.

El contrato OpenAPI de las 9 operaciones pasa el lint. Tres pruebas comprueban que contrato y código coinciden, y dos mutaciones demuestran que lo detectan. 94 pruebas y 29/29 mutaciones.

## 2. Orden sugerido

1. La tabla de decisión y la medición (§1).
2. Por qué QA tiene una **bandeja** y Prescripciones no: un resultado ya ocurrió (§1, §9).
3. El contrato: transiciones como sub-recursos y una sola operación con `Idempotency-Key` (§4).
4. Correr `php tests/ejecutar.php --solo=API,CLI,AHT`.
5. Los criterios C-1..C-5, con su estado de hoy (§5.3).
6. El plan de slices y worktrees (§13).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el almacén sí y QA no? | El almacén guarda bytes grandes, no tiene reglas y su PUT por huella es idempotente: separarlo cuesta 11 ms y no rompe ninguna garantía. QA lee la membresía en cada operación: separarlo rompe la revocación inmediata y no gana nada medible. |
| ¿La bandeja no contradice el fail-closed? | No. Fail-closed es del servidor: sin evidencia confirmada no registra nada. La bandeja es del cliente: guarda la petición completa y la reenvía igual. Si llega tarde, el servidor la rechaza con 409 y el tester se entera (CLI-06). |
| ¿Por qué no hay `Idempotency-Key` en cerrar o iniciar? | Son transiciones de estado: repetirlas da 409, no un duplicado. El cliente no las reintenta; si no hay respuesta, informa INCIERTO y se consulta el estado (CLI-07). |
| ¿Cómo sabe que el YAML no miente? | Por API-01..03: rutas, permisos, estados y códigos se comparan con el código ejecutándolo. M23 y M24 cambian el YAML y la suite falla. |
| ¿Los 118 ms de C no son lentos? | Están inflados por `php -S`. Igual, es una persona registrando un resultado cada varios minutos. La latencia no decide aquí: deciden el volumen, la disponibilidad y los permisos. |
| ¿Qué tendría que pasar para separar QA? | Que se cumpla C-1..C-5. Por ejemplo, que el HIS deje QA sin servicio más de una vez al mes por despliegues (C-3). Eso se puede medir desde esta semana con el registro de acceso. |

## 4. Cambios que podrían pedir en vivo

| Cambio | Dónde | Cómo verificar |
|---|---|---|
| Límite del almacén a 5 s | `AlmacenEvidenciasHttp::LIMITE_SEGUNDOS` | AHT-04 (ajustar el rango) |
| Circuito que abre tras 5 fallas | `new CircuitoInterruptor($reloj, 5)` | AHT-03 |
| Una operación nueva en el contrato | YAML + ruta + caso de uso con `PERMISO` | API-01 falla hasta que estén los tres |

## 5. Comandos

```bash
cd docs/modulos/qa-regresion-defectos/micro-his-qa
php tests/ejecutar.php --solo=API,CLI,AHT,OBS
QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php bin/medir.php 100
npx @redocly/cli@1.34.3 lint api/openapi.yaml
```
