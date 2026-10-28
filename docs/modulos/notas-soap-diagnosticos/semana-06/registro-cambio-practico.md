# Registro del cambio práctico defendido — «Corregir una nota SOAP firmada»

| | |
|---|---|
| **Estudiante** | Joshua Eduardo García Reyes (`jgarciar73-svg`) |
| **Módulo** | ASII-11 Notas SOAP y diagnósticos |
| **Cambio** | Permitir que el médico autor **corrija una nota ya firmada** sin modificarla: la corrección es una versión nueva que apunta a la anterior y explica el motivo |
| **Método** | Pruebas primero (rojo → verde), sobre el MISMO micro-HIS de las semanas 3 a 5 |
| **Resultado** | 113 pruebas: **112 pasan, 0 fallan**, 1 omitida sin PostgreSQL (con PostgreSQL pasan las 113) |

## 1. Solicitud

> «La Dra. Méndez firmó la nota de Carlos y una hora después se dio cuenta de que anotó 38.6 °C cuando la temperatura era 37.6 °C. Hoy no puede hacer nada: la nota firmada no se edita y no hay forma de corregirla. Necesitamos que pueda corregirla dejando claro qué cambió y por qué, sin borrar ni alterar lo que firmó.»

El cambio no nace de cero. La regla **R-07** de la semana 1 ya lo dejaba escrito:

> Una nota firmada (`status = signed`) no se modifica. Una corrección es una nota nueva con `version_no` siguiente, `supersedes_id` apuntando a la anterior y `correction_reason` obligatorio.

La guía de defensa de la semana 1 también lo tenía anotado como un cambio probable: «CU nuevo "Corregir nota"». Hasta la semana 5 solo estaba implementada la primera mitad de R-07, que una nota firmada no cambia. Este cambio implementa la segunda mitad.

| Aspecto de la arquitectura | Cómo lo cubre el cambio |
|---|---|
| **Actores** | El médico tratante gana un caso de uso. Enfermería sigue solo consultando. |
| **UML** | CU-06 «Corregir nota firmada», que extiende a CU-04 (desde el historial se ve el error) e incluye a CU-02 y CU-05. |
| **RF/RNF** | RF-11 a RF-15 nuevos (§2). RNF-01, RNF-02 y RNF-03 se mantienen. |
| **SOLID (SRP)** | La regla va a la entidad, la coordinación a un caso de uso nuevo y la forma del JSON a un formulario nuevo. Los casos de uso existentes no cambian. |
| **Capas** | Una pieza nueva o ampliada por capa; la regla de dependencias no se toca (§4). |
| **Repository** | El puerto `RepositorioNotas` gana `correccionDe()` y guarda dos columnas más, en sus dos adaptadores. |
| **Contrato API** | Operación nueva `POST /api/v1/notas-soap/{notaId}/correcciones` con `x-permiso: notas_soap.corregir`. El contrato pasa de 1.0.0 a **1.1.0**. |

## 2. Requisitos nuevos

Los requisitos de la semana 2 llegan hasta RF-10 (bitácora), así que los nuevos empiezan en RF-11.

| ID | Requisito | Criterio de aceptación (Dado / Cuando / Entonces) | Prueba |
|---|---|---|---|
| RF-11 | El autor de una nota firmada puede corregirla creando una versión nueva con su diagnóstico. La original queda firmada y sin cambios. | **Dado** la nota firmada de la Dra. Méndez, **cuando** envía la corrección con motivo, **entonces** responde 201 con `version_no = 2`, `supersedes_id = id original`, `status = draft`, y la original sigue `signed` con su texto original. (CA-13) | COR-01, DOM-10, INT-11 |
| RF-12 | Cada versión se corrige una sola vez. Para corregir otra vez se corrige la versión más reciente (cadena v1 → v2 → v3). | **Dado** una versión que ya tiene corrección, **cuando** se intenta corregirla de nuevo, **entonces** responde 409 `nota_ya_corregida`. (CA-14) | COR-03, REP-09, INT-11 |
| RF-13 | Solo se corrige una nota firmada, con motivo de al menos 10 caracteres, cuatro secciones completas y un diagnóstico CIE-10 válido. | Borrador → 409 `nota_no_firmada`; motivo corto → 422 `motivo_insuficiente`; sección vacía → 422 `nota_incompleta`; CIE-10 inválido → 422 `diagnostico_invalido`. Ninguno guarda nada. (CA-15) | COR-02, COR-05, DOM-10 |
| RF-14 | Solo puede corregir el autor, con permiso `notas_soap.corregir`, todavía asignado al episodio, del mismo hospital y con el expediente abierto. | Enfermería → 403 en la ruta; otro médico → 403; otro hospital → 403; expediente cerrado → 409 `expediente_no_disponible`. (CA-16) | COR-04, COR-06 |
| RF-15 | La línea de tiempo muestra qué versión corrige a cuál, sin texto clínico. | **Dado** una nota y su corrección, **cuando** enfermería consulta el listado, **entonces** ve las dos con `version_no` y `supersedes_id` y sin las secciones SOAP. (CA-17) | COR-08 |
| RNF-01 (R-06) | La corrección, su diagnóstico y el evento `NOTA_CORREGIDA` se guardan juntos o nada. | Si falla el diagnóstico → 500 y no queda ni la versión nueva ni el evento. | COR-07 |

