# Plan de aseguramiento de la calidad (SQA) — Prescripciones con validación de alergias

**Módulo:** Prescripciones electrónicas con validación de alergias (ASII-15) · **Objeto:** Micro-HIS Prescripciones (contrato 1.2.0) · **Autor del plan:** Boris Alexander Quiroa Orellana · **Versión:** 1.0, 27/10/2026 · **Base:** IEEE 730 (adaptado)

## 1. Propósito y alcance

**Propósito:** asegurar que **ninguna prescripción se emita contra una alergia que debía impedirla**. Para eso, el plan cuida que:
- la verificación sea correcta;
- el sistema falle cerrado si el Expediente no responde;
- lo emitido sea inmutable;
- nadie emita, lea ni anule fuera de su permiso y de su hospital.

Además, que los defectos y riesgos se detecten **con datos** y no por percepción.

**Alcance:**
- **Incluido:** el Micro-HIS Prescripciones (`src/`, `database/`, `api/`, `bootstrap/`), sus pruebas y su operación en la base del hospital (`integracion-hospital.sql`).
- **Excluido:** la calidad de los módulos vecinos (Expediente, RBAC, Auditoría), excepto **lo que Prescripciones recibe de ellos**: cómo se registran las alergias (AR-03, C-D4) y la disponibilidad del Expediente (C-D6).

**Atributos de calidad que cubre el plan:**

| Atributo | Qué significa aquí | Métricas |
|---|---|---|
| Calidad del producto | Hace lo que dicen los requisitos, y las pruebas detectan defectos | M1, M2 |
| Seguridad clínica | La verificación encuentra las alergias, aunque estén escritas de otra forma | M3 |
| Seguridad | Nadie emite, lee ni anula fuera de su permiso, de su hospital ni con una clave ajena | M4 |
| Confiabilidad | Con el Expediente caído no se emite; los reintentos no duplican | M5 |
| Trazabilidad | Contrato = código; cada código de error tiene una prueba | M6 |
| Riesgo | Los hallazgos de la revisión técnica se cierran a tiempo | M7 |

## 2. Organización y responsables

| Rol | Quién | Responsabilidad en el plan |
|---|---|---|
| **Desarrollador del módulo** | Boris Alexander Quiroa Orellana | Mantiene M1, M2, M5 y M6 en verde; corrige las no conformidades de código; actualiza las pruebas, las mutaciones y el corpus de M3 |
| **Responsable de QA del equipo** | Por designar (integrante de otro módulo) | Ejecuta `medir-sqa.php` cada semana; lleva el [tablero](plantillas/tablero-seguimiento.csv); abre las no conformidades; audita el cumplimiento del plan |
| **Farmacéutico clínico (referente)** | Por designar | Dueño del **corpus de M3**: agrega los casos reales que aparezcan; decide AR-03 y AR-07; revisa C-D4 y C-D5 cada semana |
| **DBA del hospital** | Por designar | Controles detectivos diarios (`controles-detectivos.sql`); permisos de la base; la bandeja hacia Auditoría |
| **Responsable de seguridad** | Por designar | Recibe las alertas de M4 y de C-D3; aprueba el cierre de AR-01 y AR-02 |
| **Líder técnico / docente** | Por designar | Aprueba excepciones (riesgo aceptado), cambios de umbral y el escalamiento de severidad |

**Independencia:** QA no es el desarrollador, y el corpus clínico no lo escribe solo el desarrollador. El que mide no es el que corrige.

## 3. Métricas (7)

