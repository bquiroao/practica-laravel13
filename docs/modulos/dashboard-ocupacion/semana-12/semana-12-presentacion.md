---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo del Dashboard de ocupación hospitalaria
---

# Dashboard de ocupación hospitalaria
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- Encabezado del corte → tabla de salas con filtro → detalle de sala → generar corte.
- Recorrido de 9 pasos en el prototipo, con capturas de escritorio y móvil.
- El panel simula el rol, la antigüedad del corte y una falla al generar.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | La jefatura consulta; el administrador genera cortes |
| Estados | Cuatro estados de cama con su conteo; nivel baja, media o alta en texto |
| Errores | Datos viejos avisados (R-06); corte fallido sin perder el anterior (R-09) |

---

## 3. Accesibilidad y movilidad

- Hora absoluta y fuente siempre visibles; resultados del filtro anunciados.
- Foco en el `<h1>`, títulos distintos y «Saltar al contenido».
- Tarjetas en móvil; **sin scroll horizontal** a 320, 360 y 430 px.

---

## 4. Cambio solicitado: ver las camas que no cuadran

**Pedido de jefatura:** ver en pantalla la cama con dos pacientes que detecta la semana 6, y saber a quién avisar (R-04, B-08).

- «Camas por revisar (R-04)» en el detalle de cada sala.
- «Dónde se corrige» explica qué pasa, qué hacer y en qué módulo.
- El tablero no corrige nada (R-09).

Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
