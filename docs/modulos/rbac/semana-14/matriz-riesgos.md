# Matriz de riesgos — RBAC

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable» · **Plan:** [plan-sqa-rbac.md](plan-sqa-rbac.md)

## 1. Escalas

| Valor | Probabilidad (P) | Impacto (I) en RBAC |
|:-:|---|---|
| 1 | Muy improbable: requiere varias fallas simultáneas | Cosmético |
| 2 | Improbable: hay una defensa y una prueba que la cubre | Molestia operativa sin efecto en el acceso |
| 3 | Posible: hay evidencia de que puede ocurrir (hallazgo abierto, caso sin prueba) | Datos inconsistentes o evidencia incompleta, sin acceso indebido |
| 4 | Probable: ocurre en uso normal o en un despliegue estándar | Se pierde evidencia de una denegación, **o** se bloquea el trabajo clínico |
| 5 | Casi seguro | **Acceso indebido** a datos clínicos o **salto de tenant** |

**Nivel = P × I.**

| Nivel | Puntaje | Qué exige |
|---|---|---|
| **Alto** | 12 o más | Tratamiento obligatorio con fecha |
| **Medio** | 6–11 | Control activo y seguimiento semanal |
| **Bajo** | 5 o menos | Se acepta con monitoreo |

La probabilidad se asigna **con evidencia**:
- un hallazgo abierto o una métrica en rojo la suben;
- una prueba y un control activo la bajan.

## 2. Matriz

| ID | Riesgo | Causa / evidencia | P | I | Nivel | Controles preventivos | Controles detectivos | Métrica | Responsable | Tratamiento |
|---|---|---|:-:|:-:|:-:|---|---|---|---|---|
| RS-01 | **Escalamiento de privilegios**: una regla defectuosa permite una operación | Error en una regla o excepción que deja pasar (fail-open). El mutante 1 lo simula y se detecta. | 2 | 5 | **10 Medio** | CP-01, CP-02, CP-04 | CD-01, CD-02, CD-07 | M1, M5 | Responsable del módulo | Mitigar: mantener M5 = 100 % y los mutantes detectados |
| RS-02 | **Salto de tenant**: un token de un hospital opera en otro | `X-Tenant-ID` distinto del hospital del token | 2 | 5 | **10 Medio** | CP-02, CP-04 | CD-01, CD-02 (escenario en M4), CD-04 (consulta 2) | M4 | Revisor de seguridad | Mitigar: HTTP-07, PEF-05, paridad 31/38 |
| RS-03 | **Autenticación de demostración en un despliegue** | AR-01: `crearKernel` siempre usa tokens estáticos (reproducido: 201 con `token-admin-oriente`) | 3 | 5 | **15 Alto** | CP-05 (pendiente) | CD-02 (M6), revisión RT-10 | M6 | Responsable del módulo | **Mitigar en S15**: autenticador por entorno; sin `MICROHIS_ENTORNO=desarrollo` → 401 |
| RS-04 | **Denegación sin evidencia**: fallan la bitácora y su respaldo | AR-04: `catch` vacío y `@mkdir` silencioso | 2 | 4 | **8 Medio** | CP-04 | CD-05 (pendiente), CD-04 (consulta 4) | M4, M6 | Responsable del módulo | **Mitigar en S15**: `error_log` + comprobar el respaldo al arrancar |
| RS-05 | **Membresías duplicadas** con fecha de fin | AR-02: R-03 solo cubre membresías abiertas (reproducido: 201 y 201) | 3 | 3 | **9 Medio** | CP-01, CP-03 | CD-02 (M6), CD-04 (consulta 5) | M6 | Responsable del módulo | **Mitigar en S15**: detectar solapamiento o `Idempotency-Key` (B-5) |
| RS-06 | **Contrato divergente**: otros módulos integran contra algo que el código no hace | Cambio en `Rutas` o en una vista sin actualizar `openapi.yaml` | 2 | 4 | **8 Medio** | CP-02, CP-07 | CD-01 (API-01..03), CD-03 (lint + paridad) | M3 | Responsable del módulo | Mitigar: M3 = 100 % y lint en cada PR |
| RS-07 | **Filtración** de motivo, rol o permiso en el 403 | Vista nueva que no use `ErrorVista` | 1 | 5 | **5 Bajo** | CP-02 (ARQ-07: solo las Vistas dan formato) | CD-01 (HTTP-03, REV-06), CD-06 (EC-4 sin motivo) | M1 | Revisor de seguridad | Aceptar con monitoreo |
| RS-08 | **PDP o base de datos no disponibles** bloquean el trabajo clínico | Fail-closed es correcto para la seguridad, pero deja sin operar | 3 | 4 | **12 Alto** | Cliente con timeout, reintento y circuit breaker (S5); UI sin cola (S10) | CD-02 (M7), monitoreo del registro de acceso (duración, 5xx) | M7 | Responsable del módulo + equipo del HIS | **Aceptar con mitigación**: medir M7 con PostgreSQL en S15. Procedimiento de emergencia («romper el vidrio») propuesto para el HIS, fuera del alcance del módulo. |
| RS-09 | **Regresión no detectada en persistencia real** | CON-PDO e INT-00..05 omitidas: sin PostgreSQL configurado | 4 | 3 | **12 Alto** | CP-02 | CD-01 (M1: omitidas ≤ 2) | M1 | Responsable del módulo | **Mitigar en S15**: base de pruebas PostgreSQL y correr INT/CON-PDO |
| RS-10 | **Pruebas débiles**: afirman el código HTTP pero no el código de negocio | M2 = 88.9 %: `USUARIO_INACTIVO` y `USUARIO_NO_ENCONTRADO` sin afirmar | 3 | 3 | **9 Medio** | CP-01 | CD-02 (M2), CD-07 | M2 | Responsable del módulo | **Mitigar en S15**: afirmar los 2 códigos |
| RS-11 | **Sondeo entre hospitales sin rastro** por permisos efectivos | AR-03 (residual del cambio B-6) | 2 | 2 | **4 Bajo** | CP-03 | CD-04 (consulta 2, solo cubre `/decisiones`) | M6 (Menor) | Revisor de seguridad | Aceptar con monitoreo; corregir junto con RS-04 |
| RS-12 | **Dependencia no declarada** (`mbstring`) rompe un despliegue | AR-05 | 2 | 3 | **6 Medio** | CP-06 (README de requisitos) | CD-01 (la suite falla sin la extensión) | — | Responsable del módulo | Mitigar en S15: sección de requisitos |

