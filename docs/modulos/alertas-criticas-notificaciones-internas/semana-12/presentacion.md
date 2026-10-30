---
marp: true
paginate: true
title: Alertas críticas y notificaciones internas — Segundo parcial
---

# Alertas críticas y notificaciones internas
## Segundo parcial · ASII 2026
Erick Rolando Ramazzini Muralles · @ErickRamazzini
Componentes, UX, accesibilidad y movilidad

---

## 1. Componentes y refactor
- Frontend: Bandeja, Tarjeta, Detalle, BotónAcuse, FormularioCierre, Historial, AvisoEscalamiento, ClienteApi.
- Backend en 4 capas, con contratos de entrada y salida por componente.
- Refactor: la decisión «terminar / subir nivel / agotar» pasa a ser una regla pura del dominio.

---

## 2. UX por rol
- Enfermería acusa; médico y jefe cierran (RF-08); coordinación recibe los agotados.
- Estados: carga, vacío, éxito, error recuperable y sin permiso.
- Mensajes con la regla y la acción de recuperación.

---

## 3. Accesibilidad (semana 9)
- 16 criterios evaluados; contraste calculado con la fórmula WCAG.
- 8 hallazgos priorizados; el prototipo ya corrige H-01, H-03, H-04, H-07 y H-08.
- Foco corregido: 2.35:1 → contorno oscuro con halo, 16.24:1.

---

## 4. Movilidad (semana 10)
- 320–430 px: tarjetas, una acción primaria, historial plegado, áreas táctiles de 44 px.
- Sin conexión: no se marca acusada sin confirmación; reintentar es seguro (RN-06).

---

## 5. Demostración con el prototipo
- Camino feliz: enfermería acusa → médico cierra con nota y confirmación.
- Error crítico: acuse sin conexión → la alerta sigue escalada → reintento.
- Permisos: jefe en una alerta de nivel 0 → RN-07.

---

## 6. Riesgos y cambio solicitado
- Riesgos abiertos: D-03 (cierre por rol en la web MVC) y CSRF en formularios.
- Cambio preparado: «mostrar el contador de alertas pendientes en el título de la pestaña».
- Matriz decisión → RF/RNF → evidencia en `matriz-decision-rf-evidencia.md`.
