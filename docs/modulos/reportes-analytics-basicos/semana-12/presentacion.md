---
marp: true
paginate: true
title: Reportes y analytics básicos · Segunda evaluación parcial
---

# Reportes y analytics básicos
## Componentes, UX, accesibilidad y movilidad

Erick Rolando Ramazzini Muralles · ASII 2026 · Segunda evaluación parcial

---

# 1. Componentes (semana 7)

- Backend Laravel: controlador delgado → acción → dominio → repositorios de solo lectura.
- Frontend Vue: vista, filtros, tarjeta, ficha, desglose, registros y un **formateador único**.
- Refactor: la precisión salió del controlador y de la vista; el desglose viaja como **conteos enteros**, así siempre suma el total (RN-07).

---

# 2. Experiencia por rol (semana 8)

| Rol | Ve | Diferencia |
|---|---|---|
| Administrador y Jefe de área | Los 10 reportes, 3 niveles | Flujo completo |
| Médico | Solo sus citas | Filtro de médico fijo (RN-08) |

Estados diseñados: carga, vacío («Sin datos», nunca cero), éxito, error recuperable y sin permiso.

---

# 3. Accesibilidad (semana 9)

8 hallazgos, todos corregidos en el prototipo de la semana 11:

- Tablas con `caption` y `scope` (WCAG 1.3.1) y bordes con contraste 4.54:1 (WCAG 1.4.11)
- Una sola precisión en todas las vistas y ficha con etiquetas legibles
- «Reintentar» que conserva los filtros y fechas validadas junto al campo
- Migas entre niveles y foco que pasa al resultado

---

# 4. Movilidad (semana 10)

- 320–430 px: una idea por pantalla; el valor primero.
- Desglose y registros como tarjetas; botones de 44 px.
- Sin conexión: el número se queda con su hora de cálculo; nunca se presenta un dato viejo como actual.

---

# 5. Riesgos y cómo se tratan

| Riesgo | Tratamiento |
|---|---|
| Un número sin origen | Ficha + desglose + registros (RN-05, RN-07) |
| Precisión distinta según la vista | Formateador único |
| Exponer datos de pacientes | Solo código (RN-09) |
| Fuente caída | Error claro con código de seguimiento y reintento |
| Rol sin permiso | 403 sin datos parciales |