Las 7 se miden **sobre el código real** con [`herramientas/medir-sqa.php`](herramientas/medir-sqa.php) ([línea base](evidencia/linea-base-sqa.txt)).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si falla |
|---|---|---|---|---|---|
| **M1** | Pruebas automáticas en verde | `tests/ejecutar.php` con PostgreSQL | 0 fallan **y** 0 omitidas en CI | Desarrollador | ROJO: no se mergea (CP-1). ÁMBAR (omitidas): el CI debe tener PostgreSQL |
| **M2** | Defectos inyectados detectados (mutation score) | `tests/mutaciones.php` con PostgreSQL | 100 % | Desarrollador | Un mutante que sobrevive pide una prueba nueva antes del release |
| **M3** | **Sensibilidad** de la verificación en un corpus de alergias (y especificidad informativa) | 12 casos que deben advertir o bloquear y 2 que no: código, nombre, mayúsculas, cruzadas, sales y variantes | **100 % de sensibilidad** (0 falsos negativos) | Farmacéutico (corpus) y Desarrollador | Un falso negativo es una no conformidad **crítica**: se decide con farmacia y se corrige antes del release |
| **M4** | Intentos de acceso indebido bloqueados | 12 sondas HTTP: permisos, tenant, override, clave ajena, DELETE/PUT, sin token | 100 % | Seguridad | ROJO: bloqueo del release y revisión de RT-08, RT-10, RT-11 y RT-14 |
| **M5** | Bloqueo seguro e idempotencia | 4 comprobaciones: previa y emisión con el Expediente caído; anular con el Expediente caído; 50 envíos con la misma clave | 100 % | Desarrollador | ROJO = **incidente** si ocurre en producción (se emitió sin verificar o se duplicó) |
| **M6** | Contrato = código y códigos de error con prueba | Guardas API-01..03 y JUS-04; el enum de errores del OpenAPI frente a las pruebas | 100 % y 100 % | Desarrollador | Guarda roja: no se publica el contrato. Código sin prueba: ÁMBAR, con prueba nueva o retiro del código |
| **M7** | Hallazgos de la revisión técnica abiertos | `semana-13/plantillas/registro-hallazgos.csv` | 0 críticos; mayores con plazo ≤ 7 días | QA | Un crítico abierto = ROJO y bloqueo del release; un mayor vencido escala al líder técnico |

**Por qué M3 es una métrica y no solo más pruebas:** las 111 pruebas usan los nombres exactos del catálogo y pasan todas. El corpus mide **contra el mundo**, no contra el código. Lo mantiene farmacia y crece con cada alergia real que no se detectó. Hoy da **9/12**: los 3 falsos negativos son AR-03.

### 3.1 Las métricas detectan defectos: comprobado

Una métrica que nunca cambia no mide nada. Con [`herramientas/probar-deteccion.php`](herramientas/probar-deteccion.php) se inyectó **un** defecto por vez en una copia del Micro-HIS y se comparó cada **valor** con el de la copia sin defecto ([evidencia](evidencia/deteccion-defectos.txt)). Se compara el valor, no el color, porque M3, M4 y M7 ya están en rojo en la línea base.

| Defecto inyectado | Métrica que lo detecta | Valor (referencia → con el defecto) |
|---|---|---|
| D1 · La coincidencia por nombre deja de normalizar | **M3**, M1 | 9/12 → 7/12; 93 → 91 pruebas |
| D2 · El repositorio devuelve prescripciones de otro hospital | **M4**, M1 | 11/12 → 9/12; 93 → 89 |
| D3 · El Expediente caído se lee como «sin alergias» | **M5**, M1 | 4/4 → 2/4; 93 → 86 |
| D4 · El contrato agrega un código de error sin prueba | **solo M6** | 20/23 → 20/24 códigos |

**4 de 4 detectados.** D4 lo detecta **solo** M6: las guardas del contrato verifican que lo que el servidor **devuelve** esté documentado, no que lo documentado se pruebe.

## 4. Controles preventivos

