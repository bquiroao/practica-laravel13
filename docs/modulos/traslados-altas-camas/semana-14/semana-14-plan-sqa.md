# Semana 14 – Plan de aseguramiento de la calidad (SQA): traslado o alta del paciente con liberación consistente de cama

Módulo: Traslados, altas y camas · Grupo G1 · Responsable del plan: Joshua Eduardo García Reyes.

## 1. Alcance y objetivo de calidad

Se asegura la calidad de `micro-his-traslados/` y de los documentos de las semanas 11 a 15. En este módulo la calidad significa, en este orden: (1) **una cama, un paciente**: nunca dos pacientes en la misma cama ni una cama ocupada sin paciente, (2) **un movimiento se hace completo o no se hace** (origen, destino, orden y episodio), (3) **cada requisito tiene evidencia que se pueda volver a ejecutar**. No se aseguran el historial de camas, el aviso a limpieza ni la salida temporal porque aún no existen (RF-06, RF-07 y RF-08, hallazgo H-04 de la semana 13).

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

**Línea base** (con las semanas 11 a 13 presentes): M-01 10/10 verde · M-02 10/10 verde · M-03 2 amarillo · M-04 0 verde · M-05 54 verde · M-06 8/8 verde · M-07 3/3 verde → «ninguna métrica en ROJO». Con solo la semana 14 en la rama: M-01, M-04, M-05 y M-06 igual; M-02, M-03 y M-07 salen N/D.

## 3. Matriz de riesgos (probabilidad × impacto, escala 1 a 3)

| ID | Riesgo | P | I | Nivel | Origen | Control |
|---|---|---|---|---|---|---|
| R-01 | Dos pacientes quedan en la misma cama porque el estado dice «disponible» | 3 | 3 | **9** | H-01 | CP-01, CD-02 |
| R-02 | Dos enfermeras mandan a la vez a dos pacientes al mismo destino y ganan las dos | 2 | 3 | **6** | H-02 | CP-02, CD-02 |
| R-03 | Una cama liberada queda en mantenimiento y nadie avisa a limpieza | 3 | 2 | **6** | H-04 | CP-03 |
| R-04 | Una orden con datos inválidos o un tipo mal escrito tumba el movimiento sin respuesta clara | 2 | 2 | 4 | H-03, H-05 | CP-04, CD-01 |
| R-05 | El historial se desordena por fechas en dos formatos | 2 | 2 | 4 | H-06 | CP-05 |
| R-06 | Un movimiento queda a medias (origen libre, destino sin ocupar o episodio abierto) | 1 | 3 | 3 | RNF-01 | CD-03 |
| R-07 | Un rol sin permiso registra un movimiento | 1 | 3 | 3 | RNF-03 | CD-03 |
| R-08 | El acta o la matriz dicen algo que el código ya no hace | 2 | 2 | 4 | semana 13 | CD-04 |

Nivel = P × I. Atención inmediata: 9; atención en la semana 15: 6; vigilancia: 4 o menos.

## 4. Controles

| Control | Tipo | Qué hace | Riesgos | Métrica |
|---|---|---|---|---|
| CP-01 | Preventivo | Antes de aplicar, comprobar que el destino no tiene una estancia abierta (`ended_at IS NULL`), además de su estado. | R-01 | M-03 |
| CP-02 | Preventivo | Escribir el destino con condición (`WHERE status = 'available'`), revisar filas afectadas dentro de la transacción y, en PostgreSQL, bloquear la fila o apoyarse en la exclusión GiST de `bed_stays`. | R-02 | M-03 |
| CP-03 | Preventivo | Registrar el aviso a limpieza con reintento sin deshacer el movimiento (RF-07). | R-03 | M-02 |
| CP-04 | Preventivo | `CHECK` en `tipo_alta` y `requisito_cama`; validar `tipo` de entrada con 422. | R-04 | M-03 |
| CP-05 | Preventivo | Un solo formato de fecha para toda estancia. | R-05 | M-01 |
| CD-01 | Detectivo | Pruebas de caja negra de la semana 15 para cada hallazgo abierto. | R-04 | M-01 |
| CD-02 | Detectivo | Consulta de consistencia (RNF-02) tras cada movimiento y una vez al día: camas con más de una estancia abierta o «ocupada» sin estancia abierta. | R-01, R-02 | M-03 |
| CD-03 | Detectivo | Pruebas existentes PER-01, APP-03 y ARQ-01 en cada PR. | R-06, R-07 | M-01, M-04 |
| CD-04 | Detectivo | `verificar-hallazgos.php` falla si el acta y el código se separan. | R-08 | M-07 |

La consulta de CD-02 es: `SELECT bed_id, COUNT(*) FROM bed_stays WHERE ended_at IS NULL GROUP BY bed_id HAVING COUNT(*) > 1`.

## 5. Responsables

| Rol | Persona | Responsabilidad |
|---|---|---|
| Dueño del módulo | Joshua García | Corre `medir-sqa.php` cada semana y actualiza el estado de los controles. |
| Revisor de calidad | Compañero de G1 (nombre por completar) | Revisa cada PR contra las métricas antes de aprobarlo. |
| Responsable de la base PostgreSQL | Módulo de Verificación BD (por confirmar) | Confirma que la exclusión GiST de `bed_stays` respalda CP-02. |

## 6. Calendario

| Semana | Actividad | Resultado esperado |
|---|---|---|
| 14 | Medir la línea base y publicar este plan. | `medir-sqa.php` sin ROJO. |
| 15 | Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF; cubrir H-01, H-02 y H-03. | M-01 100 %, M-02 10 de 10. |
| Después de la 15 | CP-01 y CP-02 en código; CP-04 y CP-05 en el esquema; CP-03 cuando se defina el servicio de limpieza. | M-03 en 0. |

## 7. Evidencia y límites

- Evidencia: `php docs/modulos/traslados-altas-camas/semana-14/medir-sqa.php` imprime las siete métricas con su semáforo y termina con código 1 si alguna está en ROJO.
- Límites: las métricas miden lo que el repositorio puede comprobar; la concurrencia real (R-02) solo se prueba contra PostgreSQL, no con SQLite. M-05 y M-06 son indicadores de mantenibilidad y cobertura de estados, no de cobertura de líneas.