**Por qué solo el autor:** la firma es personal. Si otro médico pudiera «corregir» la nota de la Dra. Méndez, la versión nueva quedaría a nombre de ella (se hereda el autor) con un contenido que no escribió. Si otro médico tiene algo que agregar, escribe su propia nota (CU-01).

## 3. Análisis de impacto (antes de programar)

| Artefacto | ¿Cambia? | Motivo |
|---|---|---|
| UML (semana 1) | Agrega CU-06 | Ya previsto en R-07 y en la guía de defensa de la semana 1 |
| RF/RNF (semana 2) | Agrega RF-11 a RF-15 y CA-13 a CA-17 | Ver §2 |
| SRP (semana 2) | Clases nuevas, ninguna existente gana responsabilidades | `RegistrarNotaConDiagnostico` y `FirmarNota` no cambian |
| Capas (semana 3) | Una pieza por capa | Ver §4 |
| Repository (semana 4) | **El puerto cambia**: `correccionDe()` | Cambiar una interfaz obliga a implementarla en **ambos** adaptadores; la prueba de contrato REP-09 corre igual contra los dos |
| Contrato API (semana 5) | Operación nueva y dos campos nuevos en `Nota` (`supersedes_id`, `correction_reason`) | Cambio compatible: versión 1.1.0, sin `/api/v2` |
| Esquema SQL | `clinical_notes` gana `supersedes_id UNIQUE` y `correction_reason`, con un `CHECK` que los exige juntos y `version_no > 1` | Las mismas columnas que ya tiene la migración `000400` de HOSPITAL en el repositorio del grupo |
| Casos de uso existentes, transacción, bandeja de salida, idempotencia, cliente | **No cambian** | Comprobado con `diff`: ninguno aparece entre los archivos modificados (§4) |

## 4. Implementación

Archivos **nuevos** (6 de código y 1 de pruebas):

| Capa | Archivo | Líneas | Responsabilidad |
|---|---|---:|---|
| Dominio | `Excepciones/NotaNoFirmada.php` | 10 | Borrador → 409 |
| Dominio | `Excepciones/NotaYaCorregida.php` | 10 | Versión ya corregida → 409 |
| Dominio | `Excepciones/MotivoCorreccionInsuficiente.php` | 10 | Motivo corto → 422 |
| Aplicación | `Comandos/CorregirNotaComando.php` | 30 | Entrada del caso de uso, sin HTTP |
| Aplicación | `CasosDeUso/CorregirNota.php` | 86 | Hospital → nota → autor y asignación → ¿ya corregida? → expediente → reglas del dominio → transacción |
| Presentación | `Entrada/FormularioCorreccionNota.php` | 66 | Forma del JSON (400 si un campo no es texto) |
| Pruebas | `tests/Aplicacion/CorreccionPrueba.php` | 115 | COR-01 a COR-08 |

Archivos **modificados** (en total sobre el micro-HIS: 30 archivos, +652 / −22 líneas, contando pruebas y contrato):

