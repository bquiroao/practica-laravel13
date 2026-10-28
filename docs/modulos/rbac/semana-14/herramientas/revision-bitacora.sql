-- Semana 14 · Control detectivo CD-04: revisión semanal de la bitácora de auditoría (PostgreSQL).
-- Uso: psql -U <usuario> -d micro_his_rbac -v desde="'2026-11-09'" -v hasta="'2026-11-16'" -f revision-bitacora.sql
-- Solo lectura: la tabla es append-only (trigger eventos_auditoria_inmutable).
-- NOTA: sin ejecutar todavía contra una base real (no hay PostgreSQL configurado: S15).

-- 1) Denegaciones por hospital y motivo en el periodo. Umbral: un motivo que crece > 50 % frente a la semana anterior se investiga.
SELECT hospital_id,
       datos->>'motivo'            AS motivo,
       count(*)                    AS denegaciones
FROM eventos_auditoria
WHERE tipo = 'ACCESO_DENEGADO'
  AND ocurrido_en >= :desde::timestamptz AND ocurrido_en < :hasta::timestamptz
GROUP BY hospital_id, datos->>'motivo'
ORDER BY denegaciones DESC;

-- 2) Posible uso de un token fuera de su hospital. Umbral: ≥ 3 TENANT_NO_COINCIDE de un mismo actor en un día → incidente.
SELECT actor_id,
       date_trunc('day', ocurrido_en) AS dia,
       count(*)                        AS intentos
FROM eventos_auditoria
WHERE tipo = 'ACCESO_DENEGADO' AND datos->>'motivo' = 'TENANT_NO_COINCIDE'
  AND ocurrido_en >= :desde::timestamptz AND ocurrido_en < :hasta::timestamptz
GROUP BY actor_id, date_trunc('day', ocurrido_en)
HAVING count(*) >= 3
ORDER BY intentos DESC;

-- 3) Personas que siguen intentando operar después de que se les revocó el rol. Umbral: > 5 intentos en 24 h → avisar al administrador.
SELECT d.actor_id,
       count(*) AS intentos_tras_revocar
FROM eventos_auditoria d
JOIN eventos_auditoria r
  ON r.tipo = 'ROL_REVOCADO' AND r.datos->>'usuario_destino' = d.actor_id AND r.hospital_id = d.hospital_id
WHERE d.tipo = 'ACCESO_DENEGADO' AND d.datos->>'motivo' = 'MEMBRESIA_NO_VIGENTE'
  AND d.ocurrido_en BETWEEN r.ocurrido_en AND r.ocurrido_en + interval '24 hours'
  AND d.ocurrido_en >= :desde::timestamptz AND d.ocurrido_en < :hasta::timestamptz
GROUP BY d.actor_id
HAVING count(*) > 5;

-- 4) Integridad de la evidencia: eventos ACCESO_DENEGADO sin alguno de los campos obligatorios. Umbral: 0.
SELECT correlation_id
FROM eventos_auditoria
WHERE tipo = 'ACCESO_DENEGADO'
  AND NOT (datos ? 'permiso' AND datos ? 'recurso' AND datos ? 'motivo' AND datos ? 'ip')
  AND ocurrido_en >= :desde::timestamptz AND ocurrido_en < :hasta::timestamptz;

-- 5) Cambios de rol del periodo (para conciliar con las solicitudes aprobadas). Umbral: 100 % conciliados.
SELECT tipo, actor_id, datos->>'usuario_destino' AS persona, datos->>'rol' AS rol, datos->>'motivo' AS motivo, ocurrido_en
FROM eventos_auditoria
WHERE tipo IN ('ROL_ASIGNADO', 'ROL_REVOCADO')
  AND ocurrido_en >= :desde::timestamptz AND ocurrido_en < :hasta::timestamptz
ORDER BY ocurrido_en;
