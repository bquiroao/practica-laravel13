# Autorrevisión del autor (criterio de entrada E-6)

**Autor:** Boris Alexander Quiroa Orellana · **Paquete:** `micro-his-rbac` en el commit indicado por [criterios-entrada.txt](evidencia/criterios-entrada-2-despues.txt) · **Checklist:** [RT-01..RT-15](checklist-revision.md)

## Qué es y qué no es

Antes de convocar la reunión, el autor aplica el mismo checklist a su propio trabajo y registra lo que encuentra con el mismo formato. Hay dos razones:

1. No se gasta el tiempo de los revisores en lo que el autor podía ver solo.
2. Sirve como **prueba del formato y del checklist** antes de usarlos con el equipo.

**No reemplaza la revisión:** el autor tiene puntos ciegos sobre su propio diseño. Por eso todos estos hallazgos llegan a la reunión **abiertos**, para que el equipo confirme o ajuste la severidad, y los revisores buscan además lo que aquí no aparece.

## Resultado por ítem

| Ítem | Resultado | Hallazgos |
|---|:-:|---|
| RT-01 Una regla, una condición | Sí | — |
| RT-02 Fail-closed | Sí | — |
| RT-03 Una sola definición de vigencia | **No** | AR-02, AR-07 |
| RT-04 Cambio + evento en una transacción | Sí | — (INT-05 sin ejecutar: falta PostgreSQL) |
| RT-05 Trazabilidad | **No** | AR-03, AR-04 |
| RT-06 Contrato = código | Sí | — |
| RT-07 Errores sin filtrar información | Sí | — |
| RT-08 Rutas protegidas | Sí | — |
| RT-09 Tenant | Sí, con riesgo | AR-03 |
| RT-10 Identidad | **No** | AR-01 |
| RT-11 Reglas administrativas | **No** | AR-02 |
| RT-12 Validación en la frontera | Sí | — |
| RT-13 Dependencias entre capas | Sí | — |
| RT-14 Solo dependencias necesarias | **No** | AR-05, AR-06 |
| RT-15 Secretos y datos sensibles | Sí | — (los tokens de demostración son AR-01) |

**Total:** 7 hallazgos.

| Severidad | Cantidad | IDs |
|---|---:|---|
| Crítico | 0 | — |
| Mayor | 3 | AR-01, AR-02, AR-04 |
| Menor | 2 | AR-03, AR-05 |
| Observación | 2 | AR-06, AR-07 |

Registro en CSV: [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv).

Los hallazgos AR-01, AR-02 y AR-03 se **reproducen** con [herramientas/verificar-autorrevision.php](herramientas/verificar-autorrevision.php) ([salida](evidencia/autorrevision-verificaciones.txt)):

```text
AR-01  crearKernel(config.php) + «Bearer token-admin-oriente» → asignar rol: HTTP 201 ROL_ASIGNADO
       tokens estáticos aceptados por config.php: 4 (token-admin-oriente, token-ana, token-marta, token-admin-norte)
AR-02  misma asignación dos veces (sin fin): HTTP 201 y 409 → membresías activas de laboratorio: 1
AR-02  misma asignación dos veces (con fin): HTTP 201 y 201 → membresías activas de laboratorio: 2
AR-03  token de hosp-norte contra hosp-oriente: permisos-efectivos deja 0 evento(s); decisiones deja 1 (TENANT_NO_COINCIDE)
```

## Hallazgos

### AR-01 · La autenticación de demostración no se puede desactivar · Mayor · Riesgo

| | |
|---|---|
| Ítem | RT-10 |
| Ubicación | `bootstrap/contenedor.php:178` · `config/config.php:14-20` |
| Descripción | El punto de entrada real (`crearKernel`, usado por `public/index.php`) **siempre** autentica con `AutenticadorTokenDemo` y 4 tokens estáticos escritos en `config.php`. No hay variable ni modo que lo impida fuera de desarrollo. |
| Evidencia | Con la configuración real, `Bearer token-admin-oriente` asigna un rol: `HTTP 201 ROL_ASIGNADO`. |
| Regla afectada | R-04; el principio fail-closed aplicado al arranque |
| Propuesta | Elegir el autenticador por configuración. Sin `MICROHIS_ENTORNO=desarrollo`, arrancar **sin** tokens de demostración: toda petición da 401. |
| Estado | Abierto |

### AR-02 · R-03 permite duplicar membresías con fecha de fin · Mayor · Defecto

