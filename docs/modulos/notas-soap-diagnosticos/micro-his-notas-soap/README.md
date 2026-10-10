# Micro-HIS Notas SOAP y diagnósticos (ASII-11)

Versión mínima y separada del módulo 11 del Sistema Hospitalario Integrado, escrita en **PHP 8.2+ vanilla** (sin framework ni Composer). Ejecuta un solo proceso de punta a punta: **registro de nota SOAP y asociación de diagnóstico clínico**, más la firma y la consulta de la nota.

Se inició en la semana 3 y se usa como base en las semanas 4 y 5.

- Semana 3 — capas, PDO y vista C4: [`../semana-03/semana-03-arquitectura-micro-his.md`](../semana-03/semana-03-arquitectura-micro-his.md)
- Semana 4 — MVC en la entrada, patrón Repository con adaptadores PDO y en memoria, repositorio compartido: [`../semana-04/semana-04-mvc-repository.md`](../semana-04/semana-04-mvc-repository.md)

## Capas

```
src/
├── Presentacion/
│   ├── Http/           Kernel (front controller), Solicitud, Respuesta, autenticación demo
│   ├── Controladores/  NotasSoapController (la C del MVC: sin SQL ni reglas)
│   ├── Entrada/        FormularioRegistroNota (JSON → comando; solo revisa formato)
│   └── Vistas/         VistaJson, VistaHtml (+ plantillas), SelectorVista (la V del MVC)
├── Aplicacion/         Casos de uso, comandos, NotaResultado, puertos técnicos, decorador RepositorioNotasConEventos
├── Dominio/            NotaSoap, SeccionesSoap, Diagnostico, EventoClinico, excepciones e interfaces Repository
└── Persistencia/
    ├── Pdo/            Adaptadores PDO (sentencias preparadas), transacción y bandeja de salida
    ├── EnMemoria/      Los mismos puertos sobre arreglos de PHP, con transacción en memoria
    └── Sistema/        Reloj y generador de UUID
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**. El único archivo que conoce todas las clases concretas es `bootstrap/contenedor.php`: `crearKernel()` usa PDO y `crearKernelEnMemoria()` usa los adaptadores en memoria, sin cambiar nada más. La regla se comprueba en cada ejecución de las pruebas (grupo `ARQ`).

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
| `ARQ` | Regla de dependencias, PDO solo en Persistencia, controlador sin SQL ni reglas, dos adaptadores por puerto, sin framework |
| `INT` | Adaptadores PDO contra SQLite y PostgreSQL, bandeja de salida y mismo flujo con PDO y en memoria |

## Demostración sin base de datos

```bash
php bin/demo-en-memoria.php
```

Corre el mismo Kernel, controlador y casos de uso con los adaptadores en memoria y muestra lo que quedó guardado, incluida la bandeja de salida.

## Levantar la API

```bash
createdb micro_his_notas_soap
psql -d micro_his_notas_soap -f database/schema.sql -f database/datos-demo.sql
# variables NOTASSOAP_DB_* como arriba
php -S 127.0.0.1:8080 -t public
```

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/api/v1/notas-soap` | Registra la nota con su diagnóstico (201) |
| `GET` | `/api/v1/notas-soap/{id}` | Consulta la nota y sus diagnósticos (200) |
| `POST` | `/api/v1/notas-soap/{id}/firma` | Firma la nota; solo el autor (200) |

Todas las peticiones llevan `X-Tenant-ID: hosp-oriente` y `Authorization: Bearer <token>`. Con `Accept: text/html` la respuesta sale en HTML (por ejemplo, para abrir la nota en el navegador); sin esa cabecera sale en JSON.

```bash
curl -X POST http://127.0.0.1:8080/api/v1/notas-soap \
  -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-mendez" \
  --data @ejemplos/nota.json
```

Tokens de demostración (datos ficticios, definidos en `config/config.php`):

| Token | Usuario | Rol | Asignado a `epi-100` |
|---|---|---|---|
| `token-mendez` | `dra-mendez` | médico | Sí |
| `token-lopez` | `dr-lopez` | médico | No |
| `token-ana` | `enf-ana` | enfermería | No |

| Error | HTTP |
|---|---|
| Falta `X-Tenant-ID` / cuerpo sin `diagnostico` / campo que no es texto | 400 |
| Token ausente o desconocido | 401 |
| Sin rol médico, sin asignación, otro hospital, no es el autor | 403 |
| Nota o ruta inexistente | 404 |
| Expediente cerrado o episodio de otro paciente / nota ya firmada | 409 |
| Sección SOAP vacía / diagnóstico CIE-10 inválido | 422 |
| Error de base de datos (mensaje genérico) | 500 |

## Límites conocidos

- La autenticación es de demostración. En el HIS la identidad sale del JWT del módulo 1 (RBAC).
- El catálogo CIE-10 no se consulta: solo se valida el formato del código.
- No incluye corrección de notas firmadas (versión nueva con `supersedes_id`); queda para una semana posterior.
- La bandeja de salida (`outbox_events`) se llena al firmar, pero el publicador que la envía a CENTRAL está diseñado, no implementado.
- Los adaptadores en memoria pierden los datos al terminar el proceso: sirven para pruebas y demostraciones, no para `php -S`.
- Ninguna credencial está en el repositorio: la conexión se configura con variables de entorno.
