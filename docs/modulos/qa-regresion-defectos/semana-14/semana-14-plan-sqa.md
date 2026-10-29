# Semana 14 — Plan de aseguramiento de la calidad (SQA)

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Tarea** | Definir el plan SQA: calidad del flujo, confiabilidad, seguridad, trazabilidad y riesgos, con responsable, umbral, fuente y acción para cada métrica y control |
| **Entregable** | Plan SQA con 5–7 métricas, matriz de riesgos, controles preventivos y detectivos, responsables y calendario de seguimiento |

## Entregables

| Pedido | Dónde |
|---|---|
| 5–7 métricas con responsable, umbral, fuente y acción | §2: **6 métricas**, calculadas por [medir-sqa.php](herramientas/medir-sqa.php). Línea base en §6 |
| Matriz de riesgos | §3: 11 riesgos con probabilidad, impacto y control |
| Controles preventivos y detectivos | §4: 8 preventivos y 8 detectivos. 4 detectivos son consultas SQL ([controles-sqa.sql](herramientas/controles-sqa.sql)) con su contraprueba |
| Responsables | §1 y en cada métrica, riesgo y control |
| Calendario de seguimiento | §5 |

## 1. Alcance y responsables

**Qué se asegura:** el flujo «diseño de casos manuales → campaña de regresión → evidencia → defectos → trazabilidad», tal como está en `micro-his-qa/` (servidor, contrato 1.1.0 y base) y en el prototipo de la semana 12.

| Atributo | Qué significa aquí | Métricas | Riesgos |
|---|---|---|---|
| **Calidad del flujo** | Las reglas R-01..R-15 hacen lo que dicen, y las pruebas lo demuestran | M-1, M-2, M-3 | K-03, K-07 |
| **Confiabilidad** | Un resultado no se pierde ni se duplica; la decisión GO/NO-GO es la correcta | M-4, M-5 | K-01, K-03, K-06, K-09 |
| **Seguridad** | Permiso por operación, aislamiento de tenant, datos clínicos protegidos | M-1 (APP-09, INT), M-6 | K-04, K-05 |
| **Trazabilidad** | Requisito ↔ caso (versión) ↔ intento ↔ evidencia ↔ defecto, y lo que bloquea el GO visible antes de cerrar | M-3, M-4 | K-02, K-10, K-11 |

| Rol | Quién | Responde por |
|---|---|---|
| **Autor del módulo** | Boris Quiroa | M-1, M-2, M-3; el retrabajo de los hallazgos; los controles en el código |
| **Líder QA** | Rol del hospital (en el curso, el docente o quien él designe) | M-4 frente a cada cierre; los controles CD-01..CD-03 durante una campaña |
| **Moderador de revisiones** | Compañero de otro módulo (semana 13) | Las revisiones formales y el seguimiento de los hallazgos |
| **Responsable de producto** | Rol del hospital | Aceptar o no un riesgo residual (firma la excepción) |
| **Administrador de la plataforma** | Rol del HIS central | El ambiente: extensiones de PHP, PostgreSQL, respaldos (K-08) |
| **Revisor de accesibilidad** | Compañero con el checklist de la semana 9 | M-6 y el recorrido |

## 2. Métricas

Cada métrica tiene **una** fuente que la calcula sin intervención y una acción concreta cuando sale del umbral. Una métrica sin acción es decoración.

