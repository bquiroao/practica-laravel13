---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura del Expediente médico electrónico base
---

# Expediente médico electrónico base
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «apertura y consulta autorizada del expediente longitudinal»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Personal de admisión**: abre el expediente (CU-01), uno por paciente activo.
- **Profesional tratante**: consulta la historia local (CU-02) y la de otros hospitales (CU-03).
- **Índice central** (sistema): identifica al paciente en la red y guarda los consentimientos.
- CU-05 autoriza y CU-06 audita **todo** acceso (R-08).

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-02 solo con asignación activa | `asignacionActiva` → 403 E4 |
| RF-03 historia externa con consentimiento | `Consentimiento::vigente` → 403 E5 |
| RF-05 auditar también los rechazos | `ServicioExpediente::auditado` |
| RNF-01 y RNF-02 | **DIP**: HOSPITAL, CENTRAL, auditoría y reloj son puertos |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa los puertos.
- MVC: `Rutas` → `ControladorExpediente` (sin SQL ni reglas) → `VistaJson`.
- Repository de **HOSPITAL** (expedientes y auditoría) y **CENTRAL** aparte, por dueño.
- PDO e InMemory: **el mismo guion de pruebas** en ambos.

---

## 4. Contrato API (S5)

| Operación | Éxito | Errores |
|---|---|---|
| `POST /patients/{id}/record` | 201 `{expediente}` | 409 E3 · 422 E2 · 503 E8 |
| `GET /patients/{id}/record` | 200 | 403 E4 · 404 NF · 503 E8 |
| `GET /patients/{id}/record/external` | 200 externos o solo local | 403 E4, E5 · 404 NF · 503 E8 |

OpenAPI 3.1 con lint sin avisos y prueba de contrato de 11 de 11.

---

## 5. Cambio práctico: auditar también el fallo

**Pedido:** si CENTRAL se cae mientras un médico pide la historia de otro hospital, el intento debe quedar en la auditoría (R-08, RNF-04). Es el hallazgo H-05 de la S13.

- Aplicación: `auditado()` registra el evento **E8** y vuelve a lanzar el error.
- Si la caída es la propia auditoría, no se oculta el error original.
- **Dominio, persistencia, rutas y contrato sin cambios.**

---

## 6. Evidencia del cambio

| m1 consulta a p1, CENTRAL caída | Antes | Después |
|---|---|---|
| Consulta local | 200 · 1 evento | 200 · 1 evento |
| Consulta longitudinal | 503 E8 · **0 eventos** | 503 E8 · **1 evento E8** |

Pruebas: **27 de 27** (antes 25). S13: H-05 «no reproducido».

---

## 7. Riesgos y siguientes pasos

- La auditoría aún admite `UPDATE` (H-04): en producción la protege el trigger inmutable.
- Abrir sin comprobar rol (H-02) y alcance del consentimiento (H-03) siguen abiertos.
- El profesional aún llega en la petición: JWT y RBAC en la semana 15.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
