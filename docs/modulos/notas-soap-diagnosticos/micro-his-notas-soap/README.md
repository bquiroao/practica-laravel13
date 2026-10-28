# Micro-HIS Notas SOAP y diagnósticos (ASII-11)

Versión mínima y separada del módulo 11 del Sistema Hospitalario Integrado, escrita en **PHP 8.2+ vanilla** (sin framework ni Composer). Ejecuta un solo proceso de punta a punta: **registro de nota SOAP y asociación de diagnóstico clínico**, más la firma, la consulta y, desde la semana 6, la corrección de una nota firmada.

Se inició en la semana 3 y se usa como base en las semanas 4 a 7.

- Semana 3 — capas, PDO y vista C4: [`../semana-03/semana-03-arquitectura-micro-his.md`](../semana-03/semana-03-arquitectura-micro-his.md)
- Semana 4 — MVC en la entrada, patrón Repository con adaptadores PDO y en memoria, repositorio compartido: [`../semana-04/semana-04-mvc-repository.md`](../semana-04/semana-04-mvc-repository.md)
- Semana 5 — cliente-servidor, contrato OpenAPI, idempotencia, cliente con circuit breaker y evaluación de la frontera de microservicio: [`../semana-05/semana-05-cliente-servidor-microservicios.md`](../semana-05/semana-05-cliente-servidor-microservicios.md)
- Semana 6 — defensa de la arquitectura y cambio práctico «corregir una nota firmada»: [`../semana-06/semana-06-defensa-arquitectura.md`](../semana-06/semana-06-defensa-arquitectura.md)
- Semana 7 — diseño de componentes backend y frontend y refactor del punto de mayor acoplamiento: [`../semana-07/semana-07-componentes-refactor.md`](../semana-07/semana-07-componentes-refactor.md)

## Capas

