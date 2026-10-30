# Plan SQA — Catálogo de pruebas de laboratorio

## 1. Objetivo y alcance

Asegurar que «alta y consulta de prueba con requisitos de muestra y vigencia» cumpla RF-01 a RF-08 y RNF-01 a RNF-06, con atención especial a lo que pone en riesgo una orden de laboratorio: una prueba mal versionada, requisitos de muestra incorrectos o un catálogo visible para el hospital equivocado.

**Alcance:** Micro-HIS (`micro-his/`), contrato API, documentación del módulo y su integración con Órdenes por API. **Fuera:** módulos ajenos.

## 2. Roles

| Rol | Persona | Qué hace |
|---|---|---|
| Responsable de calidad del módulo | Erick Ramazzini | Mide las métricas, mantiene la matriz de riesgos y abre los defectos D-NN |
| Revisor de PR | Boris Quiroa (bquiroao) | Aprueba o rechaza cada PR según los controles de la sección 5 |
| Moderador de revisión técnica | Según el plan de la semana 13 | Conduce la revisión formal y verifica cierres |

## 3. Métricas

| ID | Métrica | Fórmula | Umbral | Fuente | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|---|
| M-01 | Pruebas automáticas que pasan | correctas ÷ total, excluyendo las marcadas `[DEFECTO D-NN]` | 100 % | `php pruebas/ejecutar.php` (`evidencia/salida-pruebas.txt`) | Erick | No se abre el PR hasta corregir o registrar el defecto |
| M-02 | Reglas cubiertas por pruebas | reglas RN-01 a RN-08 con al menos una prueba ÷ 8 | 100 % | Matriz RF/RNF → prueba (semana 15) | Erick | Agregar la prueba que falta antes del siguiente PR |
| M-03 | Defectos mayores abiertos | número de D-NN de severidad mayor sin corregir | 0 al cierre de cada parcial; ninguno con más de 7 días abierto | Registro de defectos (semana 15) y revisión técnica (RT-NN) | Erick | Priorizar la corrección sobre trabajo nuevo |
| M-04 | Tamaño de PR | líneas insertadas (`git diff --stat origin/develop...HEAD`, sin .png) | Menos de 400 | Salida del comando en la descripción del PR | Erick | Partir el PR en A, B… |
| M-05 | Coherencia contrato–código | estados HTTP del contrato con prueba ÷ estados del contrato | 100 % | `contrato-api.md` frente a T-13 a T-16 | Erick | Agregar la prueba o corregir el contrato |
| M-06 | Hallazgos de accesibilidad de severidad alta abiertos | H-NN de severidad alta sin corregir | 0 antes de la demostración final | Backlog de la semana 9 | Erick | Corregir antes de agregar pantallas nuevas |

## 4. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; nivel = P × I (1–2 bajo, 3–4 medio, 6–9 alto).

| ID | Riesgo | P | I | Nivel | Control |
|---|---|---|---|---|---|
| R-01 | Dos versiones de la misma prueba vigentes a la vez | 1 | 3 | Medio | CP-01, CD-01 |
| R-02 | La versión nueva se guarda pero la anterior no se cierra (o al revés) | 1 | 3 | Medio | CP-02, CD-02 |
| R-03 | Un hospital ve o modifica el catálogo de otro | 1 | 3 | Medio | CP-03, CD-03 |
| R-04 | Un valor que el dominio acepta y la base rechaza produce 500 en vez de 422 | 2 | 2 | Medio | CP-04, CD-04 |
| R-05 | Órdenes usa una regla de vigencia distinta y acepta pruebas no vigentes | 2 | 3 | Alto | CP-05, CD-05 |
| R-06 | Cambio incompatible en el JSON rompe a Órdenes | 2 | 2 | Medio | CP-06, CD-05 |
| R-07 | PR mayor de 400 líneas bloqueado por la validación del repositorio | 2 | 1 | Bajo | CP-07 |
| R-08 | Requisitos de muestra mal capturados (tipo o volumen) | 2 | 3 | Alto | CP-08, CD-06 |

## 5. Controles

### Preventivos

| ID | Control |
|---|---|
| CP-01 | Regla RN-05 en `RegistrarPrueba` y restricción `UNIQUE (tenant_id, code, version)` |
| CP-02 | Transacción única en `guardarNuevaVersion` con rollback |
| CP-03 | Tenant tomado del token; todas las consultas con `tenant_id` |
| CP-04 | Límites del dominio iguales a los de las columnas (revisión C-06) |
| CP-05 | Órdenes lee vigentes solo por la API (etapa 1, semana 5) |
| CP-06 | Contrato versionado (`/v1`); campos nuevos solo opcionales |
| CP-07 | Plan de PR por semana con líneas estimadas antes de empezar |
| CP-08 | Listas cerradas y confirmación antes de guardar (H-01, H-03) |

### Detectivos

| ID | Control |
|---|---|
| CD-01 | Pruebas T-02, T-03 y T-09 en cada ejecución |
| CD-02 | Prueba T-08 (rollback en base real) |
| CD-03 | Prueba T-09 con datos de dos hospitales y T-14 (401/403) |
| CD-04 | Pruebas de caja negra de límites (semana 15) |
| CD-05 | Pruebas T-13 a T-16 contra el contrato y revisión C-08/C-09 |
| CD-06 | Revisión del Jefe de laboratorio en la confirmación (W-04) y prueba T-04 |

## 6. Calendario de seguimiento

| Cuándo | Qué se revisa | Métricas / controles | Evidencia |
|---|---|---|---|
| En cada PR | Pruebas, tamaño y descripción completa | M-01, M-04 | Descripción del PR |
| Cada semana | Defectos abiertos y riesgos de nivel alto | M-03, R-05, R-08 | Esta matriz actualizada |
| Al cerrar las semanas 5 y 15 | Coherencia contrato–código y cobertura de reglas | M-02, M-05 | Matriz RF/RNF → prueba |
| Antes de la revisión técnica (semana 13) | Criterios de entrada E-1 a E-5 | M-01, M-02 | `plan-revision.md` |
| Antes de cada evaluación parcial | Defectos mayores y accesibilidad | M-03, M-06 | Backlog de la semana 9 y registro de defectos |
