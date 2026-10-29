-- Semana 14 · Controles detectivos del plan SQA que se ejecutan contra la base del módulo (esquema «qa»).
-- Cada consulta devuelve FILAS SOLO SI HAY UN PROBLEMA: cero filas = control en verde.
--   psql "$QA_PSQL" -f herramientas/controles-sqa.sql

SET search_path TO qa;

-- CD-01 · Compensa RT-01 mientras no se corrija: defectos con severidad PROPUESTA CRÍTICA sin clasificar
-- en campañas que siguen EN_CURSO. El Líder QA los clasifica antes de cerrar.
SELECT 'CD-01' AS control, d.tenant, d.id AS defecto, d.caso_id, c.id AS campana, c.nombre
  FROM defectos d JOIN campanas c ON c.tenant = d.tenant AND c.id = d.campana_id
 WHERE d.severidad IS NULL AND d.severidad_propuesta = 'CRITICA' AND c.estado = 'EN_CURSO';

-- CD-02 · Compensa RT-05: campañas cerradas con GO donde ningún caso fue aplicable (todo NO_APLICA).
SELECT 'CD-02' AS control, c.tenant, c.id AS campana, c.nombre
  FROM campanas c
 WHERE c.estado = 'CERRADA' AND c.decision = 'GO'
   AND NOT EXISTS (SELECT 1 FROM intentos i WHERE i.campana_id = c.id AND i.resultado <> 'NO_APLICA');

-- CD-03 · Detecta lo que RT-02 esconde: defectos CRÍTICOS abiertos de OTRA campaña sobre casos del alcance de una
-- campaña EN_CURSO (bloquearán su cierre y su trazabilidad no los muestra).
SELECT 'CD-03' AS control, c.tenant, c.id AS campana, a.caso_id, d.id AS defecto, d.campana_id AS de_la_campana
  FROM campanas c
  JOIN campana_alcance a ON a.campana_id = c.id
  JOIN defectos d ON d.tenant = c.tenant AND d.caso_id = a.caso_id AND d.campana_id <> c.id
 WHERE c.estado = 'EN_CURSO' AND d.severidad = 'CRITICA' AND d.estado <> 'CERRADO';

-- CD-04 · Integridad: intentos cuya campaña es de otro tenant que su caso (no debería existir nunca).
SELECT 'CD-04' AS control, i.id AS intento, c.tenant AS tenant_campana
  FROM intentos i JOIN campanas c ON c.id = i.campana_id
 WHERE NOT EXISTS (SELECT 1 FROM casos_prueba p WHERE p.tenant = c.tenant AND p.caso_id = i.caso_id);
