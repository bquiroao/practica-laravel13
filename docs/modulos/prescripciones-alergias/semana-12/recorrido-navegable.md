# Recorrido navegable — demostración con el prototipo

**Prototipo:** [`prototipo/index.html`](prototipo/index.html) (se abre con doble clic). **Duración:** unos 7 minutos. Cada paso enlaza a su captura, la misma que produce `herramientas/recorrido.cjs`. Las capturas son de escritorio; las de móvil tienen el mismo nombre en `capturas/movil/`.

## Parte A · Roles, estados y el camino feliz (≈ 2,5 min, Dr. López)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 1 | Elegir «Dr. López» | Rol, hospital y avatar, siempre en la barra; tres personas con permisos distintos | [01](capturas/escritorio/01-ingresar.png) |
| 2 | Paciente P-002 | La franja de alergias aparece **antes** de elegir el medicamento | [02](capturas/escritorio/02-paciente.png) |
| 3 | Cefalexina 500 mg c/6 h + Paracetamol 1500 mg c/6 h → «Verificar alergias» | 422 de la API → resumen de errores con el foco y el enlace al campo (M-03) | [05](capturas/escritorio/05-dosis-excedida.png) |
| 4 | Paracetamol a 500 → Verificar | **ADVERTENCIA** cruzada, moderada; el resultado recibe el foco y se anuncia | [06](capturas/escritorio/06-advertencia.png) |
| 5 | «Justificar y emitir» → escribir la justificación | La hoja existe porque la API respondió `puede_justificar: true` (cambio solicitado); el contador es neutro | [07](capturas/escritorio/07-justificar.png) |
| 6 | Con «La sesión vence en la próxima petición» marcada en «Más», pulsar «Emitir con justificación» | El diálogo aparece **sobre** la página; tras renovar se repite la misma petición → #1, una sola | [08](capturas/escritorio/08-sesion-vencida.png) · [09](capturas/escritorio/09-emitida.png) |

## Parte B · El error crítico (≈ 2 min)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 7 | P-005, Amoxicilina → Verificar | «Sin alergias activas · 1 registrada como resuelta» → sin conflictos | [14](capturas/escritorio/14-sin-conflictos.png) |
| 8 | «Más» → «Registrar la alergia en el Expediente» → volver | La pantalla abierta **no** se entera: sigue diciendo «sin alergias activas» | [15](capturas/escritorio/15-alergia-registrada.png) |
| 9 | «Emitir» | «⛔ No se emitió: alergia nueva»: el servidor volvió a verificar (R-03) y no se guardó nada | [16](capturas/escritorio/16-bloqueo-al-emitir.png) |
| 10 | «Cambiar medicamento» | Franja actualizada, ítem marcado, dosis y días conservados | [17](capturas/escritorio/17-borrador-intacto.png) |

## Parte C · Red inestable (≈ 1 min)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 11 | Paracetamol → Verificar → «Más» → «Perder la próxima respuesta» → Emitir | «No sabemos si quedó emitida»; el borrador queda bloqueado | [18](capturas/escritorio/18-incierto.png) · [19](capturas/escritorio/19-borrador-bloqueado.png) |
| 12 | «Reintentar» | La misma clave → «#2 emitida… no la duplicó» | [20](capturas/escritorio/20-ya-emitida.png) |
| 13 | Anular con la respuesta perdida → Reintentar | 409 reconciliado: «Su anulación quedó registrada» | [22](capturas/escritorio/22-anular-incierto.png) · [23](capturas/escritorio/23-anulacion-reconciliada.png) |

## Parte D · El cambio solicitado (≈ 1,5 min)

| # | Hacer | Señalar | Captura |
|---|---|---|---|
| 14 | Dra. Ruiz, P-002 con Cefalexina → «Justificar y emitir» | La API respondió `puede_justificar: false` → solo M-07, **sin** área de texto | [24](capturas/escritorio/24-sin-override.png) |
| 15 | Dr. López → «Más» → «Retirar el permiso override» → la misma advertencia | La copia de permisos de la sesión dice que lo tiene; **manda la API** → M-07 | [26](capturas/escritorio/26-rbac-retira-override.png) |
| 16 | En la terminal: `node herramientas/paridad.cjs` | El kernel real responde `true` en el paso 2 y `false` en el 11: 0 diferencias | [paridad.txt](evidencia/paridad.txt) |

## Si algo falla en vivo

- **El navegador no abre el archivo:** se muestran las capturas de esta tabla, que son las mismas que produce el recorrido.
- **Se quiere repetir desde cero:** recargar la página. Todo vive en memoria; no queda nada guardado.
