# Matriz decisión → RF/RNF → evidencia (segundo parcial)

| ID | Decisión (semanas 7–11) | RF / RNF | Evidencia |
|---|---|---|---|
| DEC-14 | `ServicioAlerta` y `PresentacionAlerta` (refactor conceptual) | RF-04, RF-05, RNF-07 | Semana 7: 3 `grep` antes/después |
| DEC-15 | Registro en 2 pasos con resumen de la consecuencia | RF-01 | W4, M2/M3, captura 01 |
| DEC-16 | La franja no bloquea el expediente (sin modal) | RF-06 | W1-5, captura 02 |
| DEC-17 | Estado de carga distinto del vacío | RF-05 | W2-4, flujos de la semana 8 |
| DEC-18 | Severidad siempre en español | RF-04, RNF-03 | H-01 → B-01; capturas 02 y 03 |
| DEC-19 | Error ligado al campo y foco al primero | RNF-03 | H-02 → B-02; prototipo: foco en `#alergeno` con `aria-invalid="true"` |
| DEC-20 | `role="alert"` solo en el título y solo mientras está pendiente | RNF-03 | H-05 → B-05; axe con 0 violaciones |
| DEC-21 | Formulario solo para el médico (también en la API) | RF-08, RNF-01 | T-37, prototipo con ENF-022 |
| DEC-22 | Tarjetas en lugar de tabla por debajo de 430 px | RNF-03 | Semana 10; sin desplazamiento horizontal a 375 px |
| DEC-23 | Reintento de POST seguro gracias al 409 | RNF-05 | T-11, T-41; escenario E2 |
| DEC-24 | Sin registro local cuando no hay conexión | RNF-05 | Semana 10, M4 |
| DEC-25 | Mensaje `ALERTA_CAMBIO` dentro de la franja fija | RF-07 | Defecto visto en las capturas de la semana 11 y corregido; captura 03 |
| DEC-26 | Contraste calculado, no estimado | RNF-03 | `semana-09/herramientas/contraste.py` → 12/12 |
