# Checklist de la revisión técnica formal — Pacientes

**Proceso revisado:** «registro, búsqueda y actualización segura de un paciente» · **Paquete:** ver [plan §2](plan-revision-tecnica.md#2-alcance-y-paquete-de-revisión)

## Cómo se usa

- **Formulación.** Cada ítem es una afirmación que se responde **Sí / No / N/A**. Un «No» genera un hallazgo con el [formato de hallazgos](formato-hallazgos.md).
- **Dónde mirar.** Acota la lectura dentro del paquete.
- **Cómo se verifica.** Da una comprobación objetiva: prueba, comando o búsqueda. El revisor **no** se limita a la prueba: lee el código buscando el caso que la prueba no cubre.
- **Perspectiva.** Cada ítem tiene un revisor responsable (plan §3), pero cualquiera puede registrar hallazgos en cualquier ítem.
- **Severidad si falla.** Es la severidad por defecto. El moderador puede ajustarla en la reunión con el criterio del [formato](formato-hallazgos.md#2-severidad).

| Perspectiva | Revisor | Ítems |
|---|---|---|
| **D** · Dominio e integridad de la ficha | R3 | RT-01..RT-05 |
| **C** · Contrato API y frontera | R2 | RT-06..RT-08 |
| **S** · Permisos, tenant y confidencialidad | R1 | RT-09..RT-13 |
| **A** · Arquitectura y dependencias | Moderador + R3 | RT-14, RT-15 |

## Checklist (15 ítems)

| ID | Persp. | Afirmación a verificar | Dónde mirar | Cómo se verifica | Base | Sev. si falla |
|---|:-:|---|---|---|---|:-:|
| RT-01 | D | **Datos demográficos e identidad válidos (R-01).** `Paciente::desdeDatos` valida **todos** los campos y acumula los errores. El documento lo crea solo `FabricaIdentificador`, que rechaza tipos y formatos inválidos. Una ficha nunca existe con datos sin validar. | `Dominio/Modelo/{Paciente,Dpi,Pasaporte,FabricaIdentificador}.php` | DOM-01..04, paridad paso 25 (S12). Probar un DPI imposible. | R-01, RF-01, RNF-01 | Mayor |
| RT-02 | D | **Un solo dueño del orden de las reglas.** El cruce de visibilidad, datos, edición, protección y duplicados está **solo** en `RevisionDeFicha`, en el orden documentado (visibilidad antes que validación). Ningún caso de uso lo repite. | `Dominio/Servicios/RevisionDeFicha.php`, `Aplicacion/CasosDeUso/*` | REF-01, REF-02, M21. `php bin/acoplamiento.php`: los casos de uso, con P ≤ 1. | R-11, D-22 | **Crítico** |
| RT-03 | D | **Duplicados (R-02, R-03).** El mismo documento **bloquea**, también en carreras (índice único en el esquema). Nombre + fecha iguales **advierten**, y confirmar exige `pacientes.confirmar_duplicado` y motivo. Al editar, un documento ajeno da 409. | `PoliticaDuplicados`, `IndiceDuplicados*`, `schema.sql` (índices), `PdoRepositorioPacientes::registrar` | DOM-06, APP-02, PER-04, PROT-05, CON-*-04; paridad pasos 15–18 y 21–24 | R-02, R-03, RF-02, RF-03 | Mayor |
| RT-04 | D | **Edición segura (R-06, R-07, R-08).** La versión se compara **en la misma sentencia** que escribe. Cambiar la identidad exige motivo, con la misma normalización que la búsqueda. Las fichas restringidas solo las edita supervisión. Un campo **ausente** no cambia la protección ni el documento (1.2.0). | `PdoRepositorioPacientes::actualizar`, `PoliticaEdicion`, `RevisionDeFicha::paraEdicion`, `Texto::normalizar` | PER-05, APP-03, APP-04, B4-01..04, M1, M25 | R-06, R-07, R-08, RF-17 | Mayor |
| RT-05 | D | **Integridad y trazabilidad (R-09).** Ficha + evento + clave de idempotencia se guardan en **una** transacción. Cada escritura deja un evento con antes/después y motivo. La bitácora es append-only y ningún evento contiene el documento en claro. | `Transaccion`, `PdoTransaccion`, `EventoAuditoria`, `schema.sql` (trigger) | APP-07, PER-06, CON-*-06/07, IDEM-04, REF-03, M8 | R-09, RNF-02 | **Crítico** |
| RT-06 | C | **Contrato = código.** Las mismas operaciones, el mismo `x-permiso`, todos los códigos que el servidor devuelve documentados, versión 1.2.0 coherente con el cambio B-4, y el lint válido. | `api/openapi.yaml`, `Rutas.php` | API-01..03; `npx @redocly/cli@1.34.3 lint api/openapi.yaml` | RF-08, D-15 | Mayor |
| RT-07 | C | **Los errores no filtran información.** El 404 es idéntico si el paciente es de otro hospital. El 409 de un protegido muestra `RESERVADO` sin documento. Ningún error revela datos **de otro hospital**. El 422 lista campos sin repetir lo enviado. Sin trazas ni SQL. | `ErrorVista`, `PoliticaDuplicados`, `Kernel::procesar` | HTTP-02, HTTP-05, PROT-05, PROT-06. Registrar en otro hospital un documento existente. | R-04, R-11, D-20 | **Crítico** |
| RT-08 | C | **Validación de formato en la frontera.** Tipos, booleanos, `If-Match` e `Idempotency-Key` se validan en `FormularioPaciente` (400/428). El dominio nunca recibe tipos inválidos. Ausente se distingue de `null`. | `Presentacion/Http/Formularios/FormularioPaciente.php` | MVC-01..03, HTTP-06, paridad pasos 13 y 26 | RF-08, D-12 | Mayor |
| RT-09 | S | **Permiso por operación, fail-closed.** Cada caso de uso exige su permiso **antes** de leer datos. Las capacidades extra (ver protegidos, proteger, confirmar, editar restringidos) valen «nada» por defecto. | `ControlAcceso`, `Capacidades`, `CasosDeUso/*` | APP-01, PROT-01..04, M24 | R-05, D-23 | **Crítico** |
| RT-10 | S | **Tenant.** Sin `X-Tenant-ID` → 400. El token debe ser del mismo hospital. **Toda** consulta y **todo** dato devuelto pertenecen al hospital activo, incluidos los del índice de duplicados. | `Kernel`, `Consulta/RepositorioPacientes*`, `IndiceDuplicados*`, `PoliticaDuplicados` | HTTP-02, HTTP-05, APP-05; paridad pasos 33–34 | R-01, D-20, OWASP API1 | **Crítico** |
| RT-11 | S | **Identidad del actor.** El actor y el hospital salen del token, nunca del cuerpo. La autenticación de demostración **no puede** quedar activa fuera de desarrollo. | `Autenticador*`, `crearKernel`, `config.php` | Buscar `actor` en los formularios; leer `crearKernel`. | R-05, OWASP API2 | **Crítico** |
| RT-12 | S | **Confidencialidad de protegidos (R-11).** No aparecen por nombre sin permiso. La vista reducida es la opción por defecto, incluidos `nombres`/`apellidos`. No se edita lo que no se ve. El rastro que deja un protegido en búsquedas y errores está **justificado**. | `Paciente::{resumen,detalle,seMuestraReducidoPara}`, `CriterioBusqueda`, `PoliticaConfidencialidad` | PROT-01..06, B4-03, M19, M20, M26 | R-11, RF-12..RF-16, RNF-10 | **Crítico** |
| RT-13 | S | **Documento protegido.** HMAC para buscar y para la unicidad, cifrado para leer, claves fuera del repositorio. El documento **nunca** viaja ni se registra en claro (respuestas, registro de acceso, eventos). | `ProteccionDocumentos`, `PdoRepositorioPacientes`, `RegistroAccesoJson`, `EventoAuditoria` | PER-01, PER-07, OBS-02, ARQ-05, M2, M17 | R-04, RNF-03 | **Crítico** |
| RT-14 | A | **Dependencias entre capas.** Presentación → Aplicación → Dominio ← Persistencia. El Dominio no usa PDO, HTTP ni el reloj del sistema. `Cliente` no conoce clases del servidor. | `src/**` | ARQ-01..08; `php bin/acoplamiento.php` | D-09, D-13 | Mayor |
| RT-15 | A | **Solo dependencias estrictamente necesarias.** Sin Composer. Las extensiones PHP que el código usa están **declaradas**. Las herramientas de desarrollo no son necesarias para ejecutar el flujo. Ningún recurso crece sin límite. | README (requisitos), `src/**`, `public/index.php`, `AlmacenIdempotencia*` | `herramientas/verificar-autorrevision.php` (AR-05, AR-06) | D-13, RNF-06 | Menor |

## Registro por revisor (durante la preparación)

Cada revisor copia esta tabla, marca **Sí / No / N/A** en sus ítems y anota los hallazgos preliminares en [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv):

| ID | Sí / No / N/A | Hallazgos (IDs) | Tiempo (min) |
|---|---|---|---|
| RT-01 … RT-15 | | | |
