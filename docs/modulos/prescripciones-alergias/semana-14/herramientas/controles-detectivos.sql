-- Semana 14 · Controles DETECTIVOS del plan SQA de Prescripciones, sobre la base del hospital (solo lectura).
-- Los ejecuta el DBA (C-D2, C-D3, C-D6 y C-D7 a diario; C-D4 y C-D5 cada semana) con un usuario de solo lectura.
-- Cada consulta que devuelve filas abre una no conformidad (plan §6).
-- Probado contra database/schema.sql en PostgreSQL 18 (evidencia/controles-detectivos-prueba.txt).
SET search_path TO prescripciones;

-- C-D2 · Prescripciones sin su traza PRESCRIPCION_EMITIDA en la bandeja (R-12: la prescripción y su traza van juntas).
SELECT p.prescription_id, p.hospital, p.prescriber
FROM prescriptions p
WHERE NOT EXISTS (SELECT 1 FROM prescription_audit_outbox o
                  WHERE o.action = 'PRESCRIPCION_EMITIDA' AND o.resource = 'prescripcion:' || p.prescription_id);

-- C-D3 · Los triggers de inmutabilidad siguen instalados y habilitados (R-10).
SELECT 'FALTA o DESHABILITADO: ' || n.nombre AS alerta
FROM (VALUES ('prescriptions_inmutable'), ('prescription_items_inmutable'), ('prescription_voids_inmutable')) AS n(nombre)
WHERE NOT EXISTS (SELECT 1 FROM pg_trigger t WHERE t.tgname = n.nombre AND t.tgenabled <> 'D');

-- C-D4 · Alergias ACTIVAS registradas solo por nombre (sin código): la verificación las compara por nombre exacto (AR-03).
--        Cada fila es un falso negativo posible; las que no coinciden con ningún principio activo del catálogo son las más riesgosas.
SELECT a.allergen_name, a.severity, COUNT(DISTINCT a.local_patient_id) AS pacientes,
       EXISTS (SELECT 1 FROM medications m WHERE lower(btrim(m.active_ingredient)) = lower(btrim(a.allergen_name))) AS coincide_con_catalogo
FROM allergies a
WHERE a.status = 'active' AND NULLIF(btrim(a.allergen_code), '') IS NULL
GROUP BY a.allergen_name, a.severity
ORDER BY coincide_con_catalogo, pacientes DESC;

-- C-D5 · Uso rutinario del override: prescriptores con más del 20 % de sus emisiones de 7 días con advertencia justificada.
SELECT prescriber, COUNT(*) FILTER (WHERE allergy_check_result = 'ADVERTENCIA') AS con_advertencia, COUNT(*) AS emitidas,
       ROUND(100.0 * COUNT(*) FILTER (WHERE allergy_check_result = 'ADVERTENCIA') / COUNT(*), 1) AS porcentaje
FROM prescriptions
WHERE issued_at >= now() - interval '7 days'
GROUP BY prescriber
HAVING COUNT(*) FILTER (WHERE allergy_check_result = 'ADVERTENCIA') > 0.2 * COUNT(*);

-- C-D6 · El Expediente no respondió en las últimas 24 h: emisiones que el bloqueo seguro detuvo (VERIFICACION_NO_DISPONIBLE).
SELECT hospital, COUNT(*) AS emisiones_detenidas, MIN(occurred_at) AS primera, MAX(occurred_at) AS ultima
FROM prescription_audit_outbox
WHERE action = 'VERIFICACION_NO_DISPONIBLE' AND occurred_at >= now() - interval '1 day'
GROUP BY hospital;

-- C-D7 · Trazas de la bandeja sin enviar al módulo Auditoría hace más de 1 hora.
SELECT action, COUNT(*) AS pendientes, MIN(occurred_at) AS mas_antigua
FROM prescription_audit_outbox
WHERE sent_at IS NULL AND occurred_at < now() - interval '1 hour'
GROUP BY action;
