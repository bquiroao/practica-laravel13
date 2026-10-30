# Semana 1 — Alertas críticas y notificaciones internas

**Proceso modelado:** detección, envío, acuse y escalamiento de una alerta crítica.
**Estudiante:** Erick Rolando Ramazzini Muralles (@ErickRamazzini) · ASII 2026, UMG.
**Entregable:** UML de casos de uso, actividad y secuencia con trazabilidad entre ellos, más la narrativa de alcance.

## 1. Narrativa de alcance

Cuando otro módulo del hospital detecta una situación que no puede esperar (por ejemplo, un potasio sérico de 6.8 mmol/L del paciente `PAC-00451`), alguien del personal debe enterarse a tiempo y dejar constancia de que lo hizo. Hoy eso se resuelve con llamadas sin registro. Este módulo **recibe** el evento crítico, **avisa** al personal interno responsable, **registra el acuse** y, si nadie acusa en el plazo, **escala** al siguiente nivel jerárquico. Todo queda en un historial: quién, qué y cuándo.

**Objetivo:** que ninguna alerta crítica quede sin un responsable que la acuse, y que el hospital pueda demostrar cuánto tardó la respuesta.

### Dentro del alcance
- Recibir un evento crítico de un sistema origen y crear la alerta (sin duplicarla si llega dos veces).
- Resolver destinatarios por rol y servicio, según el nivel de escalamiento.
- Enviar la notificación interna por un **puerto de notificación**. En este curso el adaptador es **simulado**: registra lo «enviado» y **no se envían correos, SMS ni push reales**.
- Reintentar una entrega fallida (máximo 3 intentos por destinatario).
- Registrar el acuse (idempotente) y cerrar la alerta con una nota.
- Escalar por tiempo (plazo según prioridad) hasta el nivel máximo.
- Consultar el historial de cada alerta y configurar las reglas de escalamiento.

### Fuera del alcance
- Calcular o validar resultados clínicos (eso es de `ingreso-resultados-laboratorio`).
- Pintar la alerta visual en el expediente o guardar alergias (módulo de alergias clínicas).
- Escribir en el expediente (`expediente-medico`) o en datos demográficos (`pacientes`).
- Administrar roles y permisos (`rbac`): este módulo solo los **consulta**.
- Canales de envío reales (correo, SMS, push) e infraestructura de colas.

## 2. Frontera con módulos vecinos (propuesta, por confirmar con cada dueño)

| Módulo vecino | Qué entrega o consulto | Estado |
|---|---|---|
| `ingreso-resultados-laboratorio` | Evento «valor crítico» con `id_origen`, `codigo_paciente`, prueba, valor y prioridad | Contrato por confirmar |
| Alergias clínicas (alerta visual) | Evento «alergia grave registrada» | Contrato por confirmar |
| `expediente-medico` | Referencia de solo lectura al episodio | Por confirmar |
| `pacientes` | Solo el código ficticio del paciente | Por confirmar |
| `rbac` | Rol, permisos y tenant del usuario autenticado | Por confirmar |

## 3. Actores

| Actor | Tipo | Objetivo en el proceso |
|---|---|---|
| Sistema origen (laboratorio / alergias) | Sistema externo | Entregar el evento crítico (CU-01) |
| Enfermería de turno | Humano, nivel 0 | Acusar la alerta (CU-04) y ver su historial (CU-07) |
| Médico tratante | Humano, nivel 0 | Acusar (CU-04), cerrar con nota (CU-06) y ver historial (CU-07) |
| Jefe de servicio | Humano, nivel 1 | Recibir el escalamiento, acusar, cerrar y ver historial |
| Coordinación médica de turno | Humano, nivel 2 | Último nivel de escalamiento; acusar (CU-04) |
| Administrador de reglas de alerta | Humano | Configurar plazos y destinatarios (CU-08) |
| Reloj del sistema (planificador) | Temporal | Disparar la revisión de plazos vencidos (CU-05) |