```
src/
├── Presentacion/
│   ├── Http/           Kernel (front controller), Rutas (con permiso), Solicitud, Respuesta, autenticación demo
│   │   ├── Autorizacion/   PoliticaPermisos ← PermisosPorRol (permiso de cada ruta)
│   │   └── Observabilidad/ RegistroAcceso (una línea JSON por petición, sin datos clínicos)
│   ├── Controladores/  NotasSoapController (la C del MVC: sin SQL ni reglas)
│   ├── Entrada/        FormularioRegistroNota, FormularioCorreccionNota, FiltroNotasExpediente (entrada → comando; solo revisa formato)
│   └── Vistas/         VistaJson, VistaHtml (+ plantillas), SelectorVista (la V del MVC)
├── Aplicacion/         Casos de uso, comandos, resultados, puertos técnicos (incluye RegistroIdempotencia), decorador RepositorioNotasConEventos
│   └── Servicios/      RegistradorClinico (semana 7): la única transacción de escritura con su evento
├── Dominio/            NotaSoap, SeccionesSoap, Diagnostico, EventoClinico, excepciones e interfaces Repository
│   ├── Servicios/      ReglasDeEscritura (semana 7): R-01, R-02 y R-03 en un solo lugar
│   ├── Cambios/        CambioClinico, NotaConDiagnostico, FirmaDeNota (semana 7): qué se guarda en cada cambio
│   └── Eventos/        EntradaBitacora (semana 7): nombre y campos de cada evento de bitácora
└── Persistencia/
    ├── Pdo/            Adaptadores PDO (sentencias preparadas), transacción y bandeja de salida
    ├── EnMemoria/      Los mismos puertos sobre arreglos de PHP, con transacción en memoria
    └── Sistema/        Reloj y generador de UUID

src/Cliente/            Librería para otros módulos: ClienteNotasSoap, CircuitoInterruptor, TransporteHttpStream, ResultadoCliente
api/openapi.yaml        Contrato OpenAPI 3.1 (las pruebas API-01 a API-04 lo comparan con el código)
bin/medir.php           Mide el mismo registro en proceso, por HTTP en proceso y por red
bin/acoplamiento.php    Semana 7: Ce, Ca y preocupaciones mezcladas por clase, y líneas repetidas entre casos de uso
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**. El único archivo que conoce todas las clases concretas es `bootstrap/contenedor.php`: `crearKernel()` usa PDO y `crearKernelEnMemoria()` usa los adaptadores en memoria, sin cambiar nada más. La regla se comprueba en cada ejecución de las pruebas (grupo `ARQ`). El `Cliente` no es una capa del servidor: solo conoce el contrato y el servidor no lo importa (ARQ-13).

## Requisitos

- PHP 8.2 o superior con `pdo_pgsql` (para la API) y, opcionalmente, `pdo_sqlite` (para las pruebas de integración en memoria).
- PostgreSQL 15 o superior solo si se quiere levantar la API.

## Ejecutar las pruebas

```bash
php tests/ejecutar.php
```

No necesita base de datos: usa dobles en memoria y, si está `pdo_sqlite`, una base SQLite en memoria. Para incluir la prueba contra PostgreSQL:

```bash
NOTASSOAP_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_notas_soap" \
NOTASSOAP_DB_USUARIO=postgres NOTASSOAP_DB_CLAVE=<su clave> php tests/ejecutar.php
```

En PowerShell: `$env:NOTASSOAP_DB_DSN="pgsql:host=127.0.0.1;port=5432;dbname=micro_his_notas_soap"` y así con las otras dos variables, antes de `php tests/ejecutar.php`.

| Grupo | Qué prueba |
|---|---|
| `DOM` | Reglas de dominio: secciones obligatorias, formato CIE-10, tipo de diagnóstico, firma e inmutabilidad |
| `APP` | Casos de uso con dobles: camino feliz y cada rechazo (acceso, hospital, expediente, reglas) |
| `PER` | Errores de persistencia con dobles de PDO (`PdoQueFalla`, `PdoEspia`) y reversión de la transacción |
| `REP` | Contrato del Repository: las mismas pruebas contra el adaptador en memoria y el PDO |
| `HTTP` | Rutas, cabeceras, códigos de estado, vista HTML escapada y que los errores no expongan detalles internos |
| `ARQ` | Regla de dependencias, PDO solo en Persistencia, controlador sin SQL ni reglas, dos adaptadores por puerto, sin framework; desde la semana 7, los servicios del módulo solo reciben interfaces (ARQ-15) |
| `INT` | Adaptadores PDO contra SQLite y PostgreSQL, bandeja de salida, idempotencia, mismo flujo con PDO y en memoria, y modo memoria con archivo |
| `API` | El contrato `api/openapi.yaml` y el código dicen lo mismo: operaciones, permisos, códigos HTTP y códigos de error |
| `CLI` | Cliente: reintentos, respuesta perdida sin duplicar, firma sin reintento, circuit breaker y red real caída |
| `OBS` | `X-Request-ID` y registro de acceso sin datos sensibles |
| `COR` | Semana 6: corrección de una nota firmada (versión nueva, una por versión, permisos, reglas, atomicidad, línea de tiempo) |
| `REF` | Semana 7: caracterización del refactor (formato exacto de la bitácora, orden de las verificaciones y manejo de fallas en registro y corrección) |

## Demostración sin base de datos

```bash
php bin/demo-en-memoria.php
```

Corre el mismo Kernel, controlador y casos de uso con los adaptadores en memoria y muestra lo que quedó guardado, incluida la bandeja de salida.

También se puede levantar la API sin base de datos. Como `php -S` atiende cada petición en un proceso nuevo, para que los datos se conserven entre peticiones hay que indicar un archivo:

```bash
NOTASSOAP_ALMACEN=memoria NOTASSOAP_MEMORIA_ARCHIVO=/tmp/notas-demo.ser php -S 127.0.0.1:8080 -t public
```

En PowerShell: `$env:NOTASSOAP_ALMACEN="memoria"` y `$env:NOTASSOAP_MEMORIA_ARCHIVO="$env:TEMP\notas-demo.ser"` antes de `php -S`. Para empezar de cero, se borra el archivo.

## Medir la frontera

```bash
php bin/medir.php 3000 300
```

Ejecuta el mismo registro en cuatro escenarios (caso de uso en proceso, Kernel en proceso, servicio por red local y servicio por red con dos vecinos remotos) más un control. Muestra p50, p95 y p99. La interpretación está en la sección 5.2 del documento de la semana 5.

## Medir el acoplamiento

```bash
php bin/acoplamiento.php 14
```

Lee `src/` con el tokenizador de PHP (no ejecuta nada) y muestra, por clase, cuántas clases usa (Ce), cuántas la usan (Ca) y cuántas de las 6 preocupaciones del módulo mezcla. Con esto se eligió el refactor de la semana 7.

## Validar el contrato

```bash
npx @redocly/cli@1.34.3 lint api/openapi.yaml
```

## Levantar la API

```bash
createdb micro_his_notas_soap
psql -d micro_his_notas_soap -f database/schema.sql -f database/datos-demo.sql
# variables NOTASSOAP_DB_* como arriba
php -S 127.0.0.1:8080 -t public
```

| Método | Ruta | Permiso | Descripción |
|---|---|---|---|
| `POST` | `/api/v1/notas-soap` | `notas_soap.registrar` | Registra la nota con su diagnóstico (201). Acepta `Idempotency-Key` |
| `GET` | `/api/v1/notas-soap/{id}` | `notas_soap.consultar` | Consulta la nota y sus diagnósticos (200) |
| `POST` | `/api/v1/notas-soap/{id}/firma` | `notas_soap.firmar` | Firma la nota; solo el autor (200) |
| `POST` | `/api/v1/notas-soap/{id}/correcciones` | `notas_soap.corregir` | Corrige una nota firmada: crea la versión siguiente en borrador con `supersedes_id` y `correction_reason`; la original no cambia (201) |
| `GET` | `/api/v1/expedientes/{id}/notas-soap?estado=draft\|signed` | `notas_soap.consultar` | Resúmenes de las notas del expediente, sin texto clínico (200) |

El contrato completo (payloads, cabeceras y errores) está en [`api/openapi.yaml`](api/openapi.yaml).

Todas las peticiones llevan `X-Tenant-ID: hosp-oriente` y `Authorization: Bearer <token>`. `X-Request-ID` es opcional: si no llega o es inválido, el servidor genera uno y lo devuelve. Si el registro se repite con la misma `Idempotency-Key`, la respuesta es la misma nota con `Idempotent-Replayed: true`. Con `Accept: text/html` la respuesta sale en HTML (por ejemplo, para abrir la nota en el navegador); sin esa cabecera sale en JSON.

```bash
curl -X POST http://127.0.0.1:8080/api/v1/notas-soap \
  -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-mendez" \
  --data @ejemplos/nota.json
