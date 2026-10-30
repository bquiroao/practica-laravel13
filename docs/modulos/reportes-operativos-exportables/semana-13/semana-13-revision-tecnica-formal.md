# Semana 13 – Revisión técnica formal: filtrado y exportación reproducible de un reporte operativo

Módulo: Reportes operativos exportables · Grupo G1 · Autor del objeto revisado: Joshua Eduardo García Reyes.

## 1. Plan de la revisión

| Campo | Contenido |
|---|---|
| Objeto revisado | `micro-his-reportes/` completo: `Filtros`, `Csv`, `Archivo`, `ExportarReporte`, `ReportesPdo`, `Enrutador`/`ReportesControlador`/`VistaHttp`, `sql/esquema.sql`. Versión de `develop` del día de la revisión. |
| Objetivo | Encontrar defectos de requisitos, seguridad y robustez **antes** de las pruebas de la semana 15. |
| Documentos de referencia | `semana-02/semana-02-ocp-requerimientos-diseno.md` (RF-01..RF-10, RNF-01..RNF-06, CA-01..CA-11). |
| Duración | Preparación individual de 30 min y reunión de 45 min. Máximo 200 líneas de código por hora de lectura. |
| Entregables | Esta acta, `verificar-hallazgos.php` y la lista de acciones con responsable. |

## 2. Roles

| Rol | Persona | Función |
|---|---|---|
| Autor | Joshua García | Presenta el objeto, responde dudas y corrige; no modera ni decide severidades. |
| Moderador | Compañero de G1 (nombre por completar antes de la reunión) | Conduce, controla el tiempo y exige criterio de salida. |
| Revisor técnico | Compañero de G1 (nombre por completar antes de la reunión) | Lee con la checklist y propone severidad. |
| Secretario | Compañero de G1 (nombre por completar antes de la reunión) | Registra hallazgos con el formato del punto 5. |

## 3. Criterios de entrada y de salida

| Entrada (todos deben cumplirse) | Salida (todos deben cumplirse) |
|---|---|
| Las 9 pruebas de `tests/ejecutar.php` pasan. | Cada ítem de la checklist tiene ✔ o ✖ con hallazgo. |
| El objeto no cambia durante la revisión. | Cada hallazgo tiene severidad, responsable y límite. |
| Cada revisor leyó los RF/RNF antes de la reunión. | Ningún hallazgo de severidad Alta queda sin responsable. |
| Existe acta en blanco con este formato. | `php semana-13/verificar-hallazgos.php` termina en `PASA`. |

Severidad: **Alta** = puede filtrar o falsear datos, o terminar sin respuesta controlada; **Media** = viola un requisito sin daño inmediato; **Baja** = robustez o ruido sin efecto en los datos.

## 4. Checklist (14 ítems)

| Ítem | Pregunta | Resultado |
|---|---|---|
| C-01 | ¿El objeto revisado y su versión están identificados? | ✔ |
| C-02 | ¿Cada RF-01..RF-10 tiene una ruta o prueba, o aparece como pendiente? | ✖ H-04 |
| C-03 | ¿El hash y los filtros se calculan en un solo lugar (RNF-05)? | ✔ |
| C-04 | ¿Toda fecha recibida termina en error controlado (422) y nunca en excepción? | ✖ H-01 |
| C-05 | ¿Una fecha que no existe en el calendario se rechaza con su campo? | ✖ H-03 |
| C-06 | ¿Las celdas del CSV son seguras al abrirse en una hoja de cálculo? | ✖ H-02 |
| C-07 | ¿Cada exportación se registra antes de entregarse y un fallo no entrega archivo (RF-07)? | ✔ |
| C-08 | ¿Hospital y enmascarado se aplican antes de generar el archivo (RF-09, RNF-02)? | ✔ |
| C-09 | ¿Una entrada mal formada produce error controlado y no avisos de PHP? | ✖ H-06 |
| C-10 | ¿El mapa código de error → HTTP está en un solo lugar? | ✔ |
| C-11 | ¿El Dominio no depende de otras capas (ARQ-01)? | ✔ |
| C-12 | ¿SQL preparado, identificadores validados y sin credenciales en el repositorio? | ✔ |
| C-13 | ¿El rango máximo coincide con la definición del requisito (92 días)? | ✖ H-07 |
| C-14 | ¿Los formatos prometidos por RF-05 (CSV y PDF) están disponibles? | ✖ H-05 |

## 5. Hallazgos (formato: ID, descripción, ubicación, requisito, severidad, responsable, límite, estado)

| ID | Descripción | Ubicación | Requisito | Severidad | Responsable | Límite | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | Con `desde=2026-13-45` la expresión regular acepta el texto y `DateTimeImmutable` lanza una excepción que nadie captura: no hay respuesta 422 con campo. | `Filtros::desde` | RF-02, RF-10 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-02 | Una celda que empieza con `=`, `+`, `-` o `@` sale tal cual en el CSV: al abrirlo en Excel se ejecuta como fórmula (inyección de fórmulas). | `Csv::generar` | RNF-02 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-03 | `desde=2026-02-31` pasa la validación, responde 200 y el reporte sale vacío sin avisar. | `Filtros::desde` | RF-02 | Media | Autor (Joshua García) | Semana 15 | Abierto |
| H-04 | No hay ruta del catálogo ni de la vista previa paginada: `GET /api/v1/reportes` y `GET /api/v1/reportes/censo` responden 404. | `Enrutador` | RF-01, RF-04 | Media | Autor (Joshua García) | Después de la semana 15 | Abierto |
| H-05 | Solo existe CSV: `formato=pdf` y `formato=json` responden 400; falta el PDF (e impresión) que piden RF-05 y el enunciado del módulo. | `ExportarReporte`, `index.php` | RF-05 | Media | Autor (Joshua García) | Después de la semana 15 | Abierto |
| H-06 | Con `desde` como arreglo, PHP emite «Array to string conversion»; con `display_errors` activo el aviso se mezcla con la respuesta. | `Filtros::desde` | RF-10 | Baja | Autor (Joshua García) | Semana 15 | Abierto |
| H-07 | El máximo de 92 días se mide como diferencia: 2026-07-25 a 2026-10-25 son 93 días calendario y se aceptan. | `Filtros::desde` | RF-02 | Baja | Autor (Joshua García) | Semana 15 | Abierto |

Evidencia de cada hallazgo: `verificar-hallazgos.php` lo reproduce contra el código real (SQLite en memoria) y falla si el acta y el código dejan de coincidir.

## 6. Resultado de la revisión

Decisión: **aprobado con cambios**. Los siete hallazgos están abiertos: H-01 y H-02 (Alta) se atienden primero porque terminan sin respuesta controlada o pueden ejecutar una fórmula en la computadora de quien abre el archivo; la semana 15 los cubre con pruebas de caja negra y deja constancia del estado de cada uno. Cuando se corrija un hallazgo se pasa a «Cerrado» en esta tabla y el script confirma que ya no se reproduce.

## 7. Cómo reproducir

```
php docs/modulos/reportes-operativos-exportables/semana-13/verificar-hallazgos.php
```
