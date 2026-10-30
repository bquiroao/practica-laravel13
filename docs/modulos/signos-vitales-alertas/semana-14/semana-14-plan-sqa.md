# Semana 14 – Plan de aseguramiento de la calidad (SQA): captura de signos vitales, evaluación de umbrales y generación de alerta

Módulo: Signos vitales y alertas clínicas · Grupo G1 · Responsable del plan: Joshua Eduardo García Reyes.

## 1. Alcance y objetivo de calidad

Se asegura la calidad de `micro-his-signos-vitales/` y de los documentos de las semanas 11 a 15. En este módulo la calidad significa, en este orden: (1) **ningún valor peligroso queda sin alerta**, (2) **ningún dato clínico se guarda a medias**, (3) **cada requisito tiene evidencia que se pueda volver a ejecutar**. No se aseguran el envío real a M21 ni el reconocimiento de alertas porque aún no existen (RF-07 y RF-09, hallazgos H-04 y H-05 de la semana 13).

## 2. Métricas (7) — se miden con `php semana-14/medir-sqa.php`

| ID | Métrica | Fórmula y fuente | Verde | Amarillo | Rojo | Frecuencia |
|---|---|---|---|---|---|---|
| M-01 | Pruebas que pasan | PASA / (PASA + FALLA) de todo `tests/*.php` | 100 % | ≥ 90 % | < 90 % | cada cambio |
| M-02 | Requisitos con evidencia | RF-01..RF-10 citados en la matriz de la semana 12, la de la semana 15 o las pruebas | ≥ 9 de 10 | ≥ 7 | < 7 | cada semana |
| M-03 | Hallazgos Alta abiertos | filas «Alta» y «Abierto» del acta de la semana 13 | 0 | 1–2 | ≥ 3 | cada semana |
| M-04 | Violaciones entre capas | misma regla que la prueba ARQ-01 | 0 | — | ≥ 1 | cada cambio |
| M-05 | Archivo fuente más largo | líneas del mayor archivo de `src/` | ≤ 120 | ≤ 200 | > 200 | cada PR |
| M-06 | Estados HTTP de error con prueba | estados de `VistaJson` (más 422) citados en las pruebas | ≥ 90 % | ≥ 70 % | < 70 % | cada cambio |
| M-07 | Verificadores semanales en PASA | `verificar-prototipo.php`, `demostrar-cambio.php`, `verificar-hallazgos.php` | 100 % | ≥ 67 % | < 67 % | cada semana |

Si el documento de otra semana no está en la rama, la métrica sale **N/D**; el script nunca inventa un valor.

**Línea base** (con las semanas 11 a 13 presentes): M-01 9/9 verde · M-02 10/10 verde · M-03 2 amarillo · M-04 0 verde · M-05 71 verde · M-06 8/8 verde · M-07 3/3 verde → «ninguna métrica en ROJO». Con solo la semana 14 en la rama: M-01, M-04, M-05 y M-06 igual; M-02, M-03 y M-07 salen N/D.

## 3. Matriz de riesgos (probabilidad × impacto, escala 1 a 3)

| ID | Riesgo | P | I | Nivel | Origen | Control |
|---|---|---|---|---|---|---|
| R-01 | Un paciente pediátrico con valor peligroso no genera alerta | 3 | 3 | **9** | H-02 | CP-01, CD-01 |
| R-02 | Se guardan signos incoherentes (diastólica mayor que sistólica) | 2 | 3 | **6** | H-01 | CP-02, CD-01 |
| R-03 | Un reintento duplica tomas y alertas | 3 | 2 | **6** | H-03 | CP-03, CD-01 |
| R-04 | Una alerta crítica nunca llega a M21 | 2 | 3 | **6** | H-05 | CP-04, CD-02 |
| R-05 | Un cambio de umbrales clínicos rompe clasificaciones sin avisar | 2 | 3 | **6** | RNF-04 | CP-05, CD-03 |
| R-06 | El acta o la matriz dicen algo que el código ya no hace | 2 | 2 | 4 | semana 13 | CD-04 |
| R-07 | Alguien de otro hospital ve o registra tomas | 1 | 3 | 3 | RNF-02 | CD-03 |
| R-08 | Una falla a mitad de guardado deja datos parciales | 1 | 3 | 3 | RNF-01 | CD-03 |

Nivel = P × I. Atención inmediata: 9; atención en la semana 15: 6; vigilancia: 4 o menos.

## 4. Controles

| Control | Tipo | Qué hace | Riesgos | Métrica |
|---|---|---|---|---|
| CP-01 | Preventivo | Cargar rangos pediátricos con aprobación clínica antes de aceptar tomas de menores. | R-01 | M-03 |
| CP-02 | Preventivo | Validar relación entre signos (PAD < PAS) en `Toma::registrar`. | R-02 | M-03 |
| CP-03 | Preventivo | Clave de idempotencia por toma. | R-03 | M-03 |
| CP-04 | Preventivo | Proceso de envío con reintento que pase la alerta a «enviada». | R-04 | M-03 |
| CP-05 | Preventivo | Todo cambio de umbral exige la matriz de la semana 12 y pasar la prueba de contrato de las reglas. | R-05 | M-02 |
| CD-01 | Detectivo | Pruebas de caja negra de la semana 15 para cada hallazgo abierto. | R-01, R-02, R-03 | M-01 |
| CD-02 | Detectivo | Revisar cada semana la cola «pendiente_envio» (cuando exista el proceso). | R-04 | M-03 |
| CD-03 | Detectivo | Pruebas existentes APP-02, PER-01 y ARQ-01 en cada PR. | R-05, R-07, R-08 | M-01, M-04 |
| CD-04 | Detectivo | `verificar-hallazgos.php` falla si el acta y el código se separan. | R-06 | M-07 |

## 5. Responsables

| Rol | Persona | Responsabilidad |
|---|---|---|
| Dueño del módulo | Joshua García | Corre `medir-sqa.php` cada semana y actualiza el estado de los controles. |
| Revisor de calidad | Compañero de G1 (nombre por completar) | Revisa cada PR contra las métricas antes de aprobarlo. |
| Aprobación clínica de rangos | Docente / asesor clínico (por confirmar) | Autoriza los rangos pediátricos (CP-01) y cualquier cambio de umbral. |

## 6. Calendario

| Semana | Actividad | Resultado esperado |
|---|---|---|
| 14 | Medir la línea base y publicar este plan. | `medir-sqa.php` sin ROJO. |
| 15 | Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF; cubrir H-01, H-02 y H-03. | M-01 100 %, M-02 10 de 10. |
| Después de la 15 | CP-02 y CP-03 en código; CP-01 con aprobación clínica; CP-04 cuando exista M21. | M-03 en 0. |

## 7. Evidencia y límites

- Evidencia: `php docs/modulos/signos-vitales-alertas/semana-14/medir-sqa.php` imprime las siete métricas con su semáforo y termina con código 1 si alguna está en ROJO.
- Límites: las métricas miden lo que el repositorio puede comprobar; no sustituyen la validación clínica. M-05 y M-06 son indicadores de mantenibilidad y cobertura de estados, no de cobertura de líneas.
