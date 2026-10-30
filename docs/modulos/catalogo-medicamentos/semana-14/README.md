# Semana 14 — Catálogo de medicamentos: plan SQA (confiabilidad y aseguramiento de calidad)

Alcance: calidad de «alta, búsqueda y control de vigencia», confiabilidad, seguridad, trazabilidad y
riesgos de medicamentos, presentaciones, vigencia, búsqueda y unicidad del catálogo.

| Entregable | Sección |
|---|---|
| 7 métricas con responsable, umbral, fuente y acción | 1 |
| Matriz de riesgos | 2 |
| Controles preventivos y detectivos | 3 |
| Responsables | 4 |
| Calendario de seguimiento | 5 |
| Medición reproducible de la métrica M-02 | `evidencia/medir-trazabilidad.php` |

## 1. Métricas

| ID | Métrica | Umbral | Fuente | Frecuencia | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|---|
| M-01 | Pruebas del Micro-HIS en verde | 100 % (0 fallas; los `[DEFECTO]` abiertos se cuentan aparte) | `php pruebas/ejecutar.php` | Cada PR de código | Autor | No se abre el PR hasta corregir |
| M-02 | Requisitos (RN, CA, RNF) con al menos una prueba que los nombra | ≥ 90 % (línea base: **78 %**) | `evidencia/medir-trazabilidad.php` | Semanal | Autor | Agregar pruebas a los que faltan (semana 15) |
| M-03 | p95 de la búsqueda con 5 000 versiones (RNF-03) | ≤ 300 ms | Medición de la semana 15 / registro JSON (`ms`) | Semana 15 y cada mes en el HIS | Autor | Índice `pg_trgm`; si sigue alto, gatillo G1 (semana 5) |
| M-04 | Respuestas 5xx de la API | ≤ 0,5 % de las peticiones por semana | Registro JSON de `KernelApi` (`estado`) | Semanal en el HIS | Líder técnico | Revisar `request_id` de cada 5xx; abrir defecto |
| M-05 | Defectos críticos abiertos (revisión técnica, pruebas, usabilidad alta) | 0 antes de cada parcial y de integrar al HIS | Acta de la RTF (S13), `semana-09/backlog.md`, defectos D-NN | Cada PR y cada parcial | Moderador de la RTF | Bloquea la integración |
| M-06 | PR dentro del límite de tamaño | 100 % de los PR < 400 líneas | `git diff --stat origin/develop...HEAD` | Cada PR | Autor | Partir en A, B, C… |
| M-07 | Hallazgos WCAG de impacto alto abiertos | 0 en el prototipo y en la UI integrada | `semana-09/hallazgos.md` + verificación con Playwright | Cada cambio de UI | Autor | No se presenta la pantalla en el parcial |

Línea base de M-02 obtenida al preparar la entrega: RN 6 de 6, CA 12 de 13 (falta CA-12), RNF 3 de 8
(faltan RNF-03, 05, 06, 07 y 08); total 21 de 27 = **78 %**. Para repetirla:

```powershell
cd docs/modulos/catalogo-medicamentos/semana-14/evidencia
php medir-trazabilidad.php --evidencia=trazabilidad.txt
cd ../../../../..
```

## 2. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; nivel = P × I (≥ 6 alto, 3–4 medio, ≤ 2 bajo).

| ID | Riesgo | P | I | Nivel | Controles (sección 3) |
|---|---|---|---|---|---|
| R-01 | Dos versiones vigentes del mismo código (RN-01) | 1 | 3 | 3 medio | CP-01, CP-02, CD-01 |
| R-02 | Se elimina un medicamento usado en prescripciones (RN-02) | 2 | 3 | 6 **alto** | CP-03, CD-02 |
| R-03 | Duplicado vigente con otro nombre (RN-04) | 2 | 2 | 4 medio | CP-01, CP-02, CD-01 |
| R-04 | Un usuario ve o cambia datos de otro hospital | 1 | 3 | 3 medio | CP-04, CD-03 |
| R-05 | La búsqueda devuelve versiones no vigentes o trata `%`/`_` como comodines | 1 | 3 | 3 medio | CP-05, CD-01 |
| R-06 | El contrato con `prescripciones-alergias` no se acuerda a tiempo | 3 | 2 | 6 **alto** | CP-06, CD-04 |
| R-07 | La búsqueda no cumple RNF-03 con datos reales | 2 | 2 | 4 medio | CD-05 |
| R-08 | El esquema real de HOSPITAL difiere de la propuesta | 3 | 2 | 6 **alto** | CP-07 |
| R-09 | Tokens o credenciales ficticias llegan a un ambiente real | 1 | 3 | 3 medio | CP-08, CD-06 |
| R-10 | Regresión de accesibilidad al integrar la UI en Vue | 2 | 2 | 4 medio | CP-09, CD-07 |

