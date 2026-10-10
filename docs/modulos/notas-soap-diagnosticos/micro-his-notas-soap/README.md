# Micro-HIS Notas SOAP y diagnósticos (ASII-11)

Versión mínima y separada del módulo 11 del Sistema Hospitalario Integrado, escrita en **PHP 8.2+ vanilla** (sin framework ni Composer). Ejecuta un solo proceso de punta a punta: **registro de nota SOAP y asociación de diagnóstico clínico**, más la firma y la consulta de la nota.

Se inició en la semana 3 y se usa como base en las semanas 4 y 5. La vista arquitectónica y la explicación de cada decisión están en [`../semana-03/semana-03-arquitectura-micro-his.md`](../semana-03/semana-03-arquitectura-micro-his.md).

## Capas

```
src/
├── Presentacion/   HTTP: Kernel (front controller), NotasSoapController, Solicitud, Respuesta, autenticación demo
├── Aplicacion/     Casos de uso (RegistrarNotaConDiagnostico, FirmarNota, ConsultarNota), comandos, resultados y puertos técnicos
├── Dominio/        NotaSoap, SeccionesSoap, Diagnostico, TipoDiagnostico, UsuarioClinico, excepciones y puertos de repositorio
└── Persistencia/   Adaptadores PDO (sentencias preparadas), transacción, reloj y generador de UUID
```

Regla de dependencias: **Presentación → Aplicación → Dominio ← Persistencia**. El único archivo que conoce todas las clases concretas es `bootstrap/contenedor.php`. La regla se comprueba en cada ejecución de las pruebas (grupo `ARQ`).

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
| `HTTP` | Rutas, cabeceras, códigos de estado y que los errores no expongan detalles internos |
| `ARQ` | Regla de dependencias, PDO solo en Persistencia, solo sentencias preparadas, dominio sin E/S, sin framework |
| `INT` | Los adaptadores PDO reales contra SQLite en memoria y, opcionalmente, PostgreSQL |

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

Todas las peticiones llevan `X-Tenant-ID: hosp-oriente` y `Authorization: Bearer <token>`.

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
| Falta `X-Tenant-ID` / cuerpo inválido | 400 |
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
- Ninguna credencial está en el repositorio: la conexión se configura con variables de entorno.
