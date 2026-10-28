# Plan de aseguramiento de la calidad (SQA) — Gobernanza y auditoría

**Módulo:** Gobernanza y auditoría de movimientos del sistema · **Objeto:** Micro-HIS Auditoría (contrato 1.2.0) · **Autor del plan:** Boris Alexander Quiroa Orellana · **Versión:** 1.0, 26/10/2026 · **Base:** IEEE 730 (adaptado)

## 1. Propósito y alcance

**Propósito:** asegurar que la bitácora siga siendo **evidencia confiable**:
- inmutable;
- sellada y verificable;
- separada por hospital;
- con cada lectura registrada.

Además, que los defectos y riesgos se detecten **con datos** y no por percepción.

**Alcance:**
- **Incluido:** el Micro-HIS Auditoría (`src/`, `database/`, `api/`, `bootstrap/`), sus pruebas, y su operación en la base del HIS (`integracion-central.sql`).
- **Excluido:** la calidad de los módulos emisores (cada uno tiene su plan), excepto el **formato** de lo que envían (AR-04).

**Atributos de calidad que cubre el plan:**

| Atributo | Qué significa aquí | Métricas |
|---|---|---|
| Calidad del producto | Hace lo que dicen los requisitos | M1, M2 |
| Confiabilidad | La cadena es íntegra hoy y se puede seguir verificando cuando crezca | M3, M7 |
| Seguridad | Nadie lee ni registra fuera de su tenant ni sin permiso | M4 |
| Trazabilidad | Contrato = código; cada código de error tiene su prueba; cada decisión tiene evidencia | M5 |
| Riesgo | Los hallazgos de la revisión se cierran a tiempo | M6 |

## 2. Organización y responsables

| Rol | Quién | Responsabilidad en el plan |
|---|---|---|
| **Desarrollador del módulo** | Boris Alexander Quiroa Orellana | Mantiene las métricas M1, M2 y M5 en verde; corrige las no conformidades de código; actualiza las pruebas y las mutaciones |
| **Responsable de QA del equipo** | Por designar (integrante de otro módulo) | Ejecuta `medir-sqa.php` cada semana; lleva el [tablero](plantillas/tablero-seguimiento.csv); abre las no conformidades; audita el cumplimiento del plan |
| **DBA del HIS** | Por designar | Controles detectivos diarios en la base (`controles-detectivos.sql`); verificación de las cadenas y publicación de anclas; permisos de la base |
| **Responsable de seguridad** | Por designar | Recibe las alertas de M3 (ALTERADA o TRUNCADA) y de M4; decide los incidentes; es el destinatario del «Copiar informe» |
| **Líder técnico / docente** | Por designar | Aprueba excepciones (riesgo aceptado), cambios de umbral y el escalamiento de severidad |

**Independencia:** QA no es el desarrollador. El que mide no es el que corrige.

## 3. Métricas (7)

Las 7 se miden **sobre el código real** con [`herramientas/medir-sqa.php`](herramientas/medir-sqa.php) ([línea base](evidencia/linea-base-sqa.txt)).

| ID | Métrica | Fuente | Umbral | Responsable | Acción si falla |
|---|---|---|---|---|---|
| **M1** | Pruebas automáticas en verde | `tests/ejecutar.php` con PostgreSQL | 0 fallan **y** 0 omitidas en CI | Desarrollador | ROJO: no se mergea (control CP-1). ÁMBAR (omitidas): el CI debe tener PostgreSQL |
| **M2** | Defectos inyectados detectados (mutation score) | `tests/mutaciones.php` | 100 % | Desarrollador | Un mutante que sobrevive pide una prueba nueva antes del release |
| **M3** | Cadenas íntegras en la verificación | `POST /verificaciones` de cada cadena (CI con datos sembrados; en producción, la verificación diaria del DBA) | 100 % INTEGRA | Seguridad (producción), Desarrollador (CI) | ALTERADA o TRUNCADA = **incidente**: no se modifica nada, se envía el informe, se compara con el ancla publicada |
| **M4** | Intentos de fuga entre tenants o sin permiso, bloqueados | 12 sondas HTTP (tenant, red, permisos, emisor ajeno, DELETE/PUT, sin token) | 100 % | Seguridad | ROJO: bloqueo del release y revisión de RT-07..09 |
| **M5** | Contrato = código y códigos de error con prueba | Guardas API-01..03 y REC-04; el enum de errores del OpenAPI contra las pruebas | 100 % y 100 % | Desarrollador | Un código documentado sin prueba, o una guarda roja: se corrige antes de publicar el contrato |
| **M6** | Hallazgos de la revisión técnica abiertos | `semana-13/plantillas/registro-hallazgos.csv` | 0 críticos; mayores con plazo ≤ 7 días | QA | Un crítico abierto = ROJO y bloqueo del release; un mayor vencido escala al líder técnico |
| **M7** | Capacidad de la verificación para 1 año de un hospital | Memoria medida por movimiento × volumen proyectado (2 000/día) | ≤ 50 % del `memory_limit` | Desarrollador y DBA | Por encima: verificación por lotes (AR-02) antes de que el volumen real llegue al límite |

