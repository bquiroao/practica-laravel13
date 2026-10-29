# Checklist de la revisión técnica formal — 14 ítems

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

Cada ítem es una **pregunta que se contesta sí o no**, con una forma de verificarla que otro revisor puede repetir. Un «no» se convierte en un hallazgo en el [formato RT](registro-hallazgos.md#1-formato-de-hallazgo). La columna «Piloto» es el resultado de la revisión piloto de esta semana.

Rutas relativas a `micro-his-qa/`. «Sonda» = [herramientas/sonda-revision.php](herramientas/sonda-revision.php).

## Reglas de dominio

| # | Pregunta | Cómo se verifica | Foco | Piloto |
|---|---|---|---|---|
| C-01 | ¿Cada regla R-01..R-15 tiene un único lugar en el Dominio y una prueba con su identificador? | Matriz de la semana 6 (D-01..D-23); `grep -rn "R-0\|R-1" src/Dominio` | Dominio | ✅ |
| C-02 | ¿Cada regla que **bloquea el GO** considera todos los estados del dato: sin clasificar, corregido sin verificar, de otra campaña? | Leer `CerrarCampana`, `criticosAbiertos`, `mayoresAbiertos`; sonda RT-01 | Dominio | ❌ **RT-01** |
| C-03 | ¿Las reglas numéricas se comportan bien en los límites: 0 casos aplicables, umbral 1,0, tasa justo en el umbral? | Leer `Campana::cerrar`; sonda RT-05 | Dominio | ❌ **RT-05** |
| C-04 | ¿Un mismo dato se valida igual en todas las operaciones que lo reciben (build, prioridad, severidad)? | Buscar cada campo en `src/Dominio`; sonda RT-06 | Dominio | ❌ **RT-06** |

## Contrato API

| # | Pregunta | Cómo se verifica | Foco | Piloto |
|---|---|---|---|---|
| C-05 | ¿El contrato y el código coinciden en operaciones, permisos, estados y códigos de error? | API-01..03 en `php tests/ejecutar.php --solo=API` | Contrato | ✅ |
| C-06 | ¿Cada criterio de aceptación de la semana 2 (CA-01..CA-15) tiene una operación que lo ejerce y una prueba? | Recorrer CA por CA contra `api/openapi.yaml` y `tests/`; sonda RT-04 | Contrato | ❌ **RT-04** (CA-02) |

## Permisos y tenant

| # | Pregunta | Cómo se verifica | Foco | Piloto |
|---|---|---|---|---|
| C-07 | ¿Cada caso de uso verifica su permiso **antes** de leer o escribir nada? | `grep -L "acceso->permite" src/Aplicacion/CasosDeUso/*.php` → vacío; APP-09 | Seguridad | ✅ 9 de 9 |
| C-08 | ¿Cada consulta filtra por tenant, o por un padre que ya se cargó filtrando por tenant? | Leer cada `consulta(` de `src/Persistencia/Pdo`; INT del esquema | Seguridad | ✅ con observación **O-1** |

## Integridad de campañas, matriz, defectos y severidad

| # | Pregunta | Cómo se verifica | Foco | Piloto |
|---|---|---|---|---|
| C-09 | ¿Build, alcance y versión de cada caso se congelan al iniciar, y los intentos solo se agregan (también en la base)? | DOM-03, INT-03 (triggers de solo agregar); `schema.sql` | Dominio | ✅ |
| C-10 | ¿Los críticos de la matriz se calculan igual al iniciar (R-03) y al cerrar (R-15): críticos ∩ funcionalidades de la campaña? | `IniciarCampana` y `CerrarCampana` usan `criticosDe($tenant, $funcionalidades)`; CAM-01..05 | Dominio | ✅ |
| C-11 | ¿El ciclo del defecto no tiene atajos: solo una re-ejecución lo cierra, CRÍTICA exige pasos y evidencia, y cambiar la severidad exige motivo? | `Defecto`: 4 transiciones; DOM-06..08, mutaciones M8 y M14 | Dominio | ✅ |

## Trazabilidad, evidencia y dependencias

| # | Pregunta | Cómo se verifica | Foco | Piloto |
|---|---|---|---|---|
| C-12 | ¿La trazabilidad muestra **lo mismo** que usará el cierre (defectos que bloquean) y la **versión** de cada caso que la campaña congeló? | Comparar `ConsultarTrazabilidad` con `CerrarCampana`; sonda RT-02 y RT-03 | Trazabilidad | ❌ **RT-02**, **RT-03** |
| C-13 | ¿La evidencia es fail-closed, se guarda con su huella y su contenido clínico no sale en ninguna respuesta? | APP-11 (almacén caído → nada escrito), APP-02 (solo huella), DOM-09 | Trazabilidad | ✅ |
| C-14 | ¿Las dependencias son solo las necesarias, están declaradas y respetan la dirección de las capas? | ARQ-01..06; sin Composer; buscar `mb_`, `pdo_`, `curl_` en `src/` contra lo que declara el README | Trazabilidad | ❌ **RT-07** |

## Resumen del piloto

| | Ítems |
|---|---|
| ✅ Sí | 8 (C-01, C-05, C-07..C-11, C-13) |
| ❌ No | 6 (C-02, C-03, C-04, C-06, C-12, C-14) → 7 hallazgos |
| Observaciones | 1 (O-1, en C-08) |

**Por qué estas 14 y no otras.** La tarea pide enfocar reglas de dominio, contrato API, permisos y tenant, integridad de campañas, matriz, defectos, severidad, evidencia, trazabilidad y dependencias estrictamente necesarias. Cada uno de esos temas tiene al menos un ítem. Lo que ya verifican las pruebas automáticas (C-05, C-07, C-13) se revisa leyendo la **salida** de la prueba, no el código: el tiempo de los revisores va a lo que ninguna prueba mira (C-02, C-03, C-06, C-12).
