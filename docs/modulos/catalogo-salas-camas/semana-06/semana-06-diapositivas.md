---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura del Catálogo de salas, wards y camas
---

# Catálogo de salas, wards y camas
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «creación de sala y cama con control de estado operativo»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Administrador**: registra salas y camas (CU-01, CU-02) y desactiva salas (CU-04).
- **Supervisor**: pasa camas a mantenimiento o a disponible (CU-03).
- **Admisión** (sistema): es el único dueño del estado «ocupada».
- CU-05 autoriza todo: permiso y hospital (R-07).

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-02 cama en sala activa | `registrarCama`: E4 y E3 |
| RF-04 y RF-05 transiciones | `EstadoCama::validarCambioA` |
| RNF-02 mantenibilidad | **SRP**: las transiciones viven en una sola clase |
| RNF-03 facilidad de prueba | El dominio se prueba sin base |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa el puerto (**DIP**).
- MVC: `Rutas` → `ControladorCatalogo` (sin SQL ni reglas) → `VistaJson` (códigos HTTP).
- `RepositorioCatalogo` con PDO e InMemory: **el mismo guion de pruebas** en ambos.
- Concurrencia optimista: E7 si otro usuario cambió la cama antes.

---

## 4. Contrato API (S5)

| Operación | Éxito | Errores |
|---|---|---|
| `POST /salas` | 201 | 409 E3 · 422 E2 · 503 E8 |
| `POST /salas/{sala}/camas` | 201 | 409 E3 · 422 E2, E4 · 503 E8 |
| `PATCH /salas/{sala}/camas/{cama}/estado` | 200 | 404 · 409 E5, E7 · 422 E2, E6 |

OpenAPI 3.1 con lint y prueba de contrato de 11 de 11 (PR #350).

---

## 5. Cambio práctico: capacidad de la sala

**Pedido:** no registrar más camas en uso que la capacidad de la sala (R-10, RF-07 → 409 E9).

- Dominio: nueva `CapacidadSala` (SRP: la regla en un solo lugar).
- Persistencia: `wards.capacity` y un `INSERT … SELECT` que vuelve a contar (consistencia).
- Presentación: E9 → 409. **Rutas y controlador sin cambios.**

---

## 6. Evidencia del cambio

| Escenario | Antes | Después |
|---|---|---|
| Tercera cama, capacidad 2 | 201 | **409 E9** |
| Cama tras inactivar una | 409 E3 | **201** |
| Capacidad 0 | 201 | **422 E2** |

Pruebas del Micro-HIS: **36 de 36** (antes 28), con PDO e InMemory.

---

## 7. Riesgos y siguientes pasos

- Altas simultáneas: en PostgreSQL, `SELECT … FOR UPDATE` sobre la sala.
- `wards.capacity` requiere una migración del esquema compartido.
- Agregar E9 y `capacidad` al OpenAPI cuando se integre el PR #350.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
