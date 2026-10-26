-- Versión SQLite mínima de appointments y doctor_specialties (database/migrations/hospital/..._000100 y ..._000300) más las cancelaciones (R-10).
CREATE TABLE IF NOT EXISTS doctor_specialties (
  doctor TEXT NOT NULL, specialty TEXT NOT NULL, status TEXT NOT NULL CHECK (status IN ('active', 'inactive')));
CREATE TABLE IF NOT EXISTS appointments (
  id INTEGER PRIMARY KEY, doctor TEXT NOT NULL, patient TEXT NOT NULL, specialty TEXT NOT NULL,
  starts_at INTEGER NOT NULL, ends_at INTEGER NOT NULL CHECK (ends_at > starts_at), reason TEXT NOT NULL CHECK (trim(reason) <> ''),
  status TEXT NOT NULL DEFAULT 'scheduled' CHECK (status IN ('scheduled', 'confirmed', 'completed', 'cancelled')));
CREATE TABLE IF NOT EXISTS appointment_cancellations (appointment_id INTEGER NOT NULL REFERENCES appointments(id),
  reason TEXT NOT NULL CHECK (trim(reason) <> ''), cancelled_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP);
