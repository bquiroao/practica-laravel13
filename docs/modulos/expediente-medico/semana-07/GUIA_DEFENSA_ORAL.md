# Guía de defensa oral — Semana 7

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

> «El expediente tiene cinco componentes de frontend y once de backend, cada uno con su contrato. La medición pone arriba a `ServicioExpediente`, con Ce 7: es la puerta única para los datos sensibles, y quiero que lo siga siendo. El acoplamiento caro está en el prototipo, que decide en el cliente quién ve un expediente, con su propia tabla de atenciones, y que afirma que todo quedó auditado. El refactor deja la autorización solo en el backend: la UI muestra lo autorizado y explica el rechazo.»

**¿Por qué la autorización en el cliente no protege?** Porque cuando la UI decide, el dato ya llegó al navegador. Cualquiera puede hacer la petición directa. Es el riesgo número uno de OWASP: control de acceso roto.

**¿Por qué un Ce 7 en `ServicioExpediente` no es el problema?** Porque concentrar la autorización y la auditoría en un solo lugar es lo que permite asegurar que ningún camino se salte R-05, R-06 o R-08. Partirlo abriría más puertas.

**¿Qué tiene de malo que la UI diga «quedó en la auditoría»?** Que afirma algo que no controla. La semana 13 mostró (H-05) que un fallo técnico no se auditaba. La UI solo debería decirlo si el backend lo confirma.

**¿Qué se queda en el frontend?** El nombre abreviado hasta pedir verlo, la confirmación antes de abrir y los mensajes de rechazo. Son protecciones de pantalla, no de acceso.

**¿Qué protege el cambio?** Las pruebas «m2 sin asignación = 403 E4», «p4 sin consentimiento = 403 E5», «p2 sin vínculo MPI = solo local» y «todo queda auditado, también los rechazos».