**Supuesto declarado:** el volumen de M7 (2 000 movimientos por hospital y día) es una estimación y hay que calibrarlo. El control C-D5 entrega el dato real desde la primera semana de producción.

### 3.1 Las métricas detectan defectos: comprobado

Una métrica que nunca cambia de color no mide nada. Con [`herramientas/probar-deteccion.php`](herramientas/probar-deteccion.php) se inyectó **un** defecto en una copia del Micro-HIS por vez, y se midió ([evidencia](evidencia/deteccion-defectos.txt)):

| Defecto inyectado | Métrica que lo detecta | Valor |
|---|---|---|
| D1 · Fuga entre tenants en el detalle (`Alcance::incluye` → `true`) | **M4** ROJO, M1 ROJO | 11/12: «auditor de Oriente lee un movimiento de Norte → 200» |
| D2 · La cadena salta una secuencia al anexar | **M3** ROJO, M1 ROJO | 1/3 cadenas íntegras |
| D3 · El contrato agrega un código de error sin prueba | **M5** ÁMBAR | 16/17 códigos: `LIMITE_EXCEDIDO` |
| D4 · Un emisor puede registrar en otro hospital | **M4** ROJO, M1 ROJO | «emisor de Oriente registra en Norte → 201» |

**D3 lo detecta solo M5.** Las guardas de contrato verifican que lo que **devuelve** el servidor esté documentado, no que todo lo documentado se pruebe. Por eso M5 compara en la dirección contraria.

## 4. Controles preventivos

| ID | Control | Previene | Dónde | Responsable |
|---|---|---|---|---|
| CP-1 | Merge a `develop` solo con M1 y M5 en verde, y con el PR con su plantilla | Regresiones; contrato desactualizado | PR y CI | Desarrollador; QA verifica |
| CP-2 | Pruebas primero en cada cambio de comportamiento (rojo → verde, como en las semanas 6 y 12) | Cambios sin prueba | Proceso | Desarrollador |
| CP-3 | Cada regla nueva entra como `ReglaMovimiento` o `FiltroMovimiento` (OCP); el alcance, el sello, la idempotencia y la meta-auditoría **no** son extensibles | Que una extensión quite un invariante | Diseño (D-06, D-07) | Desarrollador; revisión |
| CP-4 | Inspección formal (semana 13) antes de cada versión mayor del contrato o cambio en `Sello`/`CadenaSellada` | Defectos de integridad que las pruebas no ven | Plan de revisión | QA (moderador) |
| CP-5 | Permisos de la base: el usuario de la aplicación solo tiene `INSERT` y `SELECT` sobre `audit_logs` | Modificaciones aunque el trigger falle | `integracion-central.sql` | DBA |
| CP-6 | Sin tokens demo fuera de desarrollo (cierre de AR-01) | Acceso de red con un token público | `crearKernel` | Desarrollador; Seguridad aprueba |

## 5. Controles detectivos

| ID | Control | Detecta | Frecuencia | Responsable | Herramienta |
|---|---|---|---|---|---|
| C-D1 | `medir-sqa.php` completo (M1..M7) | Cualquier métrica fuera de umbral | Semanal y antes de cada release | QA | [`medir-sqa.php`](herramientas/medir-sqa.php) |
| C-D2 | Huecos o repeticiones de secuencia en cada cadena | Filas borradas o insertadas por fuera de la aplicación | Diario | DBA | [`controles-detectivos.sql`](herramientas/controles-detectivos.sql) |
| C-D3 | El trigger de solo inserción sigue instalado y habilitado | Que alguien lo desactive | Diario | DBA | Ídem |
| C-D4 | Correcciones > 1 % por emisor en 7 días | Uso de correcciones para encubrir | Semanal | Seguridad | Ídem |
| C-D5 | Lecturas > movimientos de emisores por día (AR-05) | Consultas masivas; dato real para calibrar M7 | Diario | DBA | Ídem |
| C-D6 | Recursos fuera del formato `tipo:identificador` (AR-04) | Accesos que «¿quién vio la ficha?» no encontraría | Semanal | QA | Ídem |
| C-D7 | Verificación con el ancla publicada | Truncamiento de la cola de la cadena | Diario | DBA | `POST /verificaciones` con el ancla |

