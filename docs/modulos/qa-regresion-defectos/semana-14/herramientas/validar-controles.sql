-- Semana 14 · Contraprueba de los controles detectivos: siembra UN caso positivo de CD-01, CD-02 y CD-03 dentro de una
-- transacción, ejecuta los controles y deshace todo. Cada control debe devolver exactamente su fila sembrada.
--   psql "$QA_PSQL" -f herramientas/validar-controles.sql
SET search_path TO qa;
BEGIN;
-- Campaña A: cerrada con GO y sin un solo intento aplicable (positivo de CD-02).
INSERT INTO campanas (tenant, nombre, funcionalidades, umbral, estado, build, decision, tasa_aprobados, creada_por)
VALUES ('hosp-norte', 'Sembrada A (cerrada GO sin aplicables)', '{expediente}', 0.95, 'CERRADA', '1.4.0', 'GO', 1.0, 'lider-qa');
-- Campaña B: en curso, con TC-107 en su alcance.
INSERT INTO campanas (tenant, nombre, funcionalidades, umbral, estado, build, creada_por)
VALUES ('hosp-norte', 'Sembrada B (en curso)', '{expediente}', 0.95, 'EN_CURSO', '1.4.1', 'lider-qa');
INSERT INTO campana_alcance (tenant, campana_id, caso_id, version)
SELECT 'hosp-norte', currval('campanas_id_seq'), 'TC-107', 1;
-- Positivo de CD-03: crítico ABIERTO de la campaña A sobre TC-107, que está en el alcance de B.
INSERT INTO defectos (tenant, campana_id, caso_id, titulo, pasos, severidad_propuesta, con_evidencia, reportado_por, estado, severidad, prioridad)
SELECT 'hosp-norte', currval('campanas_id_seq') - 1, 'TC-107', 'Sembrado CD-03', '["paso"]', 'CRITICA', true, 'tito-tester', 'ABIERTO', 'CRITICA', 'ALTA';
-- Positivo de CD-01: CRÍTICA propuesta y sin clasificar en la campaña B, en curso.
INSERT INTO defectos (tenant, campana_id, caso_id, titulo, pasos, severidad_propuesta, con_evidencia, reportado_por)
SELECT 'hosp-norte', currval('campanas_id_seq'), 'TC-107', 'Sembrado CD-01', '["paso"]', 'CRITICA', true, 'tito-tester';
\echo '--- controles con los positivos sembrados ---'
\i herramientas/controles-sqa.sql
ROLLBACK;
\echo '--- después del ROLLBACK (base real) ---'
\i herramientas/controles-sqa.sql
