# Formato de hallazgos de la revisión

## 1. Campos

| Campo | Contenido |
|---|---|
| ID | `RT-NN` (consecutivo de la revisión) |
| Ítem | `C-01` … `C-15` del checklist |
| Archivo y línea | Ruta relativa a `micro-his/` o al documento |
| Descripción | Qué se observó, en una o dos frases verificables |
| Evidencia | Comando y salida, prueba o fragmento |
| Severidad | Ver tabla 2 |
| Tipo | Defecto · Riesgo · Mejora · Pregunta |
| Responsable | Autor (o el dueño del módulo vecino si depende de él) |
| Estado | Abierto · En corrección · Cerrado (con commit/PR) · Descartado (con motivo) |

## 2. Severidad

| Nivel | Criterio | Efecto sobre la salida |
|---|---|---|
| Crítica | Rompe RN-01, RN-02, RN-04, tenant o permisos; o expone datos | Bloquea: nueva revisión obligatoria |
| Mayor | Incumple un RF/RNF o el contrato API, sin pérdida de datos | Debe cerrarse antes de aceptar |
| Menor | Mensaje, documentación o mantenibilidad | Puede quedar con fecha de corrección |
| Observación | Sugerencia sin incumplimiento | No bloquea |

## 3. Plantilla del registro

| ID | Ítem | Archivo y línea | Descripción | Evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|---|
| RT-01 | | | | | | | | |

## 4. Ejemplos preparados por el autor (pre-revisión)

> Detectados por el autor al preparar el paquete. En la reunión los revisores los **confirman o
> descartan**; no sustituyen la revisión.

| ID | Ítem | Archivo y línea | Descripción | Evidencia | Severidad | Tipo | Responsable | Estado |
|---|---|---|---|---|---|---|---|---|
| RT-01 | C-05 | `src/Domain/Modelo/FichaMedicamento.php` (`validar`, bloque de concentración) | Si falta la unidad, no se valida el valor: con «0» solo se informa la unidad. | Hallazgo H-05 de la semana 9 | Mayor | Defecto (D-01) | Autor | Abierto |
| RT-02 | C-07 | `public/index.php` (bloque `catch (ErrorPersistencia)`) | Si la base falla al arrancar, el 503 de la API no lleva `X-Request-Id` ni `request_id`. | `semana-05/README.md` §5 | Menor | Defecto (D-02) | Autor | Abierto |
| RT-03 | C-13 | `RepositorioMedicamentosPdo::siguienteCodigo` | Dos altas simultáneas calculan el mismo código; la segunda recibe 409 `CONFLICTO_CONCURRENTE` aunque el usuario no hizo nada mal. | Lectura del código y restricción `uq_medicamentos_codigo_version` | Menor | Defecto (D-03) | Autor | Abierto |
| RT-04 | C-15 | `src/Persistence/UsoPrescripcionesPdo.php` | Lee una tabla de **simulación**; falta el contrato real con `prescripciones-alergias`. | `semana-04/README.md` §5 | Mayor | Riesgo | Dueño de `prescripciones-alergias` | Abierto |
