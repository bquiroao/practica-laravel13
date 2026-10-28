# Semana 13 — Plan de revisión técnica formal

**Estudiante / autora del módulo:** Lopez Perez, Mercedes Azucena (Azucena17)

**Módulo:** Investigación y prototipo NativePHP para extensión móvil/escritorio

**Proceso:** Evaluación y prototipo acotado de un flujo HIS mediante NativePHP

**Versión:** 1.0 · 27 de octubre de 2026 · Zona America/Guatemala

## 1. Objetivo, alcance y línea base

Preparar una revisión formal que encuentre defectos en reglas, contratos, seguridad de hospital/rol, integridad de la evaluación de viabilidad, capacidades nativas y sincronización. El flujo elegido es consultar una orden ficticia del HIS y notificar su estado; no incluye captura de resultados, validación bioquímica ni otros módulos.

Línea base de esta revisión: commit e83c98e de origin/develop usado para crear este worktree. Artefactos:

- [Semana 1: flujo y UML](../semana-01/semana-01-uml-investigacion-prototipo-nativephp.md).
- [Semana 2: RF/RNF, criterios e ISP](../semana-02/semana-02-isp-nativephp.md).
- [Caso de uso y puertos existentes](../semana-02/ejemplo-isp/despues/NativeCapabilities.php).
- [Adaptadores de demostración](../semana-02/ejemplo-isp/despues/InMemoryAdapters.php).
- [Verificación existente](../semana-02/ejemplo-isp/verificar.php).
- composer.json de la raíz, solo para comprobar dependencias y evitar confundir el scaffold Laravel con un runtime NativePHP instalado.

Las ramas independientes de semanas 7, 14 y 15 no forman parte de esta línea base hasta integrarse o seleccionarse expresamente como una nueva versión revisable. No se aprueban artefactos ausentes por referencia a trabajo futuro.

## 2. Roles e independencia

| Rol | Asignación | Responsabilidad |
|---|---|---|
| Autora | Mercedes Azucena Lopez Perez | Entregar artefactos, explicar alcance, corregir y adjuntar evidencia de retest. No aprobar su propia corrección. |
| Moderador | Por asignar por el equipo/docente | Verificar entrada, mantener foco y tiempos, arbitrar severidad y emitir acta con decisión de revisión. |
| Revisor técnico | Por asignar | Examinar reglas, interfaces, dependencias, errores y viabilidad; reproducir hallazgos. |
| Revisor de seguridad/integración | Por asignar; puede ser el mismo revisor si se registra | Revisar identidad, hospital, privacidad, permisos y sincronización; no asumir autorización por controles de UI. |
| Secretario | Moderador o integrante designado, registrado en el acta | Capturar hallazgos, responsables, plazos y decisiones sin modificar observaciones para aparentar aprobación. |

La autora puede preparar un autochequeo, pero no cuenta como revisión independiente. El apoyo de IA no sustituye la asignación ni firma de moderador/revisor. **Estado actual: revisión formal preparada, no celebrada ni aprobada.** No se enviaron convocatorias ni mensajes a terceros.

## 3. Criterios de entrada

1. Identificar commit exacto y listado de artefactos; no revisar código cambiante sin una línea base.
2. Contar con objetivo acotado, RF/RNF, criterios de aceptación y límites de lo demostrado.
3. Tener comando reproducible, entorno PHP registrado, salidas sin datos sensibles y defectos conocidos.
4. Disponer de contratos de entrada/salida y errores. Si falta una API real, declarar la ausencia y revisar solo puertos del prototipo; eso bloquea aprobación de integración real.
5. Registrar moderador y revisor independientes, disponibilidad y aceptación de roles antes de la sesión.
6. Enumerar destino/runtime NativePHP seleccionado y evidencia, o indicar explícitamente que la viabilidad nativa no está demostrada.

La preparación documental puede realizarse con roles pendientes; la sesión formal no comienza hasta cumplir 1–5. La condición 6 puede tener evidencia pendiente si el objetivo de la sesión es revisar el diseño, pero no permite concluir compatibilidad nativa.

## 4. Método y calendario propuesto

