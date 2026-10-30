# Semana 8 — Diseño de experiencia de usuario del módulo QA y regresión

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Tarea** | Diseñar el flujo de usuario de cada rol autorizado: inicio, decisión, confirmación, estados vacío/carga/éxito, errores recuperables, ayuda contextual y protección de datos |
| **Entregable** | User flow por rol y 4–6 wireframes anotados |

## Entregables

| Entregable | Archivo |
|---|---|
| User flow · Tester | [semana-08-flujo-tester.png](diagramas/semana-08-flujo-tester.png) |
| User flow · Líder QA | [semana-08-flujo-lider.png](diagramas/semana-08-flujo-lider.png) |
| User flow · Desarrollador | [semana-08-flujo-desarrollador.png](diagramas/semana-08-flujo-desarrollador.png) |
| User flow · Analista QA y Responsable de producto | [semana-08-flujo-analista-producto.png](diagramas/semana-08-flujo-analista-producto.png) |
| 5 wireframes anotados (W1–W5), con notas | [PPTX](wireframes/semana-08-wireframes-qa-regresion.pptx) · [PDF](wireframes/semana-08-wireframes-qa-regresion.pdf) |

## 1. Alcance y punto de partida

La interfaz consume el contrato de la semana 5 ([openapi.yaml](../micro-his-qa/api/openapi.yaml) 1.1.0) a través de `ClienteQa`, y respeta los componentes F1–F6 de la semana 7. Hay tres reglas que la UI **no** toma, porque las decide el servidor:
- si un resultado necesita evidencia;
- si la campaña puede iniciar;
- la decisión GO / NO-GO.

La UI las **anticipa** para ayudar, y siempre muestra lo que el servidor respondió.

## 2. Roles autorizados y lo que ve cada uno

| Rol | Permisos | Pantallas | Acciones que NO aparecen |
|---|---|---|---|
| Tester | `qa.ejecutar`, `qa.consultar` | W2, W3, W5 en lectura | Planificar, clasificar, cerrar |
| Líder QA | `qa.aprobar`, `planificar`, `clasificar`, `cerrar`, `consultar`, `ver_evidencia_clinica` | W1, W4 (clasificar), W5 | Registrar resultados |
| Desarrollador | `qa.corregir`, `qa.consultar` | W4 (corregir) | **Cerrar defecto**, cambiar severidad (R-08) |
| Analista QA | `qa.disenar`, `qa.consultar` | Diseño de casos (flujo), W1 en lectura | Aprobar su propio caso (R-02) |
| Responsable de producto | `qa.consultar` | W5 en lectura | Todas las de escritura |

**Regla de la UI:** no se muestra una acción que el rol no tiene. Aun así, el servidor responde 403 a cualquiera que la intente (M-21).

## 3. Principios de diseño

1. **Lo que pasó no se edita.** No hay «editar resultado» en ninguna pantalla; la confirmación lo dice antes de registrar (W2-7).
2. **Nunca solo color.** Cada estado lleva palabra e icono o forma: ✓ GO, ⛔ NO_GO, ● FALLA, 🔒 datos clínicos.
3. **El error dice qué hacer.** Cada 422 trae `detalle` y la UI marca **esos** campos (W3-2).
4. **Nada se pierde.** Ni lo escrito ante un error, ni un resultado sin conexión: el resultado queda PENDIENTE y se reenvía con la misma clave (W3-3).
5. **La decisión se anticipa.** `bloquea_go` muestra antes de cerrar qué impide el GO (W5-1).

## 4. User flows por rol

