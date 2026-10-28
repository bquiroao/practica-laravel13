# Checklist de la revisión técnica — Notas SOAP y diagnósticos

**15 ítems en 3 perspectivas.** Cada revisor marca **C** (cumple), **N** (no cumple → hallazgo) o **NV** (no verificado, con motivo). La severidad indica cuánto pesa un incumplimiento ([formato](formato-hallazgos.md#2-severidad)). La prueba citada es el punto de partida: el revisor lee además buscando lo que la prueba no cubre.

| ID | Pregunta | Dónde mirar | Cómo se verifica | Regla | Si falla |
|---|---|---|---|---|---|
| RT-01 | ¿Las 4 secciones y el CIE-10 se validan en el Dominio antes de guardar? | `SeccionesSoap`, `Diagnostico` | DOM-02..05; ¿hay algún camino que guarde sin pasar por el Dominio? | R-04, R-05 | Mayor |
| RT-02 | ¿Una nota firmada nunca cambia y se corrige solo con una versión nueva? | `NotaSoap::firmar/corregir`, repositorios | DOM-06, DOM-07, COR-01; buscar cualquier `UPDATE` del texto | R-07, RF-11 | Crítico |
| RT-03 | ¿Cada versión se corrige una sola vez, en la aplicación **y** en la base? | `CorregirNota`, `schema.sql` (`UNIQUE supersedes_id`) | COR-03, REP-09, INT-11 | RF-12 | Mayor |
| RT-04 | ¿Nota, diagnóstico, evento y clave de idempotencia se guardan juntos o nada? | `RegistradorClinico`, `PdoTransaccion` | PER-03..05, COR-07, REF-04 | R-06, RNF-01 | Crítico |
| RT-05 | ¿Cada escritura deja su evento de bitácora sin texto clínico? | `RegistradorClinico`, `audit_events` | REF-01; leer los campos de cada evento | RNF-03 | Mayor |
| RT-06 | ¿Cada ruta, código HTTP, código de error y cabecera está en el contrato? | `api/openapi.yaml`, `Rutas`, `Kernel` | API-01..05 y `redocly lint` | Contrato 1.2.0 | Mayor |
| RT-07 | ¿Los errores no filtran SQL, SQLSTATE, rutas internas ni texto clínico? | `Kernel::manejar()`, vistas de error | PER-06; leer cada `catch` | RNF-03 | Mayor |
| RT-08 | ¿Autor y hospital salen del token, nunca del cuerpo ni de la consulta? | `Formulario*`, `Identidad` | HTTP-07; buscar `author`/`hospital` leídos del cuerpo | RF-06 | Crítico |
| RT-09 | ¿Toda ruta declara permiso y el Kernel lo aplica antes del controlador? | `Rutas`, `Kernel`, `PermisosPorRol` | ARQ-14, HTTP-13 | R-02 | Mayor |
| RT-10 | ¿Toda lectura y escritura queda limitada al hospital del token **y del dato**? | Los 5 casos de uso, repositorios, `schema.sql` | APP-04 solo compara token y cabecera: comparar con el hospital de la nota o del expediente | R-01 | Crítico |
| RT-11 | ¿Registrar, firmar y corregir exigen autor asignado al episodio y expediente abierto? | `ReglasDeEscritura` y sus llamadas | APP-03, APP-05, COR-04, COR-06; ¿qué caso de uso no la llama? | R-02, R-03 | Mayor |
| RT-12 | ¿La idempotencia es segura: misma clave + mismo contenido = misma nota; otra = 422; clave por hospital y usuario? | `RegistroIdempotencia`, huellas de los comandos | HTTP-11..12, CID-01..05 | RF-16, I-02 | Mayor |
| RT-13 | ¿Las dependencias apuntan al Dominio y el SQL solo está en Persistencia, con sentencias preparadas? | `src/**` | ARQ-01..07, ARQ-12, ARQ-15 | Capas (S3) | Mayor |
| RT-14 | ¿Solo las dependencias necesarias: sin framework ni librerías, extensiones PHP declaradas? | `README`, `bootstrap/autoload.php` | ARQ-08; `php -m` frente a lo que usa el código | RNF-04 | Menor |
| RT-15 | ¿La configuración es segura: sin credenciales en el código y sin autenticación de demostración en producción? | `config/config.php`, `crearKernel()` | Leer la composición del punto de entrada real | Seguridad (S5) | Crítico |

Perspectivas: **R1** seguridad y hospital (RT-07..RT-10, RT-15) · **R2** contrato e idempotencia (RT-06, RT-12) · **R3** dominio e integridad (RT-01..RT-05, RT-11; RT-13 y RT-14 con el moderador). Ver [roles](plan-revision-tecnica.md#3-roles).
