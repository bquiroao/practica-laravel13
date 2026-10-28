-- Datos de PRUEBA para controles-detectivos.sql: cada control debe devolver exactamente la fila que provoca su caso.
-- Se cargan en una base TEMPORAL con database/schema.sql (nunca en la base real).
SET search_path TO prescripciones;
INSERT INTO local_patients VALUES ('P-001', 'hosp-norte'), ('P-002', 'hosp-norte'), ('P-005', 'hosp-norte');
INSERT INTO medications VALUES
 ('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, '{oral}', true),
 ('CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000, '{oral}', true),
 ('PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000, '{oral}', true);
-- C-D4: una alergia solo por nombre que coincide con el catálogo y otra que NO (la riesgosa); la que tiene código no aparece
INSERT INTO allergies (local_patient_id, allergen_code, allergen_name, allergy_type, severity, status) VALUES
 ('P-001', 'J01CA04', 'Amoxicilina', 'allergy', 'severe', 'active'),
 ('P-002', NULL, 'Amoxicilina', 'allergy', 'moderate', 'active'),
 ('P-005', NULL, 'Amoxicilina trihidrato', 'allergy', 'severe', 'active');
-- dr-ruiz: 2 emisiones, las 2 con advertencia (100 % > 20 %) → C-D5. La #3, de dr-lopez, queda SIN traza → C-D2
INSERT INTO prescriptions (hospital, local_patient_id, prescriber, idempotency_key, content_hash, allergy_check_result, override_justification, issued_at) VALUES
 ('hosp-norte', 'P-002', 'dr-ruiz', 'k1', repeat('a', 64), 'ADVERTENCIA', 'Sin alternativa: cultivo sensible solo a cefalosporinas', now() - interval '2 days'),
 ('hosp-norte', 'P-002', 'dr-ruiz', 'k2', repeat('b', 64), 'ADVERTENCIA', 'Sin alternativa: cultivo sensible solo a cefalosporinas', now() - interval '1 day'),
 ('hosp-norte', 'P-005', 'dr-lopez', 'k3', repeat('c', 64), 'SIN_HALLAZGOS', NULL, now() - interval '3 hours');
INSERT INTO prescription_items VALUES (1, 1, 'CEFA500', 500, 'mg', 8, 'oral', 5), (2, 1, 'CEFA500', 500, 'mg', 8, 'oral', 5), (3, 1, 'PARA500', 500, 'mg', 8, 'oral', 5);
-- Bandeja: las trazas de #1 y #2 (enviadas); una VERIFICACION_NO_DISPONIBLE hace 30 min → C-D6; una traza sin enviar hace 2 h → C-D7
INSERT INTO prescription_audit_outbox (action, hospital, actor, resource, result, occurred_at, sent_at) VALUES
 ('PRESCRIPCION_EMITIDA', 'hosp-norte', 'dr-ruiz', 'prescripcion:1', 'exito', now() - interval '2 days', now() - interval '2 days'),
 ('PRESCRIPCION_EMITIDA', 'hosp-norte', 'dr-ruiz', 'prescripcion:2', 'exito', now() - interval '1 day', now() - interval '1 day'),
 ('VERIFICACION_NO_DISPONIBLE', 'hosp-norte', 'dr-lopez', 'paciente:P-005', 'error', now() - interval '30 minutes', now() - interval '29 minutes'),
 ('PRESCRIPCION_BLOQUEADA', 'hosp-norte', 'dr-lopez', 'paciente:P-001', 'denegado', now() - interval '2 hours', NULL);
