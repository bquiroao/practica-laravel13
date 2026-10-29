---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura de Citas médicas y agenda
---

# Citas médicas y agenda
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «solicitud, validación de disponibilidad y confirmación o cancelación de cita»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Recepcionista**: solicita (CU-01, con CU-04), confirma (CU-02) y cancela (CU-03).
- **Médico**: cancela solo sus propias citas (R-09).
- El paciente no es actor: no hay portal y la recepcionista actúa por él.
- CU-05 autoriza todo: permiso, hospital y propiedad de la cita.

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-02 y RF-03 sin cruces | `cruce()`: E4 médico, E5 paciente |
| RF-05 cancelar con motivo | `EstadoCita` (R-07) y `appointment_cancellations` |
| RNF-02 portabilidad | **DIP**: el servicio depende de `RepositorioCitas` |
| RNF-03 facilidad de prueba | El puerto `Reloj` fija «ahora» en las pruebas |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa los puertos.
- MVC: `Rutas` → `ControladorCitas` (sin SQL ni reglas) → `VistaJson` (códigos HTTP).
- `RepositorioCitas` con PDO e InMemory: **el mismo guion de pruebas** en ambos.
- Confirmar y cancelar con concurrencia optimista: E7 si la cita cambió antes.

---

## 4. Contrato API (S5)

| Operación | Éxito | Errores |
|---|---|---|
| `POST /citas` | 201 `{id}` | 409 E4, E5 · 422 E2, E3 · 503 E8 |
| `POST /citas/{id}/confirmacion` | 200 | 404 · 422 E7 · 503 E8 |
| `POST /citas/{id}/cancelacion` | 200 | 404 · 422 E2, E7 · 503 E8 |

OpenAPI 3.1 con lint y prueba de contrato de 12 de 12 (PR #351). E6 estaba pendiente.

---

## 5. Cambio práctico: dos solicitudes del mismo horario

**Pedido:** si dos recepcionistas piden el mismo horario a la vez, solo una lo obtiene y la otra recibe **409 E6** (D7 de la S1).

- Persistencia: la condición de cruce va **dentro del `INSERT`** (una sola sentencia).
- Aplicación: `HorarioTomado` → `ReglaDeDominio('E6')`.
- Presentación: E6 → 409. **Dominio, rutas y controlador sin cambios.**

---

## 6. Evidencia del cambio

| Solicitud intercalada | Antes | Después |
|---|---|---|
| La que empezó primero | 201 | **409 E6** |
| La intercalada | 201 | 201 |
| Citas activas de m1 | **2** | 1 |

Pruebas: **30 de 30** (antes 28). La S13 ahora da H-02 «no reproducido».

---

## 7. Riesgos y siguientes pasos

- PostgreSQL: traducir la violación de la exclusión `gist` a `HorarioTomado`.
- Agregar E6 al OpenAPI cuando se integre el PR #351.
- H-01 (usuario y permiso en la entrada) sigue abierto para la semana 15.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
