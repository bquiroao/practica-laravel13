# Semana 10 — Diseño para movilidad

**Tarea:** adaptar «detección, envío, acuse y escalamiento de una alerta crítica» a móvil (320–430 px): jerarquizar datos y acciones, reducir carga cognitiva y definir navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada.
**Entregable:** propuesta responsive de **4 pantallas**, reglas de breakpoint y dos escenarios móviles.

## 1. Pantallas (Salt, `.puml` + `.png`)

| # | Pantalla | Decisiones de contenido |
|---|---|---|
| M1 | `pantallas/m1-bandeja-movil.png` | La tabla de escritorio se vuelve **tarjetas**. Orden: prioridad + tiempo restante → código de paciente y servicio → resumen de una línea → acción. La CRITICA muestra «Acusar» directo en la tarjeta. |
| M2 | `pantallas/m2-detalle-movil.png` | Una sola acción primaria a todo lo ancho («Acusar»). El historial y los destinatarios se pliegan (se abren al tocar). |
| M3 | `pantallas/m3-cierre-movil.png` | El formulario ocupa toda la pantalla, con la acción primaria arriba de «Cancelar» y la nota obligatoria. Solo lo ven médico o jefe. |
| M4 | `pantallas/m4-sin-conexion.png` | Aviso de conexión limitada, con «Reintentar ahora» y la hora de los datos mostrados. |

## 2. Reglas de breakpoint

| Ancho | Navegación | Bandeja | Detalle | Formularios |
|---|---|---|---|---|
| 320–359 px | Barra superior con «←» y título; menú ☰ | Tarjetas de una columna; descripción a 1 línea | Acción a todo lo ancho; historial plegado | Una columna, campos a 100 %, botones ≥ 44×44 px |
| 360–430 px | Igual, más el contador de pendientes | Tarjetas con servicio y nivel en la misma línea | Igual | Igual |
| 431–767 px | Igual | Tarjetas en una columna con más aire | Historial abierto | Igual |
| ≥ 768 px | Cabecera completa (como en la semana 4) | Tabla | Historial en tabla | Etiquetas a la izquierda |

Reglas generales:
- Texto base de 16 px.
- Sin desplazamiento horizontal: el contenido usa `max-width: 100%`, y las tablas solo existen desde 768 px.
- Áreas táctiles de al menos 44 px.
- La prioridad siempre va con texto, además del color.

## 3. Reducción de carga cognitiva

- Una sola acción primaria por pantalla.
- El tiempo restante es el dato más grande después de la prioridad, porque decide la urgencia.
- Se ocultan el origen técnico (`LAB-77801`) y el UUID completo: se ven en el detalle plegado.
- Los mensajes son de una frase, con la acción de recuperación en el mismo bloque.

## 4. Escenarios móviles

**Escenario A — Acuse con señal débil (enfermería en el pasillo).**
1. `enf.turno01` toca «Acusar» en M1 y la petición no llega: hay *timeout* o no hay red.
2. Se muestra M4. El botón queda en estado «reintentando» y la tarjeta conserva su estado ENVIADA; no se pinta como acusada sin confirmación.
3. Reintentar es **seguro** porque el acuse es idempotente (RN-06): si la primera petición sí llegó, la segunda responde `cambio: false` y la interfaz muestra «Acuse registrado» igual.
4. **Error que no se oculta:** si el plazo vence mientras no hay red, la alerta escala (RN-05). Al volver la señal, la tarjeta muestra «Escalada a nivel 1» y el acuse sigue permitido.

**Escenario B — Jefe de servicio recibe un escalamiento en su teléfono.**
1. `jefe.servicio01` abre el aviso «Escalada: nadie acusó en 15 min». M2 muestra arriba nivel 1, tiempo restante y «Acusar».
2. La lista de destinatarios anteriores está plegada: el jefe ve primero qué hacer y después quién no respondió.
3. **Error:** si intenta acusar una alerta de nivel 0 donde no fue notificado, recibe «No fue notificado de esta alerta» (RN-07) con el nivel al que corresponde, sin revelar datos de más.

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.
