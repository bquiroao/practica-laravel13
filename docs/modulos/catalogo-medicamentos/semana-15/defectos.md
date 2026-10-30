# Defectos registrados

Cada defecto tiene una prueba marcada `[DEFECTO D-NN]`: hoy **falla** a propósito y el ejecutor la
informa como `DEFECTO` sin romper la ejecución. Cuando se corrija, la prueba pasará y el ejecutor
avisará `CORREGIDO` para cerrar el registro.

| ID | Título | Severidad | Requisito | Detectado en | Prueba | Estado |
|---|---|---|---|---|---|---|
| D-01 | Con concentración «0» y sin unidad no se informa el error del valor | Mayor | RF-02 | Evaluación de usabilidad (H-05, semana 9) | `pruebas/unitarias/semana15-reglas.php` | Abierto |
| D-02 | El 503 por base caída al arrancar no lleva `X-Request-Id` | Menor | RNF-05 | Revisión del autor (RT-02, semana 13) | `pruebas/caja-negra/defecto-arranque.php` | Abierto |
| D-03 | Dos altas simultáneas: la segunda recibe 409 en lugar del siguiente código | Menor | RF-01 | Revisión del autor (RT-03, semana 13) | `pruebas/unitarias/semana15-reglas.php` | Abierto |

## D-01 — Error del valor no informado

- **Pasos:** enviar un alta con `concentracion_valor = "0"` y `concentracion_unidad` vacía.
- **Esperado (RF-02):** errores en `concentracion_unidad` **y** `concentracion_valor` en la misma respuesta 422.
- **Obtenido:** solo `concentracion_unidad`; el error del valor aparece después de corregir la unidad.
- **Causa:** en `FichaMedicamento::validar` el valor solo se valida si la unidad es válida.
- **Corrección propuesta:** validar el formato del valor de forma independiente (por ejemplo, con una unidad provisional) y crear `Concentracion` solo si ambos son válidos.
- **Impacto:** el usuario corrige en dos vueltas; no hay datos inválidos guardados.

## D-02 — 503 sin `X-Request-Id`

- **Pasos:** levantar `php -S` con la base apagada (`CATALOGO_BD_PUERTO=1`) y llamar `GET /api/v1/opciones`.
- **Esperado (RNF-05):** 503 con `X-Request-Id` y `request_id` en el cuerpo.
- **Obtenido:** 503 sin `X-Request-Id`: el error se produce en `public/index.php` antes de crear `KernelApi`.
- **Corrección propuesta:** crear la aplicación dentro de `KernelApi` (o envolver el arranque con el mismo manejador de `request_id`).
- **Impacto:** soporte no puede rastrear ese 503 en el registro.

## D-03 — Carrera al asignar el código

- **Pasos:** la prueba simula que otra persona registra el código calculado entre `siguienteCodigo()` y `agregar()`.
- **Esperado (RF-01):** el alta termina con el siguiente código libre (MED-000108).
- **Obtenido:** 409 `CONFLICTO_CONCURRENTE` («Otra persona registró este código…»); no se guarda nada (sin datos inválidos).
- **Corrección propuesta:** en `RegistrarMedicamento`, reintentar hasta 3 veces cuando llegue `CONFLICTO_CONCURRENTE` en un alta; o asignar el código con una secuencia por hospital en la base.
- **Impacto:** bajo (requiere dos altas en el mismo instante); la integridad se conserva gracias a `uq_medicamentos_codigo_version`.

## Relación con el plan SQA (semana 14)

Ninguno es **crítico**: M-05 (defectos críticos abiertos = 0) se cumple. D-01 es **mayor** y debe
cerrarse antes de integrar al HIS (criterio de salida de la revisión técnica, semana 13).
