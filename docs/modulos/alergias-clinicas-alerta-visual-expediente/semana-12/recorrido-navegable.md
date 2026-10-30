# Recorrido navegable para la defensa (≈ 6 minutos)

**Antes de empezar:**
- Abrir `semana-11/prototipo/index.html` en Chrome en dos ventanas: una de 1280 px y otra con las herramientas de desarrollo en modo dispositivo (375×760).
- Recargar para partir de los datos iniciales.

| Min | Acción | Qué se muestra | Decisión que se defiende |
|---|---|---|---|
| 0:00 | Sesión MED-014; expediente PAC-00451 | Franja MODERADA con Ibuprofeno | El nivel va con texto además del color (RNF-03) |
| 0:40 | «Ver PAC-00452» | «Sin alergias registradas… no confirma» en gris | DEC-02: nunca verde |
| 1:10 | «Registrar alergia» → «Siguiente» vacío | Foco en el campo, `aria-invalid`, mensaje junto al campo | B-02, semana 9 |
| 1:40 | Penicilina + «Pone en riesgo la vida» → paso 2 | Resumen «→ la alerta quedará CRÍTICA» | B-03: confirmación del riesgo |
| 2:20 | «Guardar alergia» | «Alergia registrada.» y CRÍTICA sin reconocer | DEC-05: se ve el nivel nuevo de inmediato |
| 2:50 | Cambiar a ENF-022 | Sin botón de registro; «avise al médico» | RN-07 y protección por rol |
| 3:20 | «Otro médico registra Látex» → «He leído» | 409 `ALERTA_CAMBIO`, Látex NUEVA dentro de la franja | DEC-01: el reconocimiento va por huella |
| 4:00 | «He leído» otra vez | «Reconocida por usted» | RN-09 |
| 4:30 | Modo móvil 375 px, repetir 3:20 | Franja fija, tarjetas, botón de ancho completo | Semana 10 |
| 5:10 | «Simular sin conexión» | «Alergias no disponibles… no administre» | Nunca un falso «sin alergias» |
| 5:40 | Cierre | Matriz decisión → RF/RNF → evidencia | — |
