# Recorrido navegable (5 minutos)

Se hace sobre el [prototipo](../semana-11/prototipo/index.html) de la semana 11, primero en escritorio y después a 390 px. Cada paso dice qué hacer, qué decir y qué debe verse.

| # | Rol | Qué hacer | Qué decir | Qué debe verse |
|:-:|---|---|---|---|
| 1 | Recepción | Disponibilidad → Medicina interna → Buscar. Luego Pediatría | «Recepción solo consulta; ve nombre y hora, sin datos de pacientes» | Tarjetas o tabla según el ancho; en Pediatría, «Sin franjas en estas fechas» (estado vacío) |
| 2 | Recepción | Marcar «Citas no responde» y buscar | «Si Citas no responde no se ofrecen franjas: podrían estar ocupadas (RNF-02)» | Error recuperable con «Reintentar» y ninguna franja |
| 3 | Administración | Médicos → Registrar médico: Sofía Ruiz con colegiado 11111; luego 33333 | «El error aparece junto al campo y el foco va al campo» | «Ese colegiado ya tiene perfil de médico»; después «quedó registrada» |
| 4 | Administración | Asociar Pediatría y repetir la misma asociación | «La vigencia es [desde, hasta): una misma especialidad no se cruza» | Primera vez éxito; segunda «ya está vigente en esas fechas» (409) |
| 5 | Administración | Ausencias → Dra. Ana Pérez, del 02 al 08 → Revisar | «Es el conflicto de agenda: lista las citas, el módulo no las cancela» | 2 citas, botón final inactivo hasta marcar la casilla; después Ana ya no aparece en Disponibilidad |
| 6 | Médico | Ausencias (cambio solicitado) | «El médico solo ve y registra las suyas» | Nombre fijo; la API rechaza con 403 las de otro médico |

**Prueba del paso 6 sin interfaz:** en la consola del navegador, `ApiSimulada.registrarAusencia('medico','med-luis',{desde:'2026-11-02',hasta:'2026-11-09'}).then(console.log)` devuelve `estado: 403`.

**En celular (390 px):** repetir los pasos 1, 5 y 6. Se comprueba que las franjas son botones de 44 px, que el botón final queda abajo y que no hay desplazamiento horizontal.