| Fase / fecha propuesta (Guatemala) | Actividad | Salida / responsable |
|---|---|---|
| Preparación · 27 oct | Congelar artefactos, comprobar comando y completar paquete | Autora: inventario y salidas. |
| Lectura individual · 28 oct | Revisores recorren checklist y registran hallazgos previos | Revisor asignado: evidencia por ítem; si no hay asignación, fase pendiente. |
| Sesión · 29 oct, 18:00–18:45 | 5 min alcance, 25 min reglas/contratos/seguridad, 10 min riesgos y 5 min decisión | Moderador y secretario: acta; no resolver código en vivo ni extender a otros módulos. |
| Correcciones · 30 oct, antes de 18:00 | Autora corrige hallazgos priorizados o documenta límites | Commit y retest por hallazgo. |
| Seguimiento · 30 oct, antes de 22:00 | Revisores confirman retest, estado y riesgo residual | Acta de cierre o solicitud de nueva revisión, antes del plazo académico de 23:59. |

El calendario es una propuesta, no una reunión confirmada. Si no se asignan participantes, se entrega el paquete preparado con sesión pendiente. El moderador reprograma sin inventar asistencia ni aprobación.

## 5. Checklist de 15 ítems

Estados al ejecutar revisión: **C** cumple con evidencia, **NC** no cumple, **P** pendiente y **NA** no aplica con justificación aprobada por moderador. “No existe todavía” normalmente significa P, no NA. Ningún ítem queda aprobado por marcar una casilla sin evidencia.

| ID | Criterio verificable / pregunta | Fuente / prueba a inspeccionar | Estado inicial de preparación |
|---|---|---|---|
| CK01 | ¿La evaluación se limita a consulta de orden demo y notificación, sin reglas de otros módulos? | Alcance de semana 1/2 y diff de la entrega | Listo para revisar; sin dictamen independiente. |
| CK02 | ¿Entrada vacía/espacios se rechaza antes de consultar o notificar? | execute y CA-03; gateway/notificador espía si se requiere | Listo para revisar. |
| CK03 | ¿Solo se notifican estados reconocidos y se distinguen dato inexistente e indisponibilidad? | Enum DemoOrderStatus, retorno gateway y CA-04 | Hallazgo preliminar H-01: null es ambiguo. |
| CK04 | ¿El contrato API define método/ruta, payload, respuesta, errores y versionado para la integración seleccionada? | Contrato HIS/API o declaración explícita de puertos de prueba | P: no hay API consumida por este prototipo. |
| CK05 | ¿Identidad/rol se verifican en servidor antes de recuperar estado o notificar información protegida? | Evidencia de autenticación/autorización y casos denegados | P: demostración sin sesión real. |
| CK06 | ¿Hospital/tenant deriva de contexto autenticado y se bloquea acceso cruzado? | Contrato del HIS y prueba negativa entre dos hospitales | P: no hay integración hospitalaria real. |
| CK07 | ¿Gateway/notificador traducen fallos esperados sin éxito falso ni excepción sin manejar? | execute, adaptadores y pruebas de excepción | Hallazgo preliminar H-02: no hay contrato de errores esperables. |
| CK08 | ¿Runtime, distribución/versión y destino se verificaron antes de afirmar viabilidad? | composer/lockfile, entorno, instalación y arranque | P: solo contrato PHP con dobles. |
| CK09 | ¿Permisos concedido/denegado/revocado y capacidad ausente producen degradación explícita? | Notificador de prueba y evidencia por destino real | Parcial: UNAVAILABLE modelado; SO pendiente. |
| CK10 | ¿UI, aplicación, dominio y persistencia respetan dependencias; cada consumidor usa solo capacidades necesarias? | Puertos ISP, constructores y diseño de componentes seleccionado | Parcial: ISP existente; UI/refactor de otras ramas fuera de esta base. |
| CK11 | ¿HIS conserva fuente de verdad y la extensión evita escritura clínica/cola offline? | Alcance, gateway, repositorio/snapshot si existe | Listo para revisar en contrato; cliente real pendiente. |
| CK12 | ¿Dato actual/obsoleto/sin dato se distingue ante caída, timeout y recuperación? | Política de sincronización y pruebas de red | P: no hay transporte/sincronización real en base. |
| CK13 | ¿Notificaciones, logs, exportación y capturas excluyen pacientes, valores clínicos y secretos? | CA-02, mensajes, archivos de evidencia e inventario de almacenamiento | Listo para revisar en demo; almacenamiento nativo pendiente. |
| CK14 | ¿Las dependencias son necesarias para el flujo y el SO/paquete queda aislado en adaptadores? | composer.json y constructores; excluir escáner/exportador del flujo que solo notifica | Listo para revisar en contrato; paquete nativo no elegido. |
| CK15 | ¿Cada RF/RNF tiene evidencia reproducible y los defectos abiertos no se cuentan como pruebas aprobadas? | Matriz, salidas, IDs de hallazgo y criterio de salida | Parcial: CA-01..10; revisión y runtime pendientes. |

