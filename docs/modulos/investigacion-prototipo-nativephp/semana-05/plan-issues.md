# Plan de issues — Semana 5 NativePHP

Son fichas propuestas, no issues abiertos en GitHub. Propietaria de desarrollo: Mercedes Azucena Lopez Perez; revisores y dueños de identidad/HIS por asignar/aceptar. IDs locales NP5-01..06.

| ID / prioridad | Trabajo y dependencia | Criterio de aceptación / evidencia |
|---|---|---|
| NP5-01 / P1 | Revisar OpenAPI con identidad/HIS; sin dependencias | Contrato válido, formato/errores/permiso revisados; host demo no implica despliegue. |
| NP5-02 / P1 | Resultado gateway tipado; depende 01 | FOUND/NOT_FOUND/UNAVAILABLE/TIMEOUT separados, sin éxito falso, prueba de estados/privacidad y compatibilidad con flujo previo. |
| NP5-03 / P1 | Adaptador HTTP y stub; depende 01/02 | Timeouts, status/tenant inválidos, 429/503 y esquema manejados; sin logs de token ni notificación de dato obsoleto. |
| NP5-04 / P1 | Endpoint demo con contexto autorizado; depende 01 | 200 solo permiso válido; 401/403/404 sin filtración y prueba de acceso H1/H2; sin escritura clínica. |
| NP5-05 / P1 | Elegir runtime/destino e integrar notificador; depende 03/04 | Versión/entorno registrados; permiso concedido/denegado/ausente y fallo/red probados en destino. Hasta entonces viabilidad nativa no demostrada. |
| NP5-06 / P2 | Observabilidad, comparación y decisión de frontera; depende 03/04 | Baseline/reporte reproducible; no extraer servicio sin criterio medido y presupuesto; plan de rollback de experimento. |

## Rama, worktree y PR

- Rama: semana-5-nativephp-api-mercedes-lopez.
- Worktree: shi-semana-5-nativephp-api-mercedes-lopez.
- Base: semana-2-investigacion-prototipo-nativephp-isp-mercedes-lopez, commit 8a3dc40.
- Destino PR: develop, sin merge automático.
- Alcance del PR: contrato/diseño/diagramas/plan y evidencia; implementación de NP5-02..06 pendiente.
- Descripción de PR debe enlazar evidencia y declarar que no hay API ni runtime desplegados.

Antes de implementación asignar personas/fechas con aceptación. No presentar esta tabla como compromiso de terceros ni como issues ya cerrados.