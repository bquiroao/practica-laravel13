# Formato y registro de hallazgos — Revisión técnica formal

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

## 1. Formato de hallazgo

Cada hallazgo se registra con estos campos. Lo que no tiene evidencia reproducible no entra como hallazgo: queda como **pregunta** para la reunión.

| Campo | Contenido | Ejemplo |
|---|---|---|
| **ID** | `RT-nn` (hallazgo) u `O-n` (observación, no exige retrabajo) | RT-01 |
| **Ítem** | El ítem del checklist que lo detectó | C-02 |
| **Ubicación** | `archivo:línea`, relativa a `micro-his-qa/` | `src/Dominio/Modelo/Defecto.php:133` |
| **Tipo** | Omisión (falta algo) · Incorrecto (hace otra cosa) · Inconsistencia (dos lugares discrepan) · Dependencia | Incorrecto |
| **Severidad** | **Crítica:** puede liberar un build que la regla no libera, o expone datos de otro tenant o datos clínicos. **Mayor:** incumple un RF, un CA o una decisión, sin llegar a crítica. **Menor:** latente, o con impacto acotado. **Observación:** riesgo sin defecto | Crítica |
| **Regla** | La regla, RF, CA o decisión que se incumple | R-09 / RF-10 |
| **Descripción** | Qué pasa, en una frase sin la solución | — |
| **Evidencia** | El comando que lo reproduce, con la salida observada frente a la esperada | `php herramientas/sonda-revision.php` → RT-01 |
| **Lo encontró** | El foco del revisor | Revisor de dominio |
| **Estado** | Candidato → Confirmado / Descartado (en la reunión) → Corregido → Verificado (en el seguimiento) | Confirmado |
| **Responsable y fecha** | Quién lo corrige y cuándo | Autor, semana 15 |

**Reglas de la reunión.** Se discute **si** es un defecto y su severidad, **no** cómo arreglarlo. La severidad la decide el moderador si no hay acuerdo. Un hallazgo duplicado se fusiona con el de ID menor.

## 2. Registro de la revisión piloto

Revisión piloto sobre el commit de `develop` posterior al PR #395, con la [sonda](herramientas/sonda-revision.php): 6 de los 7 hallazgos se reproducen con un comando ([sonda-1-hallazgos.txt](evidencia/sonda-1-hallazgos.txt)); RT-07 se verifica por inspección.

