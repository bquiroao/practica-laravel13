# Registro del cambio práctico defendido — «Paciente con identidad protegida»

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | ASII-03 · Pacientes: registro, edición, búsqueda y detalle |
| **Cambio** | Permitir que un paciente tenga **identidad protegida**: solo quien tenga permiso ve su demografía, lo encuentra por nombre o lo edita; los duplicados se siguen detectando sin revelar quién es |
| **Método** | Pruebas primero (rojo → verde), sobre el MISMO Micro-HIS de las semanas 3 a 5 |
| **Resultado** | 80 pruebas: **78 pasan, 0 fallan**, 2 omitidas (requieren PostgreSQL); 22/22 mutaciones detectadas |

## 1. Solicitud

> «Llegó una paciente con una orden judicial de protección: es víctima de violencia y su agresor podría buscarla en el hospital. Hoy cualquier persona de admisión o del área clínica puede buscarla por nombre, ver su teléfono y su documento, e incluso editar su ficha. Necesitamos que su identidad quede protegida, pero sin dejar de atenderla ni de detectar si alguien la vuelve a registrar.»

El cambio cubre los seis aspectos que pide la tarea:

| Aspecto de la tarea | Cómo lo cubre el cambio |
|---|---|
| **Identidad del paciente** | «Identidad protegida» pasa a ser un atributo de la ficha. Ponerlo o quitarlo exige `pacientes.proteger` y un motivo, y queda auditado. |
| **Datos demográficos** | Sin `pacientes.leer_protegido` se muestran reducidos: «Identidad protegida», sin fecha de nacimiento, teléfono ni documento. El sexo y el estado se conservan porque son necesarios para atenderla. |
| **Búsqueda** | Por nombre no aparece para quien no tiene permiso. Por código o documento completo sí aparece, pero reducida: quien ya tiene ese dato puede atenderla, pero no puede «pescarla» por nombre. |
| **Edición** | Sin permiso de lectura no se edita (no se edita lo que no se ve). Un `PUT` que no envía el campo no quita la protección por omisión. |
| **Duplicados** | Se siguen bloqueando y advirtiendo (R-02, R-03), pero el 409 reemplaza su código por `RESERVADO` y no repite el documento. |
| **Confidencialidad** | Es el eje: PROT-06 recorre todas las respuestas a admisión y al clínico, más el registro de acceso, y verifica que no aparezca ningún dato de ella. |

## 2. Requisitos nuevos

Regla nueva **R-11 — Identidad protegida**. Continúa la numeración de las semanas 1 y 2.

| ID | Requisito | Criterio de aceptación | Prueba |
|---|---|---|---|
| RF-12 | Quien tenga `pacientes.proteger` puede registrar o editar un paciente con identidad protegida, indicando un motivo. | Admisión sin permiso → 403; supervisor sin motivo → 422 `MOTIVO_REQUERIDO`; supervisor con motivo → 201 y `PACIENTE_REGISTRADO` con `identidad_protegida: true` y `motivo_proteccion`. | PROT-01, DOM-09 |
| RF-13 | La búsqueda por nombre no devuelve protegidos a quien no tiene `pacientes.leer_protegido`; por código o documento los devuelve reducidos. | Clínico busca «lopez» → 0 resultados; supervisor → 1; clínico por código → `nombre: "Identidad protegida"`, sin nacimiento ni documento. | PROT-02, CON-MEM-08 |
| RF-14 | El detalle de un protegido, sin permiso, no incluye nombre, nacimiento, teléfono ni documento, y la auditoría lo distingue. | Clínico → detalle reducido y `PACIENTE_CONSULTADO` con `acceso_limitado: true`; supervisor → completo y `acceso_limitado: false`. | PROT-03 |
| RF-15 | Un protegido solo lo edita quien puede verlo. Quitar o poner la protección exige `pacientes.proteger` y un motivo. Si el campo no se envía, la protección no cambia. | Admisión → 403; supervisor quita sin motivo → 422; supervisor edita el teléfono sin enviar el campo → sigue protegida; quita con motivo → 200 con antes/después. | PROT-04, DOM-09 |
| RF-16 | Los duplicados de un protegido se detectan sin revelar su código. | Mismo DPI → 409 `PACIENTE_EXISTENTE` con `codigo: "RESERVADO"` y sin documento; coincidencia probable → `candidatos: ["RESERVADO"]`; el supervisor ve el código real. | PROT-05 |
| RNF-10 | Ninguna respuesta ni registro técnico revela datos de un protegido a quien no tiene permiso. | Ninguna de 10 respuestas a admisión y al clínico, ni el registro de acceso, contiene nombre, apellido, fecha, teléfono ni dígitos del DPI. | PROT-06 |

## 3. Análisis de impacto (antes de programar)