| # | Métrica | Definición | Fuente | Umbral | Responsable | Frecuencia | Acción si sale del umbral |
|---|---|---|---|---|---|---|---|
| **M-1** | Pruebas en verde | Pruebas que pasan / total, con PostgreSQL y sin omitidas | `php tests/ejecutar.php` con `QA_DB_DSN` | **100 %, 0 omitidas** | Autor | Cada PR | No se mergea. Una prueba omitida cuenta como falla: sin base, las de integración no corren |
| **M-2** | Mutaciones detectadas | Mutaciones que alguna prueba detecta / total | `php tests/mutaciones.php` | **100 %**, corrida de 7 días o menos | Autor | Semanal y antes de cada release | Una mutación que sobrevive es una prueba que falta: se escribe esa prueba antes de seguir (como M11, M32) |
| **M-3** | Reglas con prueba con nombre | Reglas R-01..R-15 que aparecen en la descripción de al menos una prueba | Descripciones de `prueba()` en `tests/` | **15/15** | Autor | Cada PR | Agregar el ID de la regla a la descripción, o escribir la prueba si no existe |
| **M-4** | Hallazgos críticos abiertos | Hallazgos críticos de revisión que la sonda todavía reproduce | `semana-13/herramientas/sonda-revision.php` | **0 críticos** | Moderador; corrige el autor | Cada PR y cada revisión | **No se libera el módulo.** Mientras siga abierto, CD-01 corre antes de cada cierre |
| **M-5** | Rendimiento de la trazabilidad (RNF-08) | p95 de 20 consultas de trazabilidad de una campaña de 500 casos | Campaña sintética en `medir-sqa.php`, en memoria y en PostgreSQL | **p95 < 1000 ms en PostgreSQL** | Autor | Mensual y ante cambios en la trazabilidad o el esquema | Perfilar la consulta (`EXPLAIN ANALYZE`); el índice parcial `defectos_por_caso` es el primer candidato |
| **M-6** | Accesibilidad del prototipo | Violaciones de axe-core y comprobaciones que fallan en el recorrido completo | `semana-12/herramientas/recorrido.cjs` | **0 violaciones, 0 fallas**, corrida de 7 días o menos | Revisor de accesibilidad | Cada cambio de interfaz | No se publica el cambio. Se corrige y se agrega la comprobación al recorrido |

**Por qué estas seis.** M-1 y M-2 dicen si las pruebas existen y si sirven. M-3 dice si se puede **rastrear** qué regla prueba cada una. M-4 trae al plan lo que las pruebas no ven: la semana 13 mostró 7 hallazgos con M-1 y M-2 al 100 %. M-5 cierra el único RNF que quedó «para la semana 14» (RNF-08). M-6 cubre la parte del flujo que ve la gente.

**Una regla para las métricas que se leen de un archivo.** M-2 y M-6 son lentas (minutos), así que el tablero lee su última corrida. Si esa corrida tiene más de 7 días, la métrica sale como **fuera de umbral**: una métrica vieja no es una métrica en verde.

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3. **Nivel = P × I:** 6–9 alto, 3–4 medio, 1–2 bajo. Cada riesgo apunta a la evidencia que lo sostiene.

| # | Riesgo | Evidencia | P | I | Nivel | Controles | Responsable |
|---|---|---|---|---|---|---|---|
| **K-01** | Liberar un build con un defecto crítico **propuesto y sin clasificar** | RT-01 (semana 13), reproducido | 3 | 3 | **9 alto** | CP-01, CD-01, CD-06 | Autor (corrige); Líder QA (CD-01) |
| **K-02** | El Líder QA no ve lo que va a bloquear el cierre (la previsión difiere del cierre) | RT-02; H-3 de la semana 11 | 2 | 2 | 4 medio | CP-02, CD-03 | Autor |
| **K-03** | Liberar con GO una campaña donde nada se ejecutó (todo NO_APLICA) | RT-05, reproducido | 2 | 3 | **6 alto** | CP-01, CD-02 | Autor; Líder QA |
| **K-04** | Una consulta nueva lee datos de otro tenant | O-1: las tablas hijas se leen por el id del padre | 1 | 3 | 3 medio | CP-03, CP-04, CD-04, M-1 (INT, CON) | Autor; Moderador |
| **K-05** | Evidencia clínica en un equipo compartido o en una respuesta | U-06; RNF-04 | 2 | 3 | **6 alto** | CP-05, M-1 (APP-02), CD-07 | Autor; Revisor de accesibilidad |
| **K-06** | Un resultado se pierde o se duplica cuando falla la red o el almacén | D-06, D-08, D-18 (mitigado) | 2 | 2 | 4 medio | CP-06, M-1 (APP-11, CLI-01..08) | Autor |
| **K-07** | Pruebas en verde que no prueban (aserciones débiles) | H-3 de la semana 11: la comprobación pasaba con la previsión incompleta | 2 | 2 | 4 medio | CD-05 (M-2), revisión formal | Autor |
| **K-08** | El ambiente no tiene una extensión que el código necesita | RT-07: mbstring no declarada | 2 | 1 | 2 bajo | CP-07, CD-08 | Administrador de la plataforma |
| **K-09** | La trazabilidad se vuelve lenta con campañas grandes | RNF-08 | 1 | 2 | 2 bajo | M-5 | Autor |
| **K-10** | Una decisión de accesibilidad se pierde porque ningún requisito la pide | H-4 de la semana 12: 5 decisiones sin RNF | 2 | 2 | 4 medio | CP-08, M-6 | Responsable de producto (aprobar RNF-09 y RNF-10) |
| **K-11** | La trazabilidad de una campaña cambia por una versión posterior del caso | RT-03 (latente) y RT-04 | 1 | 2 | 2 bajo | CP-02, revisión formal cuando se implemente la edición | Autor |

