# Registro del cambio práctico defendido - Semana 6

ID: `CC-VAL-06-FIRMA-TRAZA`  
Fecha: 27-10-2026  
Estado: Propuesto y validado en script (sin despliegue productivo)

## 1) Cambio solicitado

Para cada decisión de revisión bioquímica (VALIDATE o REJECT), exigir firma y generar trazabilidad explícita (`traceId`) sin perder las reglas actuales de versión, estado y aislamiento por hospital.

## 2) Situación antes del cambio

- `ReviewPendingLabResult` decide por actor/hospital/versión/estado y motivo.
- No existe un campo explícito de firma en el comando ni en el registro.
- Se conserva trazabilidad básica (`actorId`, `hospitalId`, `decidedAt`) pero no huella de firma.

## 3) Diseño del cambio

- Política adicional previa al caso de uso:
  - firma obligatoria;
  - actor/hospital solo desde sesión;
  - generación de `traceId`;
  - hash de firma para registro.
- El caso de uso principal no se reemplaza; la política lo envuelve.

## 4) Evidencia ejecutable

Script: [verificacion/cambio-practico.php](verificacion/cambio-practico.php)  
Salida: [evidencia/cambio-practico-semana-06.txt](evidencia/cambio-practico-semana-06.txt)

Resultados clave:

1. El diseño actual valida sin firma (límite probado).
2. La política propuesta rechaza decisiones sin firma.
3. Con firma, se decide correctamente y se genera `traceId`.
4. Actor y hospital vienen de sesión, no del payload.
5. Rechazo sin motivo sigue inválido.
6. Se conservan reglas de estado (`INVALID_STATE`) y aislamiento por hospital (`NOT_FOUND`).

## 5) Impacto esperado

- Mayor no repudio en la decisión bioquímica.
- Mejor trazabilidad de auditoría por cada decisión.
- Sin romper la lógica clínica de validación/rechazo ya existente.

## 6) Riesgos y mitigación

- Riesgo: fricción operativa por firma obligatoria.
- Mitigación: firma ligada a sesión activa y segundo factor del entorno.
- Riesgo: no persistir firma en base mientras solo hay InMemory.
- Mitigación: extender adaptador PDO para guardar hash de firma y `traceId`.

