# Guía de defensa oral — Semana 8

## Resumen de 60 segundos

Diseñé un flujo para que personal de laboratorio autorizado capture un resultado únicamente cuando el ítem no tiene resultado vigente y corrija solo una versión pendiente. La interfaz mantiene visible identidad y hospital, muestra unidad y rango desde catálogo, confirma antes de persistir y deja trazabilidad de autor, hora y versión. Una corrección exige motivo y versión esperada para evitar sobrescribir cambios. Los estados de carga, vacío, conflicto, error y 503 tienen mensajes recuperables y no confunden al usuario con un éxito falso. La validación por bioquímico es otro módulo: aquí el resultado termina pendiente.

## Orden sugerido de presentación

1. Objetivo, alcance y actores (20 s).
2. WF-01 y WF-02: encontrar el ítem y capturar con contexto (35 s).
3. WF-03 y WF-05: confirmar y comprobar persistencia/autoría (25 s).
4. WF-04 y WF-06: corrección versionada y errores recuperables (35 s).
5. Trazabilidad, límites y siguiente paso de usabilidad (15 s).

## Preguntas probables

- **¿Por qué capturar solo cuando no hay resultado vigente?** Para evitar duplicados y hacer explícita la decisión entre captura inicial y corrección controlada.
- **¿Por qué la corrección necesita motivo y versión?** El motivo aporta responsabilidad auditable y la versión esperada evita sobrescribir una modificación concurrente.
- **¿Qué pasa si el resultado ya fue validado?** Es inmutable y se bloquea con explicación; la interfaz remite al flujo correspondiente.
- **¿Cómo se manejan los conflictos?** Se informa `409`, se conserva el texto, se recarga el historial y el usuario revisa antes de reintentar.
- **¿Por qué no hay datos de paciente en listas?** Minimización y privacidad por defecto; la bandeja solo necesita identificar orden, ítem y estado.
- **¿Qué relación tiene con validación?** Este módulo registra una versión `PENDING`; el bioquímico la revisa y publica o rechaza en el módulo de validación separado.