```
               Impacto 1        Impacto 2               Impacto 3
Prob. 3                                                  K-01
Prob. 2        K-08             K-02 K-06 K-07 K-10      K-03 K-05
Prob. 1                         K-09 K-11                K-04
```

**Riesgos altos: K-01, K-03 y K-05.** K-05 está mitigado: la evidencia clínica sin confirmar solo vive en memoria, y APP-02 verifica que el contenido no sale en la trazabilidad. K-01 y K-03 están **abiertos**. Hasta la semana 15 los cubren controles **compensatorios**, CD-01 y CD-02, que detectan lo que el código todavía no impide.

## 4. Controles

### Preventivos (impiden que el problema ocurra)

| # | Control | Dónde vive | Riesgos | Responsable |
|---|---|---|---|---|
| CP-01 | Las reglas de liberación se escriben con la prueba primero, incluido el caso límite: sin clasificar, cero aplicables | Semana 15, en el Dominio | K-01, K-03 | Autor |
| CP-02 | **Una sola fuente** para lo que bloquea el GO: la trazabilidad usa el mismo cálculo que el cierre | Aplicación (semana 15) | K-02, K-11 | Autor |
| CP-03 | Cada caso de uso verifica su permiso antes de tocar datos (C-07 de la semana 13) | `Aplicacion/CasosDeUso` | K-04 | Autor |
| CP-04 | Las claves foráneas incluyen el tenant y los triggers impiden modificar intentos | `schema.sql` | K-04, K-06 | Autor |
| CP-05 | La evidencia clínica sin confirmar no se escribe en el almacenamiento del navegador | Prototipo (U-06) | K-05 | Autor |
| CP-06 | Idempotency-Key y almacén fail-closed | D-06, D-08 | K-06 | Autor |
| CP-07 | El README declara las extensiones (`mbstring`, `pdo_pgsql`) y el arranque las verifica con un mensaje claro | README y `bootstrap/` (semana 15) | K-08 | Autor; Administrador |
| CP-08 | RNF-09 (WCAG 2.2 AA) y RNF-10 (móvil) aprobados como requisitos | Requisitos del módulo | K-10 | Responsable de producto |

### Detectivos (encuentran el problema si ocurrió)

| # | Control | Cómo | Riesgos | Responsable | Frecuencia |
|---|---|---|---|---|---|
| **CD-01** | Defectos con CRÍTICA propuesta sin clasificar en campañas en curso | SQL en [controles-sqa.sql](herramientas/controles-sqa.sql) | K-01 | Líder QA | **Antes de cada cierre**, mientras RT-01 esté abierto |
| **CD-02** | Campañas cerradas con GO sin un solo intento aplicable | SQL | K-03 | Líder QA | Semanal |
| **CD-03** | Críticos abiertos de otra campaña sobre casos de una campaña en curso | SQL | K-02 | Líder QA | Antes de cada cierre |
| **CD-04** | Intentos cuya campaña y caso no son del mismo tenant | SQL | K-04 | Autor | Semanal |
| CD-05 | Tablero SQA: M-1..M-6 contra sus umbrales | `medir-sqa.php` (código de salida = métricas fuera) | Todos | Autor | Cada PR y semanal |
| CD-06 | Sonda de la revisión: los hallazgos que se siguen reproduciendo | `sonda-revision.php` | K-01, K-02, K-03, K-11 | Moderador | Cada PR hasta cerrarlos |
| CD-07 | Recorrido con axe-core y la comprobación «nada clínico en el almacenamiento» | `recorrido.cjs` | K-05, K-10 | Revisor de accesibilidad | Cada cambio de interfaz |
| CD-08 | Registro de acceso en JSON con `X-Request-ID`: los 5xx y los 403 por tenant se revisan | `RegistroAcceso` (semana 5) | K-04, K-08 | Administrador | Diario en producción |

