# Plan de issues, rama, worktree y PR

Fichas propuestas locales; no son issues abiertos/cerrados en GitHub. Desarrollo: Mercedes Azucena Lopez Perez; revisores/propietarios de EMR, catálogo, identidad y operación por asignar y aceptar. Fechas de implementación por acordar.

| ID / prioridad | Trabajo / dependencia | Criterio verificable |
|---|---|---|
| OE5-01 P1 | Revisar contrato con dueños; sin dependencia | Esquemas/estados/permisos y propiedad de datos aprobados, ejemplos sin clínica. |
| OE5-02 P1 | Borde HTTP/DTO; depende 01 | Mismo Issue/Find, salida completa autorizada, 201 solo commit y 200 [] solo lectura vacía. |
| OE5-03 P1 | Identidad/tenant y esquema; depende 01 | H1 no lee/escribe H2, sesión revocada denegada, actor del contexto confiable, sin secretos cliente. |
| OE5-04 P1 | Idempotencia transaccional; depende 02/03 | Misma clave/payload y concurrencia no duplican; mismatch 409; respuesta perdida tras commit recupera ID tras reautorizar. Retención definida. |
| OE5-05 P1 | Integración sandbox EMR/catálogo; depende 02/03 | Contexto/prueba inválidos y caídas no escriben parcial; PDO rollback y consistencia concurrente verificados. |
| OE5-06 P2 | Métricas/decisión de frontera; depende 04/05 | Baseline reproducible, p95/error/costo medidos; no extraer servicio sin criterio satisfecho y responsable operativo. |

Rama: semana-5-ordenes-emr-api-mercedes-lopez.
Worktree: shi-semana-5-ordenes-emr-api-mercedes-lopez.
Base: semana-4-ordenes-emr-mvc-mercedes-lopez, 3cd3e0e.
Destino PR: develop, sin merge automático. Incluye dependencias propias de semanas 3/4 para continuidad, además del diseño de semana 5. No modifica main/develop localmente.

Checklist del PR: OpenAPI válido, regresiones PHP correctas, alcance/limitaciones declarados, diagrama y plan presentes. Implementación de los issues queda pendiente; crear fichas externas solo como acción explícita posterior.