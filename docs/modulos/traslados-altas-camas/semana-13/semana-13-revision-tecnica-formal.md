# Semana 13 – Revisión técnica formal: traslado o alta del paciente con liberación consistente de cama

Módulo: Traslados, altas y camas · Grupo G1 · Autor del objeto revisado: Joshua Eduardo García Reyes.

## 1. Plan de la revisión

| Campo | Contenido |
|---|---|
| Objeto revisado | `micro-his-traslados/` completo: `Traslado`, `Alta`, `RegistrarMovimiento`, `MovimientosPdo`, `Enrutador`/`MovimientosControlador`/`VistaJson`, `sql/esquema.sql`. Versión de `develop` del día de la revisión. |
| Objetivo | Encontrar defectos de consistencia de camas, robustez y requisitos **antes** de las pruebas de la semana 15. |
| Documentos de referencia | `semana-02/semana-02-lsp-requerimientos-diseno.md` (RF-01..RF-10, RNF-01..RNF-06, CA-01..CA-10). |
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
| Las 10 pruebas de `tests/ejecutar.php` pasan. | Cada ítem de la checklist tiene ✔ o ✖ con hallazgo. |
| El objeto no cambia durante la revisión. | Cada hallazgo tiene severidad, responsable y límite. |
| Cada revisor leyó los RF/RNF antes de la reunión. | Ningún hallazgo de severidad Alta queda sin responsable. |
| Existe acta en blanco con este formato. | `php semana-13/verificar-hallazgos.php` termina en `PASA`. |

Severidad: **Alta** = puede dejar a dos pacientes en una cama o una cama sin dueño; **Media** = viola un requisito sin daño inmediato; **Baja** = robustez o ruido sin efecto en las camas.

## 4. Checklist (14 ítems)

| Ítem | Pregunta | Resultado |
|---|---|---|
| C-01 | ¿El objeto revisado y su versión están identificados? | ✔ |
| C-02 | ¿Cada RF-01..RF-10 tiene ruta o prueba, o aparece como pendiente? | ✖ H-04 |
| C-03 | ¿El estado de la cama destino se vuelve a comprobar dentro de la escritura (RNF-01)? | ✖ H-02 |
| C-04 | ¿Se comprueba el destino contra las estancias abiertas y no solo contra su estado (RNF-02)? | ✖ H-01 |
| C-05 | ¿Un dato mal escrito se distingue de una orden que no existe? | ✖ H-03 |
| C-06 | ¿Una orden con datos inválidos termina en error controlado? | ✖ H-05 |
| C-07 | ¿Ocupar, liberar y cerrar episodio van en una transacción con rollback probado? | ✔ |
| C-08 | ¿Rol y guardas se validan antes de tocar datos (RNF-03)? | ✔ |
| C-09 | ¿Una entrada mal formada produce error controlado y no avisos de PHP? | ✖ H-07 |
| C-10 | ¿El mapa código de error → HTTP está en un solo lugar? | ✔ |
| C-11 | ¿El Dominio no depende de otras capas (ARQ-01)? | ✔ |
| C-12 | ¿SQL preparado y sin credenciales en el repositorio? | ✔ |
| C-13 | ¿Las fechas se guardan siempre en un solo formato? | ✖ H-06 |
| C-14 | ¿Los cinco códigos de rechazo de RF-09 existen en la vista y tienen prueba? | ✔ |

## 5. Hallazgos (formato: ID, descripción, ubicación, requisito, severidad, responsable, límite, estado)

| ID | Descripción | Ubicación | Requisito | Severidad | Responsable | Límite | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | Una cama con estado «available» pero con un paciente adentro (nada sincroniza `beds.status` con `bed_stays`) se acepta como destino: el traslado responde 201 y la cama queda con dos estancias abiertas. | `RegistrarMovimiento::destino`, `Traslado` | RNF-02 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-02 | `MovimientosPdo::aplicar` ocupa el destino sin volver a comprobar su estado dentro de la transacción: dos movimientos simultáneos al mismo destino podrían ganar los dos. | `MovimientosPdo::aplicar` | RNF-01 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-03 | `tipo` distinto de `traslado` o `alta` responde 409 `orden_requerida`, como si faltara la orden, en lugar de un error de validación. | `RegistrarMovimiento::ejecutar` | RF-09 | Media | Autor (Joshua García) | Semana 15 | Abierto |
| H-04 | Solo existe `POST /movimientos`: no hay historial de camas, aviso a limpieza ni salida temporal. | `Enrutador`, `sql/esquema.sql` | RF-06, RF-07, RF-08 | Media | Autor (Joshua García) | Después de la semana 15 | Abierto |
| H-05 | El esquema no limita `tipo_alta` ni `requisito_cama`; una orden con `tipo_alta = 'fuga'` hace fallar a `TipoAlta::from` con una excepción que nadie captura. | `sql/esquema.sql`, `RegistrarMovimiento::movimiento` | RF-03, RF-09 | Media | Autor (Joshua García) | Semana 15 | Abierto |
| H-06 | Las fechas se guardan en dos formatos: `2026-10-20 08:00` en los datos y `2026-10-25T12:00:00+00:00` al registrar un movimiento; ordenar o comparar como texto da resultados distintos. | `MovimientosPdo::aplicar` | RNF-02 | Baja | Autor (Joshua García) | Semana 15 | Abierto |
| H-07 | Con `episodio_id` como arreglo JSON, PHP emite «Array to string conversion»; con `display_errors` activo el aviso se mezcla con la respuesta. | `RegistrarMovimiento::ejecutar` | RNF-06 | Baja | Autor (Joshua García) | Semana 15 | Abierto |

Evidencia de cada hallazgo: `verificar-hallazgos.php` lo reproduce contra el código real (SQLite en memoria) y falla si el acta y el código dejan de coincidir.

## 6. Resultado de la revisión

Decisión: **aprobado con cambios**. Los siete hallazgos están abiertos: H-01 y H-02 (Alta) se atienden primero porque rompen el invariante central del módulo (una cama, un paciente); la semana 15 los cubre con pruebas de caja negra y deja constancia del estado de cada uno. Cuando se corrija un hallazgo se pasa a «Cerrado» en esta tabla y el script confirma que ya no se reproduce. En PostgreSQL, la exclusión GiST de `bed_stays` del módulo de Verificación BD impediría H-01 y H-02 a nivel de base; este Micro-HIS, con SQLite, no la tiene.

## 7. Cómo reproducir

```
php docs/modulos/traslados-altas-camas/semana-13/verificar-hallazgos.php
```
