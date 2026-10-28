# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Presentación:** [semana-06-defensa-auditoria.pptx](presentacion/semana-06-defensa-auditoria.pptx)

## 1. Guion por diapositiva (≈ 1,5 min cada una)

Las notas del orador de cada diapositiva tienen el texto completo; esto es el resumen.

| # | Diapositiva | Mensaje |
|---|---|---|
| 1 | Portada | 88 pruebas en verde con PostgreSQL, 21/21 mutaciones y 21 decisiones con evidencia |
| 2 | Actores y UML | Tres actores; **no hay casos de uso para editar ni borrar** |
| 3 | Requisitos y OCP | Reglas y filtros extensibles; alcance, sello e idempotencia fijos; paridad de sellos |
| 4 | Capas, MVC y Repository | Verificado leyendo el código; jsonb rompía los sellos y solo la base real lo mostró |
| 5 | Contrato y frontera | 5 operaciones, sin `PUT`/`DELETE`; 1 ms frente a 69 ms → no se separa todavía |
| 6 | Diagrama trazable | De actor a prueba; cada decisión tiene una mutación que la rompe |
| 7 | Cambio práctico | Corrección trazable: 6 aspectos, 5 fallas antes y 0 después |
| 8 | Cierre | Lo que NO cambió: Presentación, Cliente, las 8 reglas y la transacción |

## 2. Decisiones que más preguntan y cómo defenderlas

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo es «inmutable» si se puede corregir? | Corregir no es editar: el original queda igual y la corrección es otro movimiento, sellado y enlazado. |
| ¿Por qué la existencia del original se valida en el caso de uso y no en una regla? | Porque tiene que ser dentro de la transacción; si no, dos correcciones simultáneas pasarían las dos. La regla valida solo la forma. |
| ¿Por qué otro hospital da 422 y no 403? | Para no revelar que el evento existe en otro tenant: la respuesta es idéntica a la de un evento inexistente. |
| ¿Por qué la referencia va dentro del sello? | Para que nadie pueda desligar una corrección de su original sin romper la cadena. |
| ¿Qué pasa si alguien abusa de las correcciones para encubrir? | El original nunca desaparece y la corrección lleva motivo. Se propone una métrica de correcciones por emisor (semana 14). |
| ¿El contrato cambió de forma incompatible? | No: 1.1.0 solo agrega campos opcionales y un código de error; el cliente no cambió. |

## 3. Demostración en vivo (2 minutos)

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php bin/demo-correccion.php            # los 8 pasos del cambio
php tests/ejecutar.php                 # 0 fallan
# Mostrar que una prueba no pasa por casualidad: en RegistrarMovimiento.php cambiar
#   if ($original !== null && $original->hospital !== $nuevo->hospital) {   por   if (false) {
# → falla CORR-03 (es la mutación M20). Restaurar.
```

## 4. Si piden un cambio en vivo

| Pedido | Dónde se hace |
|---|---|
| «Que el motivo tenga al menos 20 caracteres» | `ReglaCorreccion` (un número) + CORR-02 |
| «Filtrar solo los movimientos vigentes (sin corrección)» | Un `FiltroMovimiento` nuevo (OCP) usando `correccionesDe`; no cambia el caso de uso |
| «Que el super administrador también pueda corregir» | Una política nueva: el emisor sería el del original, no el del token; decisión de gobernanza |
