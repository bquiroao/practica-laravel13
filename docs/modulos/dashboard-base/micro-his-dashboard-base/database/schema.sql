-- Versión SQLite mínima de local_patients, appointments y beds (database/migrations/hospital/..._000200 y ..._000300): solo las columnas que cuenta el resumen.
CREATE TABLE IF NOT EXISTS local_patients (id INTEGER PRIMARY KEY, status TEXT NOT NULL CHECK (status IN ('active', 'inactive', 'deceased')));
CREATE TABLE IF NOT EXISTS appointments (id INTEGER PRIMARY KEY, starts_at TEXT NOT NULL,
  status TEXT NOT NULL CHECK (status IN ('scheduled', 'confirmed', 'completed', 'cancelled')));
CREATE TABLE IF NOT EXISTS beds (id INTEGER PRIMARY KEY, status TEXT NOT NULL CHECK (status IN ('available', 'occupied', 'maintenance', 'inactive')));
