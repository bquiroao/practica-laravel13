-- Datos ficticios para probar la API.
INSERT INTO medical_records (medical_record_id, local_patient_id, status) VALUES
    ('exp-carlos', 'pac-carlos', 'open'),
    ('exp-rosa', 'pac-rosa', 'closed');

INSERT INTO care_episodes (care_episode_id, local_patient_id) VALUES
    ('epi-100', 'pac-carlos'),
    ('epi-200', 'pac-rosa');

-- La Dra. Méndez atiende a Carlos (epi-100) y a Rosa (epi-200); el Dr. López no está asignado.
INSERT INTO care_assignments (care_episode_id, doctor_user_ref_id, status) VALUES
    ('epi-100', 'dra-mendez', 'active'),
    ('epi-200', 'dra-mendez', 'active');