| Rol | Inicio | Decisión | Confirmación | Vacío / carga / éxito | Errores recuperables |
|---|---|---|---|---|---|
| [Tester](diagramas/semana-08-flujo-tester.png) | «Mis campañas en curso» → caso pendiente | Resultado (4 opciones); ¿verifica un defecto CORREGIDO? | Resumen antes de registrar | Carga con esqueleto; éxito con el siguiente caso | 422/413 (campo marcado), 409 (campaña cerrada), 503/sin red → PENDIENTE |
| [Líder QA](diagramas/semana-08-flujo-lider.png) | «Campañas» | Casos opcionales; severidad; cerrar o no | Planificar; cerrar (irreversible) | «Aún no hay campañas»; GO / NO-GO | 422 ALCANCE_INCOMPLETO con «agregar los que faltan»; 409 pendientes |
| [Desarrollador](diagramas/semana-08-flujo-desarrollador.png) | «Defectos de mi equipo» | ¿Está corregido y en qué build? | «Marcar CORREGIDO no lo cierra» | «No hay defectos abiertos»; CORREGIDO | 409 (otro ya lo marcó); 403 |
| [Analista / Producto](diagramas/semana-08-flujo-analista-producto.png) | «Casos» / «Campañas» | Guardar borrador / liberar o no | Guardar | BORRADOR «debe aprobarlo otro» | 409 CASO_EXISTENTE con enlace; 422 por campo |

## 5. Wireframes anotados

Cada wireframe tiene llamadas numeradas con su tipo (estado, mensaje, validación, interacción, datos, ayuda, decisión, error recuperable, éxito) y notas del orador.

| # | Pantalla | Rol | Cubre |
|---|---|---|---|
| W1 | Campañas: lista, planificar desde la matriz, iniciar | Líder QA | Inicio, decisión (críticos bloqueados), error 422 recuperable, vacío/carga/sin permiso |
| W2 | Ejecutar un caso y registrar resultado + evidencia + defecto | Tester | Decisión del resultado, validación del archivo, datos clínicos, ayuda de severidad, confirmación |
| W3 | Después de «Registrar»: éxito, 422, PENDIENTE, 409, 413, 5xx | Tester | Éxito, errores recuperables, bandeja de pendientes, código de soporte |
| W4 | Defecto: clasificar (Líder) y marcar corregido (Desarrollador) | Líder QA, Desarrollador | Validación del motivo, evidencia clínica protegida, acciones por rol |
| W5 | Trazabilidad y cierre | Líder QA, Producto | Decisión anticipada (`bloquea_go`), confirmación irreversible, pendientes |

## 6. Catálogo de mensajes

| Id | Cuándo | Texto | Tipo |
|---|---|---|---|
| M-01 | Sin campañas | «Aún no hay campañas.» [Planificar la primera] | Vacío |
| M-02 | Cargando | «Cargando campañas…» / «Cargando caso…» con esqueleto | Carga |
| M-03 | 422 ALCANCE_INCOMPLETO | «No se puede iniciar: faltan TC-107 y TC-108, críticos para «expediente».» [Agregar los que faltan] | Error recuperable |
| M-04 | 422 al diseñar | Error junto a cada campo del `detalle` | Validación |
| M-05 | 201 caso | «Guardado como borrador. Un Líder QA distinto de usted debe aprobarlo.» | Éxito |
| M-06 | 409 CASO_EXISTENTE | «TC-200 ya existe.» [Abrir TC-200] | Error recuperable |
| M-07 | PASA en un caso con defecto CORREGIDO | «¿Esta ejecución verifica el defecto #12?» [Sí, verifica] | Ayuda / decisión |
| M-08 | 201 resultado | «Registrado: intento #1 de TC-107 · defecto #12 creado.» [Siguiente caso →] | Éxito |
| M-09 | 422 EVIDENCIA_REQUERIDA / DEFECTO_REQUERIDO / MOTIVO_REQUERIDO | «Falta la evidencia: una FALLA necesita al menos una captura, log o JUnit.» (y equivalentes) | Error recuperable |
| M-10 | 413 | «La captura pesa 7,2 MB; el máximo es 5 MB.» | Error recuperable |
| M-11 | 409 CAMPANA_NO_ADMITE_RESULTADOS | «La campaña ya no admite resultados (se cerró a las 16:05).» | Error recuperable |
| M-12 | Sin respuesta / 503 | «No se pudo confirmar. Se guardó en este equipo y se reenviará solo.» | Pendiente |
| M-13 | «?» de severidad | CRÍTICA, MAYOR, MENOR y TRIVIAL con un ejemplo cada una | Ayuda |
| M-14 | Cambio de severidad sin motivo | «Escriba por qué la severidad no es MAYOR.» | Validación |
| M-15 | 409 CASOS_PENDIENTES | «Faltan resultados de TC-110.» [Ir a TC-110] | Error recuperable |
| M-16 | NO_GO | Las causas en palabras (críticos, MAYOR en críticos, tasa) | Decisión |
| M-17 | Sin defectos | «No hay defectos abiertos.» | Vacío |
| M-18 | Evidencia clínica sin permiso | «Contenido protegido: solo huella y tipo.» | Datos |
| M-19 | 200 corregido | «Marcado CORREGIDO en 1.4.1. QA lo verificará re-ejecutando TC-107.» | Éxito |
| M-20 | 409 estado del defecto | «El defecto no está abierto (otro ya lo marcó).» Se recarga. | Error recuperable |
| M-21 | 403 | «No tiene permiso para esta acción en este hospital.» | Error |
| M-22 | 401 | «Su sesión expiró. Inicie sesión; lo escrito se conserva.» | Error recuperable |
| M-23 | 5xx | «No disponible. Código: a3f9c2e1b07d4e55» (el `X-Request-ID`) | Error |

