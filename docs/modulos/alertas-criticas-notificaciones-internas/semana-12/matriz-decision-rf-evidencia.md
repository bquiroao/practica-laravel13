# Semana 12 — Matriz decisión → RF/RNF → evidencia

| # | Decisión (componentes, UX, accesibilidad, movilidad) | RF / RNF / RN | Evidencia |
|---|---|---|---|
| 1 | `ClienteApiAlertas` como único punto que conoce la API | RNF-06 | `semana-07/README.md` (contratos) |
| 2 | Regla pura `ReglaEscalamientoInmediato` (conceptual) | RF-03, RF-05, RN-08 | `semana-07/diagramas/refactor-despues.png` |
| 3 | El formulario de cierre solo se muestra a médico y jefe | RF-08 | Prototipo (`cierre()`); API `api-pruebas.php` |
| 4 | El acuse no pide confirmación; el cierre sí | RN-06, RN-09 | Wireframes W2 y W3; prototipo |
| 5 | Plazo visible «vence en → escala a» | RF-07, RN-04 | Prototipo; hallazgo H-03 |
| 6 | Prioridad con texto y símbolo, además del color | WCAG 1.4.1 | Capturas de la semana 11 |
| 7 | Contorno de foco con halo | WCAG 2.4.7 / 1.4.11 | `semana-11/README.md` (16.24:1) |
| 8 | Tarjetas por debajo de 768 px | Movilidad | `semana-10` (M1) y `capturas/movil-1-bandeja.png` |
| 9 | Sin conexión: no marcar acusada sin confirmación | RNF-05, RN-06 | `capturas/*-4-error-sin-conexion.png` |
| 10 | El mensaje RN-07 indica a quién corresponde, sin revelar datos | RN-07, RNF-01 | Wireframe W4; prototipo |
| 11 | Solo códigos de paciente en toda la interfaz | RNF-02 | Todas las capturas |
