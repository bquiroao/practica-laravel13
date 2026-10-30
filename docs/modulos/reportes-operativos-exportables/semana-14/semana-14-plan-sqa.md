# Semana 14 – Plan de aseguramiento de la calidad (SQA): filtrado y exportación reproducible de un reporte operativo

Módulo: Reportes operativos exportables · Grupo G1 · Responsable del plan: Joshua Eduardo García Reyes.

## 1. Alcance y objetivo de calidad

Se asegura la calidad de `micro-his-reportes/` y de los documentos de las semanas 11 a 15. En este módulo la calidad significa, en este orden: (1) **un archivo exportado no puede dañar a quien lo abre ni mostrar datos de más** (hospital ajeno, datos personales sin permiso), (2) **la misma exportación produce el mismo archivo** mientras no cambie su origen, (3) **cada requisito tiene evidencia que se pueda volver a ejecutar**. No se aseguran el PDF, la impresión ni la vista previa porque aún no existen (RF-04 y RF-05, hallazgos H-04 y H-05 de la semana 13).

## 2. Métricas (7) — se miden con `php semana-14/medir-sqa.php`

| ID | Métrica | Fórmula y fuente | Verde | Amarillo | Rojo | Frecuencia |
|---|---|---|---|---|---|---|
| M-01 | Pruebas que pasan | PASA / (PASA + FALLA) de todo `tests/*.php` | 100 % | ≥ 90 % | < 90 % | cada cambio |
| M-02 | Requisitos con evidencia | RF-01..RF-10 citados en la matriz de la semana 12, la de la semana 15 o las pruebas | ≥ 9 de 10 | ≥ 7 | < 7 | cada semana |
| M-03 | Hallazgos Alta abiertos | filas «Alta» y «Abierto» del acta de la semana 13 | 0 | 1–2 | ≥ 3 | cada semana |
| M-04 | Violaciones entre capas | misma regla que la prueba ARQ-01 | 0 | — | ≥ 1 | cada cambio |
| M-05 | Archivo fuente más largo | líneas del mayor archivo de `src/` | ≤ 120 | ≤ 200 | > 200 | cada PR |
| M-06 | Estados HTTP de error con prueba | estados de `VistaHttp` (más 422) citados en las pruebas | ≥ 90 % | ≥ 70 % | < 70 % | cada cambio |
| M-07 | Verificadores semanales en PASA | `verificar-prototipo.php`, `demostrar-cambio.php`, `verificar-hallazgos.php` | 100 % | ≥ 67 % | < 67 % | cada semana |

Si el documento de otra semana no está en la rama, la métrica sale **N/D**; el script nunca inventa un valor.

**Línea base** (con las semanas 11 a 13 presentes): M-01 9/9 verde · M-02 10/10 verde · M-03 2 amarillo · M-04 0 verde · M-05 71 verde · M-06 7/7 verde · M-07 3/3 verde → «ninguna métrica en ROJO». Con solo la semana 14 en la rama: M-01, M-04, M-05 y M-06 igual; M-02, M-03 y M-07 salen N/D.

## 3. Matriz de riesgos (probabilidad × impacto, escala 1 a 3)

| ID | Riesgo | P | I | Nivel | Origen | Control |
|---|---|---|---|---|---|---|
| R-01 | Una hoja de cálculo ejecuta una fórmula escondida en una celda exportada | 3 | 3 | **9** | H-02 | CP-01, CD-01 |
| R-02 | Una fecha inválida termina en excepción en lugar de un error controlado | 3 | 2 | **6** | H-01 | CP-02, CD-01 |
| R-03 | Un reporte sale vacío sin aviso por una fecha que no existe | 2 | 2 | 4 | H-03, H-07 | CP-02, CD-01 |
| R-04 | Otro módulo cambia las columnas de una vista y la huella deja de reproducirse | 2 | 3 | **6** | RNF-01 | CP-03, CD-02 |
| R-05 | Se exportan datos de otro hospital o datos personales sin permiso | 1 | 3 | 3 | RNF-02 | CD-03 |
| R-06 | Faltan PDF, vista previa y catálogo y se usa una hoja manual en su lugar | 3 | 1 | 3 | H-04, H-05 | CP-04 |
| R-07 | Una consulta pesada bloquea la base (no hay corte de 30 s) | 2 | 2 | 4 | RNF-03 | CP-05 |
| R-08 | El acta o la matriz dicen algo que el código ya no hace | 2 | 2 | 4 | semana 13 | CD-04 |

Nivel = P × I. Atención inmediata: 9; atención en la semana 15: 6; vigilancia: 4 o menos.

## 4. Controles

| Control | Tipo | Qué hace | Riesgos | Métrica |
|---|---|---|---|---|
| CP-01 | Preventivo | Neutralizar celdas que empiezan con `=`, `+`, `-` o `@` al escribir el CSV. | R-01 | M-03 |
| CP-02 | Preventivo | Validar la fecha contra el calendario real y capturar cualquier error de fecha como 422 con su campo. | R-02, R-03 | M-03 |
| CP-03 | Preventivo | Prueba de contrato que compruebe que cada vista del catálogo trae las columnas declaradas. | R-04 | M-01 |
| CP-04 | Preventivo | Registrar el formato PDF y las rutas de catálogo y vista previa. | R-06 | M-02 |
| CP-05 | Preventivo | Agregar el corte de 30 s a la consulta (RNF-03). | R-07 | M-01 |
| CD-01 | Detectivo | Pruebas de caja negra de la semana 15 para cada hallazgo abierto. | R-01, R-02, R-03 | M-01 |
| CD-02 | Detectivo | Correr `reproducir` sobre las últimas exportaciones registradas y revisar las que no coinciden. | R-04 | M-03 |
| CD-03 | Detectivo | Pruebas existentes DOM-02, REP-01 y ARQ-01 en cada PR. | R-05 | M-01, M-04 |
| CD-04 | Detectivo | `verificar-hallazgos.php` falla si el acta y el código se separan. | R-08 | M-07 |

## 5. Responsables

| Rol | Persona | Responsabilidad |
|---|---|---|
| Dueño del módulo | Joshua García | Corre `medir-sqa.php` cada semana y actualiza el estado de los controles. |
| Revisor de calidad | Compañero de G1 (nombre por completar) | Revisa cada PR contra las métricas antes de aprobarlo. |
| Dueños de las vistas fuente | Responsables de los módulos que publican las vistas (por confirmar) | Avisan antes de cambiar columnas de una vista (CP-03). |

## 6. Calendario

| Semana | Actividad | Resultado esperado |
|---|---|---|
| 14 | Medir la línea base y publicar este plan. | `medir-sqa.php` sin ROJO. |
| 15 | Pruebas unitarias, de caja blanca y de caja negra trazadas a RF/RNF; cubrir H-01, H-02 y H-03. | M-01 100 %, M-02 10 de 10. |
| Después de la 15 | CP-01 y CP-02 en código; CP-03 con los dueños de las vistas; CP-04 y CP-05 si se aprueba el alcance. | M-03 en 0. |

## 7. Evidencia y límites

- Evidencia: `php docs/modulos/reportes-operativos-exportables/semana-14/medir-sqa.php` imprime las siete métricas con su semáforo y termina con código 1 si alguna está en ROJO.
- Límites: las métricas miden lo que el repositorio puede comprobar; no sustituyen una revisión de seguridad completa. M-05 y M-06 son indicadores de mantenibilidad y cobertura de estados, no de cobertura de líneas.
