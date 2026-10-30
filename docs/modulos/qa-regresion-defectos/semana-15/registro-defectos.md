# Registro de defectos — Semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión

Los defectos del propio módulo se llevan con **las mismas reglas que el módulo exige** a los demás:
- **NUEVO → ABIERTO:** al clasificarlo, con una severidad.
- **ABIERTO → CORREGIDO:** con el build de la corrección.
- **CORREGIDO → CERRADO:** solo con una re-ejecución que pasa. La re-ejecución es la prueba que fallaba antes de corregir.

## 1. Defectos del producto (de la revisión de la semana 13)

| ID | Origen | Severidad | Descripción | Prueba que lo reproduce (rojo) | Corrección | Estado |
|---|---|---|---|---|---|---|
| **DEF-01** | RT-01 | **Crítica** | Un defecto con CRÍTICA propuesta y sin clasificar no bloqueaba el GO | UNI-01, CB-02 (filas a, i), CN-05; CON-06 corregido | `Defecto::severidadEfectiva()` (la clasificada, o la propuesta); SQL con `COALESCE(severidad, severidad_propuesta)` | **CERRADO**: 8 de 8 en verde; mutaciones M36 y M40 detectadas |
| **DEF-02** | RT-02 | Mayor | La trazabilidad no mostraba los defectos abiertos de otras campañas que el cierre sí contaba | CB-03, CN-05 | Puerto `abiertosDe()` (PDO y memoria) y trazabilidad = los de esta campaña ∪ los abiertos de sus casos, con `campana` en cada defecto (contrato 1.2.0) | **CERRADO**: M39 detectada |
| **DEF-03** | RT-05 | Mayor | Con todo NO_APLICA la campaña se liberaba con GO y tasa 100 % | UNI-02, CB-01 (fila r4) | `Campana::cerrar`: sin casos aplicables, NO_GO con su propia causa y tasa 0 | **CERRADO**: M37 detectada |
| **DEF-04** | RT-06 | Menor | CORREGIDO aceptaba cualquier texto como build | UNI-03 | `Campana::FORMATO_BUILD`, compartido por iniciar y corregir | **CERRADO**: M38 detectada |
| **DEF-05** | RT-07 | Menor | El Dominio usa `mbstring` y el README no lo declaraba | — (inspección) | README con las extensiones y un control al arrancar el contenedor | **CERRADO**: verificado por inspección y con `php -m` |
| DEF-06 | RT-04 | Mayor | CA-02: editar un caso aprobado no crea la versión siguiente: no hay operación | Sonda RT-04 | Es una funcionalidad nueva (operación, contrato, permisos); no entra como corrección | **ABIERTO**, agendado después del curso |
| DEF-07 | RT-03 | Menor | La trazabilidad usa los requisitos de la última versión del caso, no los de la congelada | Sonda RT-03 | Depende de DEF-06: hasta que exista una v2 no puede ocurrir | **ABIERTO**, junto con DEF-06 |
| DEF-08 | H-2 (semana 12) | Menor | RF-11: los requisitos sin caso no se listan | — | Necesita el alcance de la matriz en la trazabilidad | **ABIERTO** |

**Una decisión que se invirtió (DEF-01).** CON-06 decía «NUEVO no cuenta como crítico»: era una decisión de la semana 4, escrita en la prueba de contrato, para que solo contara lo que el Líder QA confirmara. Esa decisión **es** RT-01. Ahora, ante la duda, el sistema se equivoca hacia NO-GO. Si un tester exagera la severidad, el Líder QA la baja con un motivo obligatorio (R-07, queda en el historial). Si nadie la revisa, la campaña no se libera.

## 2. Defectos de las pruebas

Las pruebas también tienen defectos. Estos se encontraron al correrlas por primera vez ([pruebas-0-errores-en-las-pruebas.txt](evidencia/pruebas-0-errores-en-las-pruebas.txt)), antes de tocar el código:

| ID | Prueba | Defecto | Efecto | Corrección |
|---|---|---|---|---|
| DP-01 | UNI-03 | `"«$build»"`: PHP toma `»` como parte del nombre de la variable | El mensaje decía «build «» y ocultaba qué valor fallaba | `{$build}` |
| DP-02 | CN-01 | La función flecha captura `$clave` por valor: todas las peticiones llevaban la misma Idempotency-Key | 409 CLAVE_REUTILIZADA en vez de 201: un **falso rojo** | Clausura con `use (&$clave)` |
| DP-03 | CN-02 | Las claves `0.5` y `0.5001` de un arreglo PHP se convierten al entero 0 | La campaña no se podía planificar (umbral 0): otro falso rojo | Lista de pares |
| DP-04 | CN-01 | El **oráculo** esperaba 400 para un resultado fuera del enum; el diseño (D-13) dice 422: el formulario valida tipos y el dominio valida valores | Habría marcado como defecto un comportamiento correcto | Dos clases: tipo equivocado → 400, valor fuera del enum → 422 |
| DP-05 | CON-06 | La prueba de contrato **afirmaba el defecto** RT-01 como regla | Mientras existió, corregir RT-01 «rompía» la suite | Se reescribió la aserción, con un comentario que explica por qué |

DP-02 y DP-03 habrían dado un rojo por la razón equivocada: una prueba en rojo solo sirve si falla **por el defecto que busca**. Por eso el rojo que cuenta es [pruebas-1-antes-rojo.txt](evidencia/pruebas-1-antes-rojo.txt), donde cada falla dice el método que falta o el valor que difiere.

## 3. Efecto en el plan SQA (semana 14)

| Métrica | Línea base (semana 14) | Después (semana 15) |
|---|---|---|
| M-1 Pruebas en verde | 100/100 | **112/112** |
| M-2 Mutaciones | 35/35 | **40/40** |
| M-3 Reglas con prueba con nombre | 12/15 | **15/15** |
| M-4 Hallazgos críticos abiertos | 1 (RT-01) | **0** |
| M-5 RNF-08 | p95 140 ms | p95 181 ms ([tablero-2-despues.txt](evidencia/tablero-2-despues.txt): **6 de 6 métricas en umbral**) |
| M-6 Accesibilidad | 0 violaciones | 0 violaciones |

Con M-4 en 0, el control compensatorio CD-01 deja de ser obligatorio antes de cada cierre (plan SQA, §5).
