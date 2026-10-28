# Registro de defectos — pruebas de la semana 15

**Formato:** el de la [revisión técnica de la S13](../semana-13/formato-hallazgos.md), con la misma escala de severidad.

**Cómo se reproducen.** Cada defecto tiene una prueba que afirma el comportamiento **correcto**, y esa prueba hoy **falla**. Están en `micro-his-pacientes/tests/Defectos/DefectosPrueba.php` y se corren con:

```bash
php tests/ejecutar.php --defectos --solo=DEF
```

**Resultado actual:** fallan las 6 ([salida](evidencia/4-defectos-abiertos.txt), [captura](capturas/4-defectos-abiertos.png)). Cuando un defecto se corrija, su prueba pasará y se moverá a la suite normal como prueba de regresión.

## Resumen

| ID | Severidad | Tipo | Encontrado por | Relación | Estado |
|---|---|---|---|---|---|
| DEF-01 | **Crítico** | Defecto | Caja negra (permisos por hospital) | AR-02 (S13), RS-01 | Abierto |
| DEF-02 | **Mayor** | Defecto | Caja negra (tabla de permisos: la celda «admisión de otro hospital × confirmar» no se puede evaluar) | AR-03 (S13), RS-01 | Abierto |
| DEF-03 | **Mayor** | Riesgo | Prueba del punto de entrada real | AR-01 (S13), RS-03 | Abierto |
| DEF-04 | **Mayor** | Riesgo | Caja negra (equivalencia de búsqueda por documento) | AR-04 (S13), RS-02 | **Requiere decisión** |
| DEF-05 | Menor | Defecto | Exploratoria + valores límite del DPI | AR-07 (S13), RS-04 | Abierto |
| DEF-06 | Menor | Defecto | Exploratoria, **nuevo** en esta semana | — | Abierto |
| OBS-01 | Observación | Pregunta | Exploratoria | RF-01 | Para decidir |
| OBS-02 | Observación | Pregunta | Exploratoria | RF-03, RF-12 | Para decidir |
| OBS-03 | Observación | Pregunta | Exploratoria | RF-01 | Para decidir |
| NC-01 | No conformidad | Proceso | Tablero SQA (S15) | Plan S14 §6 y §8.2 | Escalada |

Todas las entradas exploratorias se reproducen con [herramientas/exploratorias.php](herramientas/exploratorias.php) ([salida](evidencia/7-exploratorias.txt)).

## DEF-01 · El 409 por un DPI de otro hospital revela su código y su documento · Crítico

| Campo | Valor |
|---|---|
| Ítem / regla | RT-07, RT-10 · R-02, RNF-07 (aislamiento multi-hospital), RNF-03 |
| Ubicación | `src/Dominio/Servicios/PoliticaDuplicados.php:29-37`; el índice busca en **todos** los hospitales (`IndiceDuplicadosEnMemoria.php:17-26`, `PdoIndiceDuplicados.php:17`) |
| Descripción | Admisión de `hosp-norte` registra el DPI de una paciente de `hosp-oriente` y recibe el código local de otro hospital y el documento enmascarado. |
| Evidencia | `409 {"error":"PACIENTE_EXISTENTE","codigo":"P-000001","identificador":"*********0101"}` |
| Por qué es Crítico | Confirma a un tercero que esa persona se atiende en **otro** hospital: es la exposición de mayor impacto de la escala (I = 5). Ya estaba demostrado en la S13, y la caja negra lo vuelve a confirmar. |
| Propuesta | Cuando el titular es de otro hospital, responder un código neutro (`EN_OTRO_HOSPITAL`) sin el documento. La conciliación entre hospitales le toca al MPI. |
| Prueba | DEF-01 (falla). Afirma `409 {"error":"PACIENTE_EXISTENTE","codigo":"EN_OTRO_HOSPITAL"}`. |

