<?php
declare(strict_types=1);

// Datos ficticios. Los pacientes se identifican solo por código; los nombres son marcadores.
return [
    "INSERT INTO local_user_refs VALUES ('7a1e0000-0000-4000-8000-0000000000a1'), ('7a1e0000-0000-4000-8000-0000000000a2')",
    "INSERT INTO local_patients (local_patient_id, global_id, code, given_name, family_name, birth_date, sex, status) VALUES
        ('5a000000-0000-4000-8000-000000000451', gen_random_uuid(), 'PAC-00451', 'Ficticio', 'Uno', '1984-05-12', 'female', 'active'),
        ('5a000000-0000-4000-8000-000000000452', gen_random_uuid(), 'PAC-00452', 'Ficticio', 'Dos', '1990-11-03', 'male', 'active'),
        ('5a000000-0000-4000-8000-000000000453', gen_random_uuid(), 'PAC-00453', 'Ficticio', 'Tres', '1951-02-20', 'male', 'deceased')",
    "INSERT INTO allergies (local_patient_id, verified_by_user_ref_id, allergen_name, reaction, allergy_type, severity, onset_date) VALUES
        ('5a000000-0000-4000-8000-000000000451', '7a1e0000-0000-4000-8000-0000000000a1', 'Ibuprofeno', 'Urticaria', 'allergy', 'moderate', '2021-07-15')",
];
