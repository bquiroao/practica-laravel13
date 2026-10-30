---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura del Dashboard de ocupación hospitalaria
---

# Dashboard de ocupación hospitalaria
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «consolidación y consulta de ocupación por sala y estado de cama»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Temporizador**: consolida cada 5 minutos (CU-01, con CU-04 consistencia).
- **Jefatura de enfermería** y **administrador**: consultan (CU-02) y filtran por estado (CU-03).
- CU-05 autoriza la consulta: permiso de ocupación y hospital (R-08).
- Solo cifras agregadas, nunca datos de pacientes (R-07).

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-01 ocupación por sala | `OcupacionSala::calcular`: ocupadas ÷ operativas |
| RF-03 marcar inconsistencias | Cama contra estancias abiertas (R-04) |
| RF-04 hora y aviso a los 15 min | `Corte::esViejo` |
| RNF-02 y RNF-03 | **ISP**: tres puertos; ninguno escribe camas |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa los tres puertos.
- MVC: `Rutas` → `ControladorOcupacion` (sin SQL ni reglas) → `VistaJson`.
- PDO e InMemory: **el mismo guion de pruebas** en ambos.
- Si la lectura falla, se conserva el corte anterior (R-09).

---

## 4. Contrato API (S5)

| Operación | Éxito | Errores |
|---|---|---|
| `POST /ocupacion/cortes` | 201 `{hora, salas}` | 503 E8 |
| `GET /ocupacion[?estado=]` | 200 con el corte o `{"vacio": true}` | 422 E3 · 503 E8 |

OpenAPI 3.1 con lint sin avisos y prueba de contrato de 9 de 9. El cambio de la S6 no lo modifica.

---

## 5. Cambio práctico: procedencia del dato «ocupada»

**Pedido:** si una cama tiene dos estancias abiertas (dos pacientes), el tablero debe marcarla. Es R-04 de la S1 y el hallazgo H-03 de la S13.

- Persistencia: `EXISTS` → **`COUNT(*)`** de estancias abiertas.
- Dominio: inconsistente si no hay exactamente 1 (ocupada) o 0 (otro estado).
- **Puertos, controlador y contrato sin cambios.**

---

## 6. Evidencia del cambio

| A1 ocupada con 2 estancias (PDO, tablas reales) | Antes | Después |
|---|---|---|
| Ocupación de A | 66.7 % | 66.7 % |
| Camas inconsistentes | **[]** | **[A1]** |

Pruebas: **31 de 31** (antes 29). S13: H-03 «no reproducido».

---

## 7. Riesgos y siguientes pasos

- El tablero marca, no corrige (R-09): cerrar la estancia duplicada es tarea de admisión.
- H-01 (permiso en la entrada) para la semana 15; H-04 (cama ocupada en sala inactiva) sigue abierto.
- En PostgreSQL, la exclusión de `bed_stays` evita duplicados; la marca detecta datos de otras vías.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