## 7. Ayuda contextual

| Dónde | Ayuda |
|---|---|
| W1 «?» | Qué es un caso crítico de la matriz y por qué no se puede quitar del alcance |
| W2 «?» de defecto | Severidades con ejemplos; la severidad la confirma el Líder QA |
| W2, al elegir «Datos clínicos» | Quién podrá ver el contenido |
| W4 «?» | CRÍTICA exige pasos y evidencia (R-07); un cambio de severidad exige motivo |
| W5 ★ | Crítico **para las funcionalidades de esta campaña** |
| Botones deshabilitados | Explican por qué al pasar el cursor o al recibir el foco |

## 8. Protección de datos

- **Evidencia clínica.** En W4 y W5 solo se muestran el tipo, la huella y el tamaño. «Ver contenido» exige `qa.ver_evidencia_clinica` (R-13).
- **La bandeja de pendientes** guarda el resultado en el equipo del tester. Si una evidencia es `DATOS_CLINICOS`, se cifra y se borra al cerrar la sesión (la implementación es de las semanas 10–11). Cerrar la sesión con pendientes pide confirmación.
- **Nada clínico en la URL:** solo ids (`/qa/campanas/2/casos/TC-107`).
- **El 5xx muestra solo el código de soporte**, nunca detalles internos.
- **Un recurso de otro hospital se ve como inexistente** (404), igual que en la API.

## 9. Trazabilidad

| Requisito / regla | Dónde en la UX |
|---|---|
| R-02 aprobador ≠ autor | Flujo del Analista: M-05 |
| R-03 críticos de la matriz | W1-4, W1-5 |
| R-05 evidencia | W2-4, W3-2 |
| R-06 FALLA → defecto | W2 (formulario del defecto) |
| R-07 severidad | W2-6, W4-3/4 |
| R-08 solo QA cierra | W4-6, flujo del Desarrollador, M-07 |
| R-09, R-15 GO / NO-GO | W5-1, W5-3, W5-5 |
| R-11 inmutable | W2-7 |
| R-12 idempotencia | W3-3, W3-5 |
| R-13 datos clínicos | W2-5, W4-2 |

## 10. Validación y brecha

- El diseño se revisa con la checklist heurística y WCAG de la **semana 9**.
- La bandeja y los estados se prueban en un prototipo móvil (**semana 10**) y en uno navegable (**semana 11**).
- **Brecha conocida:** no hay una pantalla de «ver contenido de una evidencia» (CU-13). Se diseña en la semana 11 junto al permiso clínico.
