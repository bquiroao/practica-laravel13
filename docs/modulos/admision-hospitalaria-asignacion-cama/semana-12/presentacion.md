---
marp: true
paginate: true
title: Admisión hospitalaria y asignación de cama — segundo parcial
---

# Admisión hospitalaria y asignación de cama

**Segundo parcial · componentes, UX, accesibilidad y movilidad**

Erick Rolando Ramazzini Muralles · @ErickRamazzini

---

## 1. Componentes y el refactor elegido

- Frontend (propuesta Vue 3): `FormularioRequerimiento`, `ListaCamasDisponibles`, `ConfirmacionAdmision`, `AvisoConflictoCama` y `ResultadoAdmision`, todos con contratos de props y eventos.
- Backend: `ApiKernel` → casos de uso → dominio → repositorios.
- Refactor: **«cama disponible»** estaba repartida en tres lugares; ahora queda en `DisponibilidadCamas`. RN-01 sigue en el índice único.

---

## 2. UX por rol y estados

- Admisionista: datos → cama → **confirmar** → resultado.
- Supervisor(a): lo mismo, más la trazabilidad. Médico: solo consulta, sin acciones que no puede usar.
- Estados diseñados: carga, vacío, éxito, error recuperable y error no recuperable.
- Datos protegidos: solo `PAC-NNNNN`, el hospital lo fija la sesión y «no encontrado» no revela otros hospitales.

---

## 3. Accesibilidad: de la evaluación al prototipo

- Se evaluó la web **real** del Micro-HIS: 8 hallazgos (H-01 a H-08) y su backlog B-01 a B-08.
- Contraste calculado con la fórmula WCAG: todos los textos están entre 6.02:1 y 14.76:1, y el foco tiene 4.68:1.
- El prototipo aplica B-01 a B-07: confirmación, validación temprana, `aria-invalid`, foco gestionado, conflicto que nombra la cama y sin doble envío.

---

## 4. Movilidad (320 a 430 px)

- Tres pasos, una decisión por pantalla, tarjetas en lugar de tablas y áreas táctiles de 44 px o más.
- Conexión limitada: **no se reintenta el POST solo**; «Verificar estado» usa RN-02 para saber la verdad.
- Defecto D-03 (barra fija sobre el campo «Origen») detectado en la captura de 375 px y corregido.

---

## 5. Demostración: roles, estados, errores y riesgos

1. Camino feliz: PAC-00451 → B-201 → confirmar → ACTIVA.
2. **Error crítico:** otra admisión toma B-201 → aviso con el nombre de la cama → B-202 → éxito.
3. Cambio de rol a Médico: solo consulta. Supervisor(a): trazabilidad.
4. Riesgo residual: el prototipo **simula** el conflicto; la garantía real es `ux_asignacion_cama_activa`.

---

## 6. Cambio solicitado

- Caso preparado: **«Mostrar en la lista cuántas camas quedan por servicio y avisar cuando queden 2 o menos»**.
- Impacto: un componente de UI y un dato más en `GET /camas-disponibles` (`total` ya existe); sin reglas nuevas.
- Detalle y registro de la defensa en `cambio-solicitado.md`.
