-- Datos de prueba propuestos para la base HOSPITAL (16 tablas). Para una base recién migrada (migrate:fresh --seed); todo va en una transacción.
-- Los ids salen de md5(clave), así que son siempre los mismos. Las firmas de notas, diagnósticos y signos son de médicos con asignación activa (lo exige la base).
-- Las fechas son relativas a now(), así que las pantallas siempre muestran un hospital "de hoy". Ningún dato es de una persona real.
BEGIN;
CREATE FUNCTION pg_temp.u(text) RETURNS uuid LANGUAGE sql IMMUTABLE AS $$ SELECT md5($1)::uuid $$;

INSERT INTO local_user_refs (local_user_ref_id, central_user_id, membership_id, display_name, role_code, synced_at) VALUES
 (pg_temp.u('usr:lucia'),  pg_temp.u('c:lucia'),  pg_temp.u('m:lucia'),  'Dra. Lucía Morales',    'doctor',    now()),
 (pg_temp.u('usr:carlos'), pg_temp.u('c:carlos'), pg_temp.u('m:carlos'), 'Dr. Carlos Pérez',      'doctor',    now()),
 (pg_temp.u('usr:andrea'), pg_temp.u('c:andrea'), pg_temp.u('m:andrea'), 'Dra. Andrea Cifuentes', 'doctor',    now()),
 (pg_temp.u('usr:rosa'),   pg_temp.u('c:rosa'),   pg_temp.u('m:rosa'),   'Enf. Rosa Hernández',   'nurse',     now()),
 (pg_temp.u('usr:marta'),  pg_temp.u('c:marta'),  pg_temp.u('m:marta'),  'Enf. Marta López',      'nurse',     now()),
 (pg_temp.u('usr:sofia'),  pg_temp.u('c:sofia'),  pg_temp.u('m:sofia'),  'Sofía Ramírez',         'admission', now());

INSERT INTO specialties (specialty_id, code, name) VALUES
 (pg_temp.u('esp:MI'), 'MI', 'Medicina interna'), (pg_temp.u('esp:PED'), 'PED', 'Pediatría'), (pg_temp.u('esp:CIR'), 'CIR', 'Cirugía general');
INSERT INTO doctor_profiles (doctor_profile_id, local_user_ref_id, license_number, valid_from) VALUES
 (pg_temp.u('med:lucia'),  pg_temp.u('usr:lucia'),  '18234', current_date - 900),
 (pg_temp.u('med:carlos'), pg_temp.u('usr:carlos'), '21567', current_date - 1500),
 (pg_temp.u('med:andrea'), pg_temp.u('usr:andrea'), '19876', current_date - 700);
INSERT INTO doctor_specialties (doctor_profile_id, specialty_id, is_primary, valid_from) VALUES
 (pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  true, current_date - 900),
 (pg_temp.u('med:carlos'), pg_temp.u('esp:CIR'), true, current_date - 1500),
 (pg_temp.u('med:andrea'), pg_temp.u('esp:PED'), true, current_date - 700);

INSERT INTO wards (ward_id, code, name, location) VALUES
 (pg_temp.u('sala:MED'), 'MED', 'Medicina interna', 'Piso 2'), (pg_temp.u('sala:PED'), 'PED', 'Pediatría', 'Piso 1'),
 (pg_temp.u('sala:EME'), 'EME', 'Emergencia', 'Planta baja'), (pg_temp.u('sala:CIR'), 'CIR', 'Cirugía', 'Piso 3');
