# Guía de defensa oral — Semana 8

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Wireframes:** [PDF](wireframes/semana-08-wireframes-qa-e2e.pdf)

## 1. Resumen en 60 segundos

Hasta ahora el módulo solo tenía la API y la CLI del pipeline. Esta semana diseñé la consola de release:
- cuatro user flows, uno por rol humano: Ingeniería QA, Responsable de release, Operador de despliegue y Desarrollador;
- seis wireframes anotados, del catálogo de escenarios al despliegue.

**La idea central:** la UI no inventa reglas. Cada validación de pantalla tiene su par en el servidor, y cada mensaje sale de un código estable del contrato. La pantalla anticipa el error; si igual ocurre, lo explica con la acción siguiente.

**Lo que destapó el diseño:** el contrato no tiene operaciones de lectura para listar, ni una vista previa del gate. Quedan anotadas como brechas para la semana 9.

## 2. Orden sugerido (8 minutos)

| Paso | Qué mostrar | Minutos |
|---|---|---|
| 1 | Roles y permisos: quién ve qué acción (sección 2) | 1:00 |
| 2 | Flujo del operador de despliegue: el más crítico | 1:30 |
| 3 | W4 Quality gate: la vista previa y «no hay aprobar igual» | 1:30 |
| 4 | W5 Desplegar: el digest en vivo y el aviso de REVERTIDO | 1:30 |
| 5 | W1 o W2: validaciones en el campo y estados vacío, éxito y conflicto | 1:30 |
| 6 | Brechas B-1…B-3 | 1:00 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el pipeline no tiene pantalla? | Es una identidad técnica. Su interfaz es la CLI y el contrato, que ya se diseñaron en la semana 5 (`ReportadorCi`). |
| Si la UI valida, ¿para qué valida el servidor? | La UI se puede saltar con un `curl`. El servidor tiene la última palabra (R-13). La UI valida para que el error no llegue a ocurrir. |
| ¿Por qué no hay un botón «aprobar igual»? | El gate no se negocia (R-09). Las excepciones de R-10 nunca cubren un crítico ni el digest, y quedan fuera del alcance de estas pantallas. |
| ¿Por qué la acción deshabilitada y no oculta? | Si el usuario **tiene** el permiso pero la regla no lo deja, ver el motivo («sin gate APROBADO») le ahorra la pregunta. Si no tiene el permiso, la acción no se muestra. |
| ¿Qué pasa si la aprobación vence mientras instalan? | El servidor responde 409 `APROBACION_VENCIDA` y la UI explica el camino: evaluar otra vez. La cuenta regresiva de W5 lo anticipa. |
| ¿De dónde salen los mensajes? | De los códigos del contrato, no del texto del servidor. Cada código del catálogo existe en `openapi.yaml`. |
| ¿Qué brechas encontraste? | No hay `GET` para listar escenarios ni versiones por evaluar, ni para el progreso de una ejecución, ni una vista previa del gate. Se resuelven sumando lecturas, sin tocar reglas. |
