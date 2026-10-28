-- Semana 4 — Índices que necesitan dos consultas principales de RepositorioPacientes y que hoy no existen en
-- database/migrations/hospital. Propuesta para una migración nueva de la conexión 'hospital'; no se ejecuta sola.

-- buscarPorApellido: lower(family_name) LIKE 'pre%' solo usa índice con text_pattern_ops sobre la misma expresión.
CREATE INDEX local_patients_family_name_lower_pattern_index ON local_patients (lower(family_name) text_pattern_ops, given_name);

-- pendientesDeVinculo: índice parcial; solo guarda los pendientes, que deberían ser pocos frente al total.
CREATE INDEX local_patients_mpi_pending_index ON local_patients (created_at, local_patient_id) WHERE mpi_link_status = 'pending';
