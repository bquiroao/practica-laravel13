---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo de Citas médicas y agenda
---

# Citas médicas y agenda
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- Agenda → asistente de 3 pasos → resultado o horario tomado → cancelación.
- Recorrido de 10 pasos en el prototipo, con capturas de escritorio y móvil.
- El rol se cambia en el panel del prototipo: recepcionista o médica.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | La médica solo ve y cancela sus citas (R-09) |
| Estados | Programada, Confirmada y Cancelada en texto; la cancelada no tiene acción (R-07) |
| Errores | Motivo obligatorio al agendar y al cancelar; horario tomado sin perder el motivo |

---

## 3. Accesibilidad y movilidad

- Foco en el `<h1>` o en el campo con error; mensajes en `role="status"`.
- «Cancelar» de cada fila con hora y paciente en su `aria-label` (B-03).
- Tarjetas en móvil; **30 de 30 mediciones sin scroll horizontal** (S11 y S12).

---

## 4. Cambio solicitado: cancelar desde la agenda

**Pedido de recepción:** cancelar cuando el paciente avisa, sin confundirlo con salir.

- «Cancelar» por fila, solo en citas activas del rol.
- Motivo obligatorio; «Cancelar la cita de A. Pérez» en rojo (6,47:1) frente a «Volver» (B-02).
- El horario vuelve a la lista de libres (R-08).

Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
