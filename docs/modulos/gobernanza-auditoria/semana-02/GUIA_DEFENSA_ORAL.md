# Guía de defensa oral — Semana 2

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-02-ocp-requerimientos-diseno.md](semana-02-ocp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «Convertí las reglas de la semana 1 en 13 RF, 9 RNF y 17 criterios de aceptación, y apliqué el principio abierto/cerrado. Lo que más cambia en una bitácora de gobernanza son los filtros de la consulta y las validaciones de cada emisor. Antes eran ifs dentro de una sola clase. Ahora son clases detrás de dos interfaces, y los servicios las reciben sin saber cuáles son. Lo demostré con dos requerimientos nuevos: filtrar solo los denegados y que cada cuenta de servicio solo escriba su propio módulo. Cada uno fue una clase más y una línea en la composición, sin tocar los servicios. También decidí qué no es extensible: el alcance por hospital, el sello encadenado, la idempotencia y la meta-auditoría son fijos, porque si se pudieran quitar por configuración la bitácora dejaría de ser evidencia. Hay 21 pruebas en verde. Una prueba de paridad muestra que antes y después producen exactamente los mismos sellos. Y 8 defectos inyectados fueron todos detectados.»

## 2. Orden sugerido de la exposición

1. **RF/RNF y criterios:** escoger RF-08 (rango obligatorio) y su CA-13 (la 23:30 entra, la 00:10 no).
2. **Antes:** mostrar los dos comentarios «otro elseif aquí» y «otro if aquí».
3. **Después:** las dos interfaces, y que los parámetros admitidos salen de los filtros.
4. **Demostración** (§5).
5. **Mutaciones**, y el hallazgo de la mutación equivalente.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| OCP en filtros y reglas | Es lo que el negocio pedirá cambiar; lo demás es estable. |
| El alcance **no** es un filtro | Si fuera un filtro, quitar una línea de la composición expondría otros hospitales. Los invariantes de seguridad no se configuran. |
| Interfaces y una clase abstracta | `FiltroPorCampo` porque usuario, módulo y acción comparten comportamiento; las reglas solo comparten un contrato. |
| Los filtros traen su `sql()` | La semana 3 compone el WHERE desde fragmentos parametrizados: OCP también en la capa de persistencia. |
| `FILTRO_NO_SOPORTADO` | Un filtro mal escrito (`usuairo`) no se ignora en silencio: devolvería toda la bitácora creyendo que filtró. |
| La meta-auditoría pasa por `anexar()` | Una sola vía de escritura: la consulta también queda sellada en la cadena. |

## 4. Preguntas probables y respuestas

**¿OCP significa que nunca se modifica el código?**
No. Significa que la clase que **aplica** las reglas no cambia cuando se agrega una regla. La raíz de composición sí cambia: es el punto de configuración, y es el único.

**¿Qué diferencia hay entre OCP y SRP aquí?**
SRP: cada regla tiene una sola razón para cambiar. OCP: agregar una regla no cambia el servicio. Separar las responsabilidades fue lo que hizo posible extender sin modificar.

**¿Cómo sabe que la refactorización no rompió nada?**
Por PAR-01: los dos diseños ejecutan los mismos 16 pasos y producen resultados idénticos, sellos incluidos.

**¿Qué es una mutación equivalente?**
Un cambio en el código que no altera el comportamiento observable, así que ninguna prueba puede detectarlo. Me pasó con «no exigir secuencias consecutivas»: el sello ya cubre ese caso.

**¿El sello detecta todo?**
No. Si se borra la **última** fila, no hay ninguna posterior que la delate. Para eso hay que publicar periódicamente el último sello fuera de la base.

## 5. Demostración en vivo

```bash
# 1. Todas las pruebas pasan
php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/verificar.php

# 2. En despues/composicion.php, comentar «$filtros[] = new FiltroResultado();»
#    → falla solo OCP-01 (el servicio no se tocó)

# 3. Restaurar la línea → 21/21 otra vez

# 4. Mutaciones
php docs/modulos/gobernanza-auditoria/semana-02/ejemplo-ocp/mutaciones.php
```