| Archivo | Cambio |
|---|---|
| `Dominio/Modelo/NotaSoap.php` | Dos propiedades opcionales (`reemplazaA`, `motivoCorreccion`), el método `corregir()` y `firmar()` que las conserva |
| `Dominio/Puertos/RepositorioNotas.php` | Método `correccionDe()` |
| `Persistencia/Pdo/PdoRepositorioNotas.php` | `INSERT` con `signed_at`, `supersedes_id` y `correction_reason`; `SELECT` de `correccionDe()` |
| `Persistencia/EnMemoria/RepositorioNotasEnMemoria.php` | `correccionDe()` y la misma regla que el `UNIQUE` de la base |
| `Aplicacion/Integracion/RepositorioNotasConEventos.php` | Delega `correccionDe()` (el decorador debe cumplir el puerto completo) |
| `Aplicacion/Resultados/NotaResultado.php`, `ResumenNotaResultado.php` | Campos `supersedes_id` y `correction_reason` en la salida |
| `Presentacion/Http/Rutas.php` | Ruta nueva con permiso `notas_soap.corregir` |
| `Presentacion/Http/Kernel.php` | Tres excepciones nuevas a su código HTTP |
| `Presentacion/Controladores/NotasSoapController.php` | Acción `corregir()`: formulario → caso de uso → vista (sigue sin SQL ni reglas, ARQ-09) |
| `Presentacion/Vistas/plantillas/nota.php`, `lista.php` | La vista HTML muestra «Corrige la nota … Motivo: …» y la versión en el listado |
| `bootstrap/contenedor.php`, `config/config.php` | Arma `CorregirNota` y da el permiso al rol médico |
| `database/schema.sql`, `tests/Integracion/esquema-sqlite.sql` | Columnas, `UNIQUE` y `CHECK` |
| `api/openapi.yaml` | Operación `corregirNota`, esquema `CorreccionNota`, campos nuevos y tres códigos de error; versión 1.1.0 |

Archivos que **no** cambiaron y que alguien podría pensar que sí: `RegistrarNotaConDiagnostico.php`, `FirmarNota.php`, `ConsultarNota.php`, `ListarNotasExpediente.php`, `Transaccion` y sus adaptadores, `PdoBandejaSalida`, `EventoClinico`, `RegistroIdempotencia` y todo `src/Cliente/`.

## 5. Pruebas: rojo → verde

| Momento | Resultado | Evidencia |
|---|---|---|
| 0. Antes del cambio | 101 pruebas: 100 pasan, 0 fallan, 1 omitida | Estado de la semana 5 |
| 1. Pruebas nuevas escritas, **sin implementación** | 113 pruebas: **99 pasan, 13 fallan** (DOM-10, COR-01..08, API-03, REP-09 ×2, INT-11) | [`evidencia/cambio-practico-1-antes-rojo.txt`](evidencia/cambio-practico-1-antes-rojo.txt) |
| 2. Implementación lista, **contrato sin actualizar** | 113 pruebas: 109 pasan, **3 fallan (API-01, API-03, API-04)**: las pruebas de contrato detectaron la ruta, sus respuestas y tres códigos de error sin documentar | [`evidencia/cambio-practico-2-contrato-desactualizado.txt`](evidencia/cambio-practico-2-contrato-desactualizado.txt) |
| 3. Contrato actualizado a 1.1.0 | 113 pruebas: **112 pasan, 0 fallan**, 1 omitida; con PostgreSQL 113/113. Redocly: válido | [`evidencia/cambio-practico-3-despues-verde.txt`](evidencia/cambio-practico-3-despues-verde.txt), [`evidencia/salida-lint-openapi.txt`](evidencia/salida-lint-openapi.txt) |

**Lo que pasó entre los pasos 1 y 2, contado tal cual.** La primera corrida después de implementar dio 6 fallas, no 3. Tres eran errores de las propias pruebas:

- **DOM-10** esperaba que la versión 2 apuntara a sí misma.
- **COR-08** usaba `??`, que trata un `null` como «falta el campo».
- **ARQ-14** tenía fijo el número de rutas en 4.

Se corrigieron las pruebas, no el código. Después de eso quedaron solo las tres fallas del contrato, que es lo que muestra el paso 2.

Las pruebas de arquitectura (ARQ-01 a ARQ-14) siguieron en verde durante todo el cambio. Las piezas nuevas respetan la regla de dependencias, el controlador sin SQL ni reglas y los dos adaptadores por puerto.

## 6. Demostración contra PostgreSQL

