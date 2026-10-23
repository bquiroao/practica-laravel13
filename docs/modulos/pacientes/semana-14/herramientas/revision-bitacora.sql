-- Semana 14 · Control detectivo CD-04: revisión semanal de la bitácora de Pacientes (PostgreSQL).
-- Tabla: eventos_auditoria (micro-his-pacientes/database/schema.sql). Responsable: RS. Sin ejecutar aún: falta la base (S15).
-- Cada consulta indica su umbral; un resultado fuera de umbral abre una no conformidad (plan §6).

-- 1. Accesos limitados a pacientes protegidos por actor en los últimos 7 días (R-11).
--    Umbral: más de 5 accesos de un mismo actor a un mismo protegido en 7 días → avisar al supervisor.
SELECT actor_id, paciente_codigo, count(*) AS accesos
FROM eventos_auditoria
WHERE accion = 'PACIENTE_CONSULTADO'
  AND (detalle ->> 'acceso_limitado')::boolean
  AND registrado_en >= now() - interval '7 days'
GROUP BY actor_id, paciente_codigo
HAVING count(*) > 5
ORDER BY accesos DESC;

-- 2. Búsquedas por documento: quién las hace y cuántas (riesgo AR-04: confirmar la presencia de un protegido).
--    Umbral: más de 20 búsquedas por documento de un actor en un día → revisar.
SELECT actor_id, date_trunc('day', registrado_en) AS dia, count(*) AS busquedas
FROM eventos_auditoria
WHERE accion = 'PACIENTE_BUSCADO' AND detalle ->> 'criterio' = 'documento'
GROUP BY actor_id, dia
HAVING count(*) > 20
ORDER BY dia DESC, busquedas DESC;

-- 3. Cambios de identidad SIN motivo (R-07). Umbral: 0 filas.
SELECT evento_id, actor_id, paciente_codigo, registrado_en
FROM eventos_auditoria
WHERE accion = 'PACIENTE_ACTUALIZADO'
  AND detalle ->> 'motivo' IS NULL
  AND (   detalle -> 'antes' ->> 'nombres'       IS DISTINCT FROM detalle -> 'despues' ->> 'nombres'
       OR detalle -> 'antes' ->> 'apellidos'     IS DISTINCT FROM detalle -> 'despues' ->> 'apellidos'
       OR detalle -> 'antes' ->> 'nacimiento'    IS DISTINCT FROM detalle -> 'despues' ->> 'nacimiento'
       OR detalle -> 'antes' ->> 'identificador' IS DISTINCT FROM detalle -> 'despues' ->> 'identificador');
-- Nota: la normalización de tildes no se replica aquí; si aparecen filas, confirmar con Texto::normalizar.

-- 4. Coincidencias probables confirmadas como «otra persona» (R-03): revisión muestral por supervisión.
--    Umbral: revisar el 100 % de las filas de la semana.
SELECT evento_id, actor_id, paciente_codigo, detalle -> 'coincidencias_confirmadas' AS confirmadas, detalle ->> 'motivo' AS motivo
FROM eventos_auditoria
WHERE accion = 'PACIENTE_REGISTRADO'
  AND jsonb_array_length(detalle -> 'coincidencias_confirmadas') > 0
  AND registrado_en >= now() - interval '7 days';

-- 5. Conciliación: fichas sin su evento de registro (R-09). Umbral: 0 filas.
SELECT p.code, p.hospital_id, p.created_at
FROM pacientes p
LEFT JOIN eventos_auditoria e ON e.paciente_codigo = p.code AND e.accion = 'PACIENTE_REGISTRADO'
WHERE e.evento_id IS NULL;

-- 6. Cambios de protección (alta o baja) en la semana, con su motivo (R-11). Umbral: 100 % con motivo.
SELECT evento_id, actor_id, paciente_codigo, registrado_en,
       detalle -> 'antes' ->> 'identidad_protegida'   AS antes,
       detalle -> 'despues' ->> 'identidad_protegida' AS despues,
       detalle ->> 'motivo'                            AS motivo
FROM eventos_auditoria
WHERE accion = 'PACIENTE_ACTUALIZADO'
  AND detalle -> 'antes' ->> 'identidad_protegida' IS DISTINCT FROM detalle -> 'despues' ->> 'identidad_protegida'
  AND registrado_en >= now() - interval '7 days';
