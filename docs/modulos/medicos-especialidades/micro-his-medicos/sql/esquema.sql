-- Esquema mínimo (SQLite) con nombres y columnas reales de database/migrations/hospital (solo las que usa el módulo).
-- horarios_medico es propia: el esquema del grupo no tiene horario del médico (H-03). Los cruces de vigencia los evita el Dominio.
CREATE TABLE local_user_refs (local_user_ref_id TEXT PRIMARY KEY, display_name TEXT NOT NULL, role_code TEXT NOT NULL,
                              status TEXT NOT NULL CHECK (status IN ('active', 'inactive', 'revoked')));
CREATE TABLE specialties (specialty_id TEXT PRIMARY KEY, code TEXT NOT NULL UNIQUE, name TEXT NOT NULL, status TEXT NOT NULL CHECK (status IN ('active', 'inactive')));
CREATE TABLE doctor_profiles (doctor_profile_id TEXT PRIMARY KEY, local_user_ref_id TEXT NOT NULL REFERENCES local_user_refs(local_user_ref_id),
                              license_number TEXT NOT NULL CONSTRAINT doctor_profiles_license_number_unique UNIQUE,
                              status TEXT NOT NULL CHECK (status IN ('active', 'inactive', 'suspended')), valid_from TEXT NOT NULL, valid_to TEXT);
CREATE TABLE doctor_specialties (doctor_specialty_id TEXT PRIMARY KEY, doctor_profile_id TEXT NOT NULL REFERENCES doctor_profiles(doctor_profile_id),
                                 specialty_id TEXT NOT NULL REFERENCES specialties(specialty_id), is_primary INTEGER NOT NULL,
                                 valid_from TEXT NOT NULL, valid_to TEXT, status TEXT NOT NULL CHECK (status IN ('active', 'inactive')));
CREATE TABLE appointments (appointment_id TEXT PRIMARY KEY, doctor_profile_id TEXT NOT NULL REFERENCES doctor_profiles(doctor_profile_id),
                           specialty_id TEXT NOT NULL, starts_at TEXT NOT NULL, ends_at TEXT NOT NULL,
                           status TEXT NOT NULL CHECK (status IN ('scheduled', 'confirmed', 'completed', 'cancelled')));
CREATE TABLE horarios_medico (doctor_profile_id TEXT NOT NULL REFERENCES doctor_profiles(doctor_profile_id),
                              dia_semana INTEGER NOT NULL CHECK (dia_semana BETWEEN 1 AND 7), inicio TEXT NOT NULL, fin TEXT NOT NULL, CHECK (fin > inicio));

-- Datos de demostración. med-eva está inactiva (no aparece en disponibilidad); u-luis aún no es médico; u-rev está revocado.
INSERT INTO specialties VALUES ('esp-mi', 'MI', 'Medicina interna', 'active'), ('esp-ped', 'PED', 'Pediatría', 'active'), ('esp-car', 'CAR', 'Cardiología', 'inactive');
INSERT INTO local_user_refs VALUES ('u-ana', 'Dra. Ana Gómez', 'doctor', 'active'), ('u-luis', 'Dr. Luis Paz', 'doctor', 'active'), ('u-eva', 'Dra. Eva Ruiz', 'doctor', 'active'), ('u-rev', 'Dr. Raúl Vega', 'doctor', 'revoked');
INSERT INTO doctor_profiles VALUES ('med-ana', 'u-ana', '11111', 'active', '2026-01-01', NULL), ('med-eva', 'u-eva', '22222', 'inactive', '2026-01-01', NULL);
INSERT INTO doctor_specialties VALUES ('ds-1', 'med-ana', 'esp-mi', 1, '2026-01-01', NULL, 'active'), ('ds-2', 'med-eva', 'esp-mi', 1, '2026-01-01', NULL, 'active');
INSERT INTO horarios_medico VALUES ('med-ana', 1, '08:00', '10:00'), ('med-eva', 1, '08:00', '09:00');
-- Lunes 2 de noviembre: cita activa de 08:00 a 08:30 y una cancelada de 09:00 (no ocupa la franja).
INSERT INTO appointments VALUES ('ap-1', 'med-ana', 'esp-mi', '2026-11-02 08:00', '2026-11-02 08:30', 'scheduled'),
  ('ap-2', 'med-ana', 'esp-mi', '2026-11-02 09:00', '2026-11-02 09:30', 'cancelled'),
  ('ap-3', 'med-ana', 'esp-mi', '2026-11-09 08:15', '2026-11-09 08:45', 'confirmed'); -- lunes 9: tapa dos franjas sin empezar en punto
