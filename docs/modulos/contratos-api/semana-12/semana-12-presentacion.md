---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo de Contratos API y documentación por rol
---

# Contratos API: OpenAPI, Postman y documentación por rol
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- «Mi documentación» → documento → versión obsoleta → documento ajeno.
- Recorrido de 7 pasos en el prototipo, con capturas de escritorio y móvil.
- El rol se cambia en el panel del prototipo: médico, integrador o farmacia.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | Cada rol ve solo su audiencia (R-06) |
| Estados | Vigente u obsoleta, con aviso anunciado y enlace a la vigente (R-08) |
| Errores | Lo ajeno responde igual que lo inexistente (R-07) |

---

## 3. Accesibilidad y movilidad

- Foco en el `<h1>`, títulos distintos y «Saltar al contenido».
- Errores y ejemplos plegados; rutas y JSON que saltan de línea.
- Tarjetas en móvil; **sin scroll horizontal** a 320, 360 y 430 px.

---

## 4. Cambio solicitado: buscar y saber qué se encontró

**Pedido de integradores:** encontrar un contrato y saber, con lector de pantalla, si hubo resultados (B-08).

- «Buscar en mi documentación», solo dentro de lo que el rol ya ve.
- «1 documento encontrado.» en `role="status"`, sin mover el foco.
- La revisión visual encontró un error en móvil (`hidden` anulado) y se corrigió: 2, 1 y 0 filas visibles medidas.

Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
