-- Datos de demostración (ficticios) para el Micro-HIS Prescripciones. Ejecutar después de schema.sql.
SET search_path TO prescripciones;

INSERT INTO local_patients (local_patient_id, hospital) VALUES
    ('P-001', 'hosp-norte'), ('P-002', 'hosp-norte'), ('P-003', 'hosp-norte'), ('P-004', 'hosp-norte'),
    ('P-005', 'hosp-norte'), ('P-006', 'hosp-norte'), ('P-007', 'hosp-norte'), ('P-900', 'hosp-sur');

-- P-003 no tiene ningún registro: alergias no evaluadas.
INSERT INTO allergies (local_patient_id, allergen_code, allergen_name, reaction, allergy_type, severity, status) VALUES
    ('P-001', 'J01CA04', 'Amoxicilina', 'urticaria generalizada', 'allergy', 'severe', 'active'),
    ('P-002', NULL, 'Penicilina', 'exantema', 'allergy', 'moderate', 'active'),
    ('P-004', 'N02BA01', 'Ácido acetilsalicílico', 'anafilaxia', 'allergy', 'life_threatening', 'active'),
    ('P-005', 'J01CA04', 'Amoxicilina', NULL, 'allergy', 'mild', 'resolved'),
    ('P-006', NULL, 'penicilina', NULL, 'unknown', 'unknown', 'active'),
    ('P-007', NULL, '  AMOXICILINA ', 'prurito', 'allergy', 'moderate', 'active');

INSERT INTO medications (medication_code, name, active_ingredient_code, active_ingredient, pharmacological_group, unit, max_daily_dose, routes, active) VALUES
    ('AMOX500', 'Amoxicilina 500 mg', 'J01CA04', 'Amoxicilina', 'betalactamicos', 'mg', 3000, '{oral}', true),
    ('CEFA500', 'Cefalexina 500 mg', 'J01DB01', 'Cefalexina', 'betalactamicos', 'mg', 4000, '{oral}', true),
    ('PARA500', 'Paracetamol 500 mg', 'N02BE01', 'Paracetamol', 'anilidas', 'mg', 4000, '{oral,rectal}', true),
    ('IBU400', 'Ibuprofeno 400 mg', 'M01AE01', 'Ibuprofeno', 'aines', 'mg', 2400, '{oral}', true),
    ('RANI150', 'Ranitidina 150 mg', 'A02BA02', 'Ranitidina', 'antih2', 'mg', 300, '{oral}', false);

INSERT INTO allergen_groups (allergen_key, pharmacological_group) VALUES
    ('j01ca04', 'betalactamicos'), ('j01ce01', 'betalactamicos'), ('penicilina', 'betalactamicos'),
    ('n02ba01', 'aines'), ('m01ae01', 'aines');
