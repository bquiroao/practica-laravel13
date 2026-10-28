# Semana 9 — Evaluación de UX, usabilidad y accesibilidad

**Módulo:** Gestión de usuarios, tenants y endurecimiento de autenticación.
**Estudiante:** Lopez Perez, Mercedes Azucena. **Fecha:** 28-10-2026.

Se evalúa el [flujo UX de semana 8](../semana-08/README.md), commit fb3ed3f: alta por invitación en tenant, credencial propia, autenticación y revocación de sesión. La base tiene seis SVG anotados. Esta es una inspección experta de diseño, no prueba con usuarios, auditoría de aplicación operativa ni certificación WCAG. No se modifica el backend ni se demuestra verificación JWT, revocación o aislamiento real.

## Método y evidencia

1. Revisar flujo de administrador, invitado y usuario con sesión propia; distinguir acciones autorizadas por servidor de contexto visible en cliente.
2. Aplicar heurísticas de visibilidad, control del usuario, consistencia, prevención de errores y recuperación.
3. Mapear criterios WCAG 2.2 a lo observable y registrar pendientes de implementación. Referencia primaria: [W3C, guía de criterios WCAG](https://www.w3.org/WAI/WCAG22/quickref/), consultada 28-10-2026.
4. Abrir los seis SVG en Edge headless con Playwright, capturar 1100 × 700, medir dos pares de colores y detectar texto fuera del borde derecho del lienzo. Evidencia reproducible en [inspeccion.json](evidencia/inspeccion.json).

Resultados: seis imágenes capturadas; cada SVG tiene cero controles HTML o tabindex, cero textos cuyo borde derecho supere 1100 px; cabecera blanca/fondo #153e49: 11.56:1; título de anotación/fondo #f2f6f7: 10.87:1. Estas dos medidas no validan todos los colores/estados ni contraste de controles futuros. No se prueba Tab útil en dibujos estáticos ni se atribuye un fallo del backend por carecer de DOM interactivo.

## Checklist completado

**D:** evidencia favorable de diseño; **H:** hallazgo de diseño/entrega; **P:** pendiente de interfaz operativa. D no significa conformidad WCAG de producto.

| # | Revisión / referencia | Resultado y evidencia |
|---|---|---|
| 1 | Contexto y visibilidad de estado (heurística) | D: WF01/02/05 muestran H-01 y rol/contexto; autorización sigue siendo de servidor. |
| 2 | Control y cancelación (heurística) | D parcial: WF02 editar/cancelar, WF05 cancelar; H03 falta navegación operable. |
| 3 | Teclado 2.1.1 | P/H01: cero controles en SVG; especificar recorrido en implementación. |
| 4 | Orden de foco 2.4.3 | P/H03: no hay recorrido observable; anotación general no define confirmación y retorno. |
| 5 | Foco visible/no oculto 2.4.7/2.4.11 | P/H03: ningún estado enfocado dibujado o medido. |
| 6 | Etiquetas/relaciones 1.3.1, 3.3.2, 4.1.2 | H01: etiquetas visuales claras pero controles son texto, sin asociación programática. |
| 7 | Contraste 1.4.3 | D parcial: dos pares ≥4.5:1; resto/estados de controles P. No se registra fallo inexistente. |
| 8 | Reflujo/zoom 1.4.10/1.4.4 | H02: lienzo fijo 1100 × 700; móvil descrito pero no representado. |
| 9 | Identificación/sugerencia de error 3.3.1/3.3.3 | H04: WF02 no muestra ejemplo de campo inválido ni resumen asociado. |
| 10 | Mensajes de estado 4.1.3 | P/H05: carga/error de WF04 coexisten en dibujo; sin mecanismo de anuncio definido por estado. |
| 11 | Autenticación accesible 3.3.8 | H06: no se especifica pegado/autocompletado/gestor; no implica bloqueo ya implementado. |
| 12 | Prevención y confirmación (heurística) | D parcial: revisar invitación y confirmar cierre; H07 recuperación con acción ambigua. |
| 13 | Consistencia de lenguaje/roles (heurística) | H08: TECH no se explica al administrador en WF02; posible asignación incorrecta. |
| 14 | Privacidad/aislamiento (regla de módulo) | D de intención: contexto H-01, mensajes genéricos y sin bearer; validación multitenant/JWT P. |
| 15 | Revocación honesta/recuperación (heurística) | D de intención: WF06 no promete cierre; H07 variantes combinadas; persistencia y denegación real P. |

## Hallazgos y backlog priorizado

P1: necesario antes de un piloto con interacción real; P2: necesario antes de aceptar el diseño para implementación. Todos abiertos, responsable propuesta: autora; revisión de accesibilidad/seguridad por asignar. Los ocho hallazgos son brechas observables del diseño o evidencia faltante, no ocho incumplimientos WCAG probados en producción.

| ID / prioridad | Evidencia y reproducción | Impacto en módulo | Corrección propuesta y criterio verificable |
|---|---|---|---|
| H01 / P1 | [WF02](evidencia/02-invitacion.png), [WF04](evidencia/04-login.png); inspeccionar SVG: 0 input/button/tabindex. | Usuario de teclado/lector no puede invitar o iniciar sesión en esta entrega gráfica. | Prototipo con form/label/input/button nativos, nombres accesibles. Verificar Tab y Enter completan invitación/login, lector anuncia etiqueta, rol y error; sin tratar SVG como interfaz operativa. |
| H02 / P1 | Cualquier WF tiene width=1100 height=700; columna de anotaciones x=756. | Reducción de imagen en teléfono hace pequeño el contexto de tenant, rol y acciones. No hay variante móvil verificable. | Columna única sin anotaciones técnicas en flujo final; a 320/375/430 CSS px y zoom 200 %, contexto/acción visibles y sin scroll horizontal de contenido ordinario. |
| H03 / P1 | [WF05](evidencia/05-sesion.png), confirmación en texto sin foco, Escape o retorno dibujados. | Cierre accidental o pérdida del contexto al cancelar; orden de foco sin probar. | Definir foco inicial en cancelar, ciclo dentro del diálogo modal si se usa, Escape cancela, retorno al disparador, foco visible sin superposición. Verificar solo teclado y captura de estados. |
| H04 / P1 | [WF02](evidencia/02-invitacion.png), campos válidos y resumen; ningún ejemplo de error de correo/rol. | Administrador no sabe qué corregir, puede perder borrador no sensible o cambiar rol/tenant al reintentar. | Mostrar resumen y error asociado por aria-describedby/aria-invalid; conservar correo/nombre/rol permitido, jamás secretos; servidor rechaza rol no autorizado sin enumerar hospitales. Probar correo inválido y rol manipulado; corregir sin duplicar invitación. |
| H05 / P1 | [WF04](evidencia/04-login.png) muestra error y “Cargando” simultáneos, sin variantes separadas. | Confusión sobre si hay una solicitud activa y riesgo de repetición de autenticación. | Separar edición/cargando/rechazo/indisponible/éxito. Región de estado para espera y resultado; botón evita doble envío y recupera disponibilidad tras error. Verificar un solo envío, anuncio por cambio y ausencia de mensaje de error durante éxito confirmado. |
| H06 / P1 | [WF03](evidencia/03-credencial.png) ayuda “política vigente” sin requisitos concretos; WF04 no define autocomplete/pegado. | Mayor esfuerzo de credencial, exclusión de gestores y errores evitables; no hay evidencia de bloqueo actual. | Política real aprobada junto al campo, permitir pegar y gestores; autocomplete username/current-password/new-password según pantalla. Mostrar contraseña es acción explícita con estado anunciado. Probar pegado/gestor/mostrar sin copiar secretos a log ni localStorage. |
| H07 / P1 | [WF06](evidencia/06-recuperacion.png) reúne invitación vencida, sesión expirada y revocación desconocida; acción “Comprobar / reintentar según estado”. | Usuario no sabe si cerró sesión; podría repetir una operación con resultado desconocido. | Pantallas/variantes por causa, una acción principal concreta. Desconocido: “Comprobar estado”; fallo confirmado: “Reintentar cierre”; expirada: “Iniciar sesión”. Simular cada respuesta y verificar sin cierre falso ni fallback de acceso. |
| H08 / P2 | [WF02](evidencia/02-invitacion.png) rol “TECH · permitido” sin descripción de facultades. | Asignación incorrecta de permisos y carga cognitiva al invitar. | Etiqueta legible “Técnico de laboratorio” con ayuda de alcance; resumen de tenant/rol antes de confirmar. Verificar que el resumen coincide con payload permitido y 403 no revela roles/tenants ajenos. |

## Orden de corrección y aceptación

H01 → H02/H03 → H04/H05/H06 → H07/H08. Corregir en prototipo futuro; esta semana conserva la línea base para que la evidencia siga siendo reproducible. Validar nuevamente todas las transiciones, no cerrar ítems por escribir la propuesta. Entrada a revisión: seis pantallas y ramas de error; salida: criterios del backlog ejecutados en interfaz real, hallazgos críticos resueltos y pendientes aceptados por responsable identificado.

Escenario administrador H-01: invitar con correo inválido, corregir, revisar rol legible, cancelar sin envío, confirmar una vez y ver invitación pendiente; intento de H-02 debe denegarse sin lista ajena. Escenario usuario: login rechazado genérico, servicio indisponible, login válido, cancelar cierre, revocación desconocida, comprobar y solo entonces cierre confirmado. Son guiones de verificación futura, no resultados ejecutados de API/JWT.

## Trazabilidad

| Reglas heredadas del módulo | Hallazgos / evidencia |
|---|---|
| Alta, tenant y atomicidad RF-01..04 | H01/H04/H08, WF01/02/03 |
| Autenticación y error genérico RF-05/06 | H05/H06, WF04 |
| Sesión contextual RF-07 | H02/H03, WF05 |
| Revocación/fallo explícito RF-08..10 | H03/H07, WF05/06 |
| Confidencialidad RNF-02/03 | H04/H06/H07, sin bearer en capturas |
| Integridad/aislamiento/cierre RNF-04..06 | H04/H07/H08, verificaciones de servidor pendientes |
| Testabilidad y alcance honesto RNF-07/08 | Script y resultados técnicos; sin certificar producto |

[Comandos y límites de reproducción](evidencia/README.md). No se aplican correcciones al backend ni a semanas de otros módulos. La evaluación de accesibilidad de interfaz tampoco sustituye las pruebas de seguridad del módulo.