**Los controles SQL se probaron a sí mismos** ([controles-1-contraprueba.txt](evidencia/controles-1-contraprueba.txt)). Dentro de una transacción se sembró un caso positivo de CD-01, CD-02 y CD-03; cada control devolvió su fila, y después del `ROLLBACK` los cuatro devolvieron 0 filas. CD-03 encontró además la campaña de carga de M-5, que también tenía TC-107 en su alcance: así se comporta un control que mira todas las campañas en curso. CD-04 no se puede sembrar porque las claves foráneas lo impiden: es una segunda barrera por si alguna se elimina.

## 5. Calendario de seguimiento

| Cuándo | Qué | Quién | Salida |
|---|---|---|---|
| **Cada PR** | M-1, M-3, M-4 (`medir-sqa.php --rapido`) | Autor | Si el código de salida no es 0, no se mergea, salvo una excepción firmada |
| **Antes de cada cierre de campaña** | CD-01 y CD-03 | Líder QA | Clasificar lo pendiente antes de cerrar |
| **Semanal** (lunes) | Tablero completo (M-1..M-6), mutaciones, CD-02 y CD-04 | Autor | Tablero guardado en `evidencia/` con la fecha |
| **Cada cambio de interfaz** | Recorrido con axe-core (M-6) | Revisor de accesibilidad | Capturas y salida |
| **Mensual** | M-5 en PostgreSQL; revisión de la matriz de riesgos | Autor y Líder QA | Riesgos recalificados |
| **Antes de cada release del módulo** | Revisión formal (semana 13) y todos los criterios de salida | Moderador | Aceptado / no aceptado |

| Fecha | Hito |
|---|---|
| 2026-10-29 (semana 14) | Línea base (§6) |
| Semana 15 | Retrabajo de RT-01, RT-02, RT-05 y RT-06 con la prueba primero; M-3 a 15/15; CP-07. **Se espera M-4 = 0 y 6/6 métricas en umbral** |
| Semana 15 + 1 | Re-revisión de lo que no cerró; si M-4 = 0, CD-01 deja de ser obligatorio antes del cierre |
| Mensual desde entonces | Matriz de riesgos y M-5 |

## 6. Línea base (2026-10-29)

[tablero-1-linea-base.txt](evidencia/tablero-1-linea-base.txt):

| # | Valor | Estado |
|---|---|---|
| M-1 | 100/100 con PostgreSQL | ✅ |
| M-2 | 35/35 (corrida del mismo día, [mutaciones.txt](evidencia/mutaciones.txt)) | ✅ |
| M-3 | **12/15: R-13, R-14 y R-15 no aparecen en ninguna descripción** | ❌ |
| M-4 | **1 crítico (RT-01)** y 3 mayores | ❌ |
| M-5 | p95 103 ms en memoria y 140 ms en PostgreSQL, con 500 casos | ✅ (RNF-08 cumplido con margen) |
| M-6 | 0 violaciones, 0 de 56 comprobaciones fallan | ✅ |

**Lo que dijo la primera medición.**
- **M-3 encontró un hueco que nadie había notado.** Las reglas R-13 (permisos), R-14 (traza) y R-15 (MAYOR en caso crítico) **sí** tienen pruebas: APP-09, la traza de APP-01 y CAM-01..05. Pero sus descripciones no nombran la regla; R-15 solo aparece en el nombre del grupo y en un comentario. Para una persona da igual, pero ninguna herramienta puede rastrear esas reglas hasta sus pruebas, y M-3 existe justamente para que ese rastreo sea automático. Se corrige en la semana 15, junto con las pruebas nuevas.
- **M-4 confirma la semana 13:** con M-1 y M-2 al 100 %, el módulo **no** está listo para liberarse.
- **M-5** cierra RNF-08, que quedó abierto desde la semana 2: medido, no supuesto.

## 7. Cómo verificarlo

```bash
cd docs/modulos/qa-regresion-defectos/semana-14
QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php herramientas/medir-sqa.php   # 4 de 6 en umbral; código de salida 2
psql -h 127.0.0.1 -p 55432 -d micro_his_qa -f herramientas/validar-controles.sql                  # cada control encuentra su positivo sembrado
```
