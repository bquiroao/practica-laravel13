# Guía de defensa oral — Semana 5

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias (ASII-15) · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md)

## 1. Resumen en 60 segundos

> «Llevé el Micro-HIS a un diseño cliente-servidor y evalué la frontera de microservicio con mediciones.
>
> El contrato OpenAPI tiene tres operaciones. Unas pruebas comparan el contrato con el código: las rutas, los permisos y los códigos de 24 escenarios reales.
>
> El cliente reintenta con la misma clave de idempotencia. Por eso un reintento nunca emite dos veces. Además, a diferencia de Auditoría, no guarda nada para enviar después: una prescripción no puede quedar pendiente.
>
> Medí qué pasa si el Expediente fuera un servicio aparte. Cuesta unos 15 milisegundos por emisión, y si el Expediente cae, no se emite ninguna prescripción, porque la regla es no emitir sin verificar.
>
> Por eso la decisión es no separar todavía. El módulo queda listo para hacerlo cambiando una línea de configuración, y la decisión está atada a cinco criterios medibles.»

## 2. Orden sugerido de la exposición

1. **La decisión y la tabla de medición** (§1), en especial el escenario E: 0 de 200.
2. **Contrato:** la tabla de operaciones y por qué la `Idempotency-Key` va en una cabecera. Ejecutar API-01..03.
3. **Resiliencia:** el diagrama de secuencia y sus tres casos (respuesta perdida, Expediente caído, incierto).
4. **Por qué sin bandeja local** (§9).
5. **Propiedad de los datos y comunicación:** quién es dueño de qué, y la corrección sobre `medications` (ASII-14).
6. **Plan:** el issue y el flujo con worktree.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| No separar el servicio | Con *fail-closed*, una dependencia remota multiplica la indisponibilidad de prescribir (E: 0/200). La latencia es secundaria |
| `Idempotency-Key` en una cabecera | La emisión no tiene una identidad natural; la clave representa la intención y se repite en cada reintento |
| El reintento no reverifica | Devuelve lo ya emitido; si reverificara, podría contradecirlo (503 o 422 sobre algo que ya existe) |
| Sin bandeja local en el cliente | Una prescripción «pendiente» engaña al médico y a farmacia. INCIERTO se muestra y se resuelve reintentando con la misma clave |
| 503 `VERIFICACION_NO_DISPONIBLE` = RECHAZADO | El servidor afirma que no emitió; no hay incertidumbre que resolver |
| Límite de 2 s y circuito en el adaptador remoto | El límite cumple RNF-08; el circuito evita esperar 2 s por un Expediente ya caído, sin cambiar la decisión clínica |
| El adaptador remoto rechaza «no evaluado con alergias» | Una respuesta contradictoria es una falla, no un dato: *fail-closed* también ante un Expediente defectuoso |

## 4. Preguntas probables y respuestas

**¿Por qué el escenario C tarda 63 ms si el D tarda 17?**
Los dos usan `php -S`, que arranca PHP por petición. C además abre una conexión a PostgreSQL por petición y pasa por todo el kernel. Por eso los milisegundos no son el argumento; la disponibilidad sí.

**¿Qué pasa si el médico cierra la pestaña con un resultado INCIERTO?**
La prescripción pudo quedar emitida o no. Al volver, consultar las prescripciones del paciente lo aclara. Y si reintenta, debe hacerlo con la misma clave (la interfaz la guarda), para que nunca se emita dos veces.

**¿Por qué Auditoría sí tiene bandeja local y Prescripciones no?**
Un movimiento de auditoría que llega tarde no daña a nadie. Una prescripción que llega tarde, sin que nadie lo sepa, deja a un paciente sin tratamiento.

**¿Cómo saben que el contrato no miente?**
API-01..03 lo comparan con el código en cada ejecución. La mutación M27 cambia un permiso en el YAML y la suite falla.

**¿Cuándo sí separarían?**
Cuando se cumpla alguno de C-1..C-5. El más probable es C-3: que enfermería o alimentación necesiten la verificación de alergias con su propio ritmo. Aun así, C-5 exige un Expediente con réplica local, para no multiplicar la indisponibilidad.

## 5. Recorrido de demostración

1. `php bin/medir.php 200` con base y leer los cinco escenarios.
2. `php tests/ejecutar.php --solo=API,CLI,HFA,OBS`.
3. Abrir `plan/issue-asii-15-prescripciones.md` y mostrar el comando `gh issue create`.
