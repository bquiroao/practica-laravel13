# Semana 13 – Revisión técnica formal: captura de signos vitales, evaluación de umbrales y generación de alerta

Módulo: Signos vitales y alertas clínicas · Grupo G1 · Autor del objeto revisado: Joshua Eduardo García Reyes.

## 1. Plan de la revisión

| Campo | Contenido |
|---|---|
| Objeto revisado | `micro-his-signos-vitales/` completo: `RegistrarToma`, `Toma`, reglas de umbral, `TomasPdo`, `Enrutador`/`TomasControlador`/`VistaJson`, `sql/esquema.sql`. Versión de `develop` del día de la revisión. |
| Objetivo | Encontrar defectos de requisitos, seguridad clínica y robustez **antes** de las pruebas de la semana 15. |
| Documentos de referencia | `semana-02/semana-02-lsp-requerimientos-diseno.md` (RF-01..RF-10, RNF-01..RNF-07, CA). |
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

Severidad: **Alta** = puede ocultar un valor peligroso o dañar datos clínicos; **Media** = viola un requisito sin daño inmediato; **Baja** = robustez o ruido sin efecto clínico.

## 4. Checklist (14 ítems)

| Ítem | Pregunta | Resultado |
|---|---|---|
| C-01 | ¿El objeto revisado y su versión están identificados? | ✔ |
| C-02 | ¿Cada RF-01..RF-10 tiene prueba o aparece como pendiente? | ✖ H-04 |
| C-03 | ¿Los límites físicos están definidos en un solo lugar? | ✔ |
| C-04 | ¿Todo valor crítico termina como alerta para cualquier edad (RNF-04)? | ✖ H-02 |
| C-05 | ¿Se validan los signos entre sí (la diastólica no supera a la sistólica)? | ✖ H-01 |
| C-06 | ¿Toma, mediciones y alertas van en una transacción con rollback probado (RNF-01)? | ✔ |
| C-07 | ¿Rol y hospital se validan antes de tocar datos (RF-02, RNF-02)? | ✔ |
| C-08 | ¿Una entrada mal formada produce error controlado y no avisos de PHP? | ✖ H-06 |
| C-09 | ¿Repetir la misma petición evita duplicar tomas y alertas? | ✖ H-03 |
| C-10 | ¿El mapa código de error → HTTP está en un solo lugar? | ✔ |
| C-11 | ¿El Dominio no depende de otras capas (ARQ-01)? | ✔ |
| C-12 | ¿SQL preparado y sin credenciales en el repositorio? | ✔ |
| C-13 | ¿Las rutas responden igual con o sin barra final? | ✖ H-07 |
| C-14 | ¿Las alertas críticas llegan a su destino y se reintentan (RF-07)? | ✖ H-05 |

## 5. Hallazgos (formato: ID, descripción, ubicación, requisito, severidad, responsable, límite, estado)

| ID | Descripción | Ubicación | Requisito | Severidad | Responsable | Límite | Estado |
|---|---|---|---|---|---|---|---|
| H-01 | Se guarda una toma con PAS 80 y PAD 120: la diastólica supera a la sistólica y nadie lo advierte. | `Toma::registrar` | RF-04 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-02 | Un niño de 5 años con FC 250 se guarda como «sin rango» y **no genera alerta**. Solo hay rangos de 18 a 120 años. | `sql/esquema.sql`, `EvaluadorUmbrales` | RNF-04 | Alta | Autor (Joshua García) | Semana 15 | Abierto |
| H-03 | Enviar dos veces la misma petición crea dos tomas (y alertas duplicadas). No hay clave de idempotencia. | `RegistrarToma::ejecutar` | RNF-01 | Media | Autor (Joshua García) | Semana 15 | Abierto |
| H-04 | No existe el caso de uso ni las columnas para reconocer una alerta una sola vez (quién y cuándo). | `src/Aplicacion`, `sql/esquema.sql` | RF-09 | Media | Autor (Joshua García) | Después de la semana 15 | Abierto |
| H-05 | Las alertas críticas quedan «pendiente_envio»; ningún código las envía a M21 ni las pasa a «enviada». | `TomasPdo` | RF-07 | Media | Autor (Joshua García) | Después de la semana 15 | Abierto |
| H-06 | Con `paciente_id` como arreglo JSON, PHP emite «Array to string conversion»; con `display_errors` activo el aviso se mezcla con la respuesta. | `RegistrarToma::ejecutar` | RNF-07 | Baja | Autor (Joshua García) | Semana 15 | Abierto |
| H-07 | `POST /api/v1/signos-vitales/tomas/` (barra final) responde 404 aunque la ruta existe. | `Enrutador` | RNF-07 | Baja | Autor (Joshua García) | Semana 15 | Abierto |

Evidencia de cada hallazgo: `verificar-hallazgos.php` lo reproduce contra el código real (SQLite en memoria) y falla si el acta y el código dejan de coincidir.

## 6. Resultado de la revisión

Decisión: **aprobado con cambios**. Los siete hallazgos están abiertos: H-01 y H-02 (Alta) se atienden primero porque afectan la seguridad clínica; la semana 15 los cubre con pruebas de caja negra y deja constancia del estado de cada uno. Cuando se corrija un hallazgo se pasa a «Cerrado» en esta tabla y el script confirma que ya no se reproduce.

## 7. Cómo reproducir

```
php docs/modulos/signos-vitales-alertas/semana-13/verificar-hallazgos.php
```
