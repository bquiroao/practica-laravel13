# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX · **Documento:** [semana-01-uml-navegacion-dashboard.md](semana-01-uml-navegacion-dashboard.md)

## 1. Resumen en 60 segundos

> «Sin token y sin hospital no se entra: el sistema manda a iniciar sesión. Con sesión, el usuario ve el layout y un menú con solo lo que su rol permite. Cada tarjeta del resumen carga por su cuenta: primero un esqueleto, después el conteo, o “vacío” si no hay datos, o un error con “Reintentar” si falla o pasan 10 s. Un error en una tarjeta no tumba a las demás. Si la sesión expira, se limpia y vuelve al login.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| `<<extend>>` para reintentar (CU-04) | Solo ocurre bajo una condición (tarjeta en error) y si el usuario lo pide. |
| Vacío ≠ error (R-05) | «No hay citas hoy» es una respuesta correcta; tratarla como error asusta y confunde. |
| Una petición por tarjeta (R-03) | Si falla una fuente, las otras tarjetas se siguen viendo. |
| Solo conteos (R-06) | El dashboard lo ve mucha gente: ningún dato que identifique a un paciente. |

## 3. Preguntas probables

**¿Qué pasa si la sesión expira mientras cargan las tarjetas?** La API responde 401 (S8), el store ejecuta `clearSession()` (S13) y el router vuelve a `/login` (E3 → F4, R-07).

**¿Por qué el reintento no es automático?** Un bucle automático sobrecarga un servidor que ya está fallando; el usuario decide (R-08).

**¿Qué falta en el código para implementarlo?** Marcar las rutas con `requiresAuth` (hoy ninguna lo usa) y crear el endpoint de conteo por tarjeta.
