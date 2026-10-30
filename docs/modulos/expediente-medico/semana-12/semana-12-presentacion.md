---
marp: true
paginate: true
title: Semana 12 — Defensa del prototipo del Expediente médico electrónico base
---

# Expediente médico electrónico base
## Segunda evaluación parcial: componentes, UX, accesibilidad y movilidad

Josué David Morales Ramírez · Análisis de Sistemas II · UMG

---

## 1. Componentes y recorrido

- Lista de pacientes → expediente con episodios → historia de otros hospitales; admisión crea expedientes.
- Recorrido de 10 pasos en el prototipo, con capturas de escritorio y móvil.
- El panel cambia el rol; los datos son ficticios.

---

## 2. Roles, estados y errores

| | Cómo se ve |
|---|---|
| Roles | El tratante consulta; admisión crea y no ve episodios |
| Estados | Atención activa, consentimiento vigente o ausente, emergencia con hora de cierre |
| Errores | Sin atención (E1), sin consentimiento (E5), justificación corta (E6) |

---

## 3. Accesibilidad, movilidad y datos sensibles

- Documento «terminado en 4521» y nombre abreviado hasta «Mostrar nombre».
- Foco en el `<h1>`, títulos distintos y «Saltar al contenido».
- Tarjetas en móvil; **sin scroll horizontal** a 320, 360 y 430 px.

---

## 4. Cambio solicitado: acceso de emergencia

**Pedido de emergencias:** ver las alergias de otro hospital de un paciente inconsciente, sin consentimiento (CU-04, R-07).

- «Es una emergencia» como opción secundaria; «Volver» sigue siendo la principal.
- Justificación de 20 caracteres como mínimo, con contador (B-05).
- Acceso de 1 hora, auditado y con revisión pendiente.

Matriz y evidencia: [semana-12-defensa-prototipo.md](semana-12-defensa-prototipo.md).