INSERT INTO beds (bed_id, ward_id, code, type, status) VALUES
 (pg_temp.u('cama:MED-01'), pg_temp.u('sala:MED'), 'MED-01', 'general', 'occupied'),
 (pg_temp.u('cama:MED-02'), pg_temp.u('sala:MED'), 'MED-02', 'general', 'available'),
 (pg_temp.u('cama:MED-03'), pg_temp.u('sala:MED'), 'MED-03', 'general', 'maintenance'),
 (pg_temp.u('cama:PED-01'), pg_temp.u('sala:PED'), 'PED-01', 'pediátrica', 'occupied'),
 (pg_temp.u('cama:PED-02'), pg_temp.u('sala:PED'), 'PED-02', 'pediátrica', 'available'),
 (pg_temp.u('cama:EME-01'), pg_temp.u('sala:EME'), 'EME-01', 'observación', 'occupied'),
 (pg_temp.u('cama:EME-02'), pg_temp.u('sala:EME'), 'EME-02', 'observación', 'available'),
 (pg_temp.u('cama:CIR-01'), pg_temp.u('sala:CIR'), 'CIR-01', 'general', 'occupied'),
 (pg_temp.u('cama:CIR-02'), pg_temp.u('sala:CIR'), 'CIR-02', 'general', 'inactive');

INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex, contact) VALUES
 (pg_temp.u('pac:1'), pg_temp.u('g:1'), 'P-000101', 'María Elena',      'Pérez Morales',     current_date - 25100, 'female', '5512-3401'),
 (pg_temp.u('pac:2'), pg_temp.u('g:2'), 'P-000102', 'José Luis',        'Ajú Tzoc',          current_date - 19000, 'male',   '4231-8870'),
 (pg_temp.u('pac:3'), pg_temp.u('g:3'), 'P-000103', 'Ana Lucía',        'Chután Xicay',      current_date - 11800, 'female', '5987-2210'),
 (pg_temp.u('pac:4'), pg_temp.u('g:4'), 'P-000104', 'Carlos Eduardo',   'Gómez Barrios',     current_date - 15500, 'male',   '3045-6612'),
 (pg_temp.u('pac:5'), pg_temp.u('g:5'), 'P-000105', 'Mateo Alejandro',  'Hernández Ruiz',    current_date - 2600,  'male',   '5521-9034'),
 (pg_temp.u('pac:6'), pg_temp.u('g:6'), 'P-000106', 'Sofía Valentina',  'Díaz Orellana',     current_date - 800,   'female', '4410-7753'),
 (pg_temp.u('pac:7'), pg_temp.u('g:7'), 'P-000107', 'Pedro Antonio',    'Calel Coy',         current_date - 28000, 'male',   NULL),
 (pg_temp.u('pac:8'), pg_temp.u('g:8'), 'P-000108', 'Rosa Marina',      'López Sagastume',   current_date - 12900, 'female', '5733-1198');
INSERT INTO medical_records (medical_record_id, local_patient_id, record_number, opened_at)
SELECT pg_temp.u('exp:' || n), pg_temp.u('pac:' || n), 'EXP-2026-' || lpad((100 + n)::text, 4, '0'), now() - interval '60 days' FROM generate_series(1, 8) n;

INSERT INTO care_episodes (care_episode_id, local_patient_id, type, status, started_at, ended_at, reason, service_code) VALUES
 (pg_temp.u('epi:1'), pg_temp.u('pac:1'), 'inpatient',  'open',   now() - interval '3 days',  NULL, 'Descompensación de diabetes e hipertensión', 'MED'),
 (pg_temp.u('epi:2'), pg_temp.u('pac:2'), 'outpatient', 'closed', now() - interval '10 days', now() - interval '10 days' + interval '40 minutes', 'Control de hipertensión arterial', 'MED'),
 (pg_temp.u('epi:3'), pg_temp.u('pac:3'), 'emergency',  'open',   now() - interval '5 hours', NULL, 'Dolor abdominal agudo', 'EME'),
 (pg_temp.u('epi:4'), pg_temp.u('pac:4'), 'inpatient',  'open',   now() - interval '2 days',  NULL, 'Postoperatorio de apendicectomía', 'CIR'),
 (pg_temp.u('epi:5'), pg_temp.u('pac:5'), 'inpatient',  'open',   now() - interval '1 day',   NULL, 'Neumonía con dificultad respiratoria', 'PED'),
 (pg_temp.u('epi:6'), pg_temp.u('pac:6'), 'outpatient', 'closed', now() - interval '20 days', now() - interval '20 days' + interval '30 minutes', 'Control de niño sano', 'PED'),
 (pg_temp.u('epi:7'), pg_temp.u('pac:7'), 'emergency',  'closed', now() - interval '30 days', now() - interval '30 days' + interval '6 hours', 'Caída con dolor en la muñeca', 'EME');
