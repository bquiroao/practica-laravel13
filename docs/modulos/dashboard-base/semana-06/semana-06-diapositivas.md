---
marp: true
paginate: true
title: Semana 6 — Defensa de la arquitectura del Dashboard base
---

# Dashboard base UI/UX e implementación transversal
## Defensa de la arquitectura — primera evaluación parcial

Flujo: «navegación del dashboard con estados de carga, vacío, error y resumen inicial»
Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Actores y casos de uso (S1)

- **Usuario del hospital**, de cualquier rol: entra (CU-01), ve el resumen (CU-02), navega (CU-03) y reintenta (CU-04).
- CU-05 se incluye siempre: sesión, hospital y permisos.
- El menú y las tarjetas muestran solo lo que el rol permite (R-02).
- Las tarjetas muestran conteos, nunca datos de pacientes (R-06).

---

## 2. Requisitos y SOLID (S2)

| Requisito | Cómo se cumple |
|---|---|
| RF-02 «vacío» no es error | `EstadoTarjeta::deConteo` |
| RF-03 error con «Reintentar» | 503 E5 con texto amable (R-09) |
| RF-05 un 403 oculta la tarjeta | E4 → 403 en `VistaJson` |
| RNF-03 mantenibilidad | **SRP**: estado, texto y catálogo por separado |

---

## 3. Capas y Repository (S3-S4)

- Presentación → Aplicación → Dominio; Persistencia implementa `FuenteConteos`.
- MVC: `Rutas` → `ControladorDashboard` (sin SQL ni reglas) → `VistaJson`.
- PDO e InMemory: **el mismo guion de pruebas** en ambos.
- Cada tarjeta carga y falla por su cuenta (R-03).

---

## 4. Contrato API (S5, versión 0.6.0)

| Operación | Éxito | Errores |
|---|---|---|
| `GET /dashboard` | 200 menú y tarjetas | 401 E1 |
| `GET /dashboard/tarjetas/{id}` | 200 resumen o vacío | 401 E1 · 403 E4 · 503 E5 |
| `GET /dashboard/navegacion/{modulo}` (S6) | 200 ruta y título | 401 E1 · 403 E6 · 404 NF |

OpenAPI 3.1 con lint sin avisos y prueba de contrato de 14 de 14.

---

## 5. Cambio práctico: una ruta escrita a mano

**Pedido:** si un rol escribe la ruta de un módulo que no puede ver, se niega con **403 E6**, distinto de «no existe» (404 NF). Es CU-03 de la S1 y el hallazgo H-02 de la S13.

- Dominio: `Catalogo::destino` usa el **mismo** menú del rol (SRP: una sola fuente de permisos).
- Presentación: ruta, acción `navegar` y los códigos en `VistaJson`.
- **Persistencia sin cambios.**

---

## 6. Evidencia del cambio

| Rol que solo ve citas | Antes | Después |
|---|---|---|
| `/navegacion/citas` | 404 | **200** |
| `/navegacion/camas` | 404 | **403 E6** |
| `/navegacion/usuarios` | 404 | 404 NF |

La prueba de contrato de la S5 **detectó** la operación sin documentar; con el contrato 0.6.0 da 14 de 14. Pruebas: 30 de 30 (antes 27).

---

## 7. Riesgos y siguientes pasos

- Los permisos aún llegan en la petición (H-01): JWT y RBAC en la semana 15.
- Una tarjeta inexistente sigue respondiendo 403 E4 (H-03).
- La S14 mejora: M-03 pasa de 3 a 5 de 5 casos de uso con prueba.
- Matriz completa y registro del cambio: [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md).