Salida completa en [`evidencia/salida-api-correccion-postgresql.txt`](evidencia/salida-api-correccion-postgresql.txt). Resumen:

```
1. Registrar                                  → 201  v1 en borrador
2. Firmar                                     → 200  v1 firmada (T 38.6 °C)
3. Corregir con motivo                        → 201  v2 en borrador, supersedes_id = v1 (T 37.6 °C)
4. Consultar v1                               → 200  sigue firmada y con 38.6 °C
5. Corregir v1 otra vez                       → 409  nota_ya_corregida
6. Corregir v2 (todavía borrador)             → 409  nota_no_firmada
7. Enfermería intenta corregir                → 403  acceso_denegado (permiso de la ruta)
8. Firmar v2                                  → 200
9. Corregir v2 con motivo "error"             → 422  motivo_insuficiente
10. Línea de tiempo (enfermería)              → 200  v1 y v2 con supersedes_id, sin texto clínico
11. audit_events: NOTA_REGISTRADA, NOTA_FIRMADA, NOTA_CORREGIDA (con reemplaza_a), NOTA_FIRMADA
12. INSERT directo de otra corrección de v1   → ERROR: duplicate key … clinical_notes_supersedes_id_key
    UPDATE directo del texto de v1            → ERROR: clinical_notes_signed_immutable
```

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| La corrección es una **nota nueva** (`POST …/correcciones`) | `PUT /notas-soap/{id}` editando la firmada | Una nota firmada es un documento clínico: editarla rompe R-07 y borra la evidencia de lo que se firmó |
| `POST /notas-soap/{id}/correcciones` como subrecurso | `POST /notas-soap` con un campo `supersedes_id` en el cuerpo | Expediente y episodio no vienen del cliente sino de la nota corregida, y el permiso queda separado (`notas_soap.corregir`) |
| Una corrección por versión, con `UNIQUE (supersedes_id)` en la base | Permitir varias correcciones de la misma versión | Con varias, la historia deja de ser una cadena y nadie sabría cuál es la vigente. El `UNIQUE` también resuelve dos peticiones simultáneas: una gana, la otra recibe 409 |
| La corrección nace en **borrador** y se firma con la operación de siempre | Que nazca firmada | El médico revisa antes de firmar, igual que en el registro. Reutiliza `FirmarNota` sin tocarlo y la bandeja de salida publica `NotaSoapFirmada` también para la v2 |
| El motivo es regla de **dominio** (10+ caracteres) | Validarlo solo en el formulario | Que el campo exista es forma (formulario). Que alcance para entender la corrección es regla clínica, y debe valer desde cualquier canal |
| Solo se corrige una nota **firmada** | Permitir «corregir» borradores | Un borrador todavía no es definitivo: crear versiones de un borrador solo ensucia el historial |
| La versión nueva lleva su **propio diagnóstico** | Copiar el de la original | Muchas veces lo que se corrige es justo el diagnóstico. Así la v2 queda completa por sí sola (RNF-01) |
| Contrato 1.1.0 | `/api/v2` | Solo se agrega una operación y dos campos de salida: los clientes actuales siguen funcionando |

## 8. Riesgos y pendientes

- **Diagnósticos de la versión anterior.** La v1 conserva su diagnóstico con `status = active`. En el HIS, Reportes y Prescripciones deben tomar el diagnóstico de la versión vigente, que es la última de la cadena. Se puede resolver con un evento `NotaSoapCorregida` o marcando el anterior como `entered_in_error` (estado que ya existe en `diagnoses`).
- **Idempotencia.** La corrección todavía no acepta `Idempotency-Key`. Si se pierde la respuesta y el cliente reintenta, el `UNIQUE` evita el duplicado, pero el reintento recibe 409 en lugar de la corrección creada.
- **Evento hacia CENTRAL.** `NotaSoapFirmada` no dice que la nota corrige a otra. Para CENTRAL convendría agregar `supersedes_id` al payload mínimo, que hoy probó DOM-09 con sus seis campos.
- **Enunciado oficial.** El cambio lo eligió el estudiante porque el enunciado específico de la semana 6 no estaba en el repositorio. Si el docente pide otro cambio en vivo, se responde con el método de la [guía de defensa](GUIA_DEFENSA_ORAL.md#2-cómo-responder-un-cambio-pedido-en-vivo).
