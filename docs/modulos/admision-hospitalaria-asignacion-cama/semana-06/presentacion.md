---
marp: true
paginate: true
title: Admisión hospitalaria y asignación de cama — primer parcial
---

# Admisión hospitalaria y asignación de cama

**Primer parcial · Sistema Hospitalario Integrado 2026**

Erick Rolando Ramazzini Muralles · @ErickRamazzini

Flujo defendido: *admisión de paciente y asignación de una cama disponible*

---

## 1. Problema, actores y frontera

- **Objetivo:** el paciente queda con UNA admisión activa y UNA cama compatible; nadie más se queda con esa cama.
- **Actores:** Admisionista y Supervisor(a) de camas (admiten), Médico (solo consulta), Pacientes y Catálogo de camas (sistemas, solo lectura).
- **Casos de uso:** CU-01 admitir y asignar · CU-02 validar paciente · CU-03 camas disponibles · CU-04 trazabilidad · CU-05 otra cama ante conflicto · CU-06 consultar.
- **Frontera:** el catálogo de camas es de Josué y los datos del paciente son de Pacientes. Admisión solo escribe en `admision`, `asignacion_cama` y `evento_admision`.

---

## 2. Requisitos y reglas

| Regla | Requisito | Criterio |
|---|---|---|
| RN-01 una cama, una asignación activa | RF-04, RF-05, RNF-01 | CA-07 |
| RN-02 un paciente, una admisión activa | RF-02 | CA-04 |
| RN-03 cama compatible · RN-04 operativa y del mismo hospital | RF-03 | CA-05, CA-06, CA-09 |
| RN-05 eventos inmutables | RF-06, RF-08 | CA-11 |
| RN-06 solo admisionista y supervisor admiten | RNF-02 | CA-08 |

Paciente **solo por código** `PAC-NNNNN` (RNF-03).

---

## 3. Principio SOLID: OCP en la compatibilidad

- **Antes:** `AsignadorCamas::esElegible()` con `if`/`switch`. Cada criterio nuevo obligaba a modificarlo, y solo devolvía `true` o `false`.
- **Después:** `EvaluadorElegibilidad` depende de la interfaz `CriterioElegibilidadCama`. Cada criterio es una clase: operativa, servicio, tipo y aislamiento.
- **Evidencia:** `verificar-ocp.php` da el mismo resultado antes y después, el motivo de cada rechazo y un criterio nuevo sin tocar el evaluador. Lo mismo se repite en `prueba-elegibilidad.php`.

---

## 4. Capas y Repository

- **Presentation** (consola, web MVC y API) → **Application** (casos de uso) → **Domain** (reglas e interfaces Repository) ← **Persistence** (PDO e InMemory).
- El controlador no tiene SQL ni reglas, y `prueba-dependencias.php` lo verifica.
- **Prueba de contrato:** las mismas 5 verificaciones contra PDO e InMemory.
- `bootstrap.php` es el único lugar que elige las clases concretas.

---

## 5. Concurrencia: dos admisiones, una cama

1. Las dos ven `B-202` libre: la verificación previa es solo un aviso temprano.
2. Las dos confirman: el índice único parcial `ux_asignacion_cama_activa` deja pasar solo una.
3. La otra recibe `409 CAMA_YA_ASIGNADA`, se revierte todo y elige otra cama (CU-05).
4. Si la cama está bloqueada por otra transacción, la espera máxima es de 2 s y luego se responde `409 CONFLICTO_CONCURRENCIA`, que es reintentable.

Probado con **dos conexiones reales** a PostgreSQL 16.

---

## 6. API v1 y frontera de microservicio

- `GET /camas-disponibles` · `POST /admisiones` · `GET /admisiones/{id}` · `GET /admisiones/{id}/eventos`
- Token Bearer: el rol y el **hospital salen del token**; un `tenant_id` en el cuerpo responde 400.
- Formato único de error con `codigo`, `mensaje`, `reintentable` y `request_id`; `X-Request-Id` en cada respuesta.
- **No se divide en microservicio:** no hay gatillo medible (G-1 a G-4). Plan por etapas E0 a E4 con outbox.

---

## 7. Trazabilidad clínica y cambio práctico

- Cada admisión deja `ADMISION_CREADA` y `CAMA_ASIGNADA` con usuario, rol, hospital e id de solicitud. Un trigger impide modificarlos.
- **Cambio preparado:** reserva temporal de cama (RN-07) mientras se confirma.
  - Un criterio nuevo, `CriterioCamaNoReservada` (OCP), y una tabla con índice único propio.
  - Sin tocar `EvaluadorElegibilidad` ni la garantía de RN-01.
- Detalle en `cambio-practico.md`.
