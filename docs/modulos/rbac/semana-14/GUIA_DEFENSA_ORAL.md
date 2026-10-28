# Guía de defensa oral — Semana 14

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-14-sqa-rbac.md](semana-14-sqa-rbac.md)

## 1. Resumen en 60 segundos

> «Definí el plan SQA de RBAC con siete métricas que cubren calidad del flujo, confiabilidad, seguridad y trazabilidad. Cada una tiene fuente, umbral, responsable, frecuencia y acción si falla.
>
> No las dejé en papel: un script las mide sobre el código real. La línea base es 4 en verde, 1 en ámbar y 2 en rojo. Uno de los rojos es un hueco que no conocía: las pruebas comprueban el código HTTP pero no el código de negocio de dos reglas.
>
> Para saber si las métricas sirven, inyecté dos defectos en una copia del código, un fail-open y una denegación sin registro, y los dos pusieron en rojo al menos dos métricas.
>
> La matriz tiene 12 riesgos; los tres altos son los tokens de demostración, la disponibilidad del PDP y la persistencia sin probar con PostgreSQL. Hay 7 controles preventivos y 7 detectivos, con un calendario por PR, semanal, por iteración y por liberación, y los hitos hasta la semana 16.»

## 2. Orden sugerido de la exposición

1. **Atributos → métricas (plan §1):** qué mide cada métrica y por qué esa.
2. **Tabla de métricas (§3):** recorrer M2, M5 y M6 con su umbral y su acción.
3. **Demostración:** correr `medir-sqa.php` y explicar los dos rojos.
4. **Mutantes (§3.1):** las métricas detectan defectos.
5. **Matriz de riesgos:** mapa de calor y los tres altos.
6. **Controles:** un preventivo (CP-01) y un detectivo (CD-04) completos.
7. **Responsables y calendario (§2, §8):** independencia y hitos S15–S16.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Medir con un script y no a mano | Una métrica que nadie puede recalcular no sirve para decidir. El script es la fuente y su salida es la evidencia. |
| Umbral del 100 % en M1, M3, M4 y M5 | Son reglas de seguridad: un 99 % significa que existe un camino que permite o que no deja evidencia. |
| M7 en ÁMBAR y no en VERDE | 0.006 ms en memoria no dice nada de RNF-09, que exige < 50 ms con la base de datos. Marcarlo VERDE sería engañoso. |
| Probar las métricas con mutantes | Una métrica que nunca se pone en rojo puede estar rota. Los mutantes demuestran que detecta lo que dice detectar. |
| Responsable SQA distinto del autor | Quien corrige no verifica: es la misma idea que el moderador de la S13. |
| No arreglar los rojos esta semana | Los arreglos tocan el paquete congelado de la revisión S13. Se hacen en su retrabajo (S15), con prueba. |

## 4. Preguntas probables y respuestas

**¿Cuál es la diferencia entre un control preventivo y uno detectivo?**
- El **preventivo** evita que el defecto entre: pruebas antes del código, guardas en la suite, revisión formal, configuración por entorno.
- El **detectivo** lo encuentra cuando ya entró: la suite en cada PR, la medición semanal, la revisión de la bitácora, los mutantes.

Un buen plan necesita los dos, porque ningún preventivo es perfecto.

**¿Por qué M2 está en rojo si todas las pruebas pasan?**
Porque pasar no es lo mismo que comprobar lo importante. Las pruebas de `jorge` (inactivo) y de un usuario inexistente comprueban 422 y 404, pero no el código `USUARIO_INACTIVO`. Un cambio que devolviera otro código con el mismo estado pasaría desapercibido.

**¿Cómo calculaste la probabilidad?**
Con evidencia:
- **Sube** si hay un hallazgo abierto que lo reproduce: AR-01 se reproduce con un 201 usando un token de demostración, por eso RS-03 tiene P = 3.
- **Baja** si hay una prueba y un control activo: el salto de tenant tiene HTTP-07, PEF-05 y la paridad, por eso RS-02 tiene P = 2.

**¿Por qué RS-08 (PDP no disponible) es un riesgo si fail-closed es lo correcto?**
Porque lo correcto para la seguridad deja a los clínicos sin poder operar. Es un riesgo de disponibilidad que se **acepta con mitigación**:
- el cliente tiene timeout, reintento y circuit breaker;
- M7 se medirá con PostgreSQL;
- para el HIS se propone un procedimiento de emergencia auditado.

**¿Qué pasa si una métrica queda en rojo dos semanas?**
Se abre una no conformidad en ≤ 2 días hábiles. Si sigue en rojo en la medición siguiente, se escala al integrador y se congela la liberación (plan §6).

**¿Qué falta?**
- Implementar CP-05 y CD-05.
- Ejecutar CD-04 con PostgreSQL.
- Medir M7 con base de datos.
- Nombrar a los responsables.

Todo eso está en el plan de acción, con plazo S15.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/semana-14
php herramientas/medir-sqa.php          # 4 VERDE · 1 ÁMBAR · 2 ROJO

# Mutante en vivo (copia aparte; no toca el repositorio):
mkdir -p /tmp/mut && git archive HEAD docs/modulos/rbac/micro-his-rbac | tar -x -C /tmp/mut
sed -i "s/\$motivo = 'ERROR_EVALUACION';/\$motivo = null;/" /tmp/mut/docs/modulos/rbac/micro-his-rbac/src/Dominio/Servicios/PoliticaAutorizacion.php
SQA_MICROHIS=/tmp/mut/docs/modulos/rbac/micro-his-rbac php herramientas/medir-sqa.php   # M1 y M5 en ROJO
```

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega una métrica» | El plan admite hasta 7 (la tarea pide 5–7). Habría que reemplazar una o justificar el cambio. En el script: un bloque `$registrar('M8', …)`. |
| «Baja el umbral de M7 a 10 ms» | Cambiar el umbral en el plan y en el script. Con PostgreSQL se verá si se cumple. |
| «Sube la probabilidad de RS-02» | Recalcular el nivel (P × I) y moverlo en el mapa de calor. Si llega a ≥ 12, necesita tratamiento con fecha. |
| «¿Quién verifica que se arregló AR-01?» | El RSQA o el MR, nunca el RM (regla de independencia). |