| ID | Ítem | Ubicación | Tipo | Sev. | Regla | Descripción | Estado |
|---|---|---|---|---|---|---|---|
| **RT-01** | C-02 | `src/Dominio/Modelo/Defecto.php:133`; `src/Persistencia/Pdo/PdoRepositorioDefectos.php:52` | Incorrecto | **Crítica** | R-09 / RF-10 | Un defecto **sin clasificar** con severidad propuesta CRÍTICA no cuenta como crítico abierto: solo se mira la severidad clasificada. La campaña cierra con **GO**, y la trazabilidad muestra «CRITICA» con `bloquea_go: false`. Con 40 casos y umbral 95 %, una sola FALLA así libera | Confirmado |
| **RT-02** | C-12 | `src/Aplicacion/CasosDeUso/ConsultarTrazabilidad.php:44` frente a `CerrarCampana.php:49` | Inconsistencia | Mayor | D-23 / RF-11 | El cierre cuenta los críticos abiertos de los casos del alcance **de cualquier campaña**; la trazabilidad solo lista los defectos **de esta** campaña. El Líder QA no ve nada que bloquee y el cierre da NO-GO por «1 defecto crítico abierto». Es H-3 de la semana 11, ahora en el servidor | Confirmado |
| **RT-03** | C-12 | `src/Persistencia/Pdo/PdoRepositorioCasos.php:54`; `ConsultarTrazabilidad.php:58` | Incorrecto | Menor | RF-11 | La trazabilidad toma los requisitos de la **última** versión del caso, no de la versión congelada en la campaña. Una v2 en borrador cambia la trazabilidad de una campaña ya ejecutada. **Latente:** hoy nada crea una v2 (ver RT-04) | Confirmado |
| **RT-04** | C-06 | `src/Dominio/Modelo/CasoPrueba.php` (solo `disenar` y `aprobar`); `api/openapi.yaml` (9 operaciones) | Omisión | Mayor | CA-02 / RF-02 | «Editar un caso aprobado crea la versión siguiente en borrador» no tiene operación ni prueba. Diseñar de nuevo el mismo id da 409 `CASO_EXISTENTE` | Confirmado |
| **RT-05** | C-03 | `src/Dominio/Modelo/Campana.php:165` | Incorrecto | Mayor | R-09 / RF-10 | Si todos los casos son NO_APLICA, la tasa vale 1,0 y la campaña se libera con **GO** sin haber ejecutado nada | Confirmado |
| **RT-06** | C-04 | `src/Dominio/Modelo/Defecto.php:112` frente a `Campana.php:96` | Inconsistencia | Menor | R-08 | Iniciar una campaña exige un build `x.y.z(+commit)`; marcar CORREGIDO acepta cualquier texto («ya quedó»). La trazabilidad del defecto pierde el build exacto | Confirmado |
| **RT-07** | C-14 | `src/Dominio/Modelo/CasoPrueba.php:50`, `src/Dominio/Evidencia/RegistroTexto.php:12`; `README.md:3` | Dependencia | Menor | Sin requisito (despliegue) | El Dominio usa `mb_strlen` y `mb_check_encoding` (extensión **mbstring**), y la persistencia `pdo_pgsql`. El README solo dice «PHP 8.2+ vanilla». En un PHP sin mbstring, diseñar un caso termina en error fatal | Confirmado |
| O-1 | C-08 | `src/Persistencia/Pdo/PdoRepositorioCampanas.php:56, 111, 115` | — | Obs. | R-13 | `campana_alcance`, `evidencias` y `referencias_externas` se leen solo por el id del padre. Es correcto porque el padre se cargó filtrando por tenant, pero depende de ese orden. Si un método nuevo lee un hijo directo, no hay una segunda barrera | Observación |

### Responsables y fechas

| ID | Responsable | Cuándo | Cómo se verifica el cierre |
|---|---|---|---|
| RT-01 | Autor | **Semana 15**, con una prueba que falla primero | La sonda deja de reproducir RT-01; la prueba nueva pasa con PostgreSQL y en memoria |
| RT-02 | Autor | Semana 15 | La sonda: la trazabilidad marca el defecto de la campaña anterior |
| RT-05 | Autor, con el Líder QA (decidir NO-GO o 409) | Semana 15 | La sonda deja de reproducir RT-05 |
| RT-04 + RT-03 | Autor | Después del curso: es una funcionalidad nueva (operación de edición), fuera del alcance de una corrección | Se agenda; mientras tanto, RT-03 no puede ocurrir |
| RT-06 | Autor | Semana 15 | La sonda deja de reproducir RT-06 |
| RT-07 | Autor | Semana 14 (plan SQA: control de ambiente) | README con extensiones; `php -m` en el control |
| O-1 | — | Se convierte en un ítem del plan SQA (semana 14) | — |

## 3. Métricas de la revisión piloto

| Métrica | Valor |
|---|---|
| Tamaño revisado | 3 319 líneas (Dominio 998, Aplicación 763, PDO 428, `schema.sql` 184, `openapi.yaml` 823, Kernel y autenticación 123) |
| Hallazgos | 7 (1 crítico, 3 mayores, 3 menores) + 1 observación |
| Densidad | 2,1 hallazgos por 1 000 líneas |
| Ítems del checklist que encontraron algo | 6 de 14 |
| Hallazgos que **ninguna** de las 100 pruebas ni de las 35 mutaciones detectaba | 7 de 7 |

El último dato es el que justifica la revisión: la suite está en verde y las mutaciones dan 35/35, pero las pruebas solo verifican lo que alguien pensó probar. Los 7 hallazgos están en lo que nadie pensó: un dato sin clasificar, otra campaña, una versión posterior, el caso con cero aplicables, el mismo campo en otra operación y el ambiente.