## 4. Ciclo de vida y reglas que representan los diagramas

Estados: `CREADA → ENVIADA → ACUSADA → CERRADA`. Desde `ENVIADA` pasa a `ESCALADA` si vence el plazo sin acuse; una alerta `ESCALADA` todavía puede acusarse.

| ID | Regla (se formaliza en la semana 2) |
|---|---|
| RN-01 | Toda alerta exige tenant, código de paciente, origen, id de origen, prioridad y descripción; si falta algo, se rechaza. |
| RN-02 | El mismo origen + id de origen en el mismo tenant no crea una segunda alerta. |
| RN-03 | Si no hay destinatarios disponibles en el nivel actual, se pasa al siguiente nivel. |
| RN-04 | Plazo de acuse según prioridad: `CRITICA` 15 min, `ALTA` 30 min. |
| RN-05 | Vencido el plazo sin acuse, la alerta sube un nivel (máximo nivel 2); en el máximo se registra «escalamiento agotado». |
| RN-06 | El acuse es idempotente: acusar dos veces no duplica registros, y una alerta acusada no se escala. |
| RN-07 | Solo acusa un destinatario notificado, del mismo tenant y con rol autorizado. |
| RN-08 | Hasta 3 intentos de entrega por destinatario; agotados, se escala de inmediato. |
| RN-09 | Solo se cierra una alerta acusada, y con nota obligatoria. |
| RN-10 | El historial solo crece: cada transición guarda evento, actor y momento. |

## 5. Diagramas

| Diagrama | Archivo |
|---|---|
| Casos de uso | `diagramas/casos-de-uso.puml` / `.png` |
| Actividad (decisiones, excepciones y resultado) | `diagramas/actividad-alerta-critica.puml` / `.png` |
| Secuencia (participantes, mensajes, validaciones y respuesta) | `diagramas/secuencia-alerta-critica.puml` / `.png` |

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.

## 6. Trazabilidad entre los tres diagramas

| Caso de uso | Actividad (acción o decisión) | Secuencia (mensajes) | Reglas |
|---|---|---|---|
| CU-01 Registrar alerta crítica | Validación de datos, evento duplicado, «Crea alerta en estado CREADA» | 1–11 | RN-01, RN-02 |
| CU-02 Resolver destinatarios | «Resuelve destinatarios de nivel 0», ¿hay destinatarios? | 12–13, 34 | RN-03 |
| CU-03 Enviar notificación interna | «Envía por el puerto de notificación» | 14–20, 35 | — |
| CU-09 Reintentar entrega fallida | Bucle «¿Entrega fallida y intentos < 3?» | `loop` 15–16 | RN-08 |
| CU-04 Acusar alerta | Decisión «¿Acusa antes de que venza el plazo?» → sí | 21–30 | RN-06, RN-07 |
| CU-05 Escalar alerta no acusada | Rama «vence el plazo», ¿nivel < 2? | 31–37 | RN-04, RN-05 |
| CU-06 Cerrar alerta | «Atiende y cierra con nota» | (se detalla en semana 2) | RN-09 |
| CU-07 Consultar historial | Resultado «alerta atendida con historial completo» | (se detalla en semana 2) | RN-10 |
| CU-08 Configurar regla de escalamiento | Plazos usados en «inicia plazo de acuse» | (fuera del flujo principal) | RN-04 |

Los nombres de la secuencia (`RegistrarAlertaCritica`, `AcusarAlerta`, `EscalarAlertasVencidas`, `DespachadorNotificaciones`, `PuertoNotificacion`, `AlertaRepository`) son los que usará el Micro-HIS desde la semana 3.

## 7. Datos ficticios usados

- Tenant `HOSP-GUA-01`, servicio `MED-INT`, paciente `PAC-00451`.
- Usuarios: `enf.turno01`, `med.tratante01`, `jefe.servicio01`, `coord.medico01`, `admin.alertas01`.
- No hay nombres reales de pacientes ni de personal.