INSERT INTO care_assignments (care_episode_id, doctor_profile_id, specialty_id, assigned_by_user_ref_id, scope, reason, source, status, valid_from, valid_to) VALUES
 (pg_temp.u('epi:1'), pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  pg_temp.u('usr:sofia'), '{"episodio": true}', 'Médico tratante', 'episode',   'active', now() - interval '3 days', NULL),
 (pg_temp.u('epi:2'), pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  pg_temp.u('usr:sofia'), '{"episodio": true}', 'Consulta externa', 'appointment', 'active', now() - interval '10 days', NULL),
 (pg_temp.u('epi:3'), pg_temp.u('med:carlos'), pg_temp.u('esp:CIR'), pg_temp.u('usr:sofia'), '{"episodio": true}', 'Emergencia', 'emergency', 'active', now() - interval '5 hours', NULL),
 (pg_temp.u('epi:4'), pg_temp.u('med:carlos'), pg_temp.u('esp:CIR'), pg_temp.u('usr:sofia'), '{"episodio": true}', 'Médico tratante', 'episode',   'active', now() - interval '2 days', NULL),
 (pg_temp.u('epi:5'), pg_temp.u('med:andrea'), pg_temp.u('esp:PED'), pg_temp.u('usr:sofia'), '{"episodio": true}', 'Médico tratante', 'episode',   'active', now() - interval '1 day', NULL),
 (pg_temp.u('epi:6'), pg_temp.u('med:andrea'), pg_temp.u('esp:PED'), pg_temp.u('usr:sofia'), '{"episodio": true}', 'Consulta externa', 'appointment', 'active', now() - interval '20 days', NULL),
 (pg_temp.u('epi:7'), pg_temp.u('med:carlos'), pg_temp.u('esp:CIR'), pg_temp.u('usr:sofia'), '{"episodio": true}', 'Emergencia', 'emergency', 'active', now() - interval '30 days', NULL);
INSERT INTO bed_stays (care_episode_id, bed_id, assigned_by_user_ref_id, started_at, reason) VALUES
 (pg_temp.u('epi:1'), pg_temp.u('cama:MED-01'), pg_temp.u('usr:sofia'), now() - interval '3 days', 'Ingreso a medicina interna'),
 (pg_temp.u('epi:3'), pg_temp.u('cama:EME-01'), pg_temp.u('usr:sofia'), now() - interval '5 hours', 'Observación en emergencia'),
 (pg_temp.u('epi:4'), pg_temp.u('cama:CIR-01'), pg_temp.u('usr:sofia'), now() - interval '2 days', 'Ingreso a cirugía'),
 (pg_temp.u('epi:5'), pg_temp.u('cama:PED-01'), pg_temp.u('usr:sofia'), now() - interval '1 day', 'Ingreso a pediatría');

INSERT INTO clinical_notes (clinical_note_id, medical_record_id, care_episode_id, author_user_ref_id, note_type, content, status, signed_at, version_no) VALUES
 (pg_temp.u('nota:1'), pg_temp.u('exp:1'), pg_temp.u('epi:1'), pg_temp.u('usr:lucia'),  'evolución', 'S: refiere sed intensa y mareo. O: PA 150/92, glucosa 280 mg/dL. A: diabetes descompensada. P: insulina según esquema y control de glucosa cada 6 horas.', 'signed', now() - interval '2 days', 1),
 (pg_temp.u('nota:2'), pg_temp.u('exp:4'), pg_temp.u('epi:4'), pg_temp.u('usr:carlos'), 'evolución', 'S: dolor leve en la herida. O: afebril, herida limpia. A: evolución favorable. P: iniciar dieta blanda y vigilar signos de infección.', 'signed', now() - interval '1 day', 1),
 (pg_temp.u('nota:3'), pg_temp.u('exp:5'), pg_temp.u('epi:5'), pg_temp.u('usr:andrea'), 'ingreso', 'S: tos y fiebre de 3 días. O: SpO2 91 %, tiraje intercostal. A: neumonía. P: oxígeno, antibiótico y control cada 4 horas.', 'draft', NULL, 1);
