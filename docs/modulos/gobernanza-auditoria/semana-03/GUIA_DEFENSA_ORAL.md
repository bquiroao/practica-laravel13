# Guía de defensa oral — Semana 3

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md) · **Código:** [micro-his-auditoria](../micro-his-auditoria/README.md)

## 1. Resumen en 60 segundos

> «Inicié el Micro-HIS de auditoría en PHP vanilla con cuatro capas. Presentación recibe HTTP y lo traduce. Aplicación orquesta los casos de uso. El Dominio tiene el movimiento, el sello y las reglas y filtros de la semana 2. Persistencia habla con PostgreSQL por PDO, solo con sentencias preparadas. La regla de dependencias la verifican cinco pruebas que leen el código. La tabla audit_logs es de solo inserción en tres niveles: el contrato no tiene actualizar, el SQL no tiene UPDATE y un trigger lo rechaza. Cada movimiento queda sellado y encadenado, con un candado por hospital para que dos registros a la vez no choquen. Hay 39 pruebas, que pasan todas contra un PostgreSQL 18 real, y 12 de 12 defectos inyectados detectados. Lo más interesante: probar con la base y el servidor reales encontró dos defectos que los dobles no veían. jsonb reordena las claves y rompería los sellos, y PHP convierte un 200 con Location en un 302.»

## 2. Orden sugerido de la exposición

1. **C4 contexto → contenedores → componentes** (§3–5).
2. **Paquetes**: la tabla de qué capa puede usar a cuál, y ARQ-01 en vivo.
3. **Vista dinámica** del registro.
4. **Esquema SQL**: trigger, únicos e índices.
5. **Hallazgos H-1 y H-2** (§12).
6. **Pruebas**: camino feliz, regla de dominio y error de persistencia (§11).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Puertos en el Dominio | El dominio dice qué necesita; PDO se adapta. Por eso las pruebas corren sin base. |
| Una sola vía de escritura (`CadenaSellada`) | Si la meta-auditoría escribiera por otro lado, podría saltarse el sello. |
| Candado `pg_advisory_xact_lock` | `FOR UPDATE` no bloquea nada si la cadena está vacía; el candado sí. |
| Trigger además de la aplicación | Protege incluso de quien escribe SQL con el usuario de la aplicación. |
| Canonizar el detalle | Sin eso, la base reordena las claves y la verificación acusaría una alteración falsa. |
| Integración con un clúster temporal | Pruebas reales sin tocar el servidor instalado en el equipo ni pedir contraseñas. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre C4 y UML?**
C4 son vistas por nivel de zoom: contexto, contenedores, componentes, código. UML es una notación. Aquí uso C4 para la estructura y UML (paquetes y secuencia) para las dependencias y la dinámica.

**¿Cómo sabe que la regla de dependencias se cumple?**
ARQ-01 lee los `use` de cada archivo y falla si una capa importa a otra prohibida. La mutación M9 lo comprueba: agregar un `use` del Dominio en Presentación hace fallar ARQ-01.

**¿Por qué una prueba de error de persistencia con dobles y otra con base real?**
El doble permite provocar la falla exacta, como que falle la meta-auditoría (APP-05). La base real demuestra que el ROLLBACK de verdad funciona (INT-04).

**¿Qué pasa si dos módulos registran a la vez en el mismo hospital?**
El segundo espera el candado de esa cadena hasta el COMMIT del primero, y toma la secuencia siguiente. Si algo fallara, el único `(cadena, secuencia)` rechaza el duplicado.

**¿Qué es una sentencia preparada?**
El SQL se envía con marcadores (`:usuario`) y los valores aparte. La base nunca interpreta un valor como SQL. PER-02 lo prueba con `u' OR '1'='1`.

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php tests/ejecutar.php                      # 33 pasan, 6 omitidas (sin base)
AUDITORIA_DB_DSN=… php tests/ejecutar.php   # 39/39
AUDITORIA_DB_DSN=… php -S 127.0.0.1:8088 -t public &
bash bin/demo-http.sh
```
