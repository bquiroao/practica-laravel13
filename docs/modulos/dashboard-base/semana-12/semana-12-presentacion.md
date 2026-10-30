---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo del Dashboard base
---

# Dashboard base UI/UX e implementación transversal
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- Layout con menú del rol → tarjetas independientes → módulo o «sin acceso».
- Recorrido de 10 pasos en el prototipo, con capturas de escritorio y móvil.
- El panel simula el rol, la respuesta de una tarjeta y la sesión por vencer.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | Menú y tarjetas según el permiso (R-02); lo ajeno se explica en el layout |
| Estados | Carga, resumen, vacío y error, siempre con texto (R-04, R-05) |
| Errores | Una tarjeta falla sola (E5) y se reintenta a pedido (R-03, R-08) |

---

## 3. Accesibilidad y movilidad

- Foco en el `<h1>`, `aria-current` en el menú y «Saltar al contenido».
- `aria-busy` y regiones vivas para el resultado de cada tarjeta.
- 1, 2 o 3 columnas según el ancho; **sin scroll horizontal** a 320, 360 y 430 px.

---

## 4. Cambio solicitado: avisar antes de que venza la sesión

**Pedido de enfermería:** la sesión vence sin avisar y se pierde lo que se estaba viendo (B-04, P1).

- Aviso con `role="alertdialog"`, 2 minutos antes del 401.
- Foco en «Seguir conectado»; Tab no sale del aviso y Esc lo cierra.
- «Cerrar sesión» limpia la sesión (R-07).

Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