Los controles SQL se **probaron** sobre el esquema real ([evidencia](evidencia/controles-detectivos-prueba.txt)):
- con la base vacía, ninguno devuelve filas;
- con los datos de prueba, cada uno devuelve exactamente su caso;
- al deshabilitar el trigger, C-D3 alerta.

## 6. No conformidades y escalamiento

| Nivel | Cuándo | Plazo | Escala a |
|---|---|---|---|
| **Incidente** | M3 ALTERADA o TRUNCADA; C-D2 o C-D3 con filas; M4 < 100 % | Inmediato | Seguridad y líder técnico; **no se modifica nada** en la base |
| **Crítica** | M1 o M2 en rojo en `develop`; hallazgo crítico abierto (M6) | ≤ 3 días | Líder técnico si vence |
| **Mayor** | M5 o M7 fuera de umbral; hallazgo mayor (M6) | ≤ 7 días | Líder técnico si vence |
| **Menor** | ÁMBAR sostenido 2 semanas | Siguiente iteración | QA |

Cada no conformidad se registra en el [tablero](plantillas/tablero-seguimiento.csv) con su métrica, la fecha, el responsable y la verificación del cierre.

## 7. Plan de acción de la línea base

La línea base (26/10/2026) tiene **2 métricas en ROJO**:

| Métrica | Causa | Acción | Responsable | Plazo | Verificación |
|---|---|---|---|---|---|
| **M6** ROJO | AR-01 (Crítico): tokens demo siempre activos | Autenticador por configuración, fail-closed (CP-6) | Desarrollador | 29/10/2026 | Prueba nueva: sin `AUDITORIA_ENTORNO=desarrollo`, `token-superadmin` → 401; M6 sin críticos |
| **M7** ROJO | AR-02: la verificación carga la cadena completa | Verificación por lotes con un cursor por secuencia | Desarrollador y DBA | 02/11/2026 | M7 en verde; INT-05 e INT-07 siguen en verde |
| M6 (mayores) | AR-04: el recurso es texto libre | Regla de formato `tipo:identificador` + C-D6 | Desarrollador | 02/11/2026 | Prueba nueva y C-D6 sin filas en los datos de prueba nuevos |

## 8. Calendario de seguimiento

### 8.1 Cadencias

| Cadencia | Qué | Quién |
|---|---|---|
| Cada commit / PR | M1, M5 (CP-1) | CI y Desarrollador |
| Diaria | C-D2, C-D3, C-D5, C-D7 | DBA |
| Semanal (lunes) | `medir-sqa.php` completo (C-D1), C-D4, C-D6; actualización del tablero | QA |
| Por release | M2 completo con PostgreSQL; revisión de M6; aprobación de Seguridad si cambió el tenant o el sello | QA y Líder técnico |
| Mensual | Revisión de la [matriz de riesgos](matriz-riesgos.md) y recalibración de umbrales (M7 con el dato de C-D5) | Líder técnico y QA |

### 8.2 Hitos

| Fecha | Hito |
|---|---|
| 26/10/2026 | Línea base (este plan) |
| 29/10/2026 | Cierre de AR-01; M6 sin críticos |
| 02/11/2026 | Primera medición semanal; cierre de AR-02 y AR-04; M7 en verde |
| 02/11/2026 – 06/11/2026 | Reuniones de la revisión técnica (semana 13) |
| 09/11/2026 | Semana 15: pruebas; cierre de DEF-07-01 (AR-03) |
| 16/11/2026 | Revisión mensual de riesgos; decisión de release |

## 9. Herramientas y evidencia

| Herramienta | Qué hace | Evidencia |
|---|---|---|
| [`medir-sqa.php`](herramientas/medir-sqa.php) | Mide M1..M7 y sale con el código 1 si alguna está en rojo | [linea-base-sqa.txt](evidencia/linea-base-sqa.txt) |
| [`probar-deteccion.php`](herramientas/probar-deteccion.php) | Inyecta 4 defectos y comprueba qué métrica los detecta | [deteccion-defectos.txt](evidencia/deteccion-defectos.txt) |
| [`controles-detectivos.sql`](herramientas/controles-detectivos.sql) | C-D2..C-D6 en la base real (solo lectura) | [controles-detectivos-prueba.txt](evidencia/controles-detectivos-prueba.txt) |
| [`tablero-seguimiento.csv`](plantillas/tablero-seguimiento.csv) | Histórico semanal de las métricas y de las no conformidades | Primera fila: la línea base |