## DEF-02 · Los candidatos de POSIBLE_DUPLICADO incluyen pacientes de otro hospital · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-10 · R-03, RNF-07 |
| Ubicación | `PoliticaDuplicados.php:40-45`; `IndiceDuplicadosEnMemoria::coincidenciasProbables()` no filtra por hospital |
| Descripción | Una homónima registrada **sin documento** en `hosp-norte` recibe 409 con el código de la paciente de `hosp-oriente`. Admisión de norte no puede abrir esa ficha (404), así que tampoco puede comparar ni confirmar. |
| Evidencia | `409 {"error":"POSIBLE_DUPLICADO","candidatos":["P-000001"]}` |
| Impacto en las pruebas | En la tabla de decisión CN-05, la celda «admisión de otro hospital × confirmar» queda **fuera**: hoy da 403, porque la coincidencia ajena exige confirmar y admisión no tiene ese permiso. Con el defecto corregido, el valor esperado es 201. |
| Propuesta | Limitar los candidatos al hospital activo. |
| Prueba | DEF-02 (falla). Afirma 201 y que la respuesta no contiene `P-000001`. |

## DEF-03 · El punto de entrada real acepta tokens de demostración en cualquier entorno · Mayor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-11 · R-05, RF-11 |
| Ubicación | `bootstrap/contenedor.php:94-97` (`crearKernel`) · `config/config.php:22-27` |
| Descripción | `crearKernel()` siempre usa `AutenticadorTokenDemo`. Sin ninguna variable de entorno, `Bearer token-supervisor-oriente` obtiene 200 con todos los permisos de supervisión. |
| Propuesta | Elegir el autenticador por configuración. Si `MICROHIS_ENTORNO` no es `desarrollo`, arrancar sin tokens de demostración (fail-closed). |
| Prueba | DEF-03 (falla). Afirma 401 al arrancar sin `MICROHIS_ENTORNO`. |

## DEF-04 · La búsqueda por DPI confirma que una paciente protegida está en el hospital · Mayor · requiere decisión

| Campo | Valor |
|---|---|
| Ítem / regla | RT-12 · R-11, RF-13, RNF-10 |
| Ubicación | `CriterioBusqueda.php:70-79` (rama `POR_DOCUMENTO`) · `Paciente::resumen()` |
| Descripción | El clínico, sin `leer_protegido`, busca el DPI de la protegida y obtiene `total: 1` con datos reducidos. Con un DPI inexistente obtiene `total: 0`. La diferencia confirma que la persona está en el hospital. |
| Por qué requiere decisión | **RF-13 pide exactamente ese comportamiento** («por código o documento los devuelve reducidos»). No es un error de código: es un **defecto del requisito**, que entra en conflicto con RNF-10. |
| Opciones | (a) Exigir `pacientes.leer_protegido` también para la búsqueda por documento: la prueba DEF-04 pasaría tal como está. (b) Mantener RF-13 y auditar esa búsqueda como acceso a un protegido: la prueba DEF-04 se reescribe para afirmar la auditoría. |
| Prueba | DEF-04 (falla). Afirma la opción (a): el total es igual para el DPI de la protegida y para uno inexistente. |

## DEF-05 · El DPI no valida el dígito verificador ni el municipio · Menor

| Campo | Valor |
|---|---|
| Ítem / regla | RT-01 · R-01, R-02 |
| Ubicación | `src/Dominio/Modelo/Dpi.php:15-27` |
| Descripción | Solo se validan los 13 dígitos y el departamento (01–22). Por eso se aceptan `1234567800101`, cuyo verificador es 0 cuando debería ser 9, y `1234567890199`, con municipio 99 en un departamento de 17. Un DPI mal digitado pasa como «válido» y **esquiva el bloqueo por documento** (R-02), lo que abre la puerta a la historia partida (RS-04). |
| Hallazgo adicional | Los tres DPI ficticios de las pruebas (`DPI_ANA`, `DPI_LUIS` y `DPI_NUEVO`) **tampoco** cumplen el verificador ([salida](evidencia/7-exploratorias.txt)). **Se midió** en una copia aparte, agregando solo la validación del verificador. La suite completa no arranca, porque `tests/Persistencia/PdoPrueba.php` arma un paciente con `DPI_ANA` al cargarse. Sin ese archivo, **fallan 70 de 100** pruebas, por un motivo ajeno a lo que prueban. Al corregir el defecto hay que regenerar primero los DPI de prueba. |
| Propuesta | Verificador módulo 11 sobre los 8 primeros dígitos, y municipio según la tabla del RENAP por departamento. La regla exacta debe confirmarse con la norma del RENAP. |
| Prueba | DEF-05 (falla). |

