# Semana 13 — Calidad del software y revisión técnica formal de Pacientes

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 · Pacientes: registro, edición, búsqueda y detalle

**Proceso revisado:** «registro, búsqueda y actualización segura de un paciente», en el Micro-HIS (contrato 1.2.0)

## Entregables

| Entregable pedido | Archivo |
|---|---|
| Plan de revisión | [plan-revision-tecnica.md](plan-revision-tecnica.md): objetivo, tipo (inspección), paquete en 3 sesiones, proceso, calendario, reglas de la reunión y métricas |
| Checklist de 12–15 ítems | [checklist-revision.md](checklist-revision.md): 15 ítems verificables (RT-01..RT-15) en 4 perspectivas |
| Roles de autor, moderador y revisores | [Plan §3](plan-revision-tecnica.md#3-roles-y-responsabilidades): autor, moderador, registrador y 3 revisores por perspectiva |
| Criterios de entrada y salida | [Plan §6 y §7](plan-revision-tecnica.md#6-criterios-de-entrada): E-1..E-8 (6 automatizados) y S-1..S-5, con la regla de decisión |
| Formato de hallazgos | [formato-hallazgos.md](formato-hallazgos.md) + [registro-hallazgos.csv](plantillas/registro-hallazgos.csv) + [acta](plantillas/acta-revision.md) |

Además, la [autorrevisión del autor](autorrevision-autor.md) prueba el checklist y el formato con **7 hallazgos reales**, cada uno reproducido contra el kernel.

## 1. Lo que cubre la revisión

| Foco que pide la tarea | Ítems |
|---|---|
| Reglas de dominio | RT-01 (validación), RT-02 (orden único de las reglas), RT-04 (edición segura) |
| Contrato API | RT-06 (contrato = código), RT-07 (errores sin filtrar), RT-08 (validación en la frontera) |
| Permisos / tenant | RT-09 (permiso por operación, fail-closed), RT-10 (tenant en todo dato devuelto), RT-11 (identidad del actor) |
| Integridad de identidad y datos demográficos | RT-01, RT-03 (duplicados), RT-05 (ficha + evento atómicos) |
| Búsqueda y edición | RT-04, RT-12 (protegidos fuera de la búsqueda por nombre) |
| Duplicados | RT-03, RT-07, RT-10 |
| Confidencialidad | RT-07, RT-12, RT-13 (documento protegido) |
| Dependencias estrictamente necesarias | RT-14 (capas), RT-15 (extensiones, recursos sin límite) |

Cada ítem es una afirmación que se responde **Sí / No / N/A**. Dice **dónde mirar** y **cómo verificar** con una prueba o un comando, y trae una severidad por defecto.

## 2. Criterios de entrada: automatizados y probados

```text
$ php herramientas/criterios-entrada.php
PASA   E-1   Paquete congelado en un commit y sin cambios locales        commit 6698035
PASA   E-2   Suite automatizada sin fallas                               Total 87 · Pasan 85 · Fallan 0 · Omitidas 2
PASA   E-3   Guardas de contrato (API-01..03) y de capas (ARQ-01..08)    11 guardas en verde
PASA   E-4   Cada sesión ≤ 1600 líneas efectivas                         S1: 957 · S2: 865 · S3: 1284
PASA   E-5   Checklist de 12–15 ítems y formato publicados               15 ítems
PASA   E-6   Autorrevisión registrada con el mismo formato               7 en el CSV · 7 en el documento
MANUAL E-7/E-8                                                           el moderador los anota en el acta
```

**La puerta rechaza de verdad.**
- La primera versión del paquete juntaba dominio, aplicación y persistencia en **1822 líneas** y no pasaba E-4: se rearmó en tres sesiones.
- Antes de escribir el checklist y la autorrevisión, la corrida falló en E-5 y E-6 ([antes](evidencia/criterios-entrada-1-antes.txt) → [después](evidencia/criterios-entrada-2-despues.txt)).

## 3. Resultado de la autorrevisión

| ID | Hallazgo | Ítem | Severidad |
|---|---|---|---|
| AR-01 | La autenticación de demostración no se puede desactivar fuera de desarrollo | RT-11 | Mayor |
| **AR-02** | **El 409 por documento revela el código y el documento enmascarado de un paciente de otro hospital** | RT-07, RT-10 | **Crítico** |
| AR-03 | Los candidatos de `POSIBLE_DUPLICADO` incluyen pacientes de otros hospitales | RT-10 | Mayor |
| AR-04 | Con el DPI se confirma que una paciente protegida está en el hospital | RT-12 | Mayor |
| AR-05 | `mbstring` y `sodium` se usan y no están declaradas | RT-15 | Menor |
| AR-06 | Las claves de idempotencia no vencen | RT-15 | Menor |
| AR-07 | El DPI no valida el dígito verificador ni el municipio | RT-01 | Menor |

**Densidad:** 2.25 hallazgos por KLOC (Crítico + Mayor + Menor). Es la **línea base** contra la que se mide la revisión del equipo.

Lo más relevante: **AR-02 y AR-03 no los detectaba ninguna de las 87 pruebas.** La suite de contrato comprueba que el bloqueo por documento funciona **entre** hospitales (`CON-*-04`), y las pruebas de tenant miran el detalle y la búsqueda, pero ninguna mira **qué devuelve** el 409 a otro hospital. Es justamente el tipo de defecto que una revisión con checklist encuentra y una suite construida por el mismo autor no ve.

## 4. Cómo se ejecuta la revisión

1. Autor y moderador congelan el commit y corren `php herramientas/criterios-entrada.php`.
2. Apertura de 15 minutos y envío del material con ≥ 48 h de anticipación.
3. Cada revisor prepara su perspectiva (≤ 2 h por sesión) y anota hallazgos en el CSV y su tiempo en `registro-preparacion.csv`.
4. Tres reuniones de ≤ 90 minutos (S1, S2, S3): se recorren RT-01..RT-15, se registran hallazgos y severidad, no se discuten soluciones.
5. El autor hace el retrabajo con una prueba por corrección. El moderador verifica y cierra con S-1..S-5 y el acta.

## 5. Limitaciones

- **La revisión se prepara, no se ejecuta.** Los nombres del moderador y los revisores los define el grupo (E-8), y el acta queda como plantilla.
- **El autor no puede revisarse a sí mismo con objetividad:** la autorrevisión es la línea base, no el resultado de la inspección.
- **RT-05 con PostgreSQL** solo se revisa por lectura (INT-00 omitida).