| Artefacto | ¿Cambia? | Motivo |
|---|---|---|
| UML (semana 1) | Agrega **CU-09 «Proteger identidad»** (actor: supervisor), que extiende CU-01 y CU-04 | Comportamiento opcional que se activa al registrar o editar |
| RF/RNF (semana 2) | Agrega R-11, RF-12..RF-16 y RNF-10 | Ver §2 |
| LSP (semana 2) | **El contrato del puerto `IndiceDuplicados` crece**: `protegidos()` | Los dos adaptadores deben cumplirlo; la suite de contrato lo verifica (CON-MEM-08) |
| Capas (semana 3) | Una pieza **nueva** en el Dominio (`PoliticaConfidencialidad`); ajustes en el modelo y en los casos de uso | `PoliticaEdicion` **no** se modifica: la regla nueva vive en su propia política |
| MVC (semana 4) | Solo el **formulario** valida el formato del campo nuevo | El controlador, las rutas y las vistas no cambian: el comportamiento nuevo llega desde el Modelo |
| Repository (semana 4) | Columna `identidad_protegida`; `:ver_protegidos` en la búsqueda SQL; `protegidos()` en PDO e InMemory | Mismo patrón de las semanas anteriores |
| Contrato API (semana 5) | `info.version` 1.0.0 → **1.1.0** (compatible): un campo opcional de entrada y uno nuevo en la salida | No requiere `/api/v2`. Ningún consumidor existente se rompe. |
| Permisos (RBAC) | Dos permisos nuevos: `pacientes.proteger` y `pacientes.leer_protegido`, concedidos al supervisor | En el HIS los administra el módulo RBAC |

**Lo que no cambió** (verificado con `git diff --quiet` sin diferencias):

| Pieza | Por qué no cambió |
|---|---|
| `PacientesControlador`, `Rutas`, `Kernel` | No hay rutas nuevas; el controlador solo pasa el comando al Modelo |
| `PacienteVista`, `ErrorVista` | Los códigos HTTP existentes (403, 409, 422) bastan |
| `PoliticaEdicion` | La regla nueva vive en `PoliticaConfidencialidad` |
| Puertos `ConsultaPacientes` / `RepositorioPacientes`, `Transaccion` | La marca viaja dentro de `Paciente` |
| Librería `Cliente` | El contrato 1.1.0 es compatible |

## 4. Implementación

| Capa | Archivo | Cambio |
|---|---|---|
| Dominio | `Servicios/PoliticaConfidencialidad.php` | **Nuevo**: `verificarRegistro()`, `exigirVisibilidad()`, `verificarCambio()` |
| Dominio | `Modelo/Paciente.php` | Atributo `identidadProtegida`; `resumen()` y `detalle()` reciben el permiso y **por defecto muestran reducido** (fail-closed); `conProteccion()` |
| Dominio | `Modelo/DatosPaciente.php` | `?bool identidadProtegida`: `null` significa «no se envió» |
| Dominio | `Modelo/CriterioBusqueda.php` | `incluirProtegidos` (por defecto `false`) e `incluyendoProtegidos()` |
| Dominio | `Servicios/PoliticaDuplicados.php` | Código `RESERVADO` para protegidos, sin documento en el 409 |
| Dominio | `Puertos/IndiceDuplicados.php` | `protegidos(array $codigos)` |
| Aplicación | `ControlAcceso` | Constantes `PROTEGER` y `LEER_PROTEGIDO` |
| Aplicación | 4 casos de uso | Consultan los permisos nuevos y llaman a la política. `ActualizarPaciente` verifica la visibilidad **antes** de validar, para no revelar nada con otros errores. |
| Persistencia | `PdoRepositorioPacientes`, `PdoIndiceDuplicados`, `IndiceDuplicadosEnMemoria`, `schema.sql` | Columna, parámetro `:ver_protegidos`, `protegidos()` |
| Presentación | `Formularios/FormularioPaciente.php` | Valida que `identidad_protegida` sea booleano (400 si no) |
| Contrato | `api/openapi.yaml` | v1.1.0 |
| Composición | `contenedor.php`, `config.php` | Inyecta la política; permisos del supervisor |

**Tamaño del cambio:** 19 archivos modificados (+202 / −45 líneas), 1 clase nueva de código y 1 archivo de pruebas nuevo.

## 5. Pruebas: rojo → verde

| Momento | Resultado | Evidencia |
|---|---|---|
| **Rojo** (pruebas escritas, sin implementación) | 78 pruebas: 70 pasan, **6 fallan** (PROT-01..06), 2 omitidas | [`evidencia/cambio-practico-1-antes-rojo.txt`](evidencia/cambio-practico-1-antes-rojo.txt) |
| Primera implementación | 5 fallan. DOM-06, PER-02 y HTTP-04 fallan por cambios esperados del contrato (parámetro nuevo de la política, parámetro SQL nuevo, campo nuevo en el resumen). PROT-04 leía el evento equivocado. **PROT-06 encontró una fuga real** (ver §7). | — |
| **Verde** (más DOM-09 y CON-MEM-08) | 80 pruebas: **78 pasan, 0 fallan**, 2 omitidas | [`evidencia/cambio-practico-2-despues-verde.txt`](evidencia/cambio-practico-2-despues-verde.txt) |
| Mutaciones M19..M22 | 22/22 detectadas | [`evidencia/mutaciones.txt`](evidencia/mutaciones.txt) |
| Contrato 1.1.0 | «Your API description is valid» | [`evidencia/salida-lint-openapi.txt`](evidencia/salida-lint-openapi.txt) |