## DEF-06 · El teléfono cuenta espacios como dígitos · Menor · **nuevo**

| Campo | Valor |
|---|---|
| Ítem / regla | RT-01 · R-01, RF-01 |
| Ubicación | `src/Dominio/Modelo/Paciente.php:68` (`/^\+?[0-9 ]{8,15}$/`) |
| Descripción | La regla y su mensaje dicen «de 8 a 15 dígitos», pero la expresión mide **caracteres**, y cuenta los espacios. |
| Evidencia | `«1      2»` (2 dígitos) → 201, guardado tal cual · `«12 34 56 7»` (7 dígitos) → 201 · `«1234567»` (7 dígitos sin espacios) → 422 |
| Cómo se encontró | Las pruebas de valores límite usaban solo dígitos (7/8/15/16) y pasaban. La exploración con espacios, que es el formato que muestra el propio ejemplo `+502 5555 1234`, encontró que el límite se mide sobre otra cosa. |
| Por qué es Menor | No expone datos ni duplica identidades. Sin embargo, deja un teléfono de contacto inservible en la ficha. |
| Propuesta | Quitar los espacios antes de contar y exigir de 8 a 15 dígitos, o guardar el número en un formato normalizado (E.164). |
| Prueba | DEF-06 (falla) |

## Observaciones (no son defectos hasta que el negocio decida)

| ID | Observación | Evidencia | Pregunta |
|---|---|---|---|
| OBS-01 | La fecha de nacimiento no tiene límite inferior. | `0001-01-01` → 201 (2025 años de edad); `1850-01-01` → 201 | ¿Se fija una edad máxima (por ejemplo, 130 años) en RF-01? |
| OBS-02 | El motivo no tiene longitud máxima y se guarda completo en la bitácora. | Un motivo de 5000 caracteres → 201, y quedan 5000 caracteres en `motivo_proteccion` | ¿Se limita a 500, como en RBAC? |
| OBS-03 | Los nombres no exigen ninguna letra, y el país del pasaporte no se compara con ISO 3166. | `«!!!»` y `«123»` → 201. Esos pacientes **no se pueden encontrar por nombre**, porque la búsqueda exige 3 letras. País `ZZZ` → 201. | ¿Se exige al menos una letra y un país del catálogo ISO? |

## NC-01 · El hito S15 del plan SQA no se cumplió · No conformidad de proceso

| Campo | Valor |
|---|---|
| Regla | Plan SQA S14 §8.2 (hito S15: «M5 = 100 %; M7: 0 Crítico; CP-05 implementado») y §6 (una métrica en ROJO en dos mediciones seguidas se escala) |
| Evidencia | [6-medir-sqa-despues.txt](evidencia/6-medir-sqa-despues.txt): M5 sigue en 75 % y M7 en 4 abiertos (Crítico 1). Es la segunda medición en ROJO. |
| Causa | La semana 15 se dedicó a **diseñar y ejecutar las pruebas**. El retrabajo de AR-01..AR-04 no se hizo. Con lo que sí se hizo, cada hallazgo tiene ahora una prueba ejecutable que dirá cuándo quedó corregido. |
| Acción | Se escala al Integrador y se **congela la liberación** (plan §6). El retrabajo pasa a S16, en este orden: DEF-01, DEF-02 y DEF-03. DEF-04 espera la decisión de negocio. El tablero tiene la fila S15 ([tablero-seguimiento.csv](../semana-14/plantillas/tablero-seguimiento.csv)). |
| Cierre | Cuando DEF-01..DEF-03 pasen, se muevan a la suite normal, y M5 = 100 % y M7 = 0 Crítico/Mayor queden verificados por alguien que no sea el autor. |