INSERT INTO diagnoses (care_episode_id, clinical_note_id, author_user_ref_id, code, description, type, status, onset_date, resolved_at) VALUES
 (pg_temp.u('epi:1'), pg_temp.u('nota:1'), pg_temp.u('usr:lucia'),  'E11.65', 'Diabetes mellitus tipo 2 con hiperglucemia', 'primary',   'active',   current_date - 3, NULL),
 (pg_temp.u('epi:1'), pg_temp.u('nota:1'), pg_temp.u('usr:lucia'),  'I10',    'Hipertensión esencial (primaria)',           'secondary', 'active',   current_date - 3, NULL),
 (pg_temp.u('epi:3'), NULL,                pg_temp.u('usr:carlos'), 'R10.4',  'Otros dolores abdominales y los no especificados', 'primary', 'active', current_date, NULL),
 (pg_temp.u('epi:4'), pg_temp.u('nota:2'), pg_temp.u('usr:carlos'), 'K35.8',  'Apendicitis aguda, otra y la no especificada', 'primary',  'active',   current_date - 2, NULL),
 (pg_temp.u('epi:5'), pg_temp.u('nota:3'), pg_temp.u('usr:andrea'), 'J18.9',  'Neumonía, no especificada',                  'primary',   'active',   current_date - 1, NULL),
 (pg_temp.u('epi:6'), NULL,                pg_temp.u('usr:andrea'), 'Z00.1',  'Control de salud de rutina del niño',        'primary',   'resolved', current_date - 20, current_date - 20);

INSERT INTO vital_signs (vital_sign_id, medical_record_id, care_episode_id, recorded_by_user_ref_id, temperature, heart_rate, respiratory_rate, systolic_bp, diastolic_bp, oxygen_saturation, weight, height, glucose, measured_at, recorded_at, status, validated_at) VALUES
 (pg_temp.u('sv:1a'), pg_temp.u('exp:1'), pg_temp.u('epi:1'), pg_temp.u('usr:lucia'),  36.8, 88, 18, 150, 92, 95, 78,   158, 280, now() - interval '3 days',  now() - interval '3 days',  'validated', now() - interval '3 days'),
 (pg_temp.u('sv:1b'), pg_temp.u('exp:1'), pg_temp.u('epi:1'), pg_temp.u('usr:lucia'),  36.6, 82, 17, 138, 84, 96, NULL, NULL, 190, now() - interval '1 day',   now() - interval '1 day',   'validated', now() - interval '1 day'),
 (pg_temp.u('sv:2a'), pg_temp.u('exp:2'), pg_temp.u('epi:2'), pg_temp.u('usr:lucia'), 36.5, 80, 16, 142, 90, 98, 82,   172, NULL, now() - interval '10 days', now() - interval '10 days', 'validated', now() - interval '10 days'),
 (pg_temp.u('sv:3a'), pg_temp.u('exp:3'), pg_temp.u('epi:3'), pg_temp.u('usr:carlos'), 38.6, 112, 22, 118, 76, 97, NULL, NULL, NULL, now() - interval '4 hours',  now() - interval '4 hours',  'validated', now() - interval '4 hours'),
 (pg_temp.u('sv:4a'), pg_temp.u('exp:4'), pg_temp.u('epi:4'), pg_temp.u('usr:carlos'),  37.1, 76, 16, 120, 78, 98, 70,   175, NULL, now() - interval '1 day',   now() - interval '1 day',   'validated', now() - interval '1 day'),
 (pg_temp.u('sv:5a'), pg_temp.u('exp:5'), pg_temp.u('epi:5'), pg_temp.u('usr:andrea'), 39.1, 128, 36, NULL, NULL, 91, 18.5, 118, NULL, now() - interval '20 hours', now() - interval '20 hours', 'validated', now() - interval '20 hours'),
 (pg_temp.u('sv:5b'), pg_temp.u('exp:5'), pg_temp.u('epi:5'), pg_temp.u('usr:andrea'), 38.2, 120, 32, NULL, NULL, 93, NULL, NULL, NULL, now() - interval '2 hours', now() - interval '2 hours', 'draft', NULL),
 (pg_temp.u('sv:6a'), pg_temp.u('exp:6'), pg_temp.u('epi:6'), pg_temp.u('usr:andrea'),  36.7, 110, 28, NULL, NULL, 99, 12.4, 86, NULL, now() - interval '20 days', now() - interval '20 days', 'validated', now() - interval '20 days'),
 (pg_temp.u('sv:7a'), pg_temp.u('exp:7'), pg_temp.u('epi:7'), pg_temp.u('usr:carlos'), 36.4, 98, 18, 160, 95, 96, NULL, NULL, NULL, now() - interval '30 days', now() - interval '30 days', 'validated', now() - interval '30 days');

