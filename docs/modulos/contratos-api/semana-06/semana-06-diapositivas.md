---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura de Contratos API y documentación por rol
---

# Contratos API: OpenAPI, Postman y documentación por rol
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «publicación y consulta de documentación y manuales diferenciados por rol»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Autor técnico**: envía una versión de un OpenAPI, una colección Postman o un manual (CU-01).
- **Responsable de integración**: aprueba o rechaza (CU-02) y marca obsoleta (CU-04).
- **Lector por rol**: consulta lo vigente de su audiencia (CU-03).
- Nadie aprueba lo propio (R-05); lo ajeno responde 404 (R-07).

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-02 versión mayor | `VersionNueva::validar`: x.y.z y E3 |
| RF-04 y RF-05 por rol | `Audiencia::permite`; lo ajeno = 404 E6 |
| RNF-03 mantenibilidad | **ISP**: tres puertos, uno por cliente |
| RNF-05 trazabilidad | Las versiones nunca se borran: se marcan obsoletas |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa los tres puertos.
- MVC: `Rutas` → `ControladorDocumentacion` (sin SQL ni reglas) → `VistaJson`.
- `RepositorioDocumentos` con PDO e InMemory: **el mismo guion de pruebas** en ambos.
- Concurrencia optimista: E7 si otra persona cambió la versión antes.

---

## 4. Contrato API (S5)

| Operación | Éxito | Errores |
|---|---|---|
| `POST /documentos/{slug}/versiones` | 201 `{id}` | 409 E3 · 422 E2 · 503 E8 |
| `POST /versiones/{id}/aprobacion` · `/rechazo` · `/obsolescencia` | 200 | 403 E5 · 404 · 409 E7 · 422 E2 |
| `GET /documentos/{slug}` y `/versiones/{version}` | 200 | 404 E6 · 503 E8 |

OpenAPI 3.1 con lint y prueba de contrato de 18 de 18 (PR #354).

---

## 5. Cambio práctico: auditoría del versionado

**Pedido:** saber quién aprobó y quién marcó obsoleta cada versión (R-10). Hoy marcarla obsoleta **borra** a quien la aprobó (H-03).

- Persistencia: nueva columna `obsoleted_by` y dos `UPDATE` fijos, uno por columna.
- `datosRevision` devuelve también `revisor` y `obsoleta_por`.
- **Ni los puertos ni el servicio ni el controlador cambiaron de firma** (ISP).

---

## 6. Evidencia del cambio

| luis aprueba, marta marca obsoleta | Antes | Después |
|---|---|---|
| `reviewed_by` | **marta** | luis |
| `obsoleted_by` | (no existe) | marta |

Pruebas: **28 de 28** (antes 26). S13: H-03 «no reproducido». S14: M-02 pasa a cumplir (8 de 10).

---

## 7. Riesgos y siguientes pasos

- R-08 pide enlazar la obsoleta con su reemplazo: sigue abierto (H-05).
- H-01 (usuario y roles en la entrada) y H-02 (lint del OpenAPI, R-04) siguen abiertos.
- El esquema real no tiene tablas de documentos: `obsoleted_by` es una propuesta de migración.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