```

Tokens de demostración (datos ficticios, definidos en `config/config.php`):

| Token | Usuario | Rol | Asignado a `epi-100` | Permisos |
|---|---|---|---|---|
| `token-mendez` | `dra-mendez` | médico | Sí | registrar, firmar, consultar, corregir |
| `token-lopez` | `dr-lopez` | médico | No | registrar, firmar, consultar, corregir |
| `token-ana` | `enf-ana` | enfermería | No | consultar |

| Error | HTTP |
|---|---|
| Falta `X-Tenant-ID` / cuerpo sin `diagnostico` / campo que no es texto / filtro o `Idempotency-Key` inválidos | 400 |
| Token ausente o desconocido | 401 |
| El rol no tiene el permiso de la ruta, sin asignación, otro hospital, no es el autor | 403 |
| Nota o ruta inexistente | 404 |
| Método equivocado (con cabecera `Allow`) | 405 |
| Expediente cerrado o episodio de otro paciente / nota ya firmada / corregir un borrador / versión ya corregida | 409 |
| Sección SOAP vacía / diagnóstico CIE-10 inválido / `Idempotency-Key` reutilizada con otro contenido / motivo de corrección de menos de 10 caracteres | 422 |
| Error de base de datos (mensaje genérico) | 500 |
| Sin conexión con la base (con cabecera `Retry-After`) | 503 |

## Límites conocidos

- La autenticación es de demostración. En el HIS la identidad sale del JWT del módulo 1 (RBAC).
- El catálogo CIE-10 no se consulta: solo se valida el formato del código.
- La corrección (semana 6) todavía no acepta `Idempotency-Key` y no marca el diagnóstico de la versión anterior como `entered_in_error`.
- La bandeja de salida (`outbox_events`) se llena al firmar, pero el publicador que la envía a CENTRAL está diseñado, no implementado.
- Los adaptadores en memoria pierden los datos al terminar el proceso. Con `php -S` solo se conservan si se usa `NOTASSOAP_MEMORIA_ARCHIVO`, y eso es únicamente para demostraciones.
- Las claves de idempotencia no caducan; en el HIS conviene borrarlas después de 24 horas.
- Ninguna credencial está en el repositorio: la conexión se configura con variables de entorno.