INSERT INTO appointments (appointment_id, local_patient_id, doctor_profile_id, specialty_id, starts_at, ends_at, status, reason) VALUES
 (pg_temp.u('cita:1'), pg_temp.u('pac:8'), pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  date_trunc('day', now()) + interval '2 days 9 hours',  date_trunc('day', now()) + interval '2 days 9 hours 30 minutes', 'confirmed', 'Primera consulta por cansancio frecuente'),
 (pg_temp.u('cita:2'), pg_temp.u('pac:2'), pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  date_trunc('day', now()) + interval '7 days 10 hours', date_trunc('day', now()) + interval '7 days 10 hours 30 minutes', 'scheduled', 'Control de presión arterial'),
 (pg_temp.u('cita:3'), pg_temp.u('pac:6'), pg_temp.u('med:andrea'), pg_temp.u('esp:PED'), date_trunc('day', now()) + interval '3 days 8 hours',  date_trunc('day', now()) + interval '3 days 8 hours 30 minutes', 'scheduled', 'Control de niño sano y vacunas'),
 (pg_temp.u('cita:4'), pg_temp.u('pac:2'), pg_temp.u('med:lucia'),  pg_temp.u('esp:MI'),  now() - interval '10 days', now() - interval '10 days' + interval '30 minutes', 'completed', 'Control de hipertensión arterial'),
 (pg_temp.u('cita:5'), pg_temp.u('pac:8'), pg_temp.u('med:carlos'), pg_temp.u('esp:CIR'), date_trunc('day', now()) - interval '5 days' + interval '14 hours', date_trunc('day', now()) - interval '5 days' + interval '14 hours 30 minutes', 'cancelled', 'Valoración quirúrgica (la paciente no asistió)');

INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_code, allergen_name, reaction, allergy_type, severity, status, onset_date, resolved_at) VALUES
 (pg_temp.u('pac:1'), pg_temp.u('usr:lucia'),  'J01CA', 'Penicilinas', 'Urticaria y dificultad para respirar', 'allergy', 'severe', 'active', current_date - 4000, NULL),
 (pg_temp.u('pac:4'), pg_temp.u('usr:carlos'), NULL,    'Látex', 'Enrojecimiento de la piel', 'allergy', 'moderate', 'active', NULL, NULL),
 (pg_temp.u('pac:3'), pg_temp.u('usr:carlos'), NULL,    'Mariscos', 'Molestias digestivas', 'intolerance', 'mild', 'resolved', current_date - 3000, current_date - 400);
-- Las asignaciones de los episodios cerrados se cierran al final: la base solo deja firmar a quien tiene la asignación activa.
UPDATE care_assignments a SET status = 'closed', valid_to = e.ended_at FROM care_episodes e WHERE e.care_episode_id = a.care_episode_id AND e.status = 'closed';
COMMIT;