## 6. Hallazgos preliminares para la sesión

Estos son hallazgos de inspección preparatoria del código, no acuerdos de una sesión formal ni defectos corregidos.

| ID / checklist | Evidencia y observación | Riesgo / severidad propuesta | Recomendación / propietario / estado |
|---|---|---|---|
| H-01 / CK03–04 | DemoOrderStatusGateway retorna ?DemoOrderStatus y execute convierte null en HIS_UNAVAILABLE; el adaptador devuelve null tanto si no encuentra orden como si no aporta estado | Diagnóstico ambiguo al integrar servicio; media | Definir resultado tipado para FOUND/NOT_FOUND/UNAVAILABLE/TIMEOUT antes de integración; autora, abierto. |
| H-02 / CK07 | execute invoca gateway y notifier sin traducción de excepciones; las interfaces no documentan error técnico | Cierre inesperado cuando un adaptador real lanza excepción; alta para integración | Definir errores esperables y su traducción en adaptador; agregar reproducción y recuperación. Autora, abierto; requiere retest. |
| H-03 / CK05–06 | El ejemplo recibe solo ID de orden, sin contexto autenticado ni hospital; los dobles no prueban aislamiento | Posible acceso cruzado si se conecta sin control; alta para integración real | Diseñar contrato de identidad/hospital y pruebas negativas antes de conectar datos reales. No afirmar vulnerabilidad explotada en demo. Autora, pendiente de diseño. |
| H-04 / CK08–09 | Semana 2 declara NativePHP no instalado y capacidades en memoria | Confundir diseño de contrato con soporte móvil/escritorio; alta si se declara soporte | Mantener “viabilidad nativa no demostrada” hasta seleccionar y ejecutar destino. Autora, limitación abierta. |

No se cambia severidad por conveniencia del plazo. Moderador/revisores deben ratificar clasificación y prioridad al celebrar la sesión.

## 7. Criterios de salida y decisión

- Para cerrar la **sesión formal**: participantes/roles registrados, 15 ítems con estado y evidencia, acta y decisión firmada o confirmada por las personas asignadas. No equivale a que el software esté aprobado.
- Para aprobar el **contrato de demostración**: no quedan críticos/altos aplicables al contrato sin resolver, regresión pasa, mensajes/resultados cumplen RF/RNF del alcance y cada exclusión de integración queda justificada. Hallazgos diferidos tienen responsable, fecha y aceptación explícita del moderador/revisor.
- Para recomendar **integración nativa**: además, CK04–09 y CK12 se verifican con contrato/servidor/destino real; no quedan críticos ni altos de integración abiertos. Los dobles no sustituyen autorización/tenant, permisos del SO ni runtime.
- Decisiones posibles: aprobado para alcance declarado; aprobado con observaciones menores no bloqueantes; requiere correcciones y retest; requiere nueva revisión. Si la sesión no se celebró, usar “preparada / pendiente”, no “aprobado con observaciones”.

Con esta línea base la conclusión preparatoria es: paquete de revisión disponible; autorización real, aislamiento, errores de integración y runtime pendientes. No se emite aprobación técnica ni se afirma que la reunión ocurrió.

## 8. Evidencia reproducible

```powershell
php docs/modulos/investigacion-prototipo-nativephp/semana-02/ejemplo-isp/verificar.php
```

La preparación ejecutó este comando con PHP 8.2.33: 14 aserciones exitosas. [Salida](evidencia/regresion-semana-02.txt). Esto confirma la regresión del contrato existente, no los ítems pendientes de integración ni la ejecución de NativePHP.

Usar [formato-hallazgos.md](formato-hallazgos.md) para cada observación y [acta-revision.md](acta-revision.md) para la sesión. Ambos son plantillas sin participantes ni aprobaciones inventadas.