-- Semana 14 · Controles DETECTIVOS del plan SQA de Auditoría, sobre la base real (solo lectura).
-- Los ejecuta el DBA a diario (C-D2..C-D5) con un usuario de solo lectura; cada consulta que devuelve filas abre una no conformidad.
-- Probado contra database/schema.sql en PostgreSQL 18 (evidencia/controles-detectivos-prueba.txt).

-- C-D2 · Huecos o repeticiones de secuencia en alguna cadena (la verificación diaria debe dar lo mismo).
SELECT COALESCE(hospital_id, '*') AS cadena, secuencia, siguiente
FROM (
    SELECT hospital_id, secuencia, LEAD(secuencia) OVER (PARTITION BY hospital_id ORDER BY secuencia) AS siguiente
    FROM audit_logs
) s
WHERE siguiente IS NOT NULL AND siguiente <> secuencia + 1;

-- C-D3 · El trigger de solo inserción sigue instalado y habilitado (si alguien lo deshabilita, la inmutabilidad depende solo del ancla).
SELECT 'FALTA o DESHABILITADO: ' || n.nombre AS alerta
FROM (VALUES ('audit_logs_sin_update_delete'), ('audit_logs_sin_truncate')) AS n(nombre)
WHERE NOT EXISTS (SELECT 1 FROM pg_trigger t WHERE t.tgname = n.nombre AND t.tgrelid = 'audit_logs'::regclass AND t.tgenabled <> 'D');

-- C-D4 · Correcciones por emisor en los últimos 7 días (riesgo: usar correcciones para encubrir). Umbral: > 1 % de lo registrado.
SELECT emisor, COUNT(*) FILTER (WHERE corrige_a IS NOT NULL) AS correcciones, COUNT(*) AS total,
       ROUND(100.0 * COUNT(*) FILTER (WHERE corrige_a IS NOT NULL) / NULLIF(COUNT(*), 0), 2) AS porcentaje
FROM audit_logs
WHERE ocurrido_en >= now() - interval '7 days' AND emisor <> 'auditoria'
GROUP BY emisor
HAVING COUNT(*) FILTER (WHERE corrige_a IS NOT NULL) > 0.01 * COUNT(*);

-- C-D5 · Proporción de lecturas (meta-auditoría) frente a movimientos de los emisores, por hospital y día (AR-05, M7).
--        Umbral de revisión: lecturas > emisores en un día (alguien consulta mucho más de lo que pasa).
SELECT COALESCE(hospital_id, '*') AS cadena, date_trunc('day', ocurrido_en) AS dia,
       COUNT(*) FILTER (WHERE emisor = 'auditoria') AS lecturas, COUNT(*) FILTER (WHERE emisor <> 'auditoria') AS emisores
FROM audit_logs
WHERE ocurrido_en >= now() - interval '1 day'
GROUP BY 1, 2
HAVING COUNT(*) FILTER (WHERE emisor = 'auditoria') > COUNT(*) FILTER (WHERE emisor <> 'auditoria');

-- C-D6 · Recursos con formato distinto de «tipo:identificador» (AR-04): la búsqueda «¿quién vio la ficha?» no los encontraría.
SELECT emisor, recurso, COUNT(*) AS veces
FROM audit_logs
WHERE recurso IS NOT NULL AND recurso !~ '^[a-z_]+:[A-Za-z0-9_-]+$'
GROUP BY emisor, recurso
ORDER BY veces DESC
LIMIT 50;