## 3. Mapa de calor (situación de la línea base)

| P \ I | 1 | 2 | 3 | 4 | 5 |
|:-:|:-:|:-:|:-:|:-:|:-:|
| **5** | | | | | |
| **4** | | | RS-09 | | |
| **3** | | | RS-05, RS-10 | RS-08 | **RS-03** |
| **2** | | RS-11 | RS-12 | RS-04, RS-06 | RS-01, RS-02 |
| **1** | | | | | RS-07 |

**Lectura:**

| Nivel | Riesgos |
|---|---|
| **Alto** (requieren tratamiento con fecha) | RS-03 (tokens de demostración), RS-08 (disponibilidad del PDP), RS-09 (persistencia sin probar) |
| **Medio** | 7 riesgos, todos con un control activo o con una corrección planificada para S15 |
| **Bajo** | 2 riesgos (RS-07, RS-11), aceptados con monitoreo |

## 4. Riesgo residual esperado tras la S15

Supone que se ejecutan las acciones del [plan §7](plan-sqa-rbac.md#7-plan-de-acción-de-la-línea-base):

| ID | Hoy | Tras S15 | Por qué baja |
|---|:-:|:-:|---|
| RS-03 | 15 | 5 (P1 × I5) | La configuración impide los tokens de demostración fuera de desarrollo, con una prueba que lo fija |
| RS-09 | 12 | 6 (P2 × I3) | INT y CON-PDO se ejecutan con PostgreSQL |
| RS-08 | 12 | 8 (P2 × I4) | M7 medido con base de datos y el cliente con circuit breaker. El riesgo es inherente a fail-closed y no baja de Medio. |
| RS-05 | 9 | 3 (P1 × I3) | La regla de solapamiento tiene prueba |
| RS-04 | 8 | 4 (P1 × I4) | La falla doble se registra y el respaldo se comprueba al arrancar |
| RS-10 | 9 | 3 (P1 × I3) | M2 = 100 % |
