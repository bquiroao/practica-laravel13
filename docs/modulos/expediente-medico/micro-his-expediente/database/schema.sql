-- Base HOSPITAL (SQLite mínima de local_patients, care_assignments y medical_records, database/migrations/hospital/..._000200 y ..._000400) más la auditoría.
CREATE TABLE IF NOT EXISTS local_patients (code TEXT PRIMARY KEY, global_id TEXT NOT NULL, status TEXT NOT NULL CHECK (status IN ('active', 'inactive', 'deceased')),
  mpi_link_status TEXT NOT NULL CHECK (mpi_link_status IN ('pending', 'linked', 'unlinked')));
CREATE TABLE IF NOT EXISTS care_assignments (patient_code TEXT NOT NULL REFERENCES local_patients(code), doctor TEXT NOT NULL, status TEXT NOT NULL);
CREATE TABLE IF NOT EXISTS medical_records (patient_code TEXT NOT NULL UNIQUE REFERENCES local_patients(code), record_number TEXT NOT NULL UNIQUE);   -- R-01
CREATE TABLE IF NOT EXISTS audit_events (id INTEGER PRIMARY KEY, actor TEXT NOT NULL, action TEXT NOT NULL, patient_code TEXT NOT NULL, outcome TEXT NOT NULL);
