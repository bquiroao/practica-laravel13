-- Base CENTRAL (SQLite mínima): consentimientos por hospital (R-06, R-10) e índice de expedientes del MPI. Horas como enteros.
CREATE TABLE IF NOT EXISTS consents (global_id TEXT NOT NULL, hospital_id TEXT NOT NULL, valid_from INTEGER NOT NULL, valid_to INTEGER, status TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS mpi_records (global_id TEXT NOT NULL, hospital_id TEXT NOT NULL, record_number TEXT NOT NULL, PRIMARY KEY (global_id, hospital_id));
