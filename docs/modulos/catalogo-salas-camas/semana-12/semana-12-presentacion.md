---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo del Catálogo de salas, wards y camas
---

# Catálogo de salas, wards y camas
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- Cinco pantallas: salas → sala → cambio de estado → resultado o conflicto.
- Recorrido de 8 pasos en el prototipo, con capturas de escritorio y móvil.
- El rol se cambia en el panel del prototipo: supervisor o administrador.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | El supervisor no inactiva; nadie cambia una cama ocupada (R-05, R-06) |
| Estados | Siempre escritos, no solo color |
| Errores | Motivo vacío junto al campo; conflicto E7 sin perder el motivo |

---

## 3. Accesibilidad y movilidad

- Foco en el `<h1>` y «Saltar al contenido»; errores con «Error:» y `aria-invalid`.
- Tarjetas en móvil, objetivos de 44 px.
- **27 de 27 mediciones sin scroll horizontal** a 320, 360 y 430 px (S11 y S12).

---

## 4. Cambio solicitado: confirmar antes de inactivar

**Pedido de jefatura:** se siguen inactivando camas por error.

- Con «Inactiva» aparece «Confirmo que la cama queda fuera de servicio…».
- Sin marcarla: error junto a la casilla y foco en ella.
- Mantenimiento y disponible siguen con un clic.

Amplía B-02 (P1) de la semana 9. Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
