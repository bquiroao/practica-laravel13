# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-01-uml-catalogo-camas.md](semana-01-uml-catalogo-camas.md)

## 1. Resumen en 60 segundos

> «Modelé cómo un hospital registra sus salas y camas y controla si cada cama se puede usar. El administrador crea salas con un código único y camas con un código único dentro de su sala. Toda cama nueva empieza disponible. El administrador o el supervisor de enfermería pueden pasarla a mantenimiento o inactivarla, pero solo con transiciones permitidas y dejando un motivo en la auditoría. El estado “ocupada” no lo toca nadie a mano: lo maneja admisión, que por eso es actor secundario. Toda operación pasa primero por la autorización del hospital. Los tres diagramas comparten identificadores, así que cada regla se sigue de uno a otro.»

## 2. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Sala = ward | El esquema solo tiene `wards` y `beds`; inventar «habitación» sería modelar algo que no existe. |
| Admisión es actor, RBAC no | Admisión es externa al catálogo y tiene un objetivo propio (ocupar y liberar camas). RBAC es un servicio interno que se incluye en todo CU. |
| `<<include>>` y no `<<extend>>` | Autorizar ocurre siempre; `extend` es para comportamiento opcional bajo una condición. |
| «Ocupada» fuera del catálogo (R-06) | Evita liberar a mano una cama con un paciente. |
| No se elimina, se desactiva (R-08) | La base lo impone con `ON DELETE RESTRICT` y se conserva el historial. |
| Concurrencia con `updated_at` (D8, E7) | Si dos usuarios cambian la misma cama, gana el primero y el segundo recibe 409 en vez de pisarlo. |

## 3. Preguntas probables

**¿Qué detiene a un supervisor que quiere poner en mantenimiento una cama ocupada?**
D6 → E5 (409). Si Admisión la ocupó *después* de que el supervisor la leyó, lo detiene D8 → E7 (409).

**¿Qué reglas ya garantiza la base y cuáles el servicio?**
La base: R-01, R-02, R-04 y R-08. El servicio: R-03, R-05 y R-06.

**Cambio en vivo probable: «agregue el estado “en limpieza”».**
Hay que tocar R-05 (nuevas transiciones), D7 en la actividad, la nota del CU-03 y, en la implementación, el `CHECK` de `beds.status`.

## 4. Recorrido de demostración

1. Casos de uso: señalar a Admisión y la nota de R-05 y R-06.
2. Seguir R-06: D6 → E5 en la actividad y el `alt` «cama ocupada» de S11 en la secuencia.
3. Seguir R-02: D4 → E3 en la actividad y la violación de `beds_ward_code_unique` en S6.