| ID | Control | Previene | Dónde | Responsable |
|---|---|---|---|---|
| CP-1 | Merge a `develop` solo con M1 y M6 en verde y el PR con su plantilla | Regresiones; contrato desactualizado | PR y CI | Desarrollador; QA verifica |
| CP-2 | Pruebas primero en cada cambio de comportamiento (rojo → verde, como en las semanas 6 y 12), con su mutación | Cambios sin prueba | Proceso | Desarrollador |
| CP-3 | Toda regla clínica vive en `PoliticaEmision` o en `VerificadorAlergias`; ningún caso de uso ni la UI deciden reglas (D-20, D-40) | Que dos lugares decidan distinto | Diseño | Desarrollador; revisión |
| CP-4 | Inspección formal (semana 13) antes de cada versión mayor del contrato o de cambiar `VerificadorAlergias` o `PoliticaEmision`, **con un R3 clínico** | Defectos clínicos que las pruebas no ven | Plan de revisión | QA (moderador) |
| CP-5 | El usuario de la aplicación en la base solo tiene `INSERT` y `SELECT` sobre las tablas de prescripción, y solo `SELECT` sobre la vista de alergias (D-21) | Modificaciones aunque el trigger falle | `integracion-hospital.sql` | DBA |
| CP-6 | Sin tokens demo fuera de desarrollo (cierre de AR-01) | Emitir a nombre de un médico con un token público | `crearKernel` | Desarrollador; Seguridad aprueba |
| CP-7 | Los alérgenos nuevos del Expediente se registran **con código** (ATC) cuando existe (cierre de AR-03, a acordar con el módulo Expediente) | Falsos negativos por texto libre | Proceso clínico y Expediente | Farmacéutico |

## 5. Controles detectivos

| ID | Control | Detecta | Frecuencia | Responsable | Herramienta |
|---|---|---|---|---|---|
| C-D1 | `medir-sqa.php` completo (M1..M7) | Cualquier métrica fuera de umbral | Semanal y antes de cada release | QA | [`medir-sqa.php`](herramientas/medir-sqa.php) |
| C-D2 | Prescripciones sin su traza `PRESCRIPCION_EMITIDA` | Que se rompa el «todo o nada» (R-12) | Diario | DBA | [`controles-detectivos.sql`](herramientas/controles-detectivos.sql) |
| C-D3 | Los triggers de inmutabilidad siguen instalados y habilitados | Que alguien los desactive (R-10) | Diario | DBA | Ídem |
| C-D4 | Alergias activas registradas solo por nombre, separando las que no coinciden con el catálogo | **La exposición a AR-03:** cuántos pacientes podrían tener un falso negativo | Semanal | Farmacéutico | Ídem |
| C-D5 | Prescriptores con más del 20 % de emisiones con advertencia justificada en 7 días | Uso rutinario del override («justificar por costumbre») | Semanal | Farmacéutico | Ídem |
| C-D6 | `VERIFICACION_NO_DISPONIBLE` en las últimas 24 h | Caídas del Expediente que detuvieron emisiones | Diario | DBA | Ídem |
| C-D7 | Trazas de la bandeja sin enviar a Auditoría hace más de 1 h | Que Auditoría no reciba lo que pasa | Diario | DBA | Ídem |

Los controles SQL se **probaron** sobre el esquema real ([evidencia](evidencia/controles-detectivos-prueba.txt)):
- con la base vacía, ninguno devuelve filas;
- con los datos de prueba, cada uno devuelve exactamente su caso;
- al deshabilitar el trigger, C-D3 alerta.

## 6. No conformidades y escalamiento

| Nivel | Cuándo | Plazo | Escala a |
|---|---|---|---|
| **Incidente** | En producción: se emitió con el Expediente caído, se duplicó una emisión (M5), C-D2 o C-D3 con filas, M4 < 100 % | Inmediato | Seguridad, farmacia y líder técnico |
| **Crítica** | Un falso negativo en M3; M1 o M2 en rojo en `develop`; un hallazgo crítico abierto (M7) | ≤ 3 días | Líder técnico si vence |
| **Mayor** | M6 con una guarda roja; un hallazgo mayor (M7); C-D4 con alergias fuera del catálogo | ≤ 7 días | Líder técnico si vence |
| **Menor** | ÁMBAR sostenido 2 semanas | Siguiente iteración | QA |

Cada no conformidad se registra en el [tablero](plantillas/tablero-seguimiento.csv) con su métrica, la fecha, el responsable y la verificación del cierre.

## 7. Plan de acción de la línea base

La línea base (27/10/2026) tiene **3 métricas en ROJO** y **1 en ÁMBAR**:

