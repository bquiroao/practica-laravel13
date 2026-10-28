# Registro de defectos — pruebas de la semana 15

**Estudiante:** Boris Alexander Quiroa Orellana · **Formato:** el de la [revisión técnica](../semana-13/formato-hallazgos.md) · **Fecha:** 26/10/2026

**Cómo se registra un defecto aquí:**
- Cada defecto abierto tiene una prueba en `tests/Defectos/DefectosPrueba.php` que describe el comportamiento **correcto** y por eso **falla** ([evidencia](evidencia/4-defectos-abiertos.txt)).
- No corre en la suite por defecto, para que la suite principal siga en verde; se ejecuta con `php tests/ejecutar.php --defectos --solo=DEF`.
- **Al corregirse**, la prueba pasa y se mueve a su grupo, como hizo UNI-08 con DEF-07-01.

## Resumen

| ID | Origen | Severidad | Estado | Prueba | Detectado por |
|---|---|---|---|---|---|
| DEF-07-01 | AR-03 (S7) | Menor | ✅ **Cerrado** | UNI-08 + M27 | Prueba unitaria REF-06 (semana 7) |
| DEF-01 | AR-01 (S13) | **Crítico** | Abierto · plazo 29/10 | DEF-01 | Autorrevisión |
| DEF-02 | AR-04 (S13) | Mayor | Abierto · plazo 02/11 | DEF-02 | Autorrevisión |
| DEF-03 | AR-06 (S13) | Menor | Abierto | DEF-03 | Autorrevisión |
| **DEF-04** | **Nuevo** (DEF-15-01) | Menor | Abierto | DEF-04 | **Caja negra CN-04, esta semana** |
| — | AR-02 (S13) | Mayor | Abierto · plazo 02/11 | Sin prueba automática: lo vigila SQA M7 (en rojo) | Autorrevisión |

## DEF-07-01 · El cursor aceptaba fechas imposibles · Menor · **Cerrado**

- **Qué pasaba:** `CursorPaginacion::leer('2026-13-45T99:00:00Z|1')` devolvía `2027-02-18T03:00Z`, porque `createFromFormat` desborda el mes 13. La consulta continuaba desde otra posición en lugar de responder 422.
- **Rojo:** UNI-08 falla antes de la corrección ([antes](evidencia/def-07-01-1-antes.txt)).
- **Corrección:** se exige que la fecha leída, vuelta a formatear, sea exactamente el texto recibido. Es lo mismo que ya hacía `FiltroRangoFechas::fecha()` (`src/Aplicacion/Servicios/CursorPaginacion.php:30-32`).
- **Verde:** UNI-08 pasa ([después](evidencia/def-07-01-2-despues.txt)). REF-06 incorpora el caso que en la semana 7 se había dejado fuera.
- **Vigilancia:** la mutación **M27** quita la comprobación, y la detectan REF-06 y UNI-08 ([mutaciones 27/27](evidencia/mutaciones.txt)).

## DEF-01 · El punto de entrada real acepta tokens de demostración en cualquier entorno · Crítico

- **Reproducción:** con `AUDITORIA_ENTORNO=produccion`, `crearKernel()` + «Bearer token-superadmin» + `X-Tenant-ID: *` → **200** (lectura de toda la red). Debería ser 401.
- **Regla afectada:** R-05, RT-09, OWASP API2.
- **Corrección propuesta:** el autenticador se elige por configuración; fuera de desarrollo, el autenticador del HIS y sin tokens demo (fail-closed).
- **Por qué no se corrigió esta semana:**
  - La tarea es **diseñar y ejecutar pruebas** y **registrar** defectos.
  - La corrección necesita el autenticador real del HIS (JwtAuth), que es otro módulo y la tarea pide no probar módulos ajenos.
  - El plan SQA le da plazo al 29/10.

## DEF-02 · Un recurso sin «tipo:identificador» se acepta · Mayor

- **Reproducción:** `POST` con `recurso: "P-000001"` → **201**. «¿Quién vio la ficha?» (`recurso=paciente:P-000001`) no lo encuentra.
- **Regla afectada:** RF-18, R-01.
- **Corrección propuesta:** una regla de formato con un catálogo de tipos por módulo. Es una regla nueva (OCP); el filtro no cambia.
- **Nota:** cambia lo que aceptan los emisores, así que hay que coordinarlo con ellos antes. Por eso no se corrigió sin acuerdo.

## DEF-03 · El contrato no dice que event_id es único en toda la red · Menor

- **Reproducción:** la descripción de `event_id` en `api/openapi.yaml` dice solo «uuid recomendado». El mismo emisor recibe 409 si repite un id en otro hospital (AR-06).
- **Corrección propuesta:** documentarlo y subir el contrato a 1.2.1.

## DEF-04 · X-Tenant-ID no limita la verificación ni el ancla · Menor · **nuevo** (DEF-15-01)

- **Cómo apareció:** la caja negra CN-04 se diseñó **desde el contrato**, que define `X-Tenant-ID` como «el hospital del alcance». La tabla de decisión esperaba que el super administrador, con la cabecera de un hospital, **no** pudiera verificar. Obtuvo 200.
- **Reproducción:** `token-superadmin` + `X-Tenant-ID: hosp-oriente` + `POST /verificaciones {"hospital": "hosp-norte"}` → **200**, y verifica Norte. Lo mismo pasa con `GET /anclas?hospital=hosp-norte`.
- **Por qué es menor:** solo el super administrador tiene `auditoria.verificar`, que ya es de red: no hay escalada de privilegios. Lo que hay es una **inconsistencia del contrato**: la cabecera dice «Oriente» y la operación es sobre «Norte», y la meta-auditoría queda en la cadena de red.
- **Corrección propuesta (a decidir):**
  - exigir `X-Tenant-ID: *` para verificar y para el ancla;
  - o limitar la cadena a la de la cabecera.

  Mientras tanto, en CN-04 esas dos celdas quedan como no evaluadas, con referencia a este defecto.

## Observación (no es un defecto)

- **AR-05:** la bitácora se observa a sí misma. Es el comportamiento pedido (R-08); se mide con el control C-D5 del plan SQA.

## Lo que esto significa para el plan SQA

Medición después de la semana 15 ([evidencia](evidencia/6-medir-sqa-despues.txt)):

| Métrica | Antes | Después |
|---|---|---|
| M1 | 98/98 | **116/116** |
| M6 | 1 crítico · 2 mayores · 2 menores | 1 crítico · 2 mayores · **1 menor** (se cerró AR-03) |
| M7 | En rojo | Sigue en rojo |

Los dos rojos tienen plazo (29/10 y 02/11) que todavía no venció.