| Mutación nueva | Detectada por |
|---|---|
| M19 La búsqueda por nombre incluye protegidos por defecto | PER-02, CON-MEM-08 |
| M20 El detalle deja de ocultar la demografía | DOM-09, PROT-02, PROT-03, PROT-04, PROT-06 |
| M21 Se puede editar a un protegido sin poder verlo | PROT-04 |
| M22 El 409 vuelve a mostrar el código del protegido | PROT-05, PROT-06 |

## 6. Demostración de punta a punta

`php bin/demo-proteccion.php` (sin base de datos; [`evidencia/salida-demo-proteccion.txt`](evidencia/salida-demo-proteccion.txt)):

```
1. Admisión intenta registrar a Sofía como protegida     POST -> 403 ACCESO_DENEGADO
2. El supervisor la registra protegida, con motivo       POST -> 201 P-000005
3. El clínico la busca por nombre                        GET  -> 200 {"resultados":[],"total":0}
4. El clínico la busca por código                        GET  -> 200 "Identidad protegida", sin nacimiento ni documento
5. El clínico abre su detalle (para atenderla)           GET  -> 200 sin nombre, nacimiento, teléfono ni documento
6. Admisión intenta registrarla otra vez con su DPI      POST -> 409 {"error":"PACIENTE_EXISTENTE","codigo":"RESERVADO"}
7. Admisión intenta editarla                             PUT  -> 403 ACCESO_DENEGADO
8. El supervisor ve el detalle completo                  GET  -> 200 Sofía Morales Díaz, *********0101
```

## 7. Decisiones tomadas durante el cambio

| Decisión | Alternativa descartada | Por qué |
|---|---|---|
| Reducir la demografía en lugar de responder 403 al detalle | 403 para todos sin permiso | El clínico **debe** poder atender a la paciente: necesita su código, sexo y estado, no su nombre ni su teléfono. |
| Por código o documento sí aparece | Ocultarla en toda búsqueda | Quien ya tiene su código (pulsera, orden) o su documento la está atendiendo. Lo peligroso es encontrarla **por nombre**. |
| `resumen()` / `detalle()` reducen **por defecto** | Mostrar todo por defecto y reducir con permiso | Fail-closed: si un caso de uso nuevo olvida pasar el permiso, el error es mostrar de menos, no filtrar datos. |
| `incluirProtegidos = false` por defecto en `CriterioBusqueda` | `true` por defecto | Mismo motivo. La mutación M19 lo comprueba. |
| Campo ausente en el `PUT` = sin cambio | Ausente = `false` (reemplazo completo) | Un formulario viejo, o un cliente que no conoce el campo (contrato 1.0.0), quitaría la protección por omisión. Lo detectó el diseño de PROT-04 antes de programar. |
| Verificar la visibilidad **antes** de validar los datos al editar | Validar primero | Un 422 o un 403 `REQUIERE_SUPERVISOR` revelarían información del paciente a quien no debe verlo. |
| El 409 de un protegido no repite el documento enmascarado | Mantenerlo (es lo que tecleó quien registra) | **Hallazgo de PROT-06:** la respuesta confirmaba los últimos dígitos de esa persona. Se quitó. |
| Política nueva en lugar de modificar `PoliticaEdicion` | Agregar las condiciones a `PoliticaEdicion` | Responsabilidad única y cambio aislado: la edición no conoce la confidencialidad. |
| Mismo error `ACCESO_DENEGADO` para «sin permiso de proteger» y «no puede ver al protegido» | Errores específicos | Un error específico revelaría que el paciente es protegido. |

## 8. Riesgos y pendientes

- **Auditoría de búsquedas.** `PACIENTE_BUSCADO` no registra si se excluyó a un protegido. Es correcto (no se revela nada), pero un auditor no ve «intentos de búsqueda por nombre» de un protegido. Mejora posible: alertar cuando alguien busca repetidamente el nombre de un protegido. Requiere comparar el criterio con nombres protegidos en el servidor y es una decisión de gobernanza.
- **Acceso de emergencia (*break-glass*).** El esquema central ya tiene tablas de *break-glass* (semana 1). Un clínico podría pedir acceso completo a un protegido con justificación y vigencia corta. Queda como trabajo del módulo de acceso compartido.
- **Otros módulos.** Admisión, citas y laboratorio deben respetar la marca al mostrar el nombre del paciente. Por eso la marca viaja en el resumen y el detalle (`identidad_protegida: true`).
- **PostgreSQL real.** La columna y el filtro `:ver_protegidos` se probaron con dobles de PDO (PER-02) y con el contrato InMemory. `CON-PDO-08` se activa con `PACIENTES_DB_DSN`.