| Métrica | Causa | Acción | Responsable | Plazo | Verificación |
|---|---|---|---|---|---|
| **M7** ROJO | AR-01 (Crítico): tokens demo siempre activos | Autenticador por configuración, fail-closed (CP-6) | Desarrollador | 30/10/2026 | Prueba nueva: sin `PRESCRIPCIONES_ENTORNO=desarrollo`, `token-dr-lopez` → 401; M7 sin críticos |
| **M4** ROJO | AR-02 (Mayor): la clave de idempotencia es del hospital | Huella con el usuario; un reintento de otro usuario → 409 | Desarrollador | 03/11/2026 | M4 12/12; prueba nueva con su mutación |
| **M3** ROJO | AR-03 (Mayor): coincidencia exacta por nombre | 1) Farmacia decide el criterio (03/11). 2) Se implementa (10/11). 3) CP-7 con el Expediente | Farmacéutico y Desarrollador | 10/11/2026 | M3 12/12, sin perder especificidad (2/2); C-D4 sin alergias fuera del catálogo en los datos nuevos |
| M6 ÁMBAR | 3 códigos del contrato sin prueba que los afirme: `RUTA_NO_ENCONTRADA` y `METODO_NO_PERMITIDO` (se prueba el estado, no el código) y `SERVICIO_NO_DISPONIBLE` (**ningún código del servidor lo emite**) | Afirmar los dos primeros en HTTP-06; retirar `SERVICIO_NO_DISPONIBLE` del contrato o explicar quién lo emite | Desarrollador | 10/11/2026 | M6 23/23 (o 22/22) |

## 8. Calendario de seguimiento

### 8.1 Cadencias

| Cadencia | Qué | Quién |
|---|---|---|
| Cada commit / PR | M1, M6 (CP-1) | CI y Desarrollador |
| Diaria | C-D2, C-D3, C-D6, C-D7 | DBA |
| Semanal (lunes) | `medir-sqa.php` completo (C-D1), C-D4, C-D5; actualización del tablero | QA y Farmacéutico |
| Por release | M2 completo con PostgreSQL; M3 con el corpus actualizado; revisión de M7; aprobación de Seguridad si cambió el tenant o la identidad | QA y Líder técnico |
| Mensual | Revisión de la [matriz de riesgos](matriz-riesgos.md); ampliación del corpus de M3 con los casos reales del mes | Líder técnico, QA y Farmacéutico |

### 8.2 Hitos

| Fecha | Hito |
|---|---|
| 27/10/2026 | Línea base (este plan) |
| 30/10/2026 | Cierre de AR-01; M7 sin críticos |
| 03/11/2026 | Primera medición semanal; cierre de AR-02 (M4 en verde); decisión de farmacia sobre AR-03 |
| 03/11/2026 – 06/11/2026 | Reuniones de la revisión técnica (semana 13) |
| 10/11/2026 | Semana 15: pruebas; implementación de AR-03 (M3 en verde) y de M6 |
| 17/11/2026 | Revisión mensual de riesgos; decisión de release |

## 9. Herramientas y evidencia

| Herramienta | Qué hace | Evidencia |
|---|---|---|
| [`medir-sqa.php`](herramientas/medir-sqa.php) | Mide M1..M7 y termina con código 1 si alguna está en rojo | [linea-base-sqa.txt](evidencia/linea-base-sqa.txt) |
| [`probar-deteccion.php`](herramientas/probar-deteccion.php) | Inyecta 4 defectos y comprueba qué métrica cambia | [deteccion-defectos.txt](evidencia/deteccion-defectos.txt) |
| [`controles-detectivos.sql`](herramientas/controles-detectivos.sql) | C-D2..C-D7 en la base real (solo lectura) | [controles-detectivos-prueba.txt](evidencia/controles-detectivos-prueba.txt) |
| [`tablero-seguimiento.csv`](plantillas/tablero-seguimiento.csv) | Histórico semanal de las métricas y de las no conformidades | Primera fila: la línea base |
