# Semana 12 — Recorrido navegable y respuesta al cambio solicitado

## Recorrido (guion para la demostración, prototipo de la semana 11)
1. Abrir `semana-11/prototipo/index.html` como `enf.turno01`. Mostrar la bandeja en escritorio y luego en móvil (ancho 375 en DevTools).
2. Abrir PAC-00451 (CRITICA): mostrar «vence en» y a quién escala.
3. Acusar: «Acuse registrado».
4. Cambiar a `jefe.servicio01` y abrir una alerta de nivel 0: no está notificado (RN-07).
5. Cambiar a `med.tratante01` y cerrar: probar con la nota vacía (error en el campo), luego con nota y confirmación.
6. **Error crítico:** marcar «Simular sin conexión» y acusar PAC-00452. La alerta sigue ESCALADA; «Reintentar ahora».
7. Navegar todo con Tab: el primer Tab muestra «Saltar al contenido».

## Cambio solicitado (CASO PREPARADO)
> El cambio real lo pide el ingeniero en el parcial; este caso demuestra cómo se respondería.

**Cambio:** «El título de la pestaña debe mostrar cuántas alertas están pendientes de acuse, por ejemplo “(2) Alertas críticas”, para que se vea aunque la pestaña no esté activa.»

| Aspecto | Respuesta |
|---|---|
| Componente | `BandejaAlertas` actualiza `document.title` con las alertas ENVIADA o ESCALADA del tenant |
| Datos | Ya vienen de `GET /api/v1/alertas`; no cambia el backend |
| Privacidad | Solo el número, nunca el código de paciente (RNF-02) |
| Accesibilidad | El número también aparece en la pestaña «Pendientes (2)», no solo en el título |
| Móvil | Sin cambio visual; el contador ya existe en M1 |
| Prueba | Con 2 pendientes el título empieza por «(2)»; con 0, no hay paréntesis |
