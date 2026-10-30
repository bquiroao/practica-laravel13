# Guía de defensa oral — Semana 11 (prototipo navegable)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final

## 1. Resumen en 60 segundos

La consola de release ya se puede recorrer en escritorio y en el teléfono, y **habla con la API real** de micro-his-e2e: no hay una API simulada. Por eso cada aprobado, rechazo o 409 que se ve lo decidió el dominio sobre PostgreSQL.

**Camino feliz:** Release registra el gate, que el servidor aprueba; Operaciones pega mal el digest, lo corrige y despliega.

**Error crítico:** la aprobación se reemplaza por un RECHAZADO **mientras** el operador instala. El servidor responde 409, la pantalla lo explica, conserva lo cargado y muestra por qué.

**Verificación:** un recorrido automático hace 88 comprobaciones con 0 violaciones de axe-core. Lo que más enseñó lo encontraron otras cosas:
- construir sobre el servidor real mostró que el gate tiene **6** reglas, no 5;
- la revisión de las capturas mostró que la pantalla seguía diciendo «Aprobada» al lado del rechazo, con el recorrido en verde.

## 2. Demostración sugerida (6 minutos)

| Paso | Qué mostrar | Minutos |
|---|---|---|
| 1 | `servidor.php`: mismo origen, API real; mapa de navegación | 1:00 |
| 2 | A en vivo: ingresar como Release → gate → diálogo (Tab, Esc) → Aprobado | 1:30 |
| 3 | A: Operaciones → digest distinto → corregido → Desplegada | 1:00 |
| 4 | B con capturas: 409 `GATE_NO_APROBADO` y la trazabilidad que lo explica | 1:30 |
| 5 | Hallazgos H-1 a H-3 y la evidencia de cada corrida | 1:00 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no una API simulada, como en el otro módulo? | Porque este módulo ya tiene el servidor completo. Una simulación copia las reglas, y una copia diverge: H-1 lo demuestra, la pantalla tenía 5 reglas y el servidor 6. |
| ¿Cómo se simula al pipeline? | No tiene pantalla. El recorrido hace sus llamadas directo a la API con su token, como lo haría el CI. |
| ¿Por qué ese error crítico? | Es la regla más delicada del módulo (R-11: cuenta la última evaluación) y es concurrente: un operador con una pantalla vieja. Solo con el servidor real se puede mostrar sin trampa. |
| ¿Qué pasa con lo que el operador ya cargó? | Se conserva. Solo cambian el mensaje, el contexto y la acción (semana 9 B-09). |
| ¿El gate muestra la decisión antes de registrar? | No, a propósito: diría algo que el servidor podría no hacer (semana 9 H-09). La vista previa sin efectos es el cambio de la semana 12. |
| ¿Por qué hace falta `?versiones=` en la URL? | El contrato no tiene un listado de versiones (brecha B-1 de la semana 8). La consola sigue las que se le indican. |