## 3. Controles

| ID | Tipo | Control | Cubre |
|---|---|---|---|
| CP-01 | Preventivo | Reglas en el dominio (`Medicamento`, `ClaveEquivalencia`) | R-01, R-03 |
| CP-02 | Preventivo | Restricciones `EXCLUDE` y `UNIQUE` en la base | R-01, R-03 |
| CP-03 | Preventivo | Puerto `ConsultaUsoPrescripciones` + confirmación escrita en la UI; propuesta de FK `ON DELETE RESTRICT` | R-02 |
| CP-04 | Preventivo | Tenant tomado solo del token; `hospital_id` del cuerpo se descarta | R-04 |
| CP-05 | Preventivo | `ESCAPE '!'` y la misma semántica en InMemory | R-05 |
| CP-06 | Preventivo | Reunión con el dueño de `prescripciones-alergias` antes de integrar; contrato por escrito | R-06 |
| CP-07 | Preventivo | Interfaz Repository: un cambio de esquema solo toca el adaptador | R-08 |
| CP-08 | Preventivo | Tokens solo en `config.php` de desarrollo; en el HIS, autenticación real y variables de entorno | R-09 |
| CP-09 | Preventivo | Criterios WCAG en la definición de terminado de cada pantalla | R-10 |
| CD-01 | Detectivo | Pruebas de dominio, contrato PDO = InMemory e integración (M-01) | R-01, R-03, R-05 |
| CD-02 | Detectivo | Prueba CA-11 (con dobles y con base) en cada PR | R-02 |
| CD-03 | Detectivo | Pruebas de API de tenant y permisos (CA-12, RNF-02) | R-04 |
| CD-04 | Detectivo | Ítem C-15 de la revisión técnica | R-06 |
| CD-05 | Detectivo | Medición de p95 (M-03) | R-07 |
| CD-06 | Detectivo | Revisión del PR: «No hay credenciales reales» del checklist final | R-09 |
| CD-07 | Detectivo | Verificación con Playwright: foco, contraste y desplazamiento a 375 px (M-07) | R-10 |

## 4. Responsables

| Rol | Persona | Qué asegura |
|---|---|---|
| Autor y dueño del módulo | Erick Rolando Ramazzini Muralles | M-01, M-02, M-03, M-06, M-07; corrige defectos |
| Líder técnico / moderador | Boris Quiroa (propuesta) | M-04, M-05; revisa y une los PR |
| Revisores | Josué Morales, Joshua García (propuesta) | Revisión técnica (S13), revisión cruzada de PR |
| Dueño de `prescripciones-alergias` | Por confirmar | Contrato de uso (R-06) |

## 5. Calendario de seguimiento

| Cuándo | Qué se revisa | Quién | Registro |
|---|---|---|---|
| Cada PR | M-01, M-06; checklist del PR | Autor → líder | Descripción del PR |
| Semana 14 | Línea base de M-02 (78 %) | Autor | `evidencia/trazabilidad.txt` |
| Semana 15 | M-01, M-02 (meta ≥ 90 %), M-03 (p95), defectos D-NN | Autor | `semana-15/` |
| Antes de integrar al HIS | M-05 = 0, M-07 = 0, R-06 y R-08 resueltos | Moderador | Acta |
| Mensual en el HIS | M-03, M-04 | Líder técnico | Tablero del registro JSON |