| | |
|---|---|
| Ítem | RT-03, RT-11 |
| Ubicación | `src/Dominio/Reglas/Asignacion/ReglaSinMembresiaDuplicada.php:21` · `database/schema.sql:45-47` |
| Descripción | La regla y el índice único solo consideran membresías **abiertas** (`valido_hasta IS NULL`). Dos asignaciones idénticas con fecha de fin se aceptan. |
| Evidencia | Con fin: `HTTP 201 y 201 → 2 activas`. Sin fin: `201 y 409 → 1`. Ya se había medido en la S10 (`reintento.php`). |
| Regla afectada | R-03 |
| Propuesta | Detectar el solapamiento de periodos en la regla y en el índice (restricción de exclusión), o `Idempotency-Key` (B-5). |
| Estado | Abierto |

### AR-03 · Un sondeo entre hospitales por permisos efectivos no deja rastro · Menor · Riesgo

| | |
|---|---|
| Ítem | RT-05, RT-09 |
| Ubicación | `src/Aplicacion/CasosDeUso/ConsultarPermisosEfectivos.php:36-48` |
| Descripción | El cambio B-6 (S12) dejó de auditar las consultas pasivas, pero un token de un hospital usado contra otro **no** es una consulta pasiva. `/decisiones` lo registra como `TENANT_NO_COINCIDE`; `/permisos-efectivos` no. No da acceso (responde todo `false`), pero se pierde la señal de un token usado fuera de su hospital. |
| Evidencia | `permisos-efectivos deja 0 evento(s); decisiones deja 1 (TENANT_NO_COINCIDE)` |
| Regla afectada | R-06, RNF-14 (cuya frontera entre pasivo y activo no contempló este caso) |
| Propuesta | En permisos efectivos, auditar **solo** el caso tenant ≠ token. |
| Estado | Abierto |

### AR-04 · La falla doble de la bitácora es silenciosa · Mayor · Riesgo

| | |
|---|---|
| Ítem | RT-05 |
| Ubicación | `src/Aplicacion/CasosDeUso/AutorizarOperacion.php:72-80` · `bootstrap/contenedor.php:168` |
| Descripción | Si fallan la bitácora principal **y** el respaldo, la denegación queda sin evidencia y nadie se entera: el segundo `catch` está vacío. Además, `@mkdir` oculta el error si no se puede crear la carpeta del respaldo, así que la falla aparece recién en la primera denegación. |
| Evidencia | Lectura del código. PER-09 cubre la falla de la principal, pero **no** la de ambas. |
| Regla afectada | R-06, RNF-02 |
| Propuesta | Registrar en `error_log` (o emitir una alerta) cuando fallan las dos. Comprobar al arrancar que el respaldo se puede escribir (fail-fast). |
| Estado | Abierto |

### AR-05 · Extensiones PHP no declaradas · Menor · Defecto

| | |
|---|---|
| Ítem | RT-14 |
| Ubicación | `src/Dominio/Reglas/Revocacion/ReglaMotivoSuficiente.php:18` · `src/Presentacion/Http/Formularios/FormularioRevocacion.php:27` · README |
| Descripción | Una regla de **dominio** usa `mb_strlen` (extensión `mbstring`). El README dice «PHP 8.2+ vanilla» pero no lista las extensiones que se necesitan: `mbstring`, `json`, y `pdo_pgsql` en modo `pdo`. |
| Evidencia | `grep -rn "mb_" src` → 2 usos; `grep -i mbstring README.md` → sin resultados |
| Propuesta | Sección «Requisitos» con PHP 8.2+, `json`, `mbstring` y `pdo_pgsql`. |
| Estado | Abierto |

### AR-06 · La librería cliente se despliega con el servidor · Observación · Mejora

| | |
|---|---|
| Ítem | RT-14 |
| Ubicación | `src/Cliente/*` |
| Descripción | La librería cliente de la S5 vive en el mismo `src/` que el servidor. El flujo no la usa (`public/index.php` no la carga), pero se despliega junto con él. ARQ-01 garantiza que no depende del servidor, no que esté separada. |
| Propuesta | Moverla a un paquete aparte cuando otro módulo la consuma. |
| Estado | Abierto |

### AR-07 · ¿Revocar una membresía futura es intencional? · Observación · Pregunta

| | |
|---|---|
| Ítem | RT-03 |
| Ubicación | `src/Dominio/Reglas/Revocacion/ReglaMembresiaRevocable.php:33` |
| Descripción | «Revocable» exige `estado = active` y que no haya vencido, pero **no** `valido_desde ≤ ahora`: se puede revocar una membresía que todavía no empieza. Puede ser correcto (cancelar una asignación programada), pero no está documentado ni probado. |
| Propuesta | Decidirlo en la reunión, documentarlo y fijarlo con una prueba. |
| Estado | Abierto |

## Tiempo del autor

| Actividad | Minutos |
|---|---:|
| Aplicar el checklist al paquete | registrar en [registro-preparacion.csv](plantillas/registro-preparacion.csv) |
| Reproducir AR-01..03 con el script | registrar |